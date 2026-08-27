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

use GlobalPayments\Api\Entities\Enums\Environment;
use GlobalPayments\Api\Entities\Enums\GatewayProvider;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\PaymentGatewayProvider\Data\Order;
use GlobalPayments\PaymentGatewayProvider\Requests\TransactionType;

if (!defined('_PS_VERSION_')) {
    exit;
}

class GeniusGateway extends AbstractGateway
{
    /**
     * Gateway ID
     *
     * @var string
     */
    public $id = GatewayId::GENIUS;

    /**
     * SDK gateway provider
     *
     * @var string
     */
    public $gatewayProvider = GatewayProvider::GENIUS;

    /**
     * Admin title for the gateway
     *
     * @var string
     */
    public $adminTitle = 'Global Payments Genius';

    /**
     * Merchant location's Merchant Name (Live)
     *
     * @var string
     */
    public $merchantName;

    /**
     * Merchant location's Site ID (Live)
     *
     * @var string
     */
    public $merchantSiteId;

    /**
     * Merchant location's Merchant Key (Live)
     *
     * @var string
     */
    public $merchantKey;

    /**
     * Merchant location's Web API Key (Live)
     *
     * @var string
     */
    public $webApiKey;

    /**
     * Sandbox Merchant Name
     *
     * @var string
     */
    public $sandboxMerchantName;

    /**
     * Sandbox Site ID
     *
     * @var string
     */
    public $sandboxMerchantSiteId;

    /**
     * Sandbox Merchant Key
     *
     * @var string
     */
    public $sandboxMerchantKey;

    /**
     * Sandbox Web API Key
     *
     * @var string
     */
    public $sandboxWebApiKey;

    /**
     * Should live payments be accepted
     *
     * @var bool
     */
    public $isProduction;

    /**
     * Should debug/logging be enabled
     *
     * @var bool
     */
    public $debug;

    public function getFirstLineSupportEmail()
    {
        return 'integrations@globalpay.com';
    }

    public function getFrontendGatewayOptions()
    {
        $webApiKey = $this->getCredentialSetting('webApiKey');
        
        // Validate credentials are configured
        if (empty($webApiKey)) {
            throw new ApiException('Genius gateway credentials are not configured. Please configure the gateway in admin settings.');
        }
        
        return [
            'webApiKey' => $webApiKey,
            'env' => $this->isProduction ? 'production' : 'sandbox',
        ];
    }

    public function getBackendGatewayOptions()
    {
        return [
            'merchantName' => $this->getCredentialSetting('merchantName'),
            'merchantSiteId' => $this->getCredentialSetting('merchantSiteId'),
            'merchantKey' => $this->getCredentialSetting('merchantKey'),
            'environment' => $this->isProduction ? Environment::PRODUCTION : Environment::TEST,
            'debug' => $this->debug,
        ];
    }

