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

use GlobalPayments\PaymentGatewayProvider\Platform\OrderAdditionalInfo;

/**
 * @param $module
 *
 * @return bool
 */
function upgrade_module_1_4_0($module)
{
    /*
     * Add the default values for Click To Pay to the Configuration table.
     */
    $module->addDefaultValues();
    /*
     * Remove the Display Payment Top hook since we started using AJAX for checkout now.
     */
    $module->unregisterHook('displayPaymentTop');

    $orderAdditionalInfo = new OrderAdditionalInfo();
    if (!$orderAdditionalInfo->installTable()) {
        return false;
    }

    return true;
}
