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

use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\PaymentGatewayProvider\Data\Order;
use GlobalPayments\PaymentGatewayProvider\Gateways\AbstractGateway;
use GlobalPayments\PaymentGatewayProvider\Gateways\GpApiGateway;
use GlobalPayments\PaymentGatewayProvider\Platform\Utils;
use GlobalPayments\PaymentGatewayProvider\Platform\Validator\Admin\Config\Validation as ConfigValidation;
use GlobalPayments\PaymentGatewayProvider\Requests\TransactionType;
use PrestaShopBundle\Translation\TranslatorComponent as Translator;

if (!defined('_PS_VERSION_')) {
    exit;
}

abstract class AbstractPaymentMethod implements PaymentMethodInterface
{
    public const PAYMENT_METHOD_ID = 'paymentMethodId';

    /**
     * The tab title shown in the admin config page.
     *
     * @var string
     */
    public $adminTitle;

    /**
     * Payment method default title.
     *
     * @var string
     */
    public $defaultTitle;

    /**
     * Payment method enabled status
     *
     * @var bool
     */
    public $enabled;

    /**
     * The form fields for the back-end interface.
     *
     * @var array
     */
    public $formFields;

    /**
     * @var AbstractGateway
     */
    public $gateway;

    /**
     * @var string
     */
    public $title;

    /**
     * Payment Method ID. Should be overridden by individual gateway implementations.
     *
     * @var string
     */
    public $id;

    /**
     * Action to perform on checkout.
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
     * Sort order for the payment at checkout.
     *
     * @var float|int
     */
    public $sortOrder;

    /**
     * @var ConfigValidation
     */
    public $configValidation;

    /**
     * @var Translator
     */
    protected $translator;

    public function __construct()
    {
        $this->gateway = new GpApiGateway();
        $this->configValidation = new ConfigValidation();
        $this->translator = (new Utils())->getTranslator();
        $this->defaultTitle = $this->getDefaultTitle();

        $this->initFormFields();
        $this->configureMerchantSettings();
    }

    /**
     * Should be overwritten to provide additional functionality after payment gateway response is received.
     *
     * @param Transaction $gatewayResponse
     * @param int $orderId
     *
     * @return void|null
     */
    public function processPaymentAfterGatewayResponse($gatewayResponse, $orderId)
    {
        return null;
    }

    /**
     * Email address of the first-line support team.
     *
     * @return string
     */
    public function getFirstLineSupportEmail()
    {
        return $this->gateway->getFirstLineSupportEmail();
    }

    public function initFormFields()
    {
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
                    'default' => $this->defaultTitle,
                ],
            ],
            $this->getPaymentMethodFormFields(),
            [
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
                    'options' => $this->getPaymentActionOptions(),
                    'disabled' => $this->getPaymentActionIsDisabled(),
                ],
                $this->id . '_sortOrder' => [
                    'title' => $this->translator->trans('Sort Order', [], 'Modules.Globalpayments.Admin'),
                    'type' => 'text',
                    'default' => 0,
                ],
            ]
        );
    }

    /**
     * Get supported payment actions at checkout.
     *
     * @return array
     */
    public function getPaymentActionOptions()
    {
        return [
            TransactionType::SALE => $this->translator->trans('Authorize + Capture', [], 'Modules.Globalpayments.Admin'),
            TransactionType::AUTHORIZE => $this->translator->trans('Authorize only', [], 'Modules.Globalpayments.Admin'),
        ];
    }

    /**
     * States whether the Payment Action input should be disabled.
     *
     * @return bool
     */
    public function getPaymentActionIsDisabled()
    {
        return false;
    }

    /**
     * Sets the configurable merchant settings for use elsewhere in the class.
     *
     * @return void
     */
    public function configureMerchantSettings()
    {
        $this->title = $this->getTitle();
        $this->enabled = \Configuration::get($this->id . '_enabled');
        $this->paymentAction = \Configuration::get($this->id . '_paymentAction');
        $this->sortOrder = \Configuration::get($this->id . '_sortOrder');

        foreach ($this->getPaymentMethodFormFields() as $key => $options) {
            /**
             * The key will look something like 'id_key', so we remove 'id_'
             */
            $attributeKey = str_replace($this->id . '_', '', $key);

            if (!property_exists($this, $attributeKey)) {
                continue;
            }

            $value = \Configuration::get($key);

            if ($options['type'] === 'switch') {
                $value = '1' === $value;
            }

            $this->{$attributeKey} = $value;
        }
    }

    /**
     * Get the title of the payment method.
     *
     * @return string
     */
    private function getTitle()
    {
        $configTitle = \Configuration::get($this->id . '_title');
        if (!empty($configTitle)) {
            return $configTitle;
        }

        return $this->defaultTitle;
    }

    /**
     * @param Order $order
     *
     * @return void
     */

    /**
     * @param $order
     * @return mixed
     * @throws ApiException
     */
    public function processPayment($order)
    {
        $request = $this->gateway->prepareRequest(
            $this->getRequestType(),
            $order
        );

        $this->processPaymentBeforeGatewayRequest($request, $order);
        $response = $this->gateway->client->submitRequest($request);
        $this->gateway->handleResponse($request, $response);

        return $response;
    }
}
