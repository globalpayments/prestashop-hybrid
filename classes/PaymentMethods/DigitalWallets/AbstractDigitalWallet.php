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

namespace GlobalPayments\PaymentGatewayProvider\PaymentMethods\DigitalWallets;

use GlobalPayments\Api\Entities\Enums\EncyptedMobileType;
use GlobalPayments\PaymentGatewayProvider\PaymentMethods\AbstractPaymentMethod;
use GlobalPayments\PaymentGatewayProvider\Requests\RequestArg;
use GlobalPayments\PaymentGatewayProvider\Requests\TransactionType;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

abstract class AbstractDigitalWallet extends AbstractPaymentMethod
{
    public const DIGITAL_WALLET_PAYER_DETAILS = 'digitalWalletPayerDetails';

    protected $template = 'module:globalpayments/views/templates/front/digital-wallets/payment_form.tpl';

    /**
     * @return EncyptedMobileType
     */
    abstract public function getMobileType();

    /**
     * {@inheritDoc}
     */
    public function getPaymentOptions($module, $params, $isCheckout)
    {
        $context = $module->getContext();
        $formAction = $context->link->getModuleLink($module->name, 'dwValidation', [], true);
        $paymentOptions = [];
        $paymentOption = new PaymentOption();

        $context->smarty->assign([
            'action' => $formAction,
            'id' => $this->id,
        ]);

        $paymentOption->setModuleName($this->id)
            ->setCallToActionText($this->title)
            ->setForm($context->smarty->fetch($this->template));

        $paymentOptions[] = $paymentOption;

        return $paymentOptions;
    }

    /**
     * {@inheritdoc}
     */
    public function processPaymentBeforeGatewayRequest($request, $order)
    {
        $request->setArguments(
            [
                RequestArg::DYNAMIC_DESCRIPTOR => $this->gateway->txnDescriptor,
                RequestArg::MOBILE_TYPE => $this->getMobileType(),
                RequestArg::PAYMENT_ACTION => $this->paymentAction,
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestType()
    {
        return TransactionType::DW_AUTHORIZATION;
    }
}
