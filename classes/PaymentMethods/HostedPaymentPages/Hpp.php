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
 * @copyright Since 2025 GlobalPayments
 * @license   LICENSE
 */


namespace GlobalPayments\PaymentGatewayProvider\PaymentMethods\HostedPaymentPages;

use GlobalPayments\PaymentGatewayProvider\PaymentMethods\AbstractAsyncPaymentMethod;
use GlobalPayments\PaymentGatewayProvider\Requests\RequestArg;
use GlobalPayments\PaymentGatewayProvider\Requests\TransactionType;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Hpp extends AbstractAsyncPaymentMethod
{
    public const PAYMENT_METHOD_ID = 'globalpayments_hpp';
    private const TITLE = 'Hosted Payment Page';
     
    /**
     * @var string
     */
    public $id = self::PAYMENT_METHOD_ID;

    /**
     * @var string
     */
    public $adminTitle = self::TITLE;

    /**
     * Enable Google Pay for HPP
     *
     * @var bool
     */
    public $enableGooglePay;

    /**
     * Enable Apple Pay for HPP
     *
     * @var bool
     */
    public $enableApplePay;

    /**
     * Enable BLIK for HPP
     *
     * @var bool
     */
    public $enableBlik;

    /**
     * Enable Open Banking for HPP
     *
     * @var bool
     */
    public $enableOpenBanking;

    /**
     * Enable Payu for HPP
     *
     * @var bool
     */
    public $enablePayu;

    /**
     * Returns the title
     * 
     * @return Sting title
     */
    public function getDefaultTitle()
    {
        return self::TITLE;
    }

    /**
     * Returns the payment action, always the same with HPP
     * 
     * @return Array Containing the payment action
     */
    public function getPaymentActionOptions()
    {
        return [
            TransactionType::SALE => $this->translator->trans('Authorize + Capture', [], 'Modules.Globalpayments.Admin'),
        ];
    }

    /**
     * Taken from the Bilk async payment method
     * 
     * @return Boolean Allways true
     */
    public function getPaymentActionIsDisabled()
    {
        return true;
    }

    /**
     * Returns the Gateway URL
     * 
     * @param Transaction Class hopfully containing the External HPP URL 
     * @return String External HPP page URL
     * @throws Exception When URL is not found
     */
    public function getRedirectUrl($transaction)
    {
        // Extract HPP URL from payByLinkResponse
        if (property_exists($transaction, 'payByLinkResponse') && 
            property_exists($transaction->payByLinkResponse, 'url') &&
            !empty($transaction->payByLinkResponse->url)
            ) {
            return $transaction->payByLinkResponse->url;
        }
        
        throw new \Exception('HPP URL not found in gateway response');
    }

    /**
     * Adds HPP to privders in the window object
     * 
     * @return Array containing this ID
     */
    public function getFrontendPaymentMethodOptions()
    {
        return [
            'providers' => [
                self::PAYMENT_METHOD_ID,
            ],
        ];
    }

    /**
     * Returns the request type
     * 
     * @return TransactionType|String HPP tranaction type
     */
    public function getRequestType()
    {
        return TransactionType::HPP_TRANSACTION;
    }

    /**
     * Callbacks for HPP
     * 
     * @return Array containing callback URL's
     */
    public function getProviderEndpoints()
    {
        $contextLink = \Context::getContext()->link;

        return [
            'returnUrl' => $contextLink->getModuleLink(
                'globalpayments',
                'hppReturn',
                [],
                true
            ),
            'statusUrl' => $contextLink->getModuleLink(
                'globalpayments',
                'hppStatus',
                [],
                true
            ),
            'cancelUrl' => $contextLink->getPageLink(
                'order',
                true
            ),
        ];
    }

    /**
     * Saves the APM/Wallets settings for HPP
     * @param Request $request the prestashop HTTP request
     * @param \Order $order the prestashop order object
     * @return Void
     */
    public function processPaymentBeforeGatewayRequest($request, $order)
    {
        \Configuration::updateValue('GLOBALPAYMENTS_GPAPI_HPP_ENABLE_GPAY', $this->enableGooglePay ? 1 : 0);
        \Configuration::updateValue('GLOBALPAYMENTS_GPAPI_HPP_ENABLE_APPLEPAY', $this->enableApplePay ? 1 : 0);
        \Configuration::updateValue('GLOBALPAYMENTS_GPAPI_HPP_ENABLE_BLIK', $this->enableBlik ? 1 : 0);
        \Configuration::updateValue('GLOBALPAYMENTS_GPAPI_HPP_ENABLE_OPEN_BANKING', $this->enableOpenBanking ? 1 : 0);
        \Configuration::updateValue('GLOBALPAYMENTS_GPAPI_HPP_ENABLE_PAYU', $this->enablePayu ? 1 : 0);


        $request->setArguments(
            [
                RequestArg::ASYNC_PAYMENT_DATA => $this->getProviderEndpoints(),
                RequestArg::DYNAMIC_DESCRIPTOR => $this->gateway->txnDescriptor,
                RequestArg::PAYMENT_ACTION => $this->paymentAction,
            ]
        );
    }

    /**
     * APM/Wallets admin options specificly for HPP
     * 
     * @return Array Containing the HPP APM/Wallets Admin settings 
     */
    public function getPaymentMethodFormFields()
    {
        return [
            $this->id . '_enableGooglePay' => [
                'title' => $this->translator->trans('Enable Google Pay', [], 'Modules.Globalpayments.Admin'),
                'type' => 'switch',
                'description' => $this->translator->trans(
                    'Enable Google Pay as a payment option in the Hosted Payment Page',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => 0,
            ],
            $this->id . '_enableApplePay' => [
                'title' => $this->translator->trans('Enable Apple Pay', [], 'Modules.Globalpayments.Admin'),
                'type' => 'switch',
                'description' => $this->translator->trans(
                    'Enable Apple Pay as a payment option in the Hosted Payment Page',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => 0,
            ],
            $this->id . '_enableBlik' => [
                'title' => $this->translator->trans('Enable BLIK', [], 'Modules.Globalpayments.Admin'),
                'type' => 'switch',
                'description' => $this->translator->trans(
                    'Enable BLIK as a payment option in the Hosted Payment Page',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => 0,
            ],
            $this->id . '_enableOpenBanking' => [
                'title' => $this->translator->trans('Enable Open Banking', [], 'Modules.Globalpayments.Admin'),
                'type' => 'switch',
                'description' => $this->translator->trans(
                    'Enable Open Banking as a payment option in the Hosted Payment Page',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => 0,
            ],
            $this->id . '_enablePayu' => [
                'title' => $this->translator->trans('Enable Payu', [], 'Modules.Globalpayments.Admin'),
                'type' => 'switch',
                'description' => $this->translator->trans(
                    'Enable Payu as a payment option in the Hosted Payment Page',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => 0,
            ],
        ];
    }

    /**
     * This payment method does not need to validate the address
     * 
     * @return Boolean Allways false
     */
    public function validateAddress()
    {
        return false;
    }

    /**
     * Is HPP intergartion option available
     * 
     * @return Boolean Allways true
     */
    public function isAvailable()
    {
        return true;
    }

    /**
     * Validates the Admin settings
     * 
     * @return Array containing validation errors, if any
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
