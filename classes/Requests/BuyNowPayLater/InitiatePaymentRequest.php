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

namespace GlobalPayments\PaymentGatewayProvider\Requests\BuyNowPayLater;

use GlobalPayments\Api\Entities\Enums\AddressType;
use GlobalPayments\Api\Entities\Enums\BNPLShippingMethod;
use GlobalPayments\Api\PaymentMethods\BNPL;
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\AddressHelper;
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\CustomerHelper;
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\OrderHelper;
use GlobalPayments\PaymentGatewayProvider\Requests\AbstractRequest;
use GlobalPayments\PaymentGatewayProvider\Requests\RequestArg;

if (!defined('_PS_VERSION_')) {
    exit;
}

class InitiatePaymentRequest extends AbstractRequest
{
    public function getTransactionType()
    {
        return $this->getArgument(RequestArg::PAYMENT_ACTION);
    }

    public function doRequest()
    {
        $providerData = $this->getArgument(RequestArg::ASYNC_PAYMENT_DATA);
        $paymentMethod = new BNPL($providerData['provider']);
        $paymentMethod->returnUrl = $providerData['returnUrl'];
        $paymentMethod->statusUpdateUrl = $providerData['statusUrl'];
        $paymentMethod->cancelUrl = $providerData['cancelUrl'];

        $orderId = $this->getArgument(RequestArg::ORDER_ID);
        $orderHelper = new OrderHelper($orderId);
        $customerHelper = new CustomerHelper();
        $customerData = $customerHelper->getCustomerDataByCartId($this->getArgument(RequestArg::CART_ID));

        $addressHelper = new AddressHelper();
        $billingAddress = $addressHelper->mapAddress($this->getArgument(RequestArg::BILLING_ADDRESS));
        $shippingMethod = $orderHelper->getShippingMethod();
        if ($shippingMethod === BNPLShippingMethod::EMAIL) {
            $shippingAddress = $billingAddress;
        } else {
            $shippingAddress = $addressHelper->mapAddress($this->getArgument(RequestArg::SHIPPING_ADDRESS));
        }

        return $paymentMethod->authorize($this->getArgument(RequestArg::AMOUNT))
            ->withCurrency($this->getArgument(RequestArg::CURRENCY))
            ->withOrderId($orderId)
            ->withProductData($orderHelper->getProductData())
            ->withAddress($shippingAddress, AddressType::SHIPPING)
            ->withAddress($billingAddress, AddressType::BILLING)
            ->withCustomerData($customerData)
            ->withBNPLShippingMethod($orderHelper->getShippingMethod())
            ->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function getArgumentList()
    {
        return [];
    }
}
