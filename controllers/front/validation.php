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
}
