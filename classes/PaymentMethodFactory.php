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

namespace GlobalPayments\PaymentGatewayProvider;

use GlobalPayments\PaymentGatewayProvider\Gateways\AbstractGateway;
use GlobalPayments\PaymentGatewayProvider\Gateways\GatewayId;
use GlobalPayments\PaymentGatewayProvider\Gateways\GpApiGateway;
use GlobalPayments\PaymentGatewayProvider\PaymentMethods\AbstractPaymentMethod;
use GlobalPayments\PaymentGatewayProvider\PaymentMethods\Apm\PayPal;
use GlobalPayments\PaymentGatewayProvider\PaymentMethods\BuyNowPayLater\Affirm;
use GlobalPayments\PaymentGatewayProvider\PaymentMethods\BuyNowPayLater\Clearpay;
use GlobalPayments\PaymentGatewayProvider\PaymentMethods\BuyNowPayLater\Klarna;
use GlobalPayments\PaymentGatewayProvider\PaymentMethods\DigitalWallets\ApplePay;
use GlobalPayments\PaymentGatewayProvider\PaymentMethods\DigitalWallets\ClickToPay;
use GlobalPayments\PaymentGatewayProvider\PaymentMethods\DigitalWallets\GooglePay;
use GlobalPayments\PaymentGatewayProvider\PaymentMethods\OpenBanking\BankPayment;

if (!defined('_PS_VERSION_')) {
    exit;
}

class PaymentMethodFactory
{
    /**
     * @var array
     */
    protected $methodCodeMap = [
        Affirm::PAYMENT_METHOD_ID => Affirm::class,
        ApplePay::PAYMENT_METHOD_ID => ApplePay::class,
        BankPayment::PAYMENT_METHOD_ID => BankPayment::class,
        Clearpay::PAYMENT_METHOD_ID => Clearpay::class,
        ClickToPay::PAYMENT_METHOD_ID => ClickToPay::class,
        GatewayId::GP_UCP => GpApiGateway::class,
        GooglePay::PAYMENT_METHOD_ID => GooglePay::class,
        Klarna::PAYMENT_METHOD_ID => Klarna::class,
        PayPal::PAYMENT_METHOD_ID => PayPal::class,
    ];

    /**
     * Create a new payment method based on the method code.
     *
     * @param string $methodCode
     * @param array $arguments
     * @return AbstractPaymentMethod|AbstractGateway
     */
    public function create($methodCode, $arguments = [])
    {
        if (empty($this->methodCodeMap[$methodCode])) {
            throw new \InvalidArgumentException('"' . $methodCode . '": isn\'t allowed');
        }

        $paymentMethod = $this->methodCodeMap[$methodCode];

        return new $paymentMethod(...$arguments);
    }
}
