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

use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\Entities\Reporting\TransactionSummary;
use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\PaymentGatewayProvider\Clients\ClientInterface;
use GlobalPayments\PaymentGatewayProvider\Clients\SdkClient;
use GlobalPayments\PaymentGatewayProvider\Data\Order;
use GlobalPayments\PaymentGatewayProvider\Handlers\HandlerInterface;
use GlobalPayments\PaymentGatewayProvider\Handlers\InvalidCardHandler;
use GlobalPayments\PaymentGatewayProvider\Handlers\PaymentTokenHandler;
use GlobalPayments\PaymentGatewayProvider\Platform\Token;
use GlobalPayments\PaymentGatewayProvider\Platform\Utils;
use GlobalPayments\PaymentGatewayProvider\Platform\Validator\Admin\Config\Validation as ConfigValidation;
use GlobalPayments\PaymentGatewayProvider\Requests;
use GlobalPayments\PaymentGatewayProvider\Requests\TransactionType;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use PrestaShopBundle\Translation\TranslatorComponent as Translator;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Shared gateway method implementations
 */
abstract class AbstractGateway implements GatewayInterface
{
    /**
     * Defines production environment
     */
    public const ENVIRONMENT_PRODUCTION = 'production';

    /**
     * Defines sandbox environment
     */
    public const ENVIRONMENT_SANDBOX = 'sandbox';

    /**
     * Gateway ID. Should be overridden by individual gateway implementations
     *
     * @var string
     */
    public $id;

    /**
     * Gateway provider. Should be overridden by individual gateway implementations
     *
     * @var string
     */
    public $gatewayProvider;

    /**
     * Payment method enabled status
     *
     * @var string
     */
    public $enabled;

    /**
     * Payment method title shown to consumer
     *
     * @var string
     */
    public $title;

    /**
     * The tab title shown in the admin config page
     *
     * @var string
     */
    public $adminTitle;

    /**
     * Action to perform on checkout
     *
     * Possible actions:
     *
     * - `authorize` - authorize the card without auto capturing
     * - `sale` - authorize the card with auto capturing
     * - `verify` - verify the card without authorizing
     *
     * @var string
     */
    public $paymentAction;

    /**
     * Transaction descriptor to list on consumer's bank account statement
     *
     * @var string
     */
    public $txnDescriptor;

    /**
     * Control of PrestaShop's card storage (tokenization) support
     *
     * @var bool
     */
    public $allowCardSaving;

    /**
     * Sort order for the payment at checkout.
     *
     * @var float|int
     */
    public $sortOrder;

    /**
     * Error response handlers
     *
     * @var HandlerInterface[]
     */
    public $errorHandlers = [
        InvalidCardHandler::class,
    ];

    /**
     * Success response handlers
     *
     * @var HandlerInterface[]
     */
    public $successHandlers = [
        PaymentTokenHandler::class,
    ];

    /**
     * The form fields for the back-end interface
     *
     * @var array
     */
    public $formFields;

    /**
     * Gateway HTTP client
     *
     * @var ClientInterface
     */
    public $client;

    /**
     * @var ConfigValidation
     */
    public $configValidation;

    /**
     * @var string
     */
    protected $template = 'module:globalpayments/views/templates/front/payment_form.tpl';

    /**
     * @var Translator
     */
    protected $translator;

    /**
     * States whether the Blik payment method should be enabled
     *
     * @var bool
     */
    public $enableBlikPayment;


    /**
     * States whether the Open Banking payment method should be enabled
     *
     * @var bool
     */
    public $enableOpenBanking;

    public function __construct()
    {
        $this->client = new SdkClient();
        $this->configValidation = new ConfigValidation();
        $this->translator = (new Utils())->getTranslator();
        $this->initFormFields();
        $this->configureMerchantSettings();
    }

    /**
     * Required options for proper client-side configuration.
     *
     * @return array<string,string>
     */
    abstract public function getFrontendGatewayOptions();

    /**
     * Required options for proper server-side configuration.
     *
     * @return array<string,string>
     */
    abstract public function getBackendGatewayOptions();

