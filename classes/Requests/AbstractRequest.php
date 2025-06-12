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

namespace GlobalPayments\PaymentGatewayProvider\Requests;

use GlobalPayments\PaymentGatewayProvider\Data\Order;

if (!defined('_PS_VERSION_')) {
    exit;
}

abstract class AbstractRequest implements RequestInterface
{
    /**
     * Request data
     *
     * @var array<string,mixed>
     */
    protected $data = [];

    /**
     * @param Order $order
     * @param array<string,mixed> $config
     */
    public function __construct(Order $order, $config)
    {
        $this->data = $order->asArray();
        $this->data[RequestArg::SERVICES_CONFIG] = $config;
    }

    public function getArgument($key)
    {
        return $this->data[$key] ?: null;
    }

    public function getArguments()
    {
        return $this->data;
    }

    public function setArguments(array $data)
    {
        if (!empty($this->data)) {
            $this->data = array_merge($this->data, $data);
        } else {
            $this->data = $data;
        }
    }

    /**
     * @return string[]
     */
    public function getDefaultArgumentList()
    {
        return [
            RequestArg::SERVICES_CONFIG,
            RequestArg::TXN_TYPE,
            RequestArg::BILLING_ADDRESS,
            RequestArg::CARD_HOLDER_NAME,
        ];
    }
}
