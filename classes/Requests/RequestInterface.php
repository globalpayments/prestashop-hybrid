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

interface RequestInterface
{
    /**
     * @param Order $order
     * @param array<string,mixed> $config
     */
    public function __construct(Order $order, $config);

    /**
     * Gets transaction type for the request
     *
     * @return string
     */
    public function getTransactionType();

    /**
     * Gets a specific request argument by name
     *
     * @param string $key
     *
     * @return mixed|null
     */
    public function getArgument($key);

    /**
     * Sets request arguments
     *
     * @param array<string,string> $data
     *
     * @return void
     */
    public function setArguments(array $data);

    /**
     * Gets request specific args
     *
     * @return string[]
     */
    public function getArguments();

    /**
     * Gets list of argument names
     *
     * @return string[]
     */
    public function getArgumentList();

    /**
     * Gets default request argument names
     *
     * @return string[]
     */
    public function getDefaultArgumentList();
}
