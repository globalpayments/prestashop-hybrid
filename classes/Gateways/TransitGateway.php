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
use GlobalPayments\PaymentGatewayProvider\Requests;
use GlobalPayments\PaymentGatewayProvider\Requests\TransactionType;

if (!defined('_PS_VERSION_')) {
    exit;
}

class TransitGateway extends AbstractGateway
{
    /**
     * Gateway ID
     *
     * @var string
     */
    public $id = GatewayId::TRANSIT;

    /**
     * SDK gateway provider
     *
     * @var string
     */
    public $gatewayProvider = GatewayProvider::TRANSIT;

    /**
     * Admin title for the gateway
     *
     * @var string
     */
    public $adminTitle = 'Global Payments TransIT';

    /**
     * Merchant location's Merchant ID (Live)
     *
     * @var string
     */
    public $merchantId;

    /**
     * Merchant location's User ID (Live)
     *
     * Note: only needed to create transation key
     *
     * @var string
     */
    public $userId;

    /**
     * Merchant location's Password (Live)
     *
     * Note: only needed to create transation key
     *
     * @var string
     */
    public $password;

    /**
     * Merchant location's Device ID (Live)
     *
     * @var string
     */
    public $deviceId;

    /**
     * Device ID for TSEP entity specifically (Live)
     *
     * @var string
     */
    public $tsepDeviceId;

    /**
     * Merchant location's Transaction Key (Live)
     *
     * @var string
     */
    public $transactionKey;

    /**
     * Sandbox Merchant ID
     *
     * @var string
     */
    public $sandboxMerchantId;

    /**
     * Sandbox User ID
     *
     * @var string
     */
    public $sandboxUserId;

    /**
     * Sandbox Password
     *
     * @var string
     */
    public $sandboxPassword;

    /**
     * Sandbox Device ID
     *
     * @var string
     */
    public $sandboxDeviceId;

    /**
     * Sandbox TSEP Device ID
     *
     * @var string
     */
    public $sandboxTsepDeviceId;

    /**
     * Sandbox Transaction Key
     *
     * @var string
     */
    public $sandboxTransactionKey;

    /**
     * Should live payments be accepted
     *
     * @var bool
     */
    public $isProduction;

    /**
     * Integration's Developer ID
     *
     * @var string
     */
    public $developerId = '003226G001';

    public function getFirstLineSupportEmail()
    {
        return '';
    }

    public function getFrontendGatewayOptions()
    {
        $deviceId = $this->getCredentialSetting('tsepDeviceId');
        
        // Validate credentials are configured
        if (empty($deviceId) || empty($this->getCredentialSetting('transactionKey'))) {
            throw new ApiException('Transit gateway credentials are not configured. Please configure the gateway in admin settings.');
        }
        
        return [
            'deviceId' => $deviceId,
            'manifest' => $this->createManifest(),
            'env' => $this->isProduction ? 'production' : 'sandbox',
        ];
    }

    public function getBackendGatewayOptions()
    {
        return [
            'merchantId' => $this->getCredentialSetting('merchantId'),
            'username' => $this->getCredentialSetting('userId'), // only needed to create transation key
            'password' => $this->getCredentialSetting('password'), // only needed to create transation key
            'transactionKey' => $this->getCredentialSetting('transactionKey'),
            'deviceId' => $this->getCredentialSetting('deviceId'), // For transaction processing
            'developerId' => $this->developerId, // provided during certification
            'environment' => $this->isProduction ? Environment::PRODUCTION : Environment::TEST,
        ];
    }

    /**
     * Creates a TransIT transaction key
     *
     * @return string
     */
    public function createTransactionKey()
    {
        $request = $this->prepareRequest(Requests\TransactionType::CREATE_TRANSACTION_KEY, new Order());
        $response = $this->submitRequest($request);

        // TODO: update Transaction type to declare transactionKey property
        // @phpstan-ignore-next-line
        return $response->transactionKey;
    }

    /**
     * Creates a TransIT manifest string
     * Uses tsepDeviceId which must match the frontend deviceId for authentication
     *
     * @return string
     * @throws ApiException
     */
    public function createManifest()
    {
        try {
            // Create manifest using tsepDeviceId (must match frontend)
            // This follows Magento's pattern of using separate config for manifest
            $request = $this->prepareRequest(
                Requests\TransactionType::CREATE_MANIFEST,
                new Order(),
                ['deviceId' => $this->getCredentialSetting('tsepDeviceId')] // Override deviceId for manifest
            );
            $manifest = $this->submitRequest($request);

            if (!is_string($manifest)) {
                throw new ApiException('Unexpected transaction response');
            }

            return $manifest;
        } catch (\Exception $e) {
            throw new ApiException('Failed to create Transit manifest: ' . $e->getMessage());
        }
    }

