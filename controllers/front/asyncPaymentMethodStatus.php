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

use GlobalPayments\Api\Entities\Enums\TransactionStatus;
use GlobalPayments\PaymentGatewayProvider\Controllers\AsyncPaymentMethod\AbstractUrl;
use GlobalPayments\PaymentGatewayProvider\PaymentMethodFactory;
use GlobalPayments\PaymentGatewayProvider\PaymentMethods\AbstractPaymentMethod;
use GlobalPayments\PaymentGatewayProvider\Platform\OrderAdditionalInfo;
use GlobalPayments\PaymentGatewayProvider\Requests\TransactionType;

if (!defined('_PS_VERSION_')) {
    exit;
}

class GlobalPaymentsAsyncPaymentMethodStatusModuleFrontController extends AbstractUrl
{
    /**
     * {@inheritdoc}
     */
    public function postProcess()
    {
        $request = $this->requestHelper->getRequest();
        $orderAdditionalInfo = new OrderAdditionalInfo();
        $paymentMethodFactory = new PaymentMethodFactory();

        try {
            $this->validateRequest();

            $transactionId = $request->getParam('id');
            $gatewayResponse = $this->gateway->getTransactionDetailsByTxnId($transactionId);
            $order = $this->getOrder($gatewayResponse);
            $currency = $this->utils->getCurrencyIsoCode($order->id_currency);

            $paymentMethodId = $orderAdditionalInfo->getAdditionalInfo(
                $order->id,
                AbstractPaymentMethod::PAYMENT_METHOD_ID
            );
            if (!$paymentMethodId) {
                $message = $this->translator->trans(
                    'Order ID: %ord_od%. Missing payment method id.',
                    ['%ord_id%' => $order->id],
                    'Modules.Globalpayments.Logs'
                );
                throw new LogicException($message);
            }
            // Endpoint has already been called for this order.
            if ($this->transactionHistory->hasPaymentTransaction($order->id)) {
                exit;
            }

            switch ($request->getParam('status')) {
                case TransactionStatus::PREAUTHORIZED:
                    $this->orderStateHelper->changeOrderStateToCaptureWaiting($order->id);
                    $this->transactionManagement->createAuthorizationTransaction(
                        $order->id,
                        $gatewayResponse->amount,
                        $currency,
                        $transactionId
                    );

                    $paymentMethod = $paymentMethodFactory->create($paymentMethodId);
                    if ($paymentMethod->paymentAction === TransactionType::SALE) {
                        $this->transactionManagement->processCapture($order, $gatewayResponse->amount);
                    }

                    break;
                case TransactionStatus::CAPTURED:
                    $this->orderStateHelper->changeOrderStateToPaymentAccepted($order->id);
                    $this->transactionManagement->createSaleTransaction(
                        $order->id,
                        $gatewayResponse->amount,
                        $currency,
                        $transactionId
                    );

                    break;
                case TransactionStatus::DECLINED:
                case 'FAILED':
                    // Cancel the order only if the status is 'Waiting for Global Payments Payment'
                    if ($this->transactionManagement->waitingForPayment($order)) {
                        $this->cancelOrder(
                            $order->id,
                            $gatewayResponse->amount,
                            $currency,
                            $transactionId,
                            TransactionType::CANCEL
                        );
                    }

                    break;
                default:
                    $message = $this->translator->trans(
                        'Order ID: %ord_id%. Unexpected transaction status on statusUrl: %trn_status%',
                        ['%ord_id%' => $gatewayResponse->orderId, '%trn_status%' => $request->getParam('status')],
                        'Modules.Globalpayments.Logs'
                    );
                    throw new LogicException($message);
            }

            exit;
        } catch (Exception $e) {
            $message = $this->translator->trans(
                'Error completing order status. %message%',
                ['%message%' => $e->getMessage()],
                'Modules.Globalpayments.Logs'
            );

            PrestaShopLogger::addLog($message, PrestaShopLogger::LOG_SEVERITY_LEVEL_WARNING, null, 'GlobalPayments');

            exit;
        }
    }
}
