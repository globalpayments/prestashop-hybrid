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

use GlobalPayments\Api\Entities\Exceptions\GatewayException;
use GlobalPayments\PaymentGatewayProvider\Data\Order as OrderModel;
use GlobalPayments\PaymentGatewayProvider\Data\PaymentTokenData;
use GlobalPayments\PaymentGatewayProvider\PaymentMethodFactory;
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\AddressHelper;
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\CheckoutHelper;
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\OrderStateHelper;
use GlobalPayments\PaymentGatewayProvider\Platform\Utils;
use GlobalPayments\PaymentGatewayProvider\Requests\IntegrationType;

if (!defined('_PS_VERSION_')) {
    exit;
}

class GlobalPaymentsValidationModuleFrontController extends ModuleFrontController
{
    /**
     * @var AddressHelper
     */
    private $addressHelper;

    /**
     * @var CheckoutHelper
     */
    private $checkoutHelper;

    /**
     * @var OrderModel
     */
    private $order;

    /**
     * @var OrderStateHelper
     */
    private $orderStateHelper;

    /**
     * @var PaymentMethodFactory
     */
    private $paymentMethodFactory;

    /**
     * @var Utils
     */
    private $utils;

    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        parent::__construct();

        $this->order = new OrderModel();
        $this->orderStateHelper = new OrderStateHelper();
        $this->paymentMethodFactory = new PaymentMethodFactory();
        $this->utils = new Utils();
    }

    public function postProcess()
    {
        $cart = $this->context->cart;
        $customerId = $cart->id_customer ?? null;
        $customer = new Customer($customerId);

        $this->checkoutHelper = new CheckoutHelper($this->module, $cart);
        $this->checkoutHelper->validate();

        $this->addressHelper = new AddressHelper();
        $currency = $this->context->currency;
        $paymentMethodId = Tools::getValue('payment-method-id');
        $paymentMethod = $this->paymentMethodFactory->create($paymentMethodId);
        
        // Check if this is HPP mode (Hosted Payment Page)
        // If integrationType is HOSTED_PAYMENT_PAGE, redirect to HPP
        if ($paymentMethod->integrationType === IntegrationType::HOSTED_PAYMENT_PAGE) {
            $this->processHppPayment($cart, $customer, $currency, $paymentMethod);
            return;
        }

        $customerName = $customer->firstname . ' ' . $customer->lastname;

        $cardDataRaw = Tools::getIsset($paymentMethodId) ?
            Tools::getValue($paymentMethodId) : null;
        $cardData = $cardDataRaw && array_key_exists('token_response', $cardDataRaw) ?
            json_decode($cardDataRaw['token_response']) : null;

        // 3DS
        $serverTransId = $cardDataRaw && array_key_exists('serverTransId', $cardDataRaw) ?
            $cardDataRaw['serverTransId'] : null;

        // Card Storage
        $muToken = Tools::getIsset($paymentMethodId . '-enable-vault')
            && Tools::getValue($paymentMethodId . '-enable-vault') === 'on';
        $muTokenId = Tools::getIsset('globalpayments-payment-method') ?
            (int) Tools::getValue('globalpayments-payment-method') : null;

        try {
            $billingAddress = $this->addressHelper->getBillingAddress();
            $shippingAddress = $this->addressHelper->getShippingAddress();
            $total = (float) $cart->getOrderTotal(true, Cart::BOTH);
            $orderState = $this->orderStateHelper->getOrderState($paymentMethod->paymentAction);

            // Force generic 'Waiting for payment' state for BLIK and Open Banking
            $blikMethodIds = ['blik'];
            $openBankingMethodIds = ['ob'];
            if (in_array(strtolower($paymentMethodId), $blikMethodIds) || in_array(strtolower($paymentMethodId), $openBankingMethodIds)) {
                $orderState = (int) \Configuration::get('PS_OS_PAYMENT');
            }

            $order = $this->order->generateOrder(
                [
                    'amount' => $total,
                    'billingAddress' => $billingAddress,
                    'cardData' => $cardData,
                    'cardHolderName' => $this->module->getCardHolderName($customerName, $cardData),
                    'cartId' => $cart->id,
                    'currency' => $currency->iso_code,
                    'dynamicDescriptor' => $paymentMethod->txnDescriptor,
                    'gatewayProviderId' => $paymentMethodId,
                    'multiUseTokenId' => $muTokenId,
                    'requestMultiUseToken' => $muToken,
                    'serverTransId' => $serverTransId,
                    'shippingAddress' => $shippingAddress,
                ]
            );

            if (null !== $muTokenId) {
                $token = (new PaymentTokenData($order->asArray()))->getMultiUseToken();
                $order->cardData = $token;
            }

            $transaction = $paymentMethod->processPayment($order);

            $extraVars = [
                'transaction_id' => $transaction->transactionReference->transactionId,
            ];

            $this->module->validateOrder(
                (int) $cart->id,
                (int) $orderState,
                $total,
                $paymentMethod->title,
                '',
                $extraVars,
                (int) $currency->id,
                false,
                $customer->secure_key
            );
            if (null === $muTokenId && $order->cardData) {
                $currentOrder = Order::getByCartId($cart->id);
                $orderPayment = OrderPayment::getByOrderReference($currentOrder->reference)[0];
                $cardDetails = $order->cardData->details;

                $orderPayment->card_number = $cardDetails->cardLast4;
                $orderPayment->card_brand = $cardDetails->cardType;
                $orderPayment->card_expiration = $cardDetails->expiryMonth . '/' . $cardDetails->expiryYear;
                $orderPayment->card_holder = $order->cardHolderName;
                $orderPayment->save();
            }

            $this->checkoutHelper->getSuccessPage($this->module->currentOrder);
        } catch (GatewayException $e) {
            $this->checkoutHelper->postResponse(
                true,
                '',
                $this->utils->mapResponseCodeToFriendlyMessage()
            );
        } catch (Exception $e) {
            $this->checkoutHelper->postResponse(
                true,
                '',
                $e->getMessage()
            );
        }
    }

    /**
     * Process HPP (Hosted Payment Page) payment
     *
     * Creates a pending order and redirects to GP-API hosted payment page.
     * Follows the same pattern as Magento HPP implementation.
     *
     * @param \Cart $cart Shopping cart
     * @param \Customer $customer Customer
     * @param \Currency $currency Currency
     * @param object $paymentMethod Payment method instance
     * @return void
     */
    private function processHppPayment(
        \Cart $cart,
        \Customer $customer,
        \Currency $currency,
        object $paymentMethod
    ): void {
        try {

            $billingAddress = $this->addressHelper->getBillingAddress();
            $shippingAddress = $this->addressHelper->getShippingAddress();
            $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
            $customerName = $customer->firstname . ' ' . $customer->lastname;

            // Create pending order first
            $orderState = (int)\Configuration::get('GLOBALPAYMENTS_PAYMENT_WAITING');
 

            $this->module->validateOrder(
                (int)$cart->id,
                $orderState,
                $total,
                $paymentMethod->title,
                '',
                [],
                (int)$currency->id,
                false,
                $customer->secure_key
            );

            $orderId = $this->module->currentOrder;
            $order = new \Order($orderId);

            //This is key for restoring the cart on payment failure
            $this->checkoutHelper->restoreCart($orderId);

            // Create order model for gateway
            $orderModel = $this->order->generateOrder(
                [
                    'amount' => $total,
                    'billingAddress' => $billingAddress,
                    'cardData' => null,
                    'cardHolderName' => $customerName,
                    'cartId' => $cart->id,
                    'currency' => $currency->iso_code,
                    'dynamicDescriptor' => $paymentMethod->txnDescriptor,
                    'gatewayProviderId' => $paymentMethod->id,
                    'multiUseTokenId' => null,
                    'requestMultiUseToken' => false,
                    'serverTransId' => null,
                    'shippingAddress' => $shippingAddress,
                ]
            );

            // Process HPP payment - returns transaction with redirect URL
            $transaction = $paymentMethod->processHppPayment($orderModel, $orderId);

            // Get redirect URL from transaction
            if (!empty($transaction->payByLinkResponse->url)) {
                $redirectUrl = $transaction->payByLinkResponse->url;
                $transactionId = $transaction->transactionReference->transactionId ?? '';

                // Store transaction data in order payment for later completion
                $this->updateOrderPaymentWithTransactionId($order, $transactionId);

                // Add message to order history
                $this->addHppInitiationMessage($order, $transactionId, $orderState);

                // Return JSON response with redirect URL for AJAX handling
                $this->checkoutHelper->postResponse(false, $redirectUrl);
            } else {
                throw new \Exception($this->module->l('Failed to get HPP redirect URL'));
            }
        } catch (GatewayException $e) {
            $this->checkoutHelper->postResponse(
                true,
                '',
                $this->utils->mapResponseCodeToFriendlyMessage()
            );
        } catch (\Exception $e) {
            $this->logError('HPP payment failed with exception', [
                'cart_id' => $cart->id ?? null,
                'error' => $e->getMessage(),
            ]);

            $this->checkoutHelper->postResponse(
                true,
                '',
                $e->getMessage()
            );
        }
    }

    /**
     * Update order payment with transaction ID
     *
     * @param \Order $order Order
     * @param string $transactionId Transaction ID
     * @return void
     */
    private function updateOrderPaymentWithTransactionId(\Order $order, string $transactionId): void
    {
        // TODO We dont have transaction ID at this point
        // if (empty($transactionId)) {
        //     $this->logWarning('Empty transaction ID provided for order payment update', [
        //         'order_id' => $order->id,
        //     ]);
        //     return;
        // }

        $orderPayments = \OrderPayment::getByOrderReference($order->reference);

        if (!empty($orderPayments)) {
            $orderPayment = $orderPayments[0];
            $orderPayment->transaction_id = $transactionId;
            $orderPayment->save();
        } else {
            $this->logWarning('No order payment found to update', [
                'order_id' => $order->id,
                'order_reference' => $order->reference,
            ]);
        }
    }

    /**
     * Add HPP initiation message to order
     *
     * @param \Order $order Order
     * @param string $transactionId Transaction ID
     * @param int $orderState Order state ID
     * @return void
     */
    private function addHppInitiationMessage(\Order $order, string $transactionId, int $orderState): void
    {
        // Add message to order history
        $message = sprintf(
            $this->module->l('HPP payment initiated. Transaction ID: %s'),
            $transactionId ?: 'N/A'
        );

        // Add private message to order
        $msg = new \Message();
        $msg->message = $message;
        $msg->id_order = $order->id;
        $msg->private = 1;
        $msg->add();
    }

    /**
     * Log warning message
     *
     * @param string $message Log message
     * @param array $context Additional context
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
     * @param array $context Additional context
     * @return void
     */
    private function logError(string $message, array $context = []): void
    {
        $this->log($message, \PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR, $context);
    }

    /**
     * Log message with context
     *
     * @param string $message Log message
     * @param int $severity Severity level
     * @param array $context Additional context
     * @return void
     */
    private function log(string $message, int $severity, array $context = []): void
    {
        $logMessage = sprintf('HPP Validation: %s', $message);

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