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
use GlobalPayments\PaymentGatewayProvider\Data\Order;
use GlobalPayments\PaymentGatewayProvider\PaymentMethodFactory;
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\AddressHelper;
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\CheckoutHelper;
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\OrderStateHelper;
use GlobalPayments\PaymentGatewayProvider\Platform\Utils;

if (!defined('_PS_VERSION_')) {
    exit;
}

class GlobalPaymentsDwValidationModuleFrontController extends ModuleFrontController
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
     * @var Order
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

        $this->order = new Order();
        $this->orderStateHelper = new OrderStateHelper();
        $this->paymentMethodFactory = new PaymentMethodFactory();
        $this->utils = new Utils();
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

        try {
            $billingAddress = $this->addressHelper->getBillingAddress();
            $shippingAddress = $this->addressHelper->getShippingAddress();
            $paymentMethod = $this->paymentMethodFactory->create($paymentMethodId);
            $total = (float) $cart->getOrderTotal(true, Cart::BOTH);
            $requestData = Tools::getValue($paymentMethodId);
            $dwToken = str_replace('\\\\', '\\', $requestData['dw_token']);
            $payerInfo = $requestData['payer_info'] ? json_decode($requestData['payer_info'], true) : null;
            $orderState = $this->orderStateHelper->getOrderState($paymentMethod->paymentAction);

            $order = $this->order->generateOrder(
                [
                    'amount' => $total,
                    'billingAddress' => $billingAddress,
                    'cartId' => $cart->id,
                    'currency' => $currency->iso_code,
                    'dwToken' => $dwToken,
                    'gatewayProviderId' => $paymentMethodId,
                    'payerInfo' => $payerInfo,
                    'shippingAddress' => $shippingAddress,
                ]
            );

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

            if ($this->module->currentOrder) {
                $paymentMethod->processPaymentAfterGatewayResponse($transaction, $this->module->currentOrder);
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
}
