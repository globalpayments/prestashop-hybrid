<?php

/**
 * NOTICE OF LICENSE
 *
 * This file is licenced under the Software License Agreement.
 * With the purchase or the installation of the software in your application
 * you accept the licence agreement.
 *
 * You must not modify, adapt or create derivative works of this source code
 *
 * @author    GlobalPayments
 * @copyright Since 2025 GlobalPayments
 * @license   LICENSE
 */

declare(strict_types=1);

namespace GlobalPayments\PaymentGatewayProvider\Platform\Helper;

use GlobalPayments\PaymentGatewayProvider\PaymentMethods\AbstractPaymentMethod;
use GlobalPayments\PaymentGatewayProvider\PaymentMethods\HostedPaymentPages\Hpp;
use GlobalPayments\PaymentGatewayProvider\Platform\OrderAdditionalInfo;
use GlobalPayments\PaymentGatewayProvider\Platform\TransactionHistory;
use GlobalPayments\PaymentGatewayProvider\Requests\TransactionType;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * HPP Helper
 *
 * Business logic for Hosted Payment Page (HPP) payment processing.
 * Handles order completion, payment failures, and cart restoration.
 *
 */
class HppHelper
{
    /**
     * Complete payment - update order status and add payment info
     *
     * @param \Order $order Order to complete
     * @param string $transactionId Transaction ID from gateway
     * @param array<string, mixed> $gatewayData Gateway response data
     * @return void
     */
    public function completePayment(\Order $order, string $transactionId, array $gatewayData, bool $addTransactionHistory = true): void
    {
        // Update order payment with transaction ID
        $this->updateOrderPayment($order, $transactionId, $gatewayData);

        if ($addTransactionHistory) {
            // Create transaction history entry for refund support (matches drop-in UI pattern)
            $this->createTransactionHistory($order, $transactionId, $gatewayData);
        }

        // Store payment method ID in OrderAdditionalInfo (required for refunds)
        $this->storePaymentMethodId($order);

        // Update order status to payment accepted
        if (!$this->isOrderAlreadyPaid($order)) {
            $this->updateOrderToPaid($order);
        }
    }

    /**
     * Update order payment record with transaction ID
     *
     * @param \Order $order Order to update
     * @param string $transactionId Transaction ID from gateway
     * @param array<string, mixed> $gatewayData Gateway response data
     * @return void
     */
    public function updateOrderPayment(\Order $order, string $transactionId, array $gatewayData): void
    {
        if (empty($transactionId)) {
            $this->logWarning('Empty transaction ID provided, skipping payment update', [
                'order_id' => $order->id,
            ]);
            return;
        }

        $orderPayments = \OrderPayment::getByOrderReference($order->reference);

        if (empty($orderPayments)) {
            $this->logWarning('No order payments found for order', [
                'order_id' => $order->id,
                'order_reference' => $order->reference,
            ]);
            return;
        }

        $orderPayment = $orderPayments[0];
        $orderPayment->transaction_id = $transactionId;

        // Update payment method if card data available
        if (isset($gatewayData['payment_method']['card'])) {
            $orderPayment->payment_method = $this->formatPaymentMethod($gatewayData['payment_method']['card']);
        }

        $orderPayment->save();
    }

    /**
     * Handle pending payment
     *
     * @param \Order $order Order to update
     * @return void
     */
    public function handlePendingPayment(\Order $order): void {
        $current_status = $order->getCurrentState();

        if ($current_status !== (int)\Configuration::get('GLOBALPAYMENTS_PAYMENT_WAITING')) {
            $order->setCurrentState((int)\Configuration::get('GLOBALPAYMENTS_PAYMENT_WAITING'));
            $order->save();
        }
    }

    /**
     * Handle failed payment - cancel order and restore cart
     *
     * @param \Order $order Order to cancel
     * @param string $transactionId Transaction ID from gateway
     * @param array<string, mixed> $gatewayData Gateway response data
     * @return void
     */
    public function handleFailedPayment(\Order $order): void
    {
        // Cancel the order
        if (!$this->isOrderCancelled($order)) {
            $this->cancelOrder($order);
        }

        // Restore the cart so customer can try again
        $this->restoreCartFromOrder($order);
    }

