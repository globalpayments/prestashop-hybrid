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

namespace GlobalPayments\PaymentGatewayProvider\Requests\OpenBanking;

use GlobalPayments\Api\Entities\Enums\RemittanceReferenceType;
use GlobalPayments\Api\PaymentMethods\BankPayment;
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
        $configData = $this->getArgument(RequestArg::CONFIG_DATA);

        $paymentMethod = new BankPayment();
        $paymentMethod->returnUrl = $providerData['returnUrl'];
        $paymentMethod->statusUpdateUrl = $providerData['statusUrl'];

        foreach ($configData as $key => $value) {
            if (is_array($value) && empty($value)) {
                continue;
            }
            if (isset($value) && property_exists($paymentMethod, $key)) {
                $paymentMethod->{$key} = $value;
            }
        }

        $orderId = $this->getArgument(RequestArg::ORDER_ID);
        $remittanceValue = 'ORD' . $orderId;

        return $paymentMethod->charge($this->getArgument(RequestArg::AMOUNT))
            ->withCurrency($this->getArgument(RequestArg::CURRENCY))
            ->withOrderId($orderId)
            ->withRemittanceReference(RemittanceReferenceType::TEXT, $remittanceValue)
            ->execute();
    }

    public function getArgumentList()
    {
        return [];
    }
}
