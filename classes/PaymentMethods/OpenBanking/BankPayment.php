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

namespace GlobalPayments\PaymentGatewayProvider\PaymentMethods\OpenBanking;

use GlobalPayments\Api\Entities\Enums\BankPaymentType;
use GlobalPayments\Api\Gateways\OpenBankingProvider;
use GlobalPayments\PaymentGatewayProvider\PaymentMethods\AbstractAsyncPaymentMethod;
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\AddressHelper;
use GlobalPayments\PaymentGatewayProvider\Requests\RequestArg;
use GlobalPayments\PaymentGatewayProvider\Requests\TransactionType;

if (!defined('_PS_VERSION_')) {
    exit;
}

class BankPayment extends AbstractAsyncPaymentMethod
{
    public const PAYMENT_METHOD_ID = 'globalpayments_bankpayment';
    private const TITLE = 'Bank Payment';

    protected $template = 'module:globalpayments/views/templates/front/async-payment-method/bank_payment_form.tpl';

    public $id = self::PAYMENT_METHOD_ID;

    /**
     * {@inheritdoc}
     */
    public $adminTitle = self::TITLE;

    /**
     * @var string
     */
    public $accountName;

    /**
     * @var string
     */
    public $accountNumber;

    /**
     * @var string
     */
    public $countries;

    /**
     * @var string
     */
    public $currencies;

    /**
     * @var string
     */
    public $iban;

    /**
     * @var string
     */
    public $sortCode;

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
                'globalpayments_ob_params' => [
                    'paymentMethodOptions' => $this->getFrontendPaymentMethodOptions(),
                ],
            ]
        );
    }

    /**
     * Countries this payment method is allowed for.
     *
     * @return array
     */
    public function getAvailableCountries()
    {
        return !empty($this->countries) ? explode('|', $this->countries) : [];
    }

    /**
     * Currencies this payment method is allowed for.
     *
     * @return array
     */
    public function getAvailableCurrencies()
    {
        return !empty($this->currencies) ? explode(',', $this->currencies) : [];
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultTitle()
    {
        return self::TITLE;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentActionOptions()
    {
        return [
            TransactionType::SALE => $this->translator->trans('Authorize + Capture', [], 'Modules.Globalpayments.Admin'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentActionIsDisabled()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getRedirectUrl($transaction)
    {
        return $transaction->bankPaymentResponse->redirectUrl;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function getRequestType()
    {
        return TransactionType::OB_AUTHORIZATION;
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
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentMethodFormFields()
    {
        return [
            $this->id . '_accountNumber' => [
                'title' => $this->translator->trans('Account Number', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'description' => $this->translator->trans(
                    'Account number, for bank transfers within the UK (UK to UK bank).
                    Only required if no bank details are stored on account.',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => '',
            ],
            $this->id . '_accountName' => [
                'title' => $this->translator->trans('Account Name', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'description' => $this->translator->trans(
                    'The name of the individual or business on the bank account.
                    Only required if no bank details are stored on account.',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => '',
            ],
            $this->id . '_sortCode' => [
                'title' => $this->translator->trans('Sort Code', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'description' => $this->translator->trans(
                    'Six digits which identify the bank and branch of an account.
                    Included with the Account Number for UK to UK bank transfers.
                    Only required if no bank details are stored on account.',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => '',
            ],
            $this->id . '_iban' => [
                'title' => $this->translator->trans('IBAN', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'description' => $this->translator->trans(
                    'Key field for bank transfers for Europe-to-Europe transfers.
                    Only required if no bank details are stored on account. <br/>
                    Only required for EUR transacting merchants.',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => '',
            ],
            $this->id . '_countries' => [
                'title' => $this->translator->trans('Countries', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'description' => $this->translator->trans(
                    'Allows you to input a COUNTRY or string of COUNTRIES to limit what is shown to the customer.
                    Including a country overrides your default account configuration. <br/>
                    Format: List of ISO 3166-2 (two characters) codes separated by a | <br/>
                    Example: FR|GB|IE',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => '',
            ],
            $this->id . '_currencies' => [
                'title' => $this->translator->trans('Currencies', [], 'Modules.Globalpayments.Admin'),
                'type' => 'select',
                'multiple' => true,
                'class' => 'required',
                'description' => $this->translator->trans(
                    'Note: The payment method will be displayed at checkout only for the selected currencies.',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'options' => [
                    'EUR' => 'EUR',
                    'GBP' => 'GBP',
                ],
                'default' => [
                    'EUR',
                    'GBP',
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function processPaymentBeforeGatewayRequest($request, $order)
    {
        parent::processPaymentBeforeGatewayRequest($request, $order);

        $currency = $request->getArgument(RequestArg::CURRENCY);
        $provider = OpenBankingProvider::getBankPaymentType($currency);

        $request->setArguments(
            [
                RequestArg::CONFIG_DATA => [
                    'accountName' => $this->accountName,
                    'accountNumber' => $this->accountNumber,
                    'iban' => $this->iban,
                    'sortCode' => $provider === BankPaymentType::FASTERPAYMENTS ? $this->sortCode : '',
                    'countries' => $this->getAvailableCountries(),
                ],
            ]
        );
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
        if (!in_array($currency, $this->getAvailableCurrencies())) {
            return false;
        }

        // Currency is available and no countries added in the admin panel
        if (empty($this->getAvailableCountries())) {
            return true;
        }

        try {
            $addressHelper = new AddressHelper();
            $billingCountryCode = $addressHelper->getBillingAddress()['countryCode'];
            if (!in_array($billingCountryCode, $this->getAvailableCountries())) {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * @{@inheritdoc}
     */
    public function validateAddress()
    {
        return false;
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
        if (!\Tools::getValue($this->id . '_currencies')) {
            $errors[] = $this->translator->trans(
                'Please provide at least one allowed currency.',
                [],
                'Modules.Globalpayments.Admin'
            );
        }

        $sortOrderValidation = $this->configValidation->validateSortOrder($this->id);
        if ($sortOrderValidation) {
            $errors[] = $sortOrderValidation;
        }

        return $errors;
    }

    /**
     * {@inheritdoc}
     */
    public function getAdditionalSmartyVariables($module)
    {
        return [
            'bankPaymentLogo' => $module->getPath() . 'views/img/bank-payment.png',
        ];
    }
}