    public function getGatewayFormFields()
    {
        return [
            $this->id . '_isProduction' => [
                'title' => $this->translator->trans('Live Mode', [], 'Modules.Globalpayments.Admin'),
                'type' => 'switch',
                'description' => $this->translator->trans(
                    'Get your credentials from your <a href="https://developer.globalpay.com/user/register"
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
            // Sandbox fields
            $this->id . '_sandboxMerchantName' => [
                'title' => $this->translator->trans('Sandbox Name', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'class' => 'required sandbox-toggle',
                'default' => '',
            ],
            $this->id . '_sandboxMerchantSiteId' => [
                'title' => $this->translator->trans('Sandbox Site ID', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'class' => 'required sandbox-toggle',
                'default' => '',
            ],
            $this->id . '_sandboxMerchantKey' => [
                'title' => $this->translator->trans('Sandbox Key', [], 'Modules.Globalpayments.Admin'),
                'type' => 'password',
                'class' => 'required sandbox-toggle',
                'default' => '',
            ],
            $this->id . '_sandboxWebApiKey' => [
                'title' => $this->translator->trans('Sandbox Web API Key', [], 'Modules.Globalpayments.Admin'),
                'type' => 'password',
                'class' => 'required sandbox-toggle',
                'default' => '',
            ],
            // Live fields
            $this->id . '_merchantName' => [
                'title' => $this->translator->trans('Live Name', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'class' => 'required live-toggle',
                'default' => '',
            ],
            $this->id . '_merchantSiteId' => [
                'title' => $this->translator->trans('Live Site ID', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'class' => 'required live-toggle',
                'default' => '',
            ],
            $this->id . '_merchantKey' => [
                'title' => $this->translator->trans('Live Key', [], 'Modules.Globalpayments.Admin'),
                'type' => 'password',
                'class' => 'required live-toggle',
                'default' => '',
            ],
            $this->id . '_webApiKey' => [
                'title' => $this->translator->trans('Live Web API Key', [], 'Modules.Globalpayments.Admin'),
                'type' => 'password',
                'class' => 'required live-toggle',
                'default' => '',
            ],
            $this->id . '_debug' => [
                'title' => $this->translator->trans('Enable Logging', [], 'Modules.Globalpayments.Admin'),
                'type' => 'switch',
                'description' => $this->translator->trans(
                    'Log all requests to and from gateway. This can also log private data and should only be enabled in a development or stage environment. Logs are saved to var/logs/ directory.',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => 0,
            ],
        ];
    }

    /**
     * Validate admin settings for Genius gateway
     *
     * @return array
     */
    public function validateAdminSettings(): array
    {
        $errors = [];

        // Skip validation if gateway is not enabled (strict check)
        $isEnabled = \Tools::getValue($this->id . '_enabled');
        if ($isEnabled !== '1' && $isEnabled !== 1 && $isEnabled !== true) {
            return $errors;
        }

        // Check if another gateway is already enabled - only one gateway allowed at a time
        $gpUcpEnabled = \Configuration::get(GatewayId::GP_UCP . '_enabled') === '1';
        $transitEnabled = \Configuration::get(GatewayId::TRANSIT . '_enabled') === '1';

        if ($gpUcpEnabled) {
            $errors[] = $this->translator->trans(
                'Another gateway (Global Payments - Unified Payments) is already enabled. Only one gateway can be active at a time. Please disable it first.',
                [],
                'Modules.Globalpayments.Admin'
            );
            return $errors;
        }

        if ($transitEnabled) {
            $errors[] = $this->translator->trans(
                'Another gateway (TransIT) is already enabled. Only one gateway can be active at a time. Please disable it first.',
                [],
                'Modules.Globalpayments.Admin'
            );
            return $errors;
        }

        if (\Tools::getValue($this->id . '_isProduction')) {
            // Live Mode is ON - validate Live credentials
            $liveFieldsEmpty = empty(\Tools::getValue($this->id . '_merchantName'))
                || empty(\Tools::getValue($this->id . '_merchantSiteId'))
                || empty(\Tools::getValue($this->id . '_merchantKey'))
                || empty(\Tools::getValue($this->id . '_webApiKey'));

            if ($liveFieldsEmpty) {
                $errors[] = $this->translator->trans(
                    'Please provide Live Credentials for Genius gateway.',
                    [],
                    'Modules.Globalpayments.Admin'
                );
            }
        } else {
            // Live Mode is OFF - validate Sandbox credentials
            $sandboxFieldsEmpty = empty(\Tools::getValue($this->id . '_sandboxMerchantName'))
                || empty(\Tools::getValue($this->id . '_sandboxMerchantSiteId'))
                || empty(\Tools::getValue($this->id . '_sandboxMerchantKey'))
                || empty(\Tools::getValue($this->id . '_sandboxWebApiKey'));

            if ($sandboxFieldsEmpty) {
                $errors[] = $this->translator->trans(
                    'Please provide Sandbox Credentials for Genius gateway.',
                    [],
                    'Modules.Globalpayments.Admin'
                );
            }
        }

        return $errors;
    }

    /**
     * Get the Genius-specific JS library URL
     * Genius uses v1 endpoint same as Heartland/Transit
     *
     * @return string
     */
    public function getGeniusJsLibUrl(): string
    {
        return 'https://js.globalpay.com/v1/globalpayments.js';
    }

    /**
     * Load the checkout scripts for Genius gateway.
     *
     * @param \GlobalPayments $module
     *
     * @return void
     */
    public function enqueuePaymentScripts($module): void
    {
        if (!$this->enabled) {
            return;
        }

        $context = $module->getContext();
        $path = $module->getFrontendScriptsPath();

        // Load Genius-specific JS library (v1 endpoint)
        $context->controller->registerJavascript(
            'globalpayments-genius-lib',
            $this->getGeniusJsLibUrl(),
            [
                'server' => 'remote',
                'position' => 'head',
                'priority' => 0
            ]
        );

        $context->controller->registerStylesheet(
            'globalpayments-secure-payment-fields',
            $path . '/views/css/globalpayments-secure-payment-fields.css'
        );

        $context->controller->registerJavascript(
            'globalpayments-secure-payment-fields',
            $path . '/views/js/globalpayments-secure-payment-fields.js',
            ['position' => 'bottom', 'priority' => 100]
        );

        \Media::addJsDef(
            [
                'globalpayments_genius_params' => $this->getPaymentFieldsParams(),
                'globalpayments_genius_threedsecure_params' => [
                    'threedsecure' => [],
                ],
            ]
        );
    }

    /**
     * Get the payment options for Genius gateway.
     * Uses parent implementation which already handles the gateway.
     *
     * @param \GlobalPayments $module
     * @param array $params
     * @param bool $isCheckout
     *
     * @return array
     */
    public function getPaymentOptions($module, $params, $isCheckout): array
    {
        return parent::getPaymentOptions($module, $params, $isCheckout);
    }

    /**
     * Handle online refund requests for Genius gateway.
     *
     * Genius doesn't support getTransactionDetails() like GP-API does, so we override this
     * method to directly process the refund without checking transaction status.
     *
     * @param Order $order
     *
     * @return Transaction
     *
     * @throws ApiException
     */
    public function processRefund(Order $order)
    {
        // Genius doesn't support getTransactionDetails() like GP-API does,
        // so we directly process as a refund without checking if transaction is active.
        // For Genius, refunds work on settled transactions.
        $request = $this->prepareRequest(TransactionType::REFUND, $order);
        $response = $this->submitRequest($request);

        if (!$response instanceof Transaction) {
            throw new ApiException(sprintf(
                'Unexpected Genius refund response: expected %s, got %s',
                Transaction::class,
                is_object($response) ? get_class($response) : gettype($response)
            ));
        }

        $this->handleResponse($request, $response);

        return $response;
    }
}
