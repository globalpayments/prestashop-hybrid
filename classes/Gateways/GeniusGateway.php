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
     * Merchant location's Merchant Name
     *
     * @var string
     */
    public $merchantName;

    /**
     * Merchant location's Site ID
     *
     * @var string
     */
    public $merchantSiteId;

    /**
     * Merchant location's Merchant Key
     *
     * @var string
     */
    public $merchantKey;

    /**
     * Merchant location's Web API Key
     *
     * @var string
     */
    public $webApiKey;

    /**
     * Should live payments be accepted
     *
     * @var bool
     */
    public $isProduction;

    public function getFirstLineSupportEmail()
    {
        return '';
    }

    public function getFrontendGatewayOptions()
    {
        return [
            'webApiKey' => $this->webApiKey,
            'env' => $this->isProduction ? 'production' : 'sandbox',
        ];
    }

    public function getBackendGatewayOptions()
    {
        return [
            'merchantName' => $this->merchantName,
            'merchantSiteId' => $this->merchantSiteId,
            'merchantKey' => $this->merchantKey,
            'environment' => $this->isProduction ? Environment::PRODUCTION : Environment::TEST,
        ];
    }

    public function getGatewayFormFields()
    {
        return [];
    }
}
