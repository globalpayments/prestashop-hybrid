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

namespace GlobalPayments\PaymentGatewayProvider\PaymentMethods\BuyNowPayLater;

use GlobalPayments\PaymentGatewayProvider\PaymentMethods\AbstractAsyncPaymentMethod;
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\AddressHelper;
use GlobalPayments\PaymentGatewayProvider\Requests\TransactionType;

if (!defined('_PS_VERSION_')) {
    exit;
}

abstract class AbstractBuyNowPayLater extends AbstractAsyncPaymentMethod
{
    /**
     * Payment method BNPL provider. Should be overridden by individual BNPL payment methods implementations.
     *
     * @var string
     */
    public $paymentMethodBNPLProvider;

    /**
     * Currencies and countries this payment method is allowed for.
     *
     * @return array
     */
    abstract public function getMethodAvailability();

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
                'globalpayments_bnpl_params' => [
                    'paymentMethodOptions' => $this->getFrontendPaymentMethodOptions(),
                ],
            ]
        );
    }

    public function getFrontendPaymentMethodOptions()
    {
        return [
            'providers' => [
                Affirm::PAYMENT_METHOD_ID,
                Clearpay::PAYMENT_METHOD_ID,
                Klarna::PAYMENT_METHOD_ID,
            ],
        ];
    }

    public function getPaymentMethodFormFields()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getRedirectUrl($transaction)
    {
        return $transaction->transactionReference->bnplResponse->redirectUrl;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestType()
    {
        return TransactionType::BNPL_AUTHORIZATION;
    }

    /**
     * Get the provider endpoints.
     *
     * @return array
     */
    public function getProviderEndpoints()
    {
        $contextLink = \Context::getContext()->link;

        return [
            'provider' => $this->paymentMethodBNPLProvider,
            'returnUrl' => $contextLink->getModuleLink(
                'globalpayments',
                'asyncPaymentMethodReturn',
                [],
                true
            ),
            'statusUrl' => $contextLink->getModuleLink(
                'globalpayments',
                'asyncPaymentMethodStatus',
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
     * States whether the payment method can be displayed at checkout.
     *
     * @return bool
     */
    public function isAvailable()
    {
        $context = \Context::getContext();
        $currency = $context->currency->iso_code;
        $methodAvailability = $this->getMethodAvailability();
        if (!isset($methodAvailability[$currency]) || !$context->cart) {
            return false;
        }

        try {
            $addressHelper = new AddressHelper();
            $billingCountryCode = $addressHelper->getBillingAddress()['countryCode'];
            if ($this->isShippingRequired()) {
                $shippingCountryCode = $addressHelper->getShippingAddress()['countryCode'];
                if (!in_array($billingCountryCode, $methodAvailability[$currency])
                    || !in_array($shippingCountryCode, $methodAvailability[$currency])
                ) {
                    return false;
                }
            } elseif (!in_array($billingCountryCode, $methodAvailability[$currency])) {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * States whether the payment method requires shipping address.
     *
     * @return bool
     */
    public function isShippingRequired()
    {
        return false;
    }

    /**
     * @{@inheritdoc}
     */
    public function validateAddress()
    {
        return true;
    }

    /**
     * {@inheritdoc}
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
