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
use GlobalPayments\PaymentGatewayProvider\Platform\TransactionHistory;

/**
 * @param $module
 *
 * @return bool
 */
function upgrade_module_1_6_4($module)
{
    $orderStateInstaller = new OrderStateInstaller();
    if (!$orderStateInstaller->update()) {
        return false;
    }

    try {
        (new TransactionHistory())->updateTable();
        $orderStateInstaller->removeVerifyState();
    } catch (Exception $e) {
    }

    return true;
}
