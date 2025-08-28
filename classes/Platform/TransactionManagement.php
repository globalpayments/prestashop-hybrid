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
 * @copyright Since 2021 GlobalPayments
 * @license   LICENSE
 */

namespace GlobalPayments\PaymentGatewayProvider\Platform;

use GlobalPayments\PaymentGatewayProvider\Data\Order as OrderModel;
use GlobalPayments\PaymentGatewayProvider\Gateways\GpApiGateway;
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\OrderStateHelper;
use GlobalPayments\PaymentGatewayProvider\Requests\TransactionType;
use PrestaShopBundle\Translation\TranslatorComponent as Translator;

if (!defined('_PS_VERSION_')) {
    exit;
}

class TransactionManagement
{
    /**
     * @var \GlobalPayments
     */
    protected $module;

    /**
     * @var \Context|null
     */
    protected $context;

    /**
     * @var OrderModel
     */
    protected $order;

    /**
     * @var OrderStateHelper
     */
    protected $orderStateHelper;

    /**
     * @var TransactionHistory
     */
    protected $transactionHistory;

    /**
     * @var Translator
     */
    protected $translator;

    /**
     * TransactionManagement constructor.
     *
     * @param \GlobalPayments $module
     */
    public function __construct(
        \GlobalPayments $module
    ) {
        $this->module = $module;

        $this->context = $module->getContext();
        $this->order = new OrderModel();
        $this->orderStateHelper = new OrderStateHelper();
        $this->transactionHistory = new TransactionHistory();
        $this->translator = $this->module->getTranslator();
    }

    /**
     * States whether the Capture button should be displayed.
     *
     * @param \Order $psOrder
     *
     * @return bool
     *
     * @throws \PrestaShopDatabaseException
     */
    public function canCapture($psOrder)
    {
        $history = $psOrder->getHistory(
            $this->context->language->id,
            \Configuration::get(OrderStateInstaller::CAPTURE_WAITING)
        );

        return !empty($history)
            && $this->getCapturedValue($psOrder->id) === 0.00
            && !$this->waitingForPayment($psOrder);
    }

    /**
     * Check if a capture was made succseefully.
     *
     * @param string $responseCode
     * @param string $responseMessage
     *
     * @return bool
     */
    public function checkSuccessfullCapture($responseCode, $responseMessage)
    {
        if ('00' === $responseCode && 'Success' === $responseMessage
            || 'SUCCESS' === $responseCode && 'CAPTURED' === $responseMessage
        ) {
            return true;
        }

        return false;
    }

    /**
     * Create an authorization transaction for a specific order.
     *
     * @param int $orderId
     * @param float $amount
     * @param string $currency
     * @param string $transactionId
     *
     * @return void
     *
     * @throws \PrestaShopDatabaseException
     */
    public function createAuthorizationTransaction($orderId, $amount, $currency, $transactionId)
    {
        $this->createTransaction(
            $orderId,
            $amount,
            $currency,
            $transactionId,
            TransactionType::AUTHORIZE
        );
    }

    public function createSaleTransaction($orderId, $amount, $currency, $transactionId)
    {
        $this->createTransaction(
            $orderId,
            $amount,
            $currency,
            $transactionId,
            TransactionType::SALE
        );
    }

    /**
     * Create a transaction for a specific order.
     *
     * @param int $orderId
     * @param float $amount
     * @param string $currency
     * @param string $transactionId
     * @param string $transactionAction
     * @param string $transactionType
     * @param int $success
     *
     * @return void
     *
     * @throws \PrestaShopDatabaseException
     */
    public function createTransaction($orderId, $amount, $currency, $transactionId, $transactionType, $success = 1)
    {
        $this->transactionHistory->saveResult(
            $orderId,
            $transactionType,
            $amount,
            $currency,
            $transactionId,
            $success
        );
    }

    /**
     * Do a transaction in the Transaction Management Tab
     *
     * @param $psOrder
     *
     * @return void
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function doTransaction($psOrder)
    {
        if (\Tools::getValue('globalpayments_transaction')) {
            $request = \Tools::getValue('globalpayments_transaction');
            $amount = \Tools::getIsset('globalpayments_amount') ?
                (float) \Tools::getValue('globalpayments_amount') : 0.00;

            switch ($request) {
                case 'capture':
                    $this->processCapture($psOrder, $amount);

                    break;
                default:
                    break;
            }

            \Tools::redirectAdmin($_SERVER['REQUEST_URI']);
            exit;
        }
    }

    /**
     * Get the amount that was already captured for an order.
     *
     * @param $orderId
     *
     * @return float
     *
     * @throws \PrestaShopDatabaseException
     */
    public function getCapturedValue($orderId)
    {
        $orderHistory = $this->transactionHistory->getHistory($orderId);
        $orderAmount = 0.00;

        foreach ($orderHistory as $orderHistoryItem) {
            if ($orderHistoryItem['action'] === TransactionType::CAPTURE) {
                $orderAmount += $orderHistoryItem['amount'];
            }
        }

        return $orderAmount;
    }