    /**
     * Custom admin options to configure the gateway-specific credentials, features, etc.
     *
     * @return array
     */
    abstract public function getGatewayFormFields();

    /**
     * Email address of the first-line support team
     *
     * @return string
     */
    abstract public function getFirstLineSupportEmail();

    public function environmentIndicatorActive()
    {
        return (isset($this->isProduction) && !$this->isProduction)
            || (isset($this->publicKey) && false === strpos($this->publicKey, 'pkapi_prod_'));
    }

    /**
     * Get the current gateway provider
     *
     * @return string
     */
    public function getGatewayProvider()
    {
        if (!$this->gatewayProvider) {
            // this shouldn't happen outside of our internal development
            throw new ApiException('Missing gateway provider configuration');
        }

        return $this->gatewayProvider;
    }

    /**
     * Sets the configurable merchant settings for use elsewhere in the class
     *
     * @return
     */
    public function configureMerchantSettings()
    {
        $this->title = $this->getTitle();
        $this->enabled = \Configuration::get($this->id . '_enabled');
        $this->paymentAction = \Configuration::get($this->id . '_paymentAction');
        $this->txnDescriptor = \Configuration::get($this->id . '_txnDescriptor');
        $this->allowCardSaving = \Configuration::get($this->id . '_allowCardSaving') === '1';
        $this->sortOrder = \Configuration::get($this->id . '_sortOrder');
        
        // Only set Blik Payment and Open Banking if conditions are met
        if ($this->isPolandWithPLNCurrency()) {
            $this->enableBlikPayment = \Configuration::get($this->id . '_enableBlikPayment') === '1';
            $this->enableOpenBanking = \Configuration::get($this->id . '_enableOpenBanking') === '1';
        } else {
            $this->enableBlikPayment = false;
            $this->enableOpenBanking = false;
        }

        foreach ($this->getGatewayFormFields() as $key => $options) {
            /**
             * The key will look something like 'id_key', so we remove 'id_'
             */
            $attributeKey = str_replace($this->id . '_', '', $key);

            if (!property_exists($this, $attributeKey)) {
                continue;
            }

            $value = \Configuration::get($key);

            if ('switch' === $options['type']) {
                $value = '1' === $value;
            }

            $this->{$attributeKey} = $value;
        }
    }

    /**
     * Check if the store is configured for Poland with PLN currency
     *
     * @return bool
     */
    private function isPolandWithPLNCurrency()
    {
        $country = new \Country((int) \Configuration::get('PS_COUNTRY_DEFAULT'));
        $currency = new \Currency((int) \Configuration::get('PS_CURRENCY_DEFAULT'));
        
        return $country->iso_code === 'PL' && $currency->iso_code === 'PLN';
    }

