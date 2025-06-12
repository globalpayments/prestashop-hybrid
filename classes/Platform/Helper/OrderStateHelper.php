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

use GlobalPayments\PaymentGatewayProvider\Platform\OrderStateInstaller;
use GlobalPayments\PaymentGatewayProvider\Requests\TransactionType;

if (!defined('_PS_VERSION_')) {
    exit;
}

class OrderStateHelper
{
    /**
     * Get the order state based on the payment action.
     *
     * @param $paymentAction
     * @return false|string
     */
    public function getOrderState($paymentAction)
    {
        switch ($paymentAction) {
            case TransactionType::SALE:
                return \Configuration::get('PS_OS_PAYMENT');
            case TransactionType::AUTHORIZE:
                return \Configuration::get(OrderStateInstaller::CAPTURE_WAITING);
            default:
                return \Configuration::get('PS_OS_PAYMENT');
        }
    }

    /**
     * Change the order state.
     *
     * @param $psOrderId
     * @param $employeeId
     * @param $orderState
     *
     * @return void
     *
     * @throws \PrestaShopException
     */
    public function changeOrderState($psOrderId, $employeeId, $orderState)
    {
        $orderHistory = new \OrderHistory();
        $orderHistory->id_order = $psOrderId;
        $orderHistory->id_employee = $employeeId;
        $orderHistory->changeIdOrderState(
            $orderState,
            $psOrderId,
            true
        );
        $orderHistory->save();
    }

    /**
     * Change the order state to 'Cancelled'.
     *
     * @param $psOrderId
     * @param $employeeId
     *
     * @return void
     *
     * @throws \PrestaShopException
     */
    public function changeOrderStateToCancelled($psOrderId, $employeeId = '')
    {
        $orderState = \Configuration::get('PS_OS_CANCELED');
        $this->changeOrderState($psOrderId, $employeeId, $orderState);
    }

    /**
     * Change the order state to 'Waiting for Global Payments capture'.
     *
     * @param $psOrderId
     * @param $employeeId
     *
     * @return void
     *
     * @throws \PrestaShopException
     */
    public function changeOrderStateToCaptureWaiting($psOrderId, $employeeId = '')
    {
        $orderState = $this->getOrderState(TransactionType::AUTHORIZE);
        $this->changeOrderState($psOrderId, $employeeId, $orderState);
    }

    /**
     * Change the order state to 'Payment accepted'.
     *
     * @param $psOrderId
     * @param $employeeId
     *
     * @return void
     *
     * @throws \PrestaShopException
     */
    public function changeOrderStateToPaymentAccepted($psOrderId, $employeeId = '')
    {
        $orderState = $this->getOrderState(TransactionType::SALE);
        $this->changeOrderState($psOrderId, $employeeId, $orderState);
    }
}
