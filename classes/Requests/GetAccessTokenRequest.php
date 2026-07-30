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
use GlobalPayments\PaymentGatewayProvider\Requests\IntegrationType;

if (!defined('_PS_VERSION_')) {
    exit;
}

class GetAccessTokenRequest extends AbstractRequest
{
    public function __construct(Order $order, $config)
    {
        // to check request is from Admin or User(frontend)
        parent::__construct($order, $config);
        
        // If request is from admin (credentials check), use empty permissions to retrieve all accounts
        if (!empty($_POST)) {
            $this->data[RequestArg::SERVICES_CONFIG]['permissions'] = [''];
        } else {
            // For frontend payment flow, set specific permissions
            $permissions = array(
                'PMT_POST_Create_Single',
            );

            $country = $config['country'] ?? null;
            $currency = $config['currency'] ?? null;
            $isMxCountry = is_string($country) && strtoupper($country) === 'MX';
            $isMxnCurrency = is_string($currency) && strtoupper($currency) === 'MXN';
            $isMxMxn = $isMxCountry && $isMxnCurrency;

            // Add installments-related permissions if installments is enabled or MX/MXN is used
            if ($isMxMxn || (!empty($config['enableInstallments']) && $config['enableInstallments'] === true)) {
                $permissions = array_merge(
                    $permissions,
                    array('INS_POST_Query', 'BIN_GET_Details', 'PMT_POST_Create')
                );
            }

            // Add Visa installments-related permissions if Visa installments is enabled
            if (
                \GlobalPayments\PaymentGatewayProvider\Platform\Utils::isVisaInstallmentsSupported($country, $currency)
                && !empty($config['enableVisaInstallments'])
                && $config['enableVisaInstallments'] === true
            ) {
            $permissions =  array_merge(
                    $permissions,
                    ['PMT_POST_Create', 'PMT_POST_Create_Single', 'INS_POST_Query']
                );
            }    

            if (
                !empty($config['dcc'])
                && $config['dcc'] === 1
                && ($config['integrationMethod'] ?? null) === IntegrationType::HOSTED_PAYMENT_PAGE
            ) {
                $permissions = array_merge(
                    $permissions,
                    ['CCS_POST_DCC', 'PMT_POST_Create']
                );
            }

            $this->data[RequestArg::SERVICES_CONFIG]['permissions'] = array_values(array_unique($permissions));
        }
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
