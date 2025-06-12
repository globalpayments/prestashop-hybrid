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

namespace GlobalPayments\PaymentGatewayProvider\Clients;

use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\PaymentGatewayProvider\Requests\RequestInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

interface ClientInterface
{
    /**
     * Sets request object for gateway request. Triggers creation of SDK
     * compatible objects from request data.
     *
     * @param RequestInterface $request
     *
     * @return ClientInterface
     */
    public function setRequest(RequestInterface $request);

    /**
     * Executes desired transaction with gathered data
     *
     * @return Transaction
     */
    public function execute();
}
