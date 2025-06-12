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

namespace GlobalPayments\PaymentGatewayProvider\Gateways;

if (!defined('_PS_VERSION_')) {
    exit;
}

abstract class GatewayId
{
    public const GP_UCP = 'globalpayments_ucp';
    public const HEARTLAND = 'globalpayments_heartland';
    public const GENIUS = 'globalpayments_genius';
    public const TRANSIT = 'globalpayments_transit';
}
