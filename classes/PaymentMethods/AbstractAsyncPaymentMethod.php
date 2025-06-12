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

namespace GlobalPayments\PaymentGatewayProvider\PaymentMethods;

use GlobalPayments\PaymentGatewayProvider\Platform\OrderAdditionalInfo;
use GlobalPayments\PaymentGatewayProvider\Requests\RequestArg;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

abstract class AbstractAsyncPaymentMethod extends AbstractPaymentMethod implements AsyncPaymentMethodInterface
{
    protected $template = 'module:globalpayments/views/templates/front/async-payment-method/payment_form.tpl';

    /**
     * {@inheritdoc}
     */
    public function enqueuePaymentScripts($module)
    {
        if (!$this->enabled) {
            return;
        }

        $context = $module->getContext();
        $path = $module->getFrontendScriptsPath();

        $context->controller->registerStylesheet(
            'globalpayments-async-payment-method',
            $path . '/views/css/globalpayments-async-payment-method.css'
        );

        $context->controller->registerJavascript(
            'globalpayments-async-payment-method',
            $path . '/views/js/async-payment-method/globalpayments-async-payment-method.js'
        );

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentOptions($module, $params, $isCheckout)
    {
        if (!$this->isAvailable()) {
            return [];
        }

        $context = $module->getContext();
        $formAction = $context->link->getModuleLink($module->name, 'asyncPaymentMethodValidation', [], true);
        $paymentOptions = [];
        $paymentOption = new PaymentOption();

        $defaultSmartyVariables = [
            'action' => $formAction,
            'id' => $this->id,
        ];
        $smartyVariables = array_merge($defaultSmartyVariables, $this->getAdditionalSmartyVariables($module));

        $context->smarty->assign($smartyVariables);

        $paymentOption->setModuleName($this->id)
            ->setCallToActionText($this->title)
            ->setForm($context->smarty->fetch($this->template));

        $paymentOptions[] = $paymentOption;

        return $paymentOptions;
    }

    /**
     * Get additional smarty variables. Should be overridden by individual payment methods implementations.
     *
     * @param \GlobalPayments $module
     *
     * @return array
     */
    public function getAdditionalSmartyVariables($module)
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function processPaymentAfterGatewayResponse($gatewayResponse, $orderId)
    {
        parent::processPaymentAfterGatewayResponse($gatewayResponse, $orderId);

        $orderAdditionalInfo = new OrderAdditionalInfo();
        $orderAdditionalInfo->setAdditionalInfo(
            $orderId,
            AbstractPaymentMethod::PAYMENT_METHOD_ID,
            $this->id
        );
    }

    /**
     * {@inheritdoc}
     */
    public function processPaymentBeforeGatewayRequest($request, $order)
    {
        $request->setArguments(
            [
                RequestArg::ASYNC_PAYMENT_DATA => $this->getProviderEndpoints(),
                RequestArg::DYNAMIC_DESCRIPTOR => $this->gateway->txnDescriptor,
                RequestArg::PAYMENT_ACTION => $this->paymentAction,
            ]
        );
    }
}