    /**
     * States whether the current order has a transaction id attached to it.
     *
     * @param \Order $psOrder
     * @return bool
     */
    public function hasTxnId($psOrder)
    {
        $orderPayment = \OrderPayment::getByOrderReference($psOrder->reference)[0];

        return !empty($orderPayment->transaction_id);
    }

    /**
     * Execute the capture action.
     *
     * @param \Order $psOrder
     * @param float $amount
     *
     * @return void
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function processCapture($psOrder, $amount)
    {
        $orderPayment = \OrderPayment::getByOrderReference($psOrder->reference)[0];
        $gateway = new GpApiGateway();
        $currency = new \Currency($psOrder->id_currency);

        $order = $this->order->generateOrder(
            [
                'amount' => $amount,
                'currency' => $currency->iso_code,
                'transactionId' => $orderPayment->transaction_id,
            ]
        );

        try {
            $request = $gateway->prepareRequest(TransactionType::CAPTURE, $order);
            $response = $gateway->submitRequest($request);

            if (!$this->checkSuccessfullCapture($response->responseCode, $response->responseMessage)) {
                return;
            }

            $this->transactionHistory->saveResult(
                (int) $psOrder->id,
                TransactionType::CAPTURE,
                $amount,
                $currency->iso_code,
                $response->transactionReference->transactionId,
                1
            );

            $this->orderStateHelper->changeOrderState(
                $psOrder->id,
                $this->context->employee->id ?? '',
                \Configuration::get('PS_OS_PAYMENT')
            );
        } catch (\Exception $e) {
            $this->transactionHistory->saveResult(
                (int) $psOrder->id,
                TransactionType::CAPTURE,
                0,
                $currency->iso_code,
                '',
                0,
                $e->getMessage()
            );
            \Tools::displayError($e->getMessage());
        }
    }

    /**
     * Execute the refund action.
     *
     * @param \Order $psOrder
     * @param float $amount
     *
     * @return void
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function processRefund($psOrder, $amount)
    {
        // Validate refund amount before processing
        try {
            $this->validateRefundAmount($psOrder, $amount);
        } catch (\Exception $e) {
            // Display error on admin page and stop processing
            $this->context->controller->errors[] = \Tools::displayError($e->getMessage());
            \PrestaShopLogger::addLog(
                'GlobalPayments Refund Error: ' . $e->getMessage(),
                \PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR
            );
            return; // Stop processing further
        }

        $currency = new \Currency($psOrder->id_currency);
        $orderId = (int) $psOrder->id;

        if ($amount > 0.00) {
            $orderPayment = \OrderPayment::getByOrderReference($psOrder->reference)[0];

            $gateway = new GpApiGateway();

            $order = $this->order->generateOrder(
                [
                    'amount' => $amount,
                    'currency' => $currency->iso_code,
                    'description' => '',
                    'transactionId' => $orderPayment->transaction_id,
                ]
            );

            try {
                $response = $gateway->processRefund($order);

                if ($response) {
                    $this->transactionHistory->saveResult(
                        $orderId,
                        TransactionType::REFUND_REVERSE,
                        $amount,
                        $currency->iso_code,
                        $response->transactionReference->transactionId,
                        1
                    );

                    // Update order status to refunded after successful refund
                    $this->updateOrderStatusAfterRefund($psOrder, $amount);
                }
            } catch (\Exception $e) {
                $this->transactionHistory->saveResult(
                    $orderId,
                    TransactionType::REFUND_REVERSE,
                    $amount,
                    $currency->iso_code,
                    '',
                    0,
                    $e->getMessage(),
                );
                $this->context->controller->errors[] = \Tools::displayError($e->getMessage());
            }
        }
    }

    /**
     * States whether the order state is 'Waiting for payment'.
     *
     * @param \Order $psOrder
     * @return bool
     */
    public function waitingForPayment($psOrder)
    {
        $currentState = (int) $psOrder->getCurrentState();
        $globalPaymentsWaiting = (int) \Configuration::get(OrderStateInstaller::PAYMENT_WAITING);
        $preparation = (int) \Configuration::get('PS_OS_PREPARATION');

        return $currentState === $globalPaymentsWaiting || $currentState === $preparation;
    }

