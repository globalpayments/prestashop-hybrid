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

use GlobalPayments\PaymentGatewayProvider\Controllers\AsyncPaymentMethod\AbstractUrl;
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\CheckoutHelper;
use GlobalPayments\PaymentGatewayProvider\Requests\TransactionType;

if (!defined('_PS_VERSION_')) {
    exit;
}

class GlobalPaymentsAsyncPaymentMethodCancelModuleFrontController extends AbstractUrl
{
    /**
     * {@inheritdoc}
     */
    public function postProcess()
    {
        $request = $this->requestHelper->getRequest();
        $checkoutHelper = new CheckoutHelper($this->module, $this->context->cart);

        try {
            $this->validateRequest();

            $transactionId = $request->getParam('id');
            $gatewayResponse = $this->gateway->getTransactionDetailsByTxnId($transactionId);
            $order = $this->getOrder($gatewayResponse);
            $currency = $this->utils->getCurrencyIsoCode($order->id_currency);

            $this->cancelOrder(
                $order->id,
                $gatewayResponse->amount,
                $currency,
                $transactionId,
                TransactionType::CUSTOMER_CANCEL
            );
        } catch (Exception $e) {
            $message = $this->translator->trans(
                'Error completing order cancel. %message%',
                ['%message%' => $e->getMessage()],
                'Modules.Globalpayments.Logs'
            );

            PrestaShopLogger::addLog($message, PrestaShopLogger::LOG_SEVERITY_LEVEL_WARNING, null, 'GlobalPayments');
        }

        $checkoutHelper->redirectToCartPage();
    }
}
