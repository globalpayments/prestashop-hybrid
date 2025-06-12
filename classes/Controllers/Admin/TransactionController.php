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

namespace GlobalPayments\PaymentGatewayProvider\Controllers\Admin;

use GlobalPayments\Api\Entities\Reporting\TransactionSummary;
use GlobalPayments\PaymentGatewayProvider\Gateways\GpApiGateway;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

if (!defined('_PS_VERSION_')) {
    exit;
}

class TransactionController extends FrameworkBundleAdminController
{
    /**
     * Get transaction details.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function getTransactionDetailsAction(Request $request)
    {
        $response = new JsonResponse();
        $transactionId = $request->get('id');
        $gateway = new GpApiGateway();

        try {
            $transactionDetails = $gateway->getTransactionDetailsByTxnId($transactionId);
            $response->setData($this->processTransactionDetails($transactionDetails));
        } catch (\Exception $e) {
            $response->setStatusCode(400);
            $response->setData(
                [
                    'message' => $e->getMessage(),
                ]
            );
        }

        return $response;
    }

    /**
     * Process the transaction details before sending them to the client.
     *
     * @param TransactionSummary $transactionSummary
     *
     * @return array
     */
    private function processTransactionDetails($transactionSummary)
    {
        $result = [];
        if (!empty($transactionSummary->transactionId)) {
            $result[] = [
                'label' => $this->trans('Transaction ID', 'Modules.Globalpayments.Admin'),
                'value' => $transactionSummary->transactionId,
            ];
        }
        if (!empty($transactionSummary->transactionStatus)) {
            $result[] = [
                'label' => $this->trans('Transaction Status', 'Modules.Globalpayments.Admin'),
                'value' => $transactionSummary->transactionStatus,
            ];
        }
        if (!empty($transactionSummary->transactionType)) {
            $result[] = [
                'label' => $this->trans('Transaction Type', 'Modules.Globalpayments.Admin'),
                'value' => $transactionSummary->transactionType,
            ];
        }
        if (!empty($transactionSummary->amount)) {
            $result[] = [
                'label' => $this->trans('Amount', 'Modules.Globalpayments.Admin'),
                'value' => $transactionSummary->amount,
            ];
        }
        if (!empty($transactionSummary->currency)) {
            $result[] = [
                'label' => $this->trans('Currency', 'Modules.Globalpayments.Admin'),
                'value' => $transactionSummary->currency,
            ];
        }
        if (!empty($transactionSummary->bnplResponse->providerName)) {
            $result[] = [
                'label' => $this->trans('BNPL Provider', 'Modules.Globalpayments.Admin'),
                'value' => $transactionSummary->bnplResponse->providerName,
            ];
        }
        if (!empty($transactionSummary->paymentType)) {
            $result[] = [
                'label' => $this->trans('Payment Type', 'Modules.Globalpayments.Admin'),
                'value' => $transactionSummary->paymentType,
            ];
        }
        if (!empty($transactionSummary->alternativePaymentResponse->providerName)) {
            $result[] = [
                'label' => $this->trans('Provider Name', 'Modules.Globalpayments.Admin'),
                'value' => strtoupper($transactionSummary->alternativePaymentResponse->providerName),
            ];
        }

        return $result;
    }
}