    /**
     * Configures shared gateway options
     *
     * @return
     */
    public function initFormFields()
    {
        $baseFields = [
            $this->id . '_paymentAction' => [
                'title' => $this->translator->trans('Payment Action', [], 'Modules.Globalpayments.Admin'),
                'type' => 'select',
                'description' => $this->translator->trans(
                    'Choose whether you wish to capture funds immediately or authorize payment only for a
                     delayed capture.',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => TransactionType::SALE,
                'options' => [
                    TransactionType::SALE => $this->translator->trans(
                        'Authorize + Capture',
                        [],
                        'Modules.Globalpayments.Admin'
                    ),
                    TransactionType::AUTHORIZE => $this->translator->trans(
                        'Authorize only',
                        [],
                        'Modules.Globalpayments.Admin'
                    ),
                ],
            ],
            $this->id . '_allowCardSaving' => [
                'title' => $this->translator->trans(
                    'Allow Card Saving',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'type' => 'switch',
                'description' => $this->translator->trans(
                    'Note: to use the card saving feature, you must have multi-use token
                    support enabled on your account. Please contact
                    <a href="mailto:%s%?Subject=PrestaShop%%20Allow%%20Card%%20Saving">support</a>
                    with any questions regarding this option.',
                    ['%s%' => $this->getFirstLineSupportEmail()],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => 0,
            ],
            $this->id . '_txnDescriptor' => [
                'title' => $this->translator->trans(
                    'Order Transaction Descriptor',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'type' => 'text',
                'maxLength' => '25',
                'description' => $this->translator->trans(
                    'During a Capture or Authorize payment action, this value will be passed along as the
                    transaction-specific descriptor listed on the customer\'s bank account. Please contact
                    <a href="mailto:%s?Subject=PrestaShop%%20Transaction%%20Descriptor%%20Option">
                    support</a> with any questions regarding this option (maxLength: 25).',
                    ['%s%' => $this->getFirstLineSupportEmail()],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => '',
            ],
            $this->id . '_sortOrder' => [
                'title' => $this->translator->trans('Sort Order', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'default' => 0,
            ],
        ];

        // Add Blik Payment and Open Banking fields only for Poland with PLN currency
        if ($this->isPolandWithPLNCurrency()) {
            $baseFields[$this->id . '_enableBlikPayment'] = [
                'title' => $this->translator->trans('Enable Blik Payment', [], 'Modules.Globalpayments.Admin'),
                'type' => 'switch',
                'description' => $this->translator->trans(
                    'Enable or disable Blik payment method.',
                    [],
                ),
                'default' => 0,
            ];
            
            $baseFields[$this->id . '_enableOpenBanking'] = [
                'title' => $this->translator->trans('Enable Open Banking', [], 'Modules.Globalpayments.Admin'),
                'type' => 'switch',
                'description' => $this->translator->trans(
                    'Enable or disable Open Banking method.',
                    [],
                ),
                'default' => 0,
            ];
        }

        $this->formFields = array_merge(
            [
                $this->id . '_enabled' => [
                    'title' => $this->translator->trans('Enable', [], 'Modules.Globalpayments.Admin'),
                    'type' => 'switch',
                    'default' => 0,
                ],
                $this->id . '_title' => [
                    'title' => $this->translator->trans('Title', [], 'Modules.Globalpayments.Admin'),
                    'type' => 'text',
                    'description' => $this->translator->trans(
                        'This controls the title which the user sees during checkout.',
                        [],
                        'Modules.Globalpayments.Admin'
                    ),
                    'default' => $this->translator->trans('Credit Card', [], 'Modules.Globalpayments.Admin'),
                ],
            ],
            $this->getGatewayFormFields(),
            $baseFields
        );
    }

    /**
     * The configuration for the globalpayments_secure_payment_fields_params object.
     *
     * @return array
     */
    public function getPaymentFieldsParams()
    {
        return [
            'id' => $this->id,
            'gatewayOptions' => $this->securePaymentFieldsFrontendConfig(),
            'fieldOptions' => $this->securePaymentFieldsConfiguration(),
            'fieldStyles' => $this->securePaymentFieldsStyles(),
        ];
    }

    /**
     * The configuration for the globalpayments_digital_wallet_params object
     */
    public function getDigitalWalletParams()
    {
        return [
            'id' => $this->id,
            'gatewayOptions' => $this->securePaymentFieldsFrontendConfig(),
        ];
    }

    public function getCredentialSetting($setting)
    {
        return $this->isProduction ? $this->{$setting} : $this->{'sandbox' . \Tools::ucfirst($setting)};
    }

    /**
     * Configuration for the secure payment fields on the client side.
     *
     * @return array
     */
    protected function securePaymentFieldsFrontendConfig()
    {
        try {
            return $this->getFrontendGatewayOptions();
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'hide' => GatewayId::GP_UCP === $this->id,
            ];
        }
    }

    /**
     * Configuration for the secure payment fields. Used on server- and
     * client-side portions of the integration.
     *
     * @return mixed[]
     */
    public function securePaymentFieldsConfiguration()
    {
        return [
            'card-number-field' => [
                'class' => 'card-number',
                'label' => $this->translator->trans('Credit Card Number', [], 'Modules.Globalpayments.Shop'),
                'placeholder' => '•••• •••• •••• ••••',
                'messages' => [
                    'validation' => $this->translator->trans(
                        'Please enter a valid Credit Card Number',
                        [],
                        'Modules.Globalpayments.Shop'
                    ),
                ],
            ],
            'card-expiry-field' => [
                'class' => 'card-expiration',
                'label' => $this->translator->trans('Credit Card Expiration Date', [], 'Modules.Globalpayments.Shop'),
                'placeholder' => 'MM / YYYY',
                'messages' => [
                    'validation' => $this->translator->trans(
                        'Please enter a valid Credit Card Expiration Date',
                        [],
                        'Modules.Globalpayments.Shop'
                    ),
                ],
            ],
            'card-cvv-field' => [
                'class' => 'card-cvv',
                'label' => $this->translator->trans('Credit Card Security Code', [], 'Modules.Globalpayments.Shop'),
                'placeholder' => '•••',
                'messages' => [
                    'validation' => $this->translator->trans(
                        'Please enter a valid Credit Card Security Code',
                        [],
                        'Modules.Globalpayments.Shop'
                    ),
                ],
            ],
        ];
    }

    /**
     * CSS styles for secure payment fields.
     *
     * @return string
     */
    protected function securePaymentFieldsStyles()
    {
        $imageBase = $this->securePaymentFieldsAssetBaseUrl() . '/images';

        $securePaymentFieldsStyles = [
            'html' => [
                'font-size' => '100%',
                '-webkit-text-size-adjust' => '100%',
            ],
            'body' => [],
            '#secure-payment-field-wrapper' => [
                'position' => 'relative',
            ],
            '#secure-payment-field' => [
                'background-color' => '#fff',
                'border' => '1px solid #ccc',
                'border-radius' => '4px',
                'display' => 'block',
                'font-size' => '14px',
                'height' => '35px',
                'padding' => '6px 12px',
                'width' => '100%',
            ],
            '#secure-payment-field:focus' => [
                'border' => '1px solid lightblue',
                'box-shadow' => '0 1px 3px 0 #cecece',
                'outline' => 'none',
            ],
            'button#secure-payment-field.submit' => [
                'border' => '0',
                'border-radius' => '0',
                'background' => 'none',
                'background-color' => '#24b9d7',
                'border-color' => 'rgba(0,0,0,0)',
                'color' => '#fff',
                'cursor' => 'pointer',
                'padding' => '0.65rem 1.25rem',
                'text-decoration' => 'none',
                'text-transform' => 'uppercase',
                'text-align' => 'center',
                'text-shadow' => 'none',
                'display' => 'inline-block',
                '-webkit-appearance' => 'none',
                'height' => 'initial',
                'width' => 'auto',
                'flex' => 'initial',
                'position' => 'static',
                'margin' => '0',
                'white-space' => 'pre-wrap',
                'margin-bottom' => '0',
                'float' => 'none',
                'font' => '600 1rem/1.25 Source Sans Pro,HelveticaNeue-Light,Helvetica Neue Light,
                    Helvetica Neue,Helvetica,Arial,Lucida Grande,sans-serif !important',
            ],
            'button#secure-payment-field.submit:disabled' => [
                'background-color' => '#808080',
                'border-color' => '#808080',
                'cursor' => 'not-allowed',
            ],
            '#secure-payment-field[type=button]:focus' => [
                'color' => '#fff',
                'background-color' => '#1d93ab',
                'border-color' => 'rgba(0,0,0,0)',
            ],
            '#secure-payment-field[type=button]:hover' => [
                'color' => '#fff',
                'background-color' => '#1d93ab',
                'border-color' => 'rgba(0,0,0,0)',
            ],
            '#secure-payment-field[type=button]:disabled:focus' => [
                'color' => '#fff',
                'background' => '#808080',
            ],
            '#secure-payment-field[type=button]:disabled:hover' => [
                'color' => '#fff',
                'background' => '#808080',
            ],
            '.card-cvv' => [
                'background' => 'transparent url(' . $imageBase . '/cvv.png) no-repeat right',
                'background-size' => '63px 40px',
            ],
            '.card-cvv.card-type-amex' => [
                'background' => 'transparent url(' . $imageBase . '/cvv-amex.png) no-repeat right',
                'background-size' => '63px 40px',
            ],
            '.card-number::-ms-clear' => [
                'display' => 'none',
            ],
            'input[placeholder]' => [
                'letter-spacing' => '.5px',
            ],
            'img.card-number-icon' => [
                'background' => 'transparent url(' . $imageBase . '/logo-unknown@2x.png) no-repeat',
                'background-size' => '100%',
                'width' => '65px',
                'height' => '40px',
                'position' => 'absolute',
                'right' => '0',
                'top' => '25px',
                'margin-top' => '-20px',
                'background-position' => '50% 50%',
            ],
            'img.card-number-icon[src$=\'/gp-cc-generic.svg\']' => [
                'background' => 'transparent url(' . $imageBase . '/logo-mastercard@2x.png) no-repeat',
                'background-size' => '100%',
                'background-position-y' => 'bottom',
            ],
            'img.card-number-icon.card-type-diners' => [
                'background' => 'transparent url(' . $imageBase . '/gp-cc-diners.svg) no-repeat',
                'background-size' => '80%',
                'background-position' => '10px 3px',
            ],
            'img.card-number-icon.invalid.card-type-amex' => [
                'background' => 'transparent url(' . $imageBase . '/logo-amex@2x.png) no-repeat 140%',
                'background-size' => '80%',
                'background-position-y' => '87%',
            ],
            'img.card-number-icon.invalid.card-type-discover' => [
                'background' => 'transparent url(' . $imageBase . '/logo-discover@2x.png) no-repeat',
                'background-size' => '115%',
                'background-position-y' => '95%',
                'width' => '80px',
            ],
            'img.card-number-icon.invalid.card-type-jcb' => [
                'background' => 'transparent url(' . $imageBase . '/logo-jcb@2x.png) no-repeat 175%',
                'background-size' => '90%',
                'background-position-y' => '85%',
            ],
            'img.card-number-icon.invalid.card-type-mastercard' => [
                'background' => 'transparent url(' . $imageBase . '/logo-mastercard@2x.png) no-repeat',
                'background-size' => '120%',
                'background-position-y' => 'bottom',
            ],
            'img.card-number-icon.invalid.card-type-visa' => [
                'background' => 'transparent url(' . $imageBase . '/logo-visa@2x.png) no-repeat',
                'background-size' => '120%',
                'background-position-y' => 'bottom',
            ],
            'img.card-number-icon.valid.card-type-amex' => [
                'background' => 'transparent url(' . $imageBase . '/logo-amex@2x.png) no-repeat 140%',
                'background-size' => '80%',
                'background-position-y' => '-6px',
            ],
            'img.card-number-icon.valid.card-type-discover' => [
                'background' => 'transparent url(' . $imageBase . '/logo-discover@2x.png) no-repeat',
                'background-size' => '115%',
                'background-position-y' => '-5px',
                'width' => '80px',
            ],
            'img.card-number-icon.valid.card-type-jcb' => [
                'background' => 'transparent url(' . $imageBase . '/logo-jcb@2x.png) no-repeat 175%',
                'background-size' => '90%',
                'background-position-y' => '-5px',
            ],
            'img.card-number-icon.valid.card-type-mastercard' => [
                'background' => 'transparent url(' . $imageBase . '/logo-mastercard@2x.png) no-repeat',
                'background-size' => '120%',
                'background-position-y' => '-1px',
            ],
            'img.card-number-icon.valid.card-type-visa' => [
                'background' => 'transparent url(' . $imageBase . '/logo-visa@2x.png) no-repeat',
                'background-size' => '120%',
                'background-position-y' => '-1px',
            ],
            '#field-validation-wrapper' => [
                'background' => '#e2401c',
                'font-size' => '1rem !important',
                'padding' => '6px 12px',
                'border-radius' => '4px',
                'border-left' => '.6180469716em solid rgba(0,0,0,.15)',
                'color' => '#fff !important',
            ],
        ];

        return $this->applyGpHostedFieldsStylesHook($securePaymentFieldsStyles);
    }

    /**
     * Allow hosted fields styling customization.
     *
     * @param array $securePaymentFieldsStyles CSS styles
     *
     * @return string
     */
    protected function applyGpHostedFieldsStylesHook($securePaymentFieldsStyles)
    {
        if (\Hook::getIdByName('actionGpHostedFieldsStyling')) {
            $hookResultArray = \Hook::exec(
                'actionGpHostedFieldsStyling',
                [
                    'styles' => json_encode($securePaymentFieldsStyles),
                ],
                null,
                true
            );
            $result = [];
            foreach ($hookResultArray as $hookResult) {
                if ($hookResult !== null) {
                    $result = array_merge($result, json_decode($hookResult, true));
                }
            }

            return json_encode($result);
        }

        return json_encode($securePaymentFieldsStyles);
    }

    /**
     * Base assets URL for secure payment fields.
     *
     * @return string
     */
    protected function securePaymentFieldsAssetBaseUrl()
    {
        if ($this->isProduction) {
            return 'https://js.globalpay.com/' . Utils::getJsLibVersion();
        }

        return 'https://js-cert.globalpay.com/' . Utils::getJsLibVersion();
    }

    /**
     * Handle payment functions
     *
     * @param Order $order
     *
     * @return Transaction
     *
     * @throws ApiException
     */
    public function processPayment(Order $order)
    {
        $request = $this->prepareRequest($this->paymentAction, $order);
        $response = $this->submitRequest($request);

        if (!$response instanceof Transaction) {
            throw new ApiException('Unexpected transaction response');
        }

        $this->handleResponse($request, $response);

        return $response;
    }

    /**
     * Handle adding new cards
     *
     * @param Order $order
     *
     * @return Transaction
     *
     * @throws ApiException
     */
    public function addPaymentMethod(Order $order)
    {
        $request = $this->prepareRequest(TransactionType::VERIFY, $order);
        $response = $this->submitRequest($request);

        if (!$response instanceof Transaction) {
            throw new ApiException('Unexpected transaction response');
        }

        $this->handleResponse($request, $response);

        return $response;
    }

    /**
     * Handle online refund requests
     *
     * @param Order $order
     *
     * @return Transaction
     *
     * @throws ApiException
     */
    public function processRefund(Order $order)
    {
        $details = $this->getTransactionDetails($order);
        $isOrderTxnIdActive = $this->isTransactionActive($details);
        $txnType = $isOrderTxnIdActive ? TransactionType::REVERSAL : TransactionType::REFUND;

        $request = $this->prepareRequest($txnType, $order);
        $response = $this->submitRequest($request);

        if (!$response instanceof Transaction) {
            throw new ApiException('Unexpected transaction response');
        }

        $this->handleResponse($request, $response);

        return $response;
    }

    /**
     * Get transaction details
     *
     * @param Order $order
     *
     * @return TransactionSummary
     *
     * @throws ApiException
     */
    public function getTransactionDetails(Order $order)
    {
        $request = $this->prepareRequest(TransactionType::REPORT_TXN_DETAILS, $order);
        $response = $this->submitRequest($request);

        if (!$response instanceof TransactionSummary) {
            throw new ApiException('Unexpected transaction response');
        }

        return $response;
    }

    /**
     * Get transaction details based on the transaction id.
     *
     * @param $txnId
     *
     * @return TransactionSummary
     *
     * @throws ApiException
     */
    public function getTransactionDetailsByTxnId($txnId)
    {
        $request = $this->prepareRequest(TransactionType::REPORT_TXN_DETAILS);
        $request->setArguments([
            Requests\RequestArg::GATEWAY_ID => $txnId,
        ]);

        return $this->submitRequest($request);
    }

    /**
     * Creates the necessary request based on the transaction type
     *
     * @param TransactionType $txnType
     * @param Order|null $order
     * @param array|null $configData
     *
     * @return Requests\RequestInterface
     */
    public function prepareRequest($txnType, ?Order $order = null, ?array $configData = null)
    {
        $map = [
            TransactionType::APM_AUTHORIZATION => Requests\Apm\InitiatePaymentRequest::class,
            TransactionType::AUTHORIZE => Requests\AuthorizationRequest::class,
            TransactionType::BNPL_AUTHORIZATION => Requests\BuyNowPayLater\InitiatePaymentRequest::class,
            TransactionType::CAPTURE => Requests\CaptureAuthorizationRequest::class,
            TransactionType::CHECK_ENROLLMENT => Requests\ThreeDSecure\CheckEnrollmentRequest::class,
            TransactionType::CREATE_MANIFEST => Requests\CreateManifestRequest::class,
            TransactionType::CREATE_TRANSACTION_KEY => Requests\CreateTransactionKeyRequest::class,
            TransactionType::DW_AUTHORIZATION => Requests\DigitalWallets\AuthorizationRequest::class,
            TransactionType::GET_ACCESS_TOKEN => Requests\GetAccessTokenRequest::class,
            TransactionType::INITIATE_AUTHENTICATION => Requests\ThreeDSecure\InitiateAuthenticationRequest::class,
            TransactionType::OB_AUTHORIZATION => Requests\OpenBanking\InitiatePaymentRequest::class,
            TransactionType::REFUND => Requests\RefundRequest::class,
            TransactionType::REVERSAL => Requests\ReversalRequest::class,
            TransactionType::SALE => Requests\SaleRequest::class,
            TransactionType::REPORT_TXN_DETAILS => Requests\TransactionDetailRequest::class,
            TransactionType::VERIFY => Requests\VerifyRequest::class,
        ];

        if (!isset($map[$txnType])) {
            throw new ApiException('Cannot perform transaction');
        }

        $order = $order ?? new Order();
        $order->transactionType = $txnType;
        $request = $map[$txnType];

        $backendGatewayOptions = $this->getBackendGatewayOptions();
        if (!empty($configData)) {
            $backendGatewayOptions = array_merge($backendGatewayOptions, $configData);
        }

        return new $request(
            $order,
            array_merge(
                ['gatewayProvider' => $this->getGatewayProvider()],
                $backendGatewayOptions
            )
        );
    }

    /**
     * Executes the prepared request
     *
     * @param Requests\RequestInterface $request
     *
     * @return string|Transaction|TransactionSummary
     */
    public function submitRequest(Requests\RequestInterface $request)
    {
        return $this->client->setRequest($request)->execute();
    }

    /**
     * Reacts to the transaction response
     *
     * @param Requests\RequestInterface $request
     * @param string|Transaction|TransactionSummary $response
     *
     * @return bool
     *
     * @throws ApiException
     */
    public function handleResponse(Requests\RequestInterface $request, $response)
    {
        if (!$response instanceof Transaction) {
            throw new ApiException('Unexpected transaction response');
        }

        /**
         * @var HandlerInterface[]
         */
        $handlers = $this->successHandlers;

        if ('00' !== $response->responseCode && 'SUCCESS' !== $response->responseCode) {
            $handlers = $this->errorHandlers;
        }

        foreach ($handlers as $handler) {
            /**
             * Current handler
             *
             * @var HandlerInterface $h
             */
            $h = new $handler($request, $response);

            $h->handle();
        }

        return true;
    }

    /**
     * Should be overridden by each gateway implementation
     *
     * @return bool
     */
    protected function isTransactionActive(TransactionSummary $details)
    {
        return false;
    }

    public function validateAdminSettings()
    {
        if (\Tools::getIsset($this->id . '_sortOrder') && !is_numeric(\Tools::getValue($this->id . '_sortOrder'))) {
            return [
                $this->translator->trans('Sort Order must have a numeric value', [], 'Modules.Globalpayments.Admin'),
            ];
        }

        return [];
    }

    private function getTitle()
    {
        $configTitle = \Configuration::get($this->id . '_title');
        if (!empty($configTitle)) {
            return $configTitle;
        }

        return $this->formFields[$this->id . '_title']['default'] ?? '';
    }

    /**
     * Load the checkout scripts.
     *
     * @param \GlobalPayments $module
     *
     * @return void
     */
    public function enqueuePaymentScripts($module)
    {
        $this->hostedFieldsScript($module);
    }

    /**
     * Load the globalpayments.js (or .min.js) script from the CDN.
     *
     * @param \GlobalPayments $module
     *
     * @return void
     */
    public function hostedFieldsScript($module)
    {
        $module->getContext()->controller->registerJavascript(
            'globalpayments-secure-payment-fields-lib',
            'https://js.globalpay.com/' . Utils::getJsLibVersion() . '/globalpayments'
                . (defined('_PS_MODE_DEV_') && _PS_MODE_DEV_ ? '' : '.min') . '.js',
            [
                'server' => 'remote',
                'position' => 'head',
                'priority' => 0
            ]
        );

        $module->getContext()->controller->registerJavascript(
            'globalpayments-secure-payment-fields-lib',
            'https://js.globalpay.com/' . Utils::getJsLibVersion() . '/globalpayments'
                . (defined('_PS_MODE_DEV_') && _PS_MODE_DEV_ ? '' : '.min') . '.js',
            ['server' => 'remote']
        );
    }

    /**
     * Get the payment options that will be displayed at checkout.
     *
     * @param \GlobalPayments $module
     * @param $params $array
     * @param bool $isCheckout
     *
     * @return array
     *
     * @throws \PrestaShopDatabaseException
     */
    public function getPaymentOptions($module, $params, $isCheckout)
    {
        $context = $module->getContext();
        $paymentOptions = [];
        $cardPaymentOptions = [];

        if (!empty($params['customerId'])) {
            $customer = new \Customer($params['customerId']);
        } else {
            $customer = $context->customer;
        }

        $formAction = $params['formAction'] ??
            $context->link->getModuleLink($module->name, 'validation', [], true);

        $context->smarty->assign([
            'action' => $formAction,
            'formData' => $this->id === GatewayId::GP_UCP ? $this->securePaymentFieldsConfiguration() : [],
            'id' => $this->id,
            'allowCardSaving' => !$customer->is_guest && $this->allowCardSaving && $isCheckout,
            'envIndicator' => $this->environmentIndicatorActive(),
            'enableBlikPayment' => $this->enableBlikPayment,
        ]);

        $paymentOption = new PaymentOption();
        $paymentOption
            ->setModuleName($this->id)
            ->setCallToActionText($this->title)
            ->setForm($context->smarty->fetch($this->template));

        $paymentOptions[] = $paymentOption;

        if (!$customer->is_guest) {
            $this->getStoredCardsPaymentOptions($module, $customer, $formAction, $cardPaymentOptions);
        }

        return array_merge($paymentOptions, $cardPaymentOptions);
    }

    /**
     * Get the stored cards that will be displayed at checkout.
     *
     * @param $module
     * @param $customer
     * @param $formAction
     * @param $cardOptions
     *
     * @return void
     *
     * @throws \PrestaShopDatabaseException
     */
    private function getStoredCardsPaymentOptions($module, $customer, $formAction, &$cardOptions)
    {
        $context = $module->getContext();

        foreach (Token::getCustomerTokens($customer->id, $this->id) as $card) {
            $cardDetails = $card->details;

            if ($cardDetails->expiryMonth < date('m') && $cardDetails->expiryYear <= date('Y')) {
                continue;
            }

            $context->smarty->assign([
                'action' => $formAction,
                'cardId' => $card->id_globalpayments_token,
                'id' => $this->id,
            ]);

            $paymentText = $this->translator->trans(
                '%title% - %type% ending in %last4% (%month%/%year%)',
                [
                    '%title%' => $this->title,
                    '%type%' => \Tools::ucfirst($cardDetails->cardType),
                    '%last4%' => $cardDetails->last4,
                    '%month%' => $cardDetails->expiryMonth,
                    '%year%' => $cardDetails->expiryYear,
                ],
                'Modules.Globalpayments.Shop'
            );

            $paymentOption = new PaymentOption();
            $paymentOption
                ->setModuleName($this->id)
                ->setCallToActionText($paymentText)
                ->setForm(
                    $context->smarty->fetch(
                        'module:globalpayments/views/templates/front/payment_form_save_card.tpl'
                    )
                );

            $cardOptions[] = $paymentOption;
        }
    }
}
