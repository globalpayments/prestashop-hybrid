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

namespace GlobalPayments\PaymentGatewayProvider\Gateways;

use GlobalPayments\Api\Entities\Enums\Channel;
use GlobalPayments\Api\Entities\Enums\Environment;
use GlobalPayments\Api\Entities\Enums\GatewayProvider;
use GlobalPayments\Api\Entities\Enums\InstallmentsFundingMode;
use GlobalPayments\Api\Entities\Enums\TransactionStatus;
use GlobalPayments\Api\Entities\Reporting\TransactionSummary;
use GlobalPayments\Api\Gateways\GpApiConnector;
use GlobalPayments\PaymentGatewayProvider\Data\Order;
use GlobalPayments\PaymentGatewayProvider\PaymentMethods\Apm\PayPal;
use GlobalPayments\PaymentGatewayProvider\PaymentMethods\BuyNowPayLater\Affirm;
use GlobalPayments\PaymentGatewayProvider\PaymentMethods\BuyNowPayLater\Clearpay;
use GlobalPayments\PaymentGatewayProvider\PaymentMethods\BuyNowPayLater\Klarna;
use GlobalPayments\PaymentGatewayProvider\PaymentMethods\DigitalWallets\ApplePay;
use GlobalPayments\PaymentGatewayProvider\PaymentMethods\DigitalWallets\ClickToPay;
use GlobalPayments\PaymentGatewayProvider\PaymentMethods\DigitalWallets\GooglePay;
use GlobalPayments\PaymentGatewayProvider\PaymentMethods\OpenBanking\BankPayment;
use GlobalPayments\PaymentGatewayProvider\Platform\Utils;
use GlobalPayments\PaymentGatewayProvider\Requests;
use GlobalPayments\PaymentGatewayProvider\Gateways\DiUiApms\{BankSelect, Blik};
use GlobalPayments\PaymentGatewayProvider\Requests\IntegrationType;
use GlobalPayments\PaymentGatewayProvider\Requests\TransactionType;
use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\PaymentGatewayProvider\Requests\RequestArg;
use GlobalPayments\PaymentGatewayProvider\Security\ThreeDSecureSecurity;


if (!defined('_PS_VERSION_')) {
    exit;
}

class GpApiGateway extends AbstractGateway
{
    /**
     * Gateway ID
     *
     * @var string
     */
    public $id = GatewayId::GP_UCP;

    /**
     * SDK gateway provider
     *
     * @var string
     */
    public $gatewayProvider = GatewayProvider::GP_API;

    /**
     * Sandbox App ID
     *
     * @var string
     */
    public $sandboxAppId;

    /**
     * Sandbox App Key
     *
     * @var string
     */
    public $sandboxAppKey;

    /**
     * Sandbox Account Name
     *
     * @var string
     */
    public $sandboxAccountName;

    /**
     * Live App ID
     *
     * @var string
     */
    public $appId;

    /**
     * Live App Key
     *
     * @var string
     */
    public $appKey;

    /**
     * Account Name
     *
     * @var string
     */
    public $accountName;

    /**
     * {@inheritdoc}
     */
    public $adminTitle;

    /**
     * Should live payments be accepted
     *
     * @var bool
     */
    public $isProduction;

    /**
     * Merchant Contact URL
     *
     * @var string
     */
    public $merchantContactUrl;

    /**
     * Integration's Developer ID
     *
     * @var string
     */
    public $developerId = '';

    /**
     * Should debug
     *
     * @var bool
     */
    public $debug;

    /**
     * States whether the 3D Secure authentication protocol should be processed
     *
     * @var bool
     */
    public $enableThreeDSecure;

    /**
     * Enable Google Pay for HPP
     *
     * @var bool
     */
    public $hppEnableGooglePay;

    /**
     * Enable Apple Pay for HPP
     *
     * @var bool
     */
    public $hppEnableApplePay;

    /**
     * Enable BLIK for HPP
     *
     * @var bool
     */
    public $hppEnableBlik;

    /**
     * Enable Open Banking for HPP
     *
     * @var bool
     */
    public $hppEnableOpenBanking;

    /**
     * Enable Payu for HPP
     *
     * @var bool
     */
    public $hppEnablePayu;

