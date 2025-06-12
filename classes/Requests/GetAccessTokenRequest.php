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

class GetAccessTokenRequest extends AbstractRequest
{
    public function __construct(Order $order, $config)
    {
        parent::__construct($order, $config);
        $this->data[RequestArg::SERVICES_CONFIG]['permissions'] = [
            'PMT_POST_Create_Single',
        ];
    }

    public function getTransactionType()
    {
        return TransactionType::GET_ACCESS_TOKEN;
    }

    /**
     * @return string[]
     */
    public function getArgumentList()
    {
        return [];
    }
}
