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
        $cart = $this->context->cart;
        $customerId = $cart->id_customer ?? null;
        $customer = new Customer($customerId);

        $this->checkoutHelper = new CheckoutHelper($this->module, $cart);
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

            $order = $this->order->generateOrder(
                [
                    'amount' => $total,
                    'cartId' => $cart->id,
                    'billingAddress' => $billingAddress,
                    'currency' => $currency->iso_code,
                    'gatewayProviderId' => $paymentMethodId,
                    'orderId' => (string) $orderId,
                    'shippingAddress' => $shippingAddress,
                ]
            );

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
}