    /**
     * Validate if the refund amount is valid (less than or equal to the original order amount)
     *
     * @param \Order $psOrder
     * @param float $totalToBeRefunded
     *
     * @return bool
     * @throws \Exception
     */
    private function validateRefundAmount($psOrder, $totalToBeRefunded)
    {
        // Get the total amount of the original order
        $originalOrderAmount = (float) $psOrder->total_paid;

        // Get the total amount already refunded for this order
        $totalAlreadyRefunded = 0.00;
        $orderSlips = \OrderSlip::getOrdersSlip($psOrder->id_customer, $psOrder->id);

        foreach ($orderSlips as $slip) {
            $totalAlreadyRefunded += (float) $slip['total_products_tax_incl'] + (float) $slip['total_shipping_tax_incl'];
        }

        // Calculate the remaining refundable amount
        $remainingRefundableAmount = $originalOrderAmount - $totalAlreadyRefunded;

        // Validate that current refund amount doesn't exceed remaining refundable amount
        if ($totalToBeRefunded > $remainingRefundableAmount) {
            $errorMessage = sprintf(
                'Refund validation failed for Order #%d. Total refund amount (%.2f) exceeds original order amount (%.2f). Already refunded: %.2f, Current refund: %.2f',
                $psOrder->id,
                ($totalAlreadyRefunded + $totalToBeRefunded),
                $originalOrderAmount,
                $totalAlreadyRefunded,
                $totalToBeRefunded
            );

            // Log the detailed error for admin/debugging
            \PrestaShopLogger::addLog(
                'GlobalPayments: ' . $errorMessage,
                \PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR
            );

            // Throw user-friendly exception to stop processing
            $userErrorMessage = sprintf(
                'Cannot process refund: The refund amount (%.2f %s) exceeds the remaining refundable amount (%.2f %s) for this order.',
                $totalToBeRefunded,
                (new \Currency($psOrder->id_currency))->iso_code,
                $remainingRefundableAmount,
                (new \Currency($psOrder->id_currency))->iso_code
            );

            throw new \Exception($userErrorMessage);
        }

        // Additional validation: Check if refund amount is positive
        if ($totalToBeRefunded <= 0) {
            throw new \Exception('Refund amount must be greater than zero.');
        }

        return true;
    }

    /**
     * Validate refund amount only (without processing) - used for early validation
     *
     * @param \Order $psOrder
     * @param float $totalToBeRefunded
     *
     * @return bool
     * @throws \Exception
     */
    public function validateRefundAmountOnly($psOrder, $totalToBeRefunded)
    {
        return $this->validateRefundAmount($psOrder, $totalToBeRefunded);
    }

    /**
     * Update order status after successful refund
     *
     * @param \Order $psOrder
     * @param float $refundAmount
     *
     * @return void
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private function updateOrderStatusAfterRefund($psOrder, $refundAmount)
    {
        // Get the total amount of the original order
        $originalOrderAmount = (float) $psOrder->total_paid;

        // Get the total amount already refunded for this order (including current refund)
        $totalAlreadyRefunded = 0.00;
        $orderSlips = \OrderSlip::getOrdersSlip($psOrder->id_customer, $psOrder->id);

        foreach ($orderSlips as $slip) {
            $totalAlreadyRefunded += (float) $slip['total_products_tax_incl'] + (float) $slip['total_shipping_tax_incl'];
        }

        // Add current refund amount
        $totalRefunded = $totalAlreadyRefunded + $refundAmount;

        // Determine appropriate order status
        $newOrderStatus = null;

        if ($totalRefunded >= $originalOrderAmount) {
            // Full refund - set to refunded status
            $newOrderStatus = \Configuration::get('PS_OS_REFUND');
        } else {
            // Partial refund - set to partial refund status (if available)
            $partialRefundStatus = \Configuration::get('PS_OS_PARTIAL_REFUND');
            if ($partialRefundStatus) {
                $newOrderStatus = $partialRefundStatus;
            } else {
                // Fallback to refunded status if partial refund status doesn't exist
                $newOrderStatus = \Configuration::get('PS_OS_REFUND');
            }
        }

        // Update order status if we have a valid status
        if ($newOrderStatus) {
            $this->orderStateHelper->changeOrderState(
                $psOrder->id,
                $this->context->employee->id ?? 0,
                $newOrderStatus
            );

            // Log the status change
            \PrestaShopLogger::addLog(
                sprintf(
                    'GlobalPayments: Order #%d status updated to %s after refund of %.2f (Total refunded: %.2f/%.2f)',
                    $psOrder->id,
                    $newOrderStatus,
                    $refundAmount,
                    $totalRefunded,
                    $originalOrderAmount
                ),
                \PrestaShopLogger::LOG_SEVERITY_LEVEL_INFORMATIVE
            );
        }
    }
}