    public function getGatewayFormFields()
    {
        return [
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
            // Sandbox fields
            $this->id . '_sandboxMerchantId' => [
                'title' => $this->translator->trans('Sandbox Merchant ID', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'class' => 'required sandbox-toggle',
                'default' => '',
            ],
            $this->id . '_sandboxUserId' => [
                'title' => $this->translator->trans('Sandbox User ID', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'class' => 'required sandbox-toggle',
                'description' => $this->translator->trans(
                    'Only needed to create transaction key.',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => '',
            ],
            $this->id . '_sandboxPassword' => [
                'title' => $this->translator->trans('Sandbox Password', [], 'Modules.Globalpayments.Admin'),
                'type' => 'password',
                'class' => 'required sandbox-toggle',
                'description' => $this->translator->trans(
                    'Only needed to create transaction key.',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => '',
            ],
            $this->id . '_sandboxDeviceId' => [
                'title' => $this->translator->trans('Sandbox Device ID', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'class' => 'required sandbox-toggle',
                'default' => '',
            ],
            $this->id . '_sandboxTsepDeviceId' => [
                'title' => $this->translator->trans('Sandbox TSEP Device ID', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'class' => 'required sandbox-toggle',
                'description' => $this->translator->trans(
                    'Device ID for TSEP entity specifically.',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => '',
            ],
            $this->id . '_sandboxTransactionKey' => [
                'title' => $this->translator->trans('Sandbox Transaction Key', [], 'Modules.Globalpayments.Admin'),
                'type' => 'password',
                'class' => 'required sandbox-toggle',
                'default' => '',
            ],
            // Live fields
            $this->id . '_merchantId' => [
                'title' => $this->translator->trans('Live Merchant ID', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'class' => 'required live-toggle',
                'default' => '',
            ],
            $this->id . '_userId' => [
                'title' => $this->translator->trans('Live User ID', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'class' => 'required live-toggle',
                'description' => $this->translator->trans(
                    'Only needed to create transaction key.',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => '',
            ],
            $this->id . '_password' => [
                'title' => $this->translator->trans('Live Password', [], 'Modules.Globalpayments.Admin'),
                'type' => 'password',
                'class' => 'required live-toggle',
                'description' => $this->translator->trans(
                    'Only needed to create transaction key.',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => '',
            ],
            $this->id . '_deviceId' => [
                'title' => $this->translator->trans('Live Device ID', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'class' => 'required live-toggle',
                'default' => '',
            ],
            $this->id . '_tsepDeviceId' => [
                'title' => $this->translator->trans('Live TSEP Device ID', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'class' => 'required live-toggle',
                'description' => $this->translator->trans(
                    'Device ID for TSEP entity specifically.',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => '',
            ],
            $this->id . '_transactionKey' => [
                'title' => $this->translator->trans('Live Transaction Key', [], 'Modules.Globalpayments.Admin'),
                'type' => 'password',
                'class' => 'required live-toggle',
                'default' => '',
            ],
        ];
    }

    /**
     * Validate admin settings for TransIT gateway
     *
     * @return array
     */
    public function validateAdminSettings(): array
    {
        $errors = [];

        // Skip validation if gateway is not enabled
        if (!\Tools::getValue($this->id . '_enabled')) {
            return $errors;
        }

        if (\Tools::getValue($this->id . '_isProduction')) {
            // Live Mode is ON - validate Live credentials
            $liveFieldsEmpty = empty(\Tools::getValue($this->id . '_merchantId'))
                || empty(\Tools::getValue($this->id . '_userId'))
                || empty(\Tools::getValue($this->id . '_password'))
                || empty(\Tools::getValue($this->id . '_deviceId'))
                || empty(\Tools::getValue($this->id . '_tsepDeviceId'))
                || empty(\Tools::getValue($this->id . '_transactionKey'));

            if ($liveFieldsEmpty) {
                $errors[] = $this->translator->trans(
                    'Please provide Live Credentials.',
                    [],
                    'Modules.Globalpayments.Admin'
                );
            }
        } else {
            // Live Mode is OFF - validate Sandbox credentials
            $sandboxFieldsEmpty = empty(\Tools::getValue($this->id . '_sandboxMerchantId'))
                || empty(\Tools::getValue($this->id . '_sandboxUserId'))
                || empty(\Tools::getValue($this->id . '_sandboxPassword'))
                || empty(\Tools::getValue($this->id . '_sandboxDeviceId'))
                || empty(\Tools::getValue($this->id . '_sandboxTsepDeviceId'))
                || empty(\Tools::getValue($this->id . '_sandboxTransactionKey'));

            if ($sandboxFieldsEmpty) {
                $errors[] = $this->translator->trans(
                    'Please provide Sandbox Credentials.',
                    [],
                    'Modules.Globalpayments.Admin'
                );
            }
        }

        return $errors;
    }

    /**
     * Get the Transit-specific JS library URL
     * Transit uses v1 endpoint instead of versioned endpoint
     *
     * @return string
     */
    public function getTransitJsLibUrl(): string
    {
        return 'https://js.globalpay.com/v1/globalpayments.js';
    }

    /**
     * Load the checkout scripts for Transit gateway.
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

        // Load Transit-specific JS library (v1 endpoint)
        $context->controller->registerJavascript(
            'globalpayments-transit-lib',
            $this->getTransitJsLibUrl(),
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
                'globalpayments_transit_params' => $this->getPaymentFieldsParams(),
                'globalpayments_transit_threedsecure_params' => [
                    'threedsecure' => [],
                ],
            ]
        );
    }

    /**
     * Get the payment options for Transit gateway.
     * Uses parent implementation which already handles Transit gateway.
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
     * Handle online refund requests for TransIT gateway.
     *
     * TransIT gateway does not support transaction detail reporting API
     * (which GP-API uses at /ucp/transactions/{id}), so we override the parent
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
        // TransIT doesn't support getTransactionDetails() like GP-API does,
        // so we directly process as a refund without checking if transaction is active.
        // For TransIT, refunds work on settled transactions.
        $request = $this->prepareRequest(TransactionType::REFUND, $order);
        $response = $this->submitRequest($request);

        if (!$response instanceof Transaction) {
            throw new ApiException(sprintf(
                'Unexpected TransIT refund response: expected %s, got %s',
                Transaction::class,
                is_object($response) ? get_class($response) : gettype($response)
            ));
        }

        $this->handleResponse($request, $response);

        return $response;
    }
}
