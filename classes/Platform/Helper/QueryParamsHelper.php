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

namespace GlobalPayments\PaymentGatewayProvider\Platform\Helper;

if (!defined('_PS_VERSION_')) {
    exit;
}

class QueryParamsHelper
{
    /**
     * Build the query params that are used for hashing.
     *
     * @param array $params
     *
     * @return array
     */
    public function buildQueryParams($params)
    {
        if (!isset($params['session_token'])) {
            return $this->buildDefaultQueryParams($params);
        }

        return $this->buildPaypalQueryParams($params);
    }

    /**
     * Build the query params specific to the BNPL/OB methods.
     *
     * @param array $params
     *
     * @return array
     */
    private function buildDefaultQueryParams($params)
    {
        return [
            'id' => $params['id'],
            'payer_reference' => $params['payer_reference'],
            'action_type' => $params['action_type'],
            'action_id' => $params['action_id'],
        ];
    }

    /**
     * Build the query params specific to the PayPal method.
     *
     * @param array $params
     *
     * @return array
     */
    private function buildPaypalQueryParams($params)
    {
        return [
            'id' => $params['id'] ?? '',
            'session_token' => $params['session_token'] ?? '',
            'payer_reference' => $params['payer_reference'] ?? '',
            'pasref' => $params['pasref'] ?? '',
            'action_type' => $params['action_type'] ?? '',
            'action_id' => $params['action_id'] ?? '',
        ];
    }
}
