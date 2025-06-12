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

use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\PaymentGatewayProvider\Platform\Utils;
use GlobalPayments\PaymentGatewayProvider\Requests\RequestArg;
use GlobalPayments\PaymentGatewayProvider\Requests\TransactionType;

if (!defined('_PS_VERSION_')) {
    exit;
}

class InvalidCardHandler extends AbstractHandler
{
    protected $acceptedTransactionTypes = [
        TransactionType::APM_AUTHORIZATION,
        TransactionType::AUTHORIZE,
        TransactionType::BNPL_AUTHORIZATION,
        TransactionType::OB_AUTHORIZATION,
        TransactionType::DW_AUTHORIZATION,
        TransactionType::SALE,
        TransactionType::VERIFY,
    ];

    public function handle()
    {
        $txnType = $this->request->getArgument(RequestArg::TXN_TYPE);
        if (!in_array($txnType, $this->acceptedTransactionTypes, true)) {
            return false;
        }

        $utils = new Utils();
        throw new ApiException($utils->mapResponseCodeToFriendlyMessage($this->response->responseCode));
    }
}
