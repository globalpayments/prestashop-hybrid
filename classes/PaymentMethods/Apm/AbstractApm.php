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

namespace GlobalPayments\PaymentGatewayProvider\PaymentMethods\Apm;

use GlobalPayments\PaymentGatewayProvider\PaymentMethods\AbstractAsyncPaymentMethod;
use GlobalPayments\PaymentGatewayProvider\Requests\TransactionType;

if (!defined('_PS_VERSION_')) {
    exit;
}

abstract class AbstractApm extends AbstractAsyncPaymentMethod
{
    /**
     * Payment method APM provider. Should be overridden by individual APM payment methods implementations.
     *
     * @var string
     */
    public $apmProvider;

    /**
     * {@inheritdoc}
     */
    public function enqueuePaymentScripts($module)
    {
        if (!parent::enqueuePaymentScripts($module)) {
            return;
        }

        \Media::addJsDef(
            [
                'globalpayments_apm_params' => [
                    'paymentMethodOptions' => $this->getFrontendPaymentMethodOptions(),
                ],
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getFrontendPaymentMethodOptions()
    {
        return [
            'providers' => [
                PayPal::PAYMENT_METHOD_ID,
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getRedirectUrl($transaction)
    {
        return $transaction->alternativePaymentResponse->redirectUrl;
    }

    /**
     * {@inheritDoc}
     */
    public function getRequestType()
    {
        return TransactionType::APM_AUTHORIZATION;
    }

    /**
     * {@inheritDoc}
     */
    public function validateAddress()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getPaymentMethodFormFields()
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function isAvailable()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getProviderEndpoints()
    {
        $contextLink = \Context::getContext()->link;

        return [
            'provider' => $this->apmProvider,
            'returnUrl' => $contextLink->getModuleLink(
                'globalpayments',
                'asyncPaymentMethodReturn',
                [],
                true
            ),
            'cancelUrl' => $contextLink->getModuleLink(
                'globalpayments',
                'asyncPaymentMethodCancel',
                [],
                true
            ),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function validateAdminSettings()
    {
        $errors = [];
        if (!\Tools::getValue($this->id . '_enabled')) {
            return $errors;
        }

        $sortOrderValidation = $this->configValidation->validateSortOrder($this->id);
        if ($sortOrderValidation) {
            $errors[] = $sortOrderValidation;
        }

        return $errors;
    }
}
