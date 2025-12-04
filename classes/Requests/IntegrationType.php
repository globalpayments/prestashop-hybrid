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
 * @copyright Since 2025 GlobalPayments
 * @license   LICENSE
 */

namespace GlobalPayments\PaymentGatewayProvider\Requests;

if (!defined('_PS_VERSION_')) {
    exit;
}

abstract class IntegrationType
{
    public const DROP_IN_UI = 'drop in ui';
    public const HOSTED_PAYMENT_PAGE = 'hosted payment page';
}
