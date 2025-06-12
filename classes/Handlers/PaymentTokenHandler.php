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

use GlobalPayments\PaymentGatewayProvider\Data\PaymentTokenData;
use GlobalPayments\PaymentGatewayProvider\Requests\RequestArg;

if (!defined('_PS_VERSION_')) {
    exit;
}

class PaymentTokenHandler extends AbstractHandler
{
    public function handle()
    {
        if (!$this->request->getArgument(RequestArg::REQUEST_MULTI_USE_TOKEN) || !$this->response->token) {
            return;
        }

        (new PaymentTokenData($this->request->getArguments()))
            ->saveNewToken($this->response->token, $this->response->cardBrandTransactionId);
    }
}
