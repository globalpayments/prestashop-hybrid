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

namespace GlobalPayments\PaymentGatewayProvider\Platform\Admin\Order\View;

use GlobalPayments\PaymentGatewayProvider\Gateways\GatewayId;
use GlobalPayments\PaymentGatewayProvider\Platform\TransactionHistory;
use GlobalPayments\PaymentGatewayProvider\Platform\TransactionManagement;

if (!defined('_PS_VERSION_')) {
    exit;
}

class TransactionManagementTab
{
    /**
     * @var \Context
     */
    protected $context;

    /**
     * @var \GlobalPayments
     */
    protected $module;

    /**
     * @var \Order
     */
    protected $order;

    /**
     * @var TransactionHistory
     */
    protected $transactionHistory;

    /**
     * @var TransactionManagement
     */
    protected $transactionManagement;

    /**
     * DigitalWalletsAddress constructor.
     *
     * @param \GlobalPayments $module
     * @param \Order $order
     */
    public function __construct(
        \GlobalPayments $module,
        \Order $order
    ) {
        $this->module = $module;
        $this->order = $order;

        $this->transactionHistory = new TransactionHistory();
        $this->transactionManagement = new TransactionManagement($this->module);
        $this->context = $this->module->getContext();
    }

    /**
     * Get the template.
     *
     * @return string
     */
    public function getTemplate()
    {
        $this->assignDataToTemplate();

        return $this->module->display(
            $this->module->getPath(),
            '/views/templates/hook/admin_order.tpl'
        );
    }

    /**
     * Assign the smarty data for the template.
     *
     * @return void
     */
    private function assignDataToTemplate()
    {
        $currency = new \Currency($this->order->id_currency);
        $linkParams = ['vieworder' => 1, 'id_order' => (int) $this->order->id];
        $link = $this->context->link->getAdminLink('AdminOrders', true, [], $linkParams);
        $payOrderAction = $this->context->link->getModuleLink($this->module->name, 'payForOrder', [], true);
        $orderPayment = \OrderPayment::getByOrderReference($this->order->reference)[0];
        $transactionId = $orderPayment->transaction_id;

        $paymentOptions = $this->module->hookPaymentOptions([
            'customerId' => $this->order->id_customer,
            'formAction' => $payOrderAction,
        ]);
        $ucpOptions = [];
        foreach ($paymentOptions as $paymentOption) {
            if ($paymentOption->getModuleName() === GatewayId::GP_UCP) {
                $ucpOptions[] = $paymentOption;
            }
        }

        $canCapture = $this->transactionManagement->canCapture($this->order);
        $waitingPayment = isset($this->module->getActivePaymentMethods()[GatewayId::GP_UCP])
            && $this->transactionManagement->waitingForPayment($this->order);

        $displayMgmTab = $canCapture || $waitingPayment;

        // Determine transaction history title based on payment method
        $transaction_history_title = $this->module->l('Global Payments Transaction Management History', 'admin_order');
        $paymentMethodName = '';
        if (!empty($orderPayment->payment_method)) {
            $paymentMethodName = strtoupper($orderPayment->payment_method);
        }
        if (in_array($paymentMethodName, ['BLIK', 'OB', 'PAYU', 'BANKSELECT'])) {
            $transaction_history_title = $this->module->l('Transaction Management History', 'admin_order');
        }

        $this->context->smarty->assign([
            'adminLink' => $link,
            'amount' => round($this->order->total_paid, 2),
            'canCapture' => $canCapture,
            'currency' => $currency->iso_code,
            'displayMgmTab' => $displayMgmTab,
            'getTransactionDetailsUrl' => $this->getTransactionDetailsUrl(),
            'hasTxnId' => $this->transactionManagement->hasTxnId($this->order),
            'orderId' => $this->order->id,
            'payOrderAction' => $payOrderAction,
            'transaction_history' => $this->transactionHistory->getHistory($this->order->id),
            'transactionId' => $transactionId,
            'ucpOptions' => $ucpOptions,
            'waitingPayment' => $waitingPayment,
            'transaction_history_title' => $transaction_history_title,
        ]);
    }

    /**
     * Get the URL for the Get Transaction Details endpoint.
     *
     * @return string
     */
    private function getTransactionDetailsUrl()
    {
        return $this->context->link::getUrlSmarty([
            'entity' => 'sf',
            'route' => 'globalpayments_get_transaction_details',
        ]);
    }
}