    /**
     * Enable Installment Plans
     *
     * @var bool
     */
    public $enableInstallments;

    /**
     * Enable Visa Installment Plans
     *
     * @var bool
     */
    public $enableVisaInstallments;

    /**
     * GpApiGateway constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->adminTitle = $this->translator->trans('Unified Payments', [], 'Modules.Globalpayments.Admin');
    }

    public function getFirstLineSupportEmail()
    {
        return 'api.integrations@globalpay.com';
    }

    public function getFrontendGatewayOptions()
    {
        $country = new \Country((int) \Configuration::get('PS_COUNTRY_DEFAULT'));
        $currency = new \Currency((int) \Configuration::get('PS_CURRENCY_DEFAULT'));
        
         return [
            'apiVersion' => GpApiConnector::GP_API_VERSION,
            'accessToken' => $this->getAccessToken(),
            'enableThreeDSecure' => $this->enableThreeDSecure,
            'enableBlikPayment' => $this->enableBlikPayment,
            'enableOpenBanking' => $this->enableOpenBanking,
            'dataResidency' => (\Configuration::get($this->id . '_transactionRegion') == 'europe') ? 'EU' : 'NONE',
            'enableInstallments' => $this->enableInstallments,
            'enableVisaInstallments' => $this->enableVisaInstallments,
            'visaInstallmentsFundingMode' => \Configuration::get($this->id . '_visaInstallmentsFundingMode') ?: 'ANY',
            'visaInstallmentsMaxTimeUnitNumber' => \Configuration::get($this->id . '_visaInstallmentsMaxTimeUnitNumber') ?: '',
            'visaInstallmentsMaxAmount' => \Configuration::get($this->id . '_visaInstallmentsMaxAmount') ?: '',
            'installmentsAccountName' => $this->getCredentialSetting('accountName'),
            'env' => $this->isProduction ? parent::ENVIRONMENT_PRODUCTION : parent::ENVIRONMENT_SANDBOX,
            'fieldValidation' => [
                'enabled' => true,
            ],
            'language' => \Context::getContext()->language->iso_code,
            'messages' => [
                'noLiabilityShift' => $this->translator->trans(
                    'Please try again with another card.',
                    [],
                    'Modules.Globalpayments.Shop'
                ),
                'notAuthenticated' => $this->translator->trans(
                    'We\'re not able to process this payment. Please refresh the page and try again.',
                    [],
                    'Modules.Globalpayments.Shop'
                ),
                'threeDSFail' => $this->translator->trans(
                    'Something went wrong while doing 3DS processing.',
                    [],
                    'Modules.Globalpayments.Shop'
                ),
            ],
            'requireCardHolderName' => true,
            'integrationMethod' => $this->integrationType,
            'hppInitiatePaymentUrl' => $this->getValidNotificationUrl('initiateHppPayment'),
            'country' => $country->iso_code,
            'currency' => $currency->iso_code,
        ];
    }

    public function getBackendGatewayOptions()
    {
        $country = new \Country((int) \Configuration::get('PS_COUNTRY_DEFAULT'));
        $currency = new \Currency((int) \Configuration::get('PS_CURRENCY_DEFAULT'));

        return [
            'appId' => $this->getCredentialSetting('appId'),
            'appKey' => $this->getCredentialSetting('appKey'),
            'dataResidency' => (\Configuration::get($this->id . '_transactionRegion') == 'europe') ? 'EU' : 'NONE',
            'integrationMethod' => $this->integrationType,
            'dcc' => (int) \Configuration::get($this->id . '_dcc'),
            'accountName' => $this->getCredentialSetting('accountName'),
            'channel' => Channel::CardNotPresent,
            'developerId' => $this->developerId,
            'country' => $country->iso_code,
            'currency' => $currency->iso_code,
            'environment' => $this->isProduction ? Environment::PRODUCTION : Environment::TEST,
            'methodNotificationUrl' => $this->getValidNotificationUrl('methodNotification'),
            'challengeNotificationUrl' => $this->getValidNotificationUrl('challengeNotification'),
            'merchantContactUrl' => $this->merchantContactUrl,
            'enableInstallments' => $this->enableInstallments,
            'enableVisaInstallments' => $this->enableVisaInstallments,
            'dynamicHeaders' => [
                'x-gp-platform' => 'prestashop;version=' . _PS_VERSION_,
                'x-gp-extension' => 'globalpayments-prestashop;version='
                    . \Module::getInstanceByName('globalpayments')->version,
            ],
            'debug' => $this->debug,
        ];
    }

    public function getGatewayFormFields()
    {
        $defaultCountryId = (int) \Configuration::get('PS_COUNTRY_DEFAULT');
        $defaultCurrencyId = (int) \Configuration::get('PS_CURRENCY_DEFAULT');
        $defaultCountry = new \Country($defaultCountryId);
        $defaultCurrency = new \Currency($defaultCurrencyId);
        $isMxInstallmentsEligible = (
            strtoupper((string) $defaultCountry->iso_code) === 'MX'
            && strtoupper((string) $defaultCurrency->iso_code) === 'MXN'
        );
        $isVisaInstallmentsEligible = (
            (strtoupper((string) $defaultCountry->iso_code) === 'GB'
                && strtoupper((string) $defaultCurrency->iso_code) === 'GBP')
            || (strtoupper((string) $defaultCountry->iso_code) === 'CA'
                && strtoupper((string) $defaultCurrency->iso_code) === 'CAD')
        );

        $integrationType = \Configuration::get($this->id . '_integrationType');
        $isDropInUI = ($integrationType === IntegrationType::DROP_IN_UI);
        $visaInstallmentsValue = \Configuration::get($this->id . '_enableVisaInstallments');
        $isVisaInstallmentsEnabled = ($visaInstallmentsValue === '1' || $visaInstallmentsValue === 1 || $visaInstallmentsValue === true);

        $fields = [
            $this->id . '_isProduction' => [
                'title' => $this->translator->trans('Live Mode', [], 'Modules.Globalpayments.Admin'),
                'type' => 'switch',
                'description' => $this->translator->trans(
                    'Get your App Id and App Key from your <a href="https://developer.globalpay.com/user/register"
                    target="_blank">Global Payments Developer Account</a>.
                    Please follow the instructions provided in the readme.txt file.
                    When you are ready for Live, please contact
                    <a href="mailto:%s%?Subject=PrestaShop%%20Live%%20Credentials">support</a>
                    to get your live credentials.',
                    ['%s%' => $this->getFirstLineSupportEmail()],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => 0,
            ],
            $this->id . '_transactionRegion' => [
                'title' => $this->translator->trans('Transaction Region', [], 'Modules.Globalpayments.Admin'),
                'type' => 'select',
                'description' => $this->translator->trans(
                    'Select where transactions are processed. This controls the GP API host for sandbox and live transactions.',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => 'global',
                'options' => [
                    'global' => $this->translator->trans('Global (default)', [], 'Modules.Globalpayments.Admin'),
                    'europe' => $this->translator->trans('Europe', [], 'Modules.Globalpayments.Admin'),
                ],
            ],
            $this->id . '_sandboxAppId' => [
                'title' => $this->translator->trans('Sandbox App Id', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'class' => 'required sandbox-toggle',
                'default' => '',
            ],
            $this->id . '_sandboxAppKey' => [
                'title' => $this->translator->trans('Sandbox App Key', [], 'Modules.Globalpayments.Admin'),
                'type' => 'password',
                'class' => 'required sandbox-toggle',
                'default' => '',
            ],
            $this->id . '_sandboxAccountName' => [
                'type' => 'hidden',
                'default' => '',
            ],
            $this->id . '_sandboxAccountNameDropdown' => [
                'title' => $this->translator->trans('Sandbox Account Name', [], 'Modules.Globalpayments.Admin'),
                'type' => 'select',
                'class' => 'required sandbox-toggle',
                'default' => 'Select Account',
                'description' => $this->translator->trans(
                    'Select which account to use when processing a transaction. Default account will be used if this is not specified.',
                    [],
                    'Modules.Globalpayments.Admin'
                ) . '<br>' . $this->translator->trans(
                    'For assistance locating your account name, please contact our',
                    [],
                    'Modules.Globalpayments.Admin'
                ) . ' <a href="https://developer.globalpay.com/support/integration-support" target="_blank">' .
                $this->translator->trans('Integration Support', [], 'Modules.Globalpayments.Admin') .
                '</a> ' . $this->translator->trans('Team based on location.', [], 'Modules.Globalpayments.Admin'),
                'skipConfigSave' => true,
                'options' => [
                    'select_account' => 'Select Account',
                ],
            ],
            $this->id . '_appId' => [
                'title' => $this->translator->trans('Live App Id', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'class' => 'required live-toggle',
                'description' => '',
                'default' => '',
            ],
            $this->id . '_appKey' => [
                'title' => $this->translator->trans('Live App Key', [], 'Modules.Globalpayments.Admin'),
                'type' => 'password',
                'class' => 'required live-toggle',
                'description' => '',
                'default' => '',
            ],
            $this->id . '_accountName' => [
                'type' => 'hidden',
                'default' => '',
            ],
            $this->id . '_accountNameDropdown' => [
                'title' => $this->translator->trans('Account Name', [], 'Modules.Globalpayments.Admin'),
                'type' => 'select',
                'class' => 'required live-toggle',
                'default' => 'Select Account',
                'description' => $this->translator->trans(
                    'Select which account to use when processing a transaction. Default account will be used if this is not specified.',
                    [],
                    'Modules.Globalpayments.Admin'
                ) . '<br>' . $this->translator->trans(
                    'For assistance locating your account name, please contact our',
                    [],
                    'Modules.Globalpayments.Admin'
                ) . ' <a href="https://developer.globalpay.com/support/integration-support" target="_blank">' .
                $this->translator->trans('Integration Support', [], 'Modules.Globalpayments.Admin') .
                '</a> ' . $this->translator->trans('Team based on location.', [], 'Modules.Globalpayments.Admin'),
                'skipConfigSave' => true,
                'options' => [
                    'select_account' => 'Select Account',
                ],
            ],
            $this->id . '_credentialsCheck' => [
                'title' => $this->translator->trans('Credentials Check', [], 'Modules.Globalpayments.Admin'),
                'type' => 'button',
                'description' => $this->translator->trans(
                    'Note: the Payment Methods will not be displayed at checkout if the credentials are not correct.',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'skipConfigSave' => true,
            ],
            $this->id . '_debug' => [
                'title' => $this->translator->trans('Enable Logging', [], 'Modules.Globalpayments.Admin'),
                'type' => 'switch',
                'description' => $this->translator->trans(
                    'Log all request to and from gateway. This can also log private data and should only be enabled
                     in a development or stage environment.',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => 0,
            ],
            $this->id . '_merchantContactUrl' => [
                'title' => $this->translator->trans('Contact Url', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'maxLength' => '256',
                'description' => $this->translator->trans(
                    'A link to an About or Contact page on your website with customer care information (maxLength: 256).',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => '',
            ],
            $this->id . '_enableThreeDSecure' => [
                'title' => $this->translator->trans('Enable 3D Secure', [], 'Modules.Globalpayments.Admin'),
                'type' => 'switch',
                'default' => 1,
            ],
            $this->id . '_integrationType' => [
                'title' => $this->translator->trans('Integration Type', [], 'Modules.Globalpayments.Admin'),
                'type' => 'select',
                'description' => $this->translator->trans(
                    'Choose whether payment form is displayed on the checkout (drop in UI), or on a hosted payment page (HPP).',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => IntegrationType::DROP_IN_UI,
                'options' => [
                    IntegrationType::DROP_IN_UI => $this->translator->trans(
                        'Drop-in UI',
                        [],
                        'Modules.Globalpayments.Admin'
                    ),
                    IntegrationType::HOSTED_PAYMENT_PAGE => $this->translator->trans(
                        'Hosted Payment Page',
                        [],
                        'Modules.Globalpayments.Admin'
                    ),
                ],
            ],
            $this->id . '_dcc' => [
                'title' => $this->translator->trans('DCC', [], 'Modules.Globalpayments.Admin'),
                'type' => 'switch',
                'description' => $this->translator->trans(
                    'Enable or disable Dynamic Currency Conversion (DCC) for transactions processed through Global Payments.',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => 0,
            ],
            $this->id . '_hppEnableGooglePay' => [
                'title' => $this->translator->trans('HPP: Enable Google Pay', [], 'Modules.Globalpayments.Admin'),
                'type' => 'switch',
                'description' => $this->translator->trans(
                    'Enable Google Pay as a payment option in the Hosted Payment Page',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => 0,
            ],
            $this->id . '_hppEnableApplePay' => [
                'title' => $this->translator->trans('HPP: Enable Apple Pay', [], 'Modules.Globalpayments.Admin'),
                'type' => 'switch',
                'description' => $this->translator->trans(
                    'Enable Apple Pay as a payment option in the Hosted Payment Page',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => 0,
            ],
            $this->id . '_hppEnableBlik' => [
                'title' => $this->translator->trans('HPP: Enable BLIK', [], 'Modules.Globalpayments.Admin'),
                'type' => 'switch',
                'description' => $this->translator->trans(
                    'Enable BLIK as a payment option in the Hosted Payment Page',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => 0,
            ],
            $this->id . '_hppEnableOpenBanking' => [
                'title' => $this->translator->trans('HPP: Enable Open Banking', [], 'Modules.Globalpayments.Admin'),
                'type' => 'switch',
                'description' => $this->translator->trans(
                    'Enable Open Banking as a payment option in the Hosted Payment Page',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => 0,
            ],
            $this->id . '_hppEnablePayu' => [
                'title' => $this->translator->trans('HPP: Enable Payu', [], 'Modules.Globalpayments.Admin'),
                'type' => 'switch',
                'description' => $this->translator->trans(
                    'Enable Payu as a payment option in the Hosted Payment Page',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => 0,
            ],
            $this->id . '_hppVisaInstallments' => [
                'title' => $this->translator->trans('HPP: Visa Installments', [], 'Modules.Globalpayments.Admin'),
                'type' => 'hidden',
                'description' => $this->translator->trans(
                    'Note: Visa Installments is an account‑level feature. This payment option will only display during checkout if it has been enabled on your Global Payments account.',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => '',
            ],
                        $this->id . '_hppInstallmentsMaxMonths' => [
                'title' => $this->translator->trans('HPP: Max number of months for VISA installments', [], 'Modules.Globalpayments.Admin'),
                'type' => 'select',
                'description' => $this->translator->trans(
                    'Set the maximum number of months for VISA installment payments (for use in HPP mode only)',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => 0,
                'options' => [
                    0 => $this->translator->trans('Please Select', [], 'Modules.Globalpayments.Admin'),
                    3 => '3',
                    6 => '6',
                    9 => '9',
                    12 => '12',
                    18 => '18',
                    24 => '24',
                ],
            ],
            $this->id . '_hppInstallmentsFundingMode' => [
                'title' => $this->translator->trans('HPP: VISA Installments Funding Mode', [], 'Modules.Globalpayments.Admin'),
                'type' => 'select',
                'description' => $this->translator->trans(
                    'Set the funding mode for VISA installment payments (for use in HPP mode only, default is "Any")',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => 'select',
                'options' => $this->fetchInstallmentsFundingModes(),
            ],
            $this->id . '_hppInstallmentsMaxValue' => [
                'title' => $this->translator->trans('HPP: VISA Installments Max Value', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'description' => $this->translator->trans(
                    'Set the maximum value for VISA installment payments (for use in HPP mode only, default is "1000")',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => '1000',
            ],
        ];

        // Conditionally add MX Installments field (only for Mexico with MXN currency)
        if ($isMxInstallmentsEligible) {
            $fields[$this->id . '_enableInstallments'] = [
                'title' => $this->translator->trans('Enable Installments', [], 'Modules.Globalpayments.Admin'),
                'type' => 'switch',
                'description' => $this->translator->trans(
                    'Enable installment payment option for customers',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => 0,
            ];
        }

        // Conditionally add Visa Installments fields (only for UK/GBP or Canada/CAD)
        if ($isVisaInstallmentsEligible) {
            $fields[$this->id . '_enableVisaInstallments'] = [
                'title' => $this->translator->trans('Enable Visa Installments', [], 'Modules.Globalpayments.Admin'),
                'type' => 'switch',
                'description' => $this->translator->trans(
                    'Enable Visa installment payment option for customers',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => 0,
            ];
            $fields[$this->id . '_visaInstallmentsFundingMode'] = [
                'title' => $this->translator->trans('Visa Installments Funding Mode', [], 'Modules.Globalpayments.Admin'),
                'type' => 'select',
                'description' => $this->translator->trans(
                    'Set the funding mode for Visa installment payments (for use in Drop-in UI mode only, default is "Any")',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => 'select',
                'options' => $this->fetchInstallmentsFundingModes(),
            ];
            $fields[$this->id . '_visaInstallmentsMaxTimeUnitNumber'] = [
                'title' => $this->translator->trans('Visa Installments Max Time Unit Number', [], 'Modules.Globalpayments.Admin'),
                'type' => 'number',
                'inputType' => 'number',
                'description' => $this->translator->trans(
                    'Set the maximum time unit number for Visa installment payments',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => '',
            ];
            $fields[$this->id . '_visaInstallmentsMaxAmount'] = [
                'title' => $this->translator->trans('Visa Installments Max Amount', [], 'Modules.Globalpayments.Admin'),
                'type' => 'number',
                'inputType' => 'number',
                'description' => $this->translator->trans(
                    'Set the maximum amount for Visa installment payments',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => '',
            ];
        }

        return $fields;
    }

    public function securePaymentFieldsConfiguration()
    {
        // For HPP mode, no card fields are needed - payment happens on hosted page
        if ($this->integrationType === IntegrationType::HOSTED_PAYMENT_PAGE) {
            return [];
        }

        $fields = [
            'payment-form' => [
                'class'       => 'payment-form',
                'label'       => '',
                'messages'    => [
                    'validation' => ''
                ]
            ]
        ];

        return $fields;
    }

    protected function getAccessToken()
    {
        $request = $this->prepareRequest(Requests\TransactionType::GET_ACCESS_TOKEN, new Order());
        $response = $this->submitRequest($request);

        return $response->token;
    }

    protected function isTransactionActive(TransactionSummary $details)
    {
        return $details->transactionStatus === TransactionStatus::PREAUTHORIZED;
    }

    public function processThreeDSecureCheckEnrollment(Order $order)
    {
        $request = $this->prepareRequest(Requests\TransactionType::CHECK_ENROLLMENT, $order);

        return $this->client->submitRequest($request);
    }

    public function processThreeDSecureInitiateAuthentication(Order $order)
    {
        $request = $this->prepareRequest(Requests\TransactionType::INITIATE_AUTHENTICATION, $order);

        return $this->client->submitRequest($request);
    }

    /**
     * Verify 3DS request security to prevent carding attacks.
     *
     * Security measures:
     * 1. Validates cryptographically signed token (can't be forged)
     * 2. Token is bound to the IP that generated it
     * 3. Token expires after 5 minutes
     * 4. Each token can only be used 2 times
     * 5. Rate limiting: 2 requests per minute per IP
     * 6. Hourly limit: 10 requests per hour per IP
     *
     * @return array|true True if valid, array with error details if not.
     */
    public function verifyThreeDSecureRequestSecurity()
    {
        $security = new ThreeDSecureSecurity($this->translator);
        $result = $security->validateSecurityToken();

        // Cleanup expired cache files occasionally
        $security->cleanupExpiredCache();

        if (!$result['valid']) {
            return [
                'error' => true,
                'code' => 'security_failed',
                'message' => $result['error'],
            ];
        }

        return true;
    }

