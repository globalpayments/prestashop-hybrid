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
use GlobalPayments\PaymentGatewayProvider\PaymentMethodFactory;
use GlobalPayments\PaymentGatewayProvider\PaymentMethods\AsyncPaymentMethodInterface;
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\AddressHelper;
use GlobalPayments\PaymentGatewayProvider\Gateways\DiUiApms\{BankSelect, Blik};
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\CheckoutHelper;
use GlobalPayments\PaymentGatewayProvider\Platform\OrderStateInstaller;
use GlobalPayments\PaymentGatewayProvider\Platform\TransactionManagement;
use GlobalPayments\PaymentGatewayProvider\Platform\Utils;
use GlobalPayments\PaymentGatewayProvider\Platform\Validator\BuyNowPayLater\Validation;
use GlobalPayments\PaymentGatewayProvider\Requests\TransactionType;

if (!defined('_PS_VERSION_')) {
    exit;
}

class GlobalPaymentsAsyncPaymentMethodValidationModuleFrontController extends ModuleFrontController
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
     * @var PaymentMethodFactory
     */
    private $paymentMethodFactory;

    /**
     * @var TransactionManagement
     */
    private $transactionManagement;

    /**
     * @var Utils
     */
    private $utils;

    /**
     * @var Validation
     */
    private $validation;

    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        parent::__construct();

        $this->order = new OrderModel();
        $this->paymentMethodFactory = new PaymentMethodFactory();
        $this->transactionManagement = new TransactionManagement($this->module);
        $this->utils = new Utils();
        $this->validation = new Validation($this->module, $this->context->cart);
    }

    /**
     * {@inheritdoc}
     */
    public function postProcess()
    {
        // Debug: Log that we've entered this method
        file_put_contents(
            __DIR__ . '/debug_controller.log', date('Y-m-d H:i:s') . " - postProcess() called\n", FILE_APPEND
        );

        $cart = $this->context->cart;
        $customerId = $cart->id_customer ?? null;
        $customer = new Customer($customerId);

        // Initialize helpers early for both BLIK and regular payments
        $this->addressHelper = new AddressHelper();
        $this->checkoutHelper = new CheckoutHelper($this->module, $cart);

        // Handle Blik payment method specifically first, before validation
        $paymentMethod = Tools::getValue('payment_method'); // For Blik requests
        $gatewayId = Tools::getValue('gateway_id'); // For Blik requests

        $bank = Tools::getValue('bank'); // for  open banking request
        // Debug: Log BLIK request parameters
        file_put_contents(__DIR__ . '/debug_controller.log',
            date('Y-m-d H:i:s') . " - payment_method: '$paymentMethod', gateway_id: '$gatewayId'\n",
            FILE_APPEND
        );

        if ($paymentMethod === 'blik' && !empty($gatewayId)) {
            error_log('DEBUG: BLIK condition matched! Processing BLIK payment');
            file_put_contents(__DIR__ . '/debug_controller.log',
                date('Y-m-d H:i:s') . " - BLIK condition matched!\n",
                FILE_APPEND
            );

            // Validate cart for BLIK payment
            if (empty($cart->id) || !Validate::isLoadedObject($cart)) {
                header('Content-Type: application/json');
                die(json_encode([
                    'success' => false,
                    'message' => 'Invalid cart. Please refresh the page and try again.'
                ]));
            }

            return $this->processBlikPayment($cart, $this->context->currency, $gatewayId, $customer);
        }

        if ($paymentMethod === 'open_banking' && !empty($gatewayId)) {
            error_log('DEBUG: Open Banking condition matched! Processing Open Banking payment');
            file_put_contents(__DIR__ . '/debug_controller.log',
                date('Y-m-d H:i:s') . " - Open Banking condition matched!\n",
                FILE_APPEND
            );

            // Validate cart for Open Banking payment
            if (empty($cart->id) || !Validate::isLoadedObject($cart)) {
                header('Content-Type: application/json');
                die(json_encode([
                    'success' => false,
                    'message' => 'Invalid cart. Please refresh the page and try again.'
                ]));
            }

            return $this->processOpenBankingPayment($cart, $this->context->currency, $gatewayId, $customer,$bank);
        }

        // Regular checkout validation for non-BLIK payments
        $this->checkoutHelper->validate();

        $this->addressHelper = new AddressHelper();
        $currency = $this->context->currency;
        $paymentMethodId = Tools::getValue('payment-method-id');
        $translator = $this->module->getTranslator();

        try {
            $billingAddress = $this->addressHelper->getBillingAddress();
            $shippingAddress = $this->addressHelper->getShippingAddress();
            /**
             * @var AsyncPaymentMethodInterface
             */
            $paymentMethod = $this->paymentMethodFactory->create($paymentMethodId);
            $total = (float) $cart->getOrderTotal(true, Cart::BOTH);
            $orderState = Configuration::get(OrderStateInstaller::PAYMENT_WAITING);

            if ($paymentMethod->validateAddress()) {
                $this->validation->validate($billingAddress, $shippingAddress, $paymentMethod->isShippingRequired());
            }

            $this->module->validateOrder(
                (int) $cart->id,
                (int) $orderState,
                $total,
                $paymentMethod->title,
                '',
                [],
                (int) $currency->id,
                false,
                $customer->secure_key
            );

            $orderId = $this->module->currentOrder;
            $this->checkoutHelper->restoreCart($orderId);

            $transaction = $paymentMethod->processPayment($order);
            $transactionId = $transaction->transactionReference->transactionId;

            /*
             * Add the transaction ID to the newly created order.
             */
            $order = new Order($orderId);
            $orderPayment = OrderPayment::getByOrderReference($order->reference)[0];
            $orderPayment->transaction_id = $transactionId;
            $orderPayment->save();

            $this->transactionManagement->createTransaction(
                $orderId,
                $total,
                $currency->iso_code,
                $transactionId,
                TransactionType::INITIATE_PAYMENT
            );

            $paymentMethod->processPaymentAfterGatewayResponse($transaction, $orderId);

            $this->checkoutHelper->postResponse(
                false,
                $paymentMethod->getRedirectUrl($transaction)
            );
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
     * Process Open Banking payment specifically
     *
     * @param Cart $cart
     * @param Currency $currency
     * @param string $gatewayId
     * @param Customer $customer
     */
    private function processBlikPayment($cart, $currency, $gatewayId, $customer)
    {
        try {
            // Get the gateway to process Open Banking payment
            $gateway = $this->paymentMethodFactory->create($gatewayId);
            $total = (float) $cart->getOrderTotal(true, Cart::BOTH);
            $orderState = Configuration::get(OrderStateInstaller::PAYMENT_WAITING);

            // Create the order
            $this->module->validateOrder(
                (int) $cart->id,
                (int) $orderState,
                $total,
                'Blik Payment',
                '',
                [],
                (int) $currency->id,
                false,
                $customer->secure_key
            );

            $orderId = $this->module->currentOrder;
            $this->checkoutHelper->restoreCart($orderId);

            // Generate order object for the gateway

            // Process the Blik sale transaction using GlobalPayments SDK
            $blikResult = Blik::processBlikSale($gateway, $orderId);

            if ($blikResult['result'] === 'success') {
                // Return success response with redirect URL
                header('Content-Type: application/json');
                die(json_encode([
                    'success' => true,
                    'redirect_url' => $blikResult['redirect'],
                    'order_id' => $orderId
                ]));
            } else {
                throw new Exception('BLIK payment processing failed');
            }

        } catch (GatewayException $e) {
            header('Content-Type: application/json');
            die(json_encode([
                'success' => false,
                'message' => $this->utils->mapResponseCodeToFriendlyMessage()
            ]));
        } catch (Exception $e) {
            header('Content-Type: application/json');
            die(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
        }
    }

    /**
     * Process Open Banking payment specifically
     *
     * @param Cart $cart
     * @param Currency $currency
     * @param string $gatewayId
     * @param Customer $customer
     */
    private function processOpenBankingPayment($cart, $currency, $gatewayId, $customer,$bank)
    {
        try {// Get the gateway to process Open Banking payment
            $gateway = $this->paymentMethodFactory->create($gatewayId);
            $total = (float) $cart->getOrderTotal(true, Cart::BOTH);
            $orderState = Configuration::get(OrderStateInstaller::PAYMENT_WAITING);

            // Create the order
            $this->module->validateOrder(
                (int) $cart->id,
                (int) $orderState,
                $total,
                'Open Banking Payment',
                '',
                [],
                (int) $currency->id,
                false,
                $customer->secure_key
            );

            $orderId = $this->module->currentOrder;
            $this->checkoutHelper->restoreCart($orderId);

            // Process the Open Banking sale transaction using GlobalPayments SDK
            $openBankingResult = BankSelect::processOpenBankingSale($gateway, $orderId,$bank);
            if ($openBankingResult['result'] === 'success') {
                // Return success response with redirect URL
                header('Content-Type: application/json');
                die(json_encode([
                    'success' => true,
                    'redirect_url' => $openBankingResult['redirect'],
                    'order_id' => $orderId
                ]));
            } else {
                throw new Exception('Open Banking payment processing failed');
            }

        } catch (GatewayException $e) {
            header('Content-Type: application/json');
            die(json_encode([
                'success' => false,
                'message' => $this->utils->mapResponseCodeToFriendlyMessage()
            ]));
        } catch (Exception $e) {
            header('Content-Type: application/json');
            die(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
        }
    }
}
