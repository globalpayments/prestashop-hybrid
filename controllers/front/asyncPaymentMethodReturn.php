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

use GlobalPayments\Api\Entities\Enums\PaymentMethodType;
use GlobalPayments\Api\Entities\Enums\TransactionStatus;
use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\PaymentGatewayProvider\Controllers\AsyncPaymentMethod\AbstractUrl;
use GlobalPayments\PaymentGatewayProvider\PaymentMethodFactory;
use GlobalPayments\PaymentGatewayProvider\PaymentMethods\AbstractPaymentMethod;
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\CheckoutHelper;
use GlobalPayments\PaymentGatewayProvider\Platform\OrderAdditionalInfo;
use GlobalPayments\PaymentGatewayProvider\Requests\TransactionType;

if (!defined('_PS_VERSION_')) {
    exit;
}

class GlobalPaymentsAsyncPaymentMethodReturnModuleFrontController extends AbstractUrl
{
    /**
     * {@inheritdoc}
     */
    public function postProcess()
    {
        $request = $this->requestHelper->getRequest();
        $checkoutHelper = new CheckoutHelper($this->module, $this->context->cart);
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

            switch ($gatewayResponse->transactionStatus) {
                case TransactionStatus::INITIATED:
                case TransactionStatus::PREAUTHORIZED:
                case TransactionStatus::CAPTURED:
                    $checkoutHelper->clearCart();
                    $checkoutHelper->redirectToSuccessPage($order->id);

                    break;
                case TransactionStatus::PENDING:
                    $paymentMethod = $paymentMethodFactory->create($paymentMethodId);
                    if ($paymentMethod->paymentAction === TransactionType::SALE) {
                        $this->orderStateHelper->changeOrderStateToPaymentAccepted($order->id);
                        $this->transactionManagement->createSaleTransaction(
                            $order->id,
                            $gatewayResponse->amount,
                            $currency,
                            $transactionId
                        );
                    } else {
                        $this->orderStateHelper->changeOrderStateToCaptureWaiting($order->id);
                        $this->transactionManagement->createAuthorizationTransaction(
                            $order->id,
                            $gatewayResponse->amount,
                            $currency,
                            $transactionId
                        );
                    }

                    $transaction = Transaction::fromId(
                        $transactionId,
                        null,
                        PaymentMethodType::APM
                    );
                    $transaction->alternativePaymentResponse = $gatewayResponse->alternativePaymentResponse;
                    $transaction->confirm()->execute();

                    $checkoutHelper->clearCart();
                    $checkoutHelper->redirectToSuccessPage($order->id);
                    break;
                case TransactionStatus::DECLINED:
                case 'FAILED':
                    $this->cancelOrder(
                        $order->id,
                        $gatewayResponse->amount,
                        $currency,
                        $transactionId,
                        TransactionType::CANCEL
                    );
                    $checkoutHelper->redirectToCartPage();

                    break;
                default:
                    $message = $this->translator->trans(
                        'Order ID: %ord_id%. Unexpected transaction status on returnUrl: %trn_status%',
                        ['%ord_id%' => $gatewayResponse->orderId, '%trn_status%' => $gatewayResponse->transactionStatus],
                        'Modules.Globalpayments.Logs'
                    );
                    throw new LogicException($message);
            }
        } catch (Exception $e) {
            $message = $this->translator->trans(
                'Error completing order return. %message%',
                ['%message%' => $e->getMessage()],
                'Modules.Globalpayments.Logs'
            );
            PrestaShopLogger::addLog($message, PrestaShopLogger::LOG_SEVERITY_LEVEL_WARNING, null, 'GlobalPayments');

            $customerMessage = $this->translator->trans(
                'Thank you. Your order has been received, but we have encountered an issue when redirecting back.
                Please contact us for assistance.',
                [],
                'Modules.Globalpayments.Shop'
            );
            $this->context->cookie->__set('globalpayments_payment_error', $customerMessage);

            $order = $order ?? null;
            $orderId = Validate::isLoadedObject($order) ? $order->id : 0;
            $checkoutHelper->clearCart();
            $checkoutHelper->redirectToSuccessPage($orderId);
        }
    }
}
