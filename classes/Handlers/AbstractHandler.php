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

namespace GlobalPayments\PaymentGatewayProvider\Handlers;

use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\PaymentGatewayProvider\Requests\RequestInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

abstract class AbstractHandler implements HandlerInterface
{
    /**
     * Current request
     *
     * @var RequestInterface
     */
    protected $request;

    /**
     * Current response
     *
     * @var Transaction
     */
    protected $response;

    /**
     * Instantiates a new request
     *
     * @param RequestInterface $request
     * @param Transaction $response
     */
    public function __construct(RequestInterface $request, Transaction $response)
    {
        $this->request = $request;
        $this->response = $response;
    }
}
