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
if (!defined('_PS_VERSION_')) {
    exit;
}

use GlobalPayments\PaymentGatewayProvider\Platform\OrderStateInstaller;

/**
 * @param $module
 *
 * @return bool
 */
function upgrade_module_1_7_8($module)
{
    $orderStateInstaller = new OrderStateInstaller();
    if (!$orderStateInstaller->update()) {
        return false;
    }

    return true;
}
