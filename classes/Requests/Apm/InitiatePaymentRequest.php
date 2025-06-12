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

namespace GlobalPayments\PaymentGatewayProvider\Requests\Apm;

use GlobalPayments\Api\PaymentMethods\AlternativePaymentMethod;
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\CustomerHelper;
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
        $customerData = $this->getCustomerData();

        $paymentMethod = new AlternativePaymentMethod($providerData['provider']);
        $paymentMethod->returnUrl = $providerData['returnUrl'];
        $paymentMethod->statusUpdateUrl = $providerData['statusUrl'] ?? '';
        $paymentMethod->cancelUrl = $providerData['cancelUrl'] ?? '';
        $paymentMethod->descriptor = 'ORD' . $this->getArgument(RequestArg::ORDER_ID);
        $paymentMethod->country = $customerData['country'];
        $paymentMethod->accountHolderName = $customerData['accountHolderName'];

        return $paymentMethod
            ->{$this->getArgument(RequestArg::PAYMENT_ACTION)}($this->getArgument(RequestArg::AMOUNT))
            ->withCurrency($this->getArgument(RequestArg::CURRENCY))
            ->withOrderId($this->getArgument(RequestArg::ORDER_ID))
            ->execute();
    }

    private function getCustomerData()
    {
        $customerHelper = new CustomerHelper();
        $customerData = $customerHelper->getCustomerDataByCartId($this->getArgument(RequestArg::CART_ID));
        $billingAddress = $this->getArgument(RequestArg::BILLING_ADDRESS);

        return [
            'accountHolderName' => $customerData->firstName . ' ' . $customerData->lastName,
            'country' => $billingAddress['countryCode'] ?? '',
        ];
    }

    public function getArgumentList()
    {
        return [];
    }
}