    /**
     * Restore cart from order (allows customer to retry payment)
     *
     * @param \Order $order Order to restore cart from
     * @return void
     */
    public function restoreCartFromOrder(\Order $order): void
    {
        try {
            $cart = new \Cart($order->id_cart);

            if (!\Validate::isLoadedObject($cart)) {
                $this->logWarning('Cart not found for order', [
                    'order_id' => $order->id,
                    'cart_id' => $order->id_cart,
                ]);
                return;
            }

            // Duplicate the cart to restore it
            $duplication = $cart->duplicate();

            if (!$duplication || !isset($duplication['success']) || !$duplication['success']) {
                $this->logWarning('Failed to duplicate cart', [
                    'order_id' => $order->id,
                    'cart_id' => $cart->id,
                ]);
                return;
            }

            $newCart = $duplication['cart'];

            // Update context with restored cart
            $context = \Context::getContext();
            $context->cart = $newCart;
            $context->cookie->id_cart = $newCart->id;
        } catch (\Exception $e) {
            $this->logError('Exception during cart restoration', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Check if order is already in paid status
     *
     * @param \Order $order Order to check
     * @return bool True if order is already paid
     */
    private function isOrderAlreadyPaid(\Order $order): bool
    {
        $currentState = $order->getCurrentState();
        $paidStates = [
            (int)\Configuration::get('PS_OS_PAYMENT'),
            (int)\Configuration::get('PS_OS_WS_PAYMENT'),
        ];

        return in_array($currentState, $paidStates, true);
    }

    /**
     * Check if order is cancelled
     *
     * @param \Order $order Order to check
     * @return bool True if order is cancelled
     */
    private function isOrderCancelled(\Order $order): bool
    {
        return $order->getCurrentState() === (int)\Configuration::get('PS_OS_CANCELED') || 
            $order->getCurrentState() === (int)\Configuration::get('GLOBALPAYMENTS_PAYMENT_DECLINED');
    }

    /**
     * Update order to paid status
     *
     * @param \Order $order Order to update
     * @param string $transactionId Transaction ID
     * @return void
     */
    private function updateOrderToPaid(\Order $order): void
    {
        $order->setCurrentState((int)\Configuration::get('PS_OS_PAYMENT'));
        $order->save();
    }

    /**
     * Cancel order
     *
     * @param \Order $order Order to cancel
     * @param string $transactionId Transaction ID
     * @param array<string, mixed> $gatewayData Gateway data
     * @return void
     */
    private function cancelOrder(\Order $order): void
    {
        $order->setCurrentState((int)\Configuration::get('GLOBALPAYMENTS_PAYMENT_DECLINED'));
        $order->save();
    }

    /**
     * Format payment method string from card data
     *
     * @param array<string, mixed> $cardData Card data from gateway
     * @return string Formatted payment method
     */
    private function formatPaymentMethod(array $cardData): string
    {
        $brand = $cardData['brand'] ?? 'Card';
        $last4 = $cardData['masked_number_last4'] ?? 'XXXX';

        return sprintf('Hosted Payment Page - %s ending in %s', $brand, $last4);
    }

    /**
     * Format amount for display
     *
     * @param int|float|string|null $amount Amount in cents
     * @return string Formatted amount
     */
    private function formatAmount(int|float|string|null $amount): string
    {
        if ($amount === null || $amount === '') {
            return 'N/A';
        }

        // Convert to float and divide by 100 (cents to currency units)
        $numericAmount = is_numeric($amount) ? (float)$amount / 100 : 0;

        return number_format($numericAmount, 2, '.', '');
    }

    /**
     * Create transaction history entry for HPP payment
     * This is required for refund support (matches drop-in UI pattern)
     *
     * @param \Order $order Order
     * @param string $transactionId Transaction ID from gateway
     * @param array<string, mixed> $gatewayData Gateway response data
     * @return void
     */
    private function createTransactionHistory(\Order $order, string $transactionId, array $gatewayData): void
    {
        try {
            $transactionHistory = new TransactionHistory();
            $currency = new \Currency($order->id_currency);

            // Get amount from gateway data or order
            $amount = $gatewayData['amount'] ?? null;
            if ($amount !== null) {
                // Convert from cents to currency units
                $amount = (float)$amount / 100;
            } else {
                $amount = $order->total_paid_tax_incl;
            }

            // HPP payments are always SALE type (AUTO capture mode)
            $transactionHistory->saveResult(
                $order->id,
                TransactionType::SALE,
                $amount,
                $currency->iso_code,
                $transactionId,
                1, // success
                'HPP Payment Captured'
            );
        } catch (\Exception $e) {
            $this->logError('Failed to create transaction history', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Store payment method ID in OrderAdditionalInfo
     * This is required for refund operations to identify the correct gateway
     * Matches the pattern used by async payment methods
     *
     * @param \Order $order Order
     * @return void
     */
    private function storePaymentMethodId(\Order $order): void
    {
        try {
            $orderAdditionalInfo = new OrderAdditionalInfo();
            $orderAdditionalInfo->setAdditionalInfo(
                $order->id,
                AbstractPaymentMethod::PAYMENT_METHOD_ID,
                Hpp::PAYMENT_METHOD_ID
            );
        } catch (\Exception $e) {
            $this->logError('Failed to store payment method ID', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log warning message
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    private function logWarning(string $message, array $context = []): void
    {
        $this->log($message, \PrestaShopLogger::LOG_SEVERITY_LEVEL_WARNING, $context);
    }

    /**
     * Log error message
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    private function logError(string $message, array $context = []): void
    {
        $this->log($message, \PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR, $context);
    }

    /**
     * Log message
     *
     * @param string $message Log message
     * @param int $severity Log severity
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    private function log(string $message, int $severity, array $context = []): void
    {
        $logMessage = sprintf('HPP Helper: %s', $message);

        if (!empty($context)) {
            $logMessage .= ' | ' . json_encode($context);
        }

        \PrestaShopLogger::addLog(
            $logMessage,
            $severity,
            null,
            'GlobalPayments'
        );
    }
}