    public function validateAdminSettings()
    {
        $errors = [];
        if (!\Tools::getValue($this->id . '_enabled')) {
            return $errors;
        }
        if (\Tools::getValue($this->id . '_isProduction')) {
            if (empty(\Tools::getValue($this->id . '_appId')) || empty(\Tools::getValue($this->id . '_appKey'))) {
                $errors[] = $this->translator->trans('Please provide Live Credentials.', [], 'Modules.Globalpayments.Admin');
            }
        } else {
            if (empty(\Tools::getValue($this->id . '_sandboxAppId')) || empty(\Tools::getValue($this->id . '_sandboxAppKey'))) {
                $errors[] = $this->translator->trans('Please provide Sandbox Credentials.', [], 'Modules.Globalpayments.Admin');
            }
        }

        $merchantUrl = \Tools::getValue($this->id . '_merchantContactUrl');
        if (!$merchantUrl || \Tools::strlen($merchantUrl) > 256) {
            $errors[] = $this->translator->trans(
                'Please provide a Contact Url (maxLength: 256).',
                [],
                'Modules.Globalpayments.Admin'
            );
        }
        if (\Tools::strlen(\Tools::getValue($this->id . '_txnDescriptor')) > 25) {
            $errors[] = $this->translator->trans(
                'Please provide Order Transaction Descriptor (maxLength: 25).',
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
    public function getPaymentOptions($module, $params, $isCheckout)
    {
        return parent::getPaymentOptions($module, $params, $isCheckout);
    }

    /**
     * {@inheritdoc}
     */
    public function enqueuePaymentScripts($module)
    {
        if (!$this->enabled) {
            return;
        }

        parent::enqueuePaymentScripts($module);

        $context = $module->getContext();
        $path = $module->getFrontendScriptsPath();

        $context->controller->registerStylesheet(
            'globalpayments-secure-payment-fields',
            $path . '/views/css/globalpayments-secure-payment-fields.css'
        );

        // added this 28th March
        $context->controller->registerJavascript(
            'globalpayments',
            'https://js.globalpay.com/' . Utils::getJsLibVersion() . '/globalpayments'
                . (defined('_PS_MODE_DEV_') && _PS_MODE_DEV_ ? '' : '.min') . '.js',
            $path . '/views/js/globalpayments-secure-payment-fields.js',
            ['position' => 'bottom', 'priority' => 100]
        );

        $context->controller->registerJavascript(
            'globalpayments-secure-payment-fields',
            $path . '/views/js/globalpayments-secure-payment-fields.js',
            ['position' => 'bottom', 'priority' => 100]
        );

        $context->controller->registerJavascript(
            'globalpayments-threedsecure-lib',
            $path . '/views/js/globalpayments-3ds'
            . (defined('_PS_MODE_DEV_') && _PS_MODE_DEV_ ? '' : '.min') . '.js',
            ['position' => 'bottom', 'priority' => 200]
        );

        // Generate a cryptographically signed token bound to this IP for 3DS security
        $securityToken = $this->generate3dsSecurityToken();

        \Media::addJsDef(
            [
                'globalpayments_secure_payment_fields_params' => $this->getPaymentFieldsParams(),
                'globalpayments_secure_payment_threedsecure_params' => [
                    'threedsecure' => [
                        'methodNotificationUrl' => $context->link->getModuleLink(
                            $module->name,
                            'methodNotification',
                            [],
                            true
                        ),
                        'challengeNotificationUrl' => $context->link->getModuleLink(
                            $module->name,
                            'challengeNotification',
                            [],
                            true
                        ),
                        'checkEnrollmentUrl' => $this->getSecured3dsUrl(
                            $context->link->getModuleLink(
                                $module->name,
                                'checkEnrollment',
                                [],
                                true
                            ),
                            $securityToken
                        ),
                        'initiateAuthenticationUrl' => $this->getSecured3dsUrl(
                            $context->link->getModuleLink(
                                $module->name,
                                'initiateAuthentication',
                                [],
                                true
                            ),
                            $securityToken
                        ),
                    ],
                ],
            ]
        );
    }

    public static function getPaymentMethods()
    {
        return [
            new GooglePay(),
            new ApplePay(),
            new ClickToPay(),
            new PayPal(),
            new Affirm(),
            new Clearpay(),
            new Klarna(),
            new BankPayment(),
        ];
    }

    /**
     * Use DiUi handler or abstract method
     *
     * @param int $order_id
     *
     * @return array
     * @throws ApiException
     */
    public function processPayment( $order_id ) {
        if (!empty($_POST["blik-payment"]) && $_POST["blik-payment"] === "1")
            return Blik::processBlikSale( $this, $order_id );
        if (!empty($_POST["open_banking"]))
            return BankSelect::processOpenBankingSale( $this, $order_id, $_POST["open_banking"] );

        return parent::processPayment( $order_id );
    }

    /**
     * Process HPP (Hosted Payment Page) payment
     *
     * Initiates a payment on the GP-API hosted payment page and returns
     * a transaction object containing the redirect URL.
     *
     * Follows the same pattern as Magento HPP implementation:
     * - Uses InitiatePaymentRequest with ASYNC_PAYMENT_DATA
     * - Returns Transaction with payByLinkResponse->url
     * - Order completion happens in hppReturn controller after redirect
     *
     * @param Order $order Order model with payment details
     * @param int $orderId PrestaShop order ID
     * @return \GlobalPayments\Api\Entities\Transaction Transaction with redirect URL
     * @throws \GlobalPayments\Api\Entities\Exceptions\ApiException If API call fails
     */
    public function processHppPayment(Order $order, int $orderId): Transaction
    {
        // Prepare HPP endpoints for callback
        $context = \Context::getContext();
        $hppEndpoints = [
            'returnUrl' => $context->link->getModuleLink('globalpayments', 'hppReturn', [], true),
            'statusUrl' => $context->link->getModuleLink('globalpayments', 'hppStatus', [], true),
            'cancelUrl' => $context->link->getPageLink('order', true), // Return to checkout on cancel
        ];

        $gatewayOptions = $this->getBackendGatewayOptions();

        // Create HPP initiate payment request
        $request = $this->prepareRequest(Requests\TransactionType::HPP_TRANSACTION, $order);

        // Set additional arguments after request creation
        $requestArguments = [
            RequestArg::ORDER_ID => $orderId,
            RequestArg::CART_ID => $order->cartId,
            RequestArg::AMOUNT => $order->amount,
            RequestArg::CURRENCY => $order->currency,
            RequestArg::BILLING_ADDRESS => $order->billingAddress,
            RequestArg::SHIPPING_ADDRESS => $order->shippingAddress,
            RequestArg::ASYNC_PAYMENT_DATA => $hppEndpoints,
        ];

        if (
            $this->integrationType === IntegrationType::HOSTED_PAYMENT_PAGE
            && $gatewayOptions['dcc'] === 1
        ) {
            $requestArguments[RequestArg::DCC] = 1;
        } else {
            $requestArguments[RequestArg::DCC] = 0;
        }

        $request->setArguments($requestArguments);

        // Use client's submitRequest method which calls doRequest() directly
        // This bypasses the normal SdkClient transaction builder flow
        $transaction = $this->client->submitRequest($request);

        return $transaction;
    }

    /**
     * Get valid notification URL for development/production
     *
     * @param string $endpoint
     * @return string
     */
    private function getValidNotificationUrl($endpoint)
    {
        $context = \Context::getContext();
        $url = $context->link->getModuleLink('globalpayments', $endpoint, [], true);

        // For sandbox/test environment, we need to provide a valid notification URL
        if (!$this->isProduction) {
            // For local development with XAMPP, ensure proper URL format
            if (strpos($url, 'localhost') !== false || strpos($url, '127.0.0.1') !== false) {
                // For GP-API sandbox, we can use a placeholder URL that indicates it's for testing
                // The actual notification won't work in localhost, but the format will be valid
                $url = str_replace(['localhost', '127.0.0.1'], 'sandbox-webhook.example.com', $url);
                $url = str_replace('http://', 'https://', $url);
            }
        }

        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            // Fallback to a basic valid URL format using dynamic site information
            $shopDomain = $context->shop->domain ?? 'sandbox-webhook.example.com';
            $shopUri = $context->shop->getBaseURI() ?? '/';
            // Build dynamic fallback URL
            $url = "https://{$shopDomain}{$shopUri}modules/globalpayments/controllers/front/{$endpoint}.php";
        }

        return $url;
    }

    /**
     * Fetch installments funding modes and manipulate them for display
     *
     * @return string[]
     */
    private function fetchInstallmentsFundingModes(): array {
        $refl = new \ReflectionClass('GlobalPayments\Api\Entities\Enums\InstallmentsFundingMode');

        $options = ['select' => 'Please Select'];

        foreach ($refl->getConstants() as $mode) {
            $options[$mode] = ucfirst(strtolower(str_replace('_', ' ', $mode)));
        }

        return $options;
    }
}
