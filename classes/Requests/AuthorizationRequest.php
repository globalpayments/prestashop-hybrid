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

if (!defined('_PS_VERSION_')) {
    exit;
}

class AuthorizationRequest extends AbstractRequest
{
    public function getTransactionType()
    {
        return TransactionType::AUTHORIZE;
    }

    /**
     * @return string[]
     */
    public function getArgumentList()
    {
        return [
            RequestArg::AMOUNT,
            RequestArg::CURRENCY,
            RequestArg::CARD_DATA,
            RequestArg::DW_TOKEN,
            RequestArg::DYNAMIC_DESCRIPTOR,
            RequestArg::MOBILE_TYPE,
            RequestArg::TXN_MODIFIER,
        ];
    }
}
