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

namespace GlobalPayments\PaymentGatewayProvider\Controllers\AsyncPaymentMethod;

use GlobalPayments\Api\Entities\Reporting\TransactionSummary;
use GlobalPayments\Api\Utils\GenerationUtils;
use GlobalPayments\PaymentGatewayProvider\Gateways\AbstractGateway;
use GlobalPayments\PaymentGatewayProvider\Gateways\GpApiGateway;
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\OrderStateHelper;
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\QueryParamsHelper;
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\RequestHelper;
use GlobalPayments\PaymentGatewayProvider\Platform\TransactionHistory;
use GlobalPayments\PaymentGatewayProvider\Platform\TransactionManagement;
use GlobalPayments\PaymentGatewayProvider\Platform\Utils;
use PrestaShopBundle\Translation\TranslatorComponent as Translator;

if (!defined('_PS_VERSION_')) {
    exit;
}

class AbstractUrl extends \ModuleFrontController
{
    /**
     * @var AbstractGateway
     */
    protected $gateway;

    /**
     * @var OrderStateHelper
     */
    protected $orderStateHelper;

    /**
     * @var QueryParamsHelper
     */
    protected $queryParamsHelper;

    /**
     * @var RequestHelper
     */
    protected $requestHelper;

    /**
     * @var TransactionHistory
     */
    protected $transactionHistory;

    /**
     * @var TransactionManagement
     */
    protected $transactionManagement;

    /**
     * @var Translator
     */
    protected $translator;

    /**
     * @var Utils
     */
    protected $utils;

    /**
     * GlobalPaymentsAbstractUrlModuleFrontController constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->gateway = new GpApiGateway();
        $this->orderStateHelper = new OrderStateHelper();
        $this->queryParamsHelper = new QueryParamsHelper();
        $this->requestHelper = new RequestHelper();
        $this->transactionHistory = new TransactionHistory();
        $this->transactionManagement = new TransactionManagement($this->module);
        $this->translator = $this->module->getTranslator();
        $this->utils = new Utils();
    }

    /**
     * Get Magento order associated with the order ID from Transaction Summary.
     *
     * @param TransactionSummary $gatewayResponse
     *
     * @return \Order
     *
     * @throws \LogicException
     */
    public function getOrder($gatewayResponse)
    {
        $orderId = $gatewayResponse->orderId;
        $order = new \Order($orderId);
        if (!\Validate::isLoadedObject($order)) {
            $message = $this->translator->trans(
                'Order ID: %ord_id%. Order not found',
                ['%ord_id%' => $orderId],
                'Modules.Globalpayments.Logs'
            );
            throw new \LogicException($message);
        }

        $orderPayment = $order->getOrderPayments()[0];
        $gatewayTransactionId = $gatewayResponse->transactionId;
        $orderTransactionId = $orderPayment->transaction_id;
        if ($gatewayTransactionId !== $orderTransactionId) {
            $message = $this->translator->trans(
                'Order ID: %ord_id%. Transaction ID changed. Expected %gateway_trn_id% but found %order_trn_id%.',
                [
                    '%ord_id%' => $orderId,
                    '%gateway_trn_id%' => $gatewayTransactionId,
                    '%order_trn_id%' => $orderTransactionId,
                ],
                'Modules.Globalpayments.Logs'
            );
            throw new \LogicException($message);
        }

        return $order;
    }

    /**
     * Cancel a given order.
     *
     * @param int $orderId
     * @param float $amount
     * @param string $currency
     * @param string $transactionId
     * @param string $message
     *
     * @return void
     */
    public function cancelOrder($orderId, $amount, $currency, $transactionId, $transactionType)
    {
        $this->orderStateHelper->changeOrderStateToCancelled($orderId);
        $this->transactionManagement->createTransaction(
            $orderId,
            $amount,
            $currency,
            $transactionId,
            $transactionType
        );
    }

    /**
     * Validate the BNPL request message by checking the signature.
     *
     * @return bool
     *
     * @throws \LogicException
     */
    public function validateRequest()
    {
        $request = $this->requestHelper->getRequest();
        $requestMethod = $request->getMethod();

        switch ($requestMethod) {
            case 'GET':
                $xgpSignature = $request->getParam('X-GP-Signature');
                $params = $request->getParams();
                $toHash = http_build_query($this->queryParamsHelper->buildQueryParams($params));

                break;
            case 'POST':
                $xgpSignature = $request->getHeader('X-Gp-Signature') ?? $request->getHeader('X-GP-Signature');
                $toHash = $request->getParam('rawContent');

                break;
            default:
                $message = $this->translator->trans(
                    'This request method is not supported.',
                    [],
                    'Modules.Globalpayments.Logs'
                );
                throw new \LogicException($message);
        }

        $appKey = $this->gateway->getCredentialSetting('appKey');
        $genSignature = GenerationUtils::generateXGPSignature($toHash, $appKey);
        if ($xgpSignature !== $genSignature) {
            $message = $this->translator->trans(
                'Invalid request signature.',
                [],
                'Modules.Globalpayments.Logs'
            );
            throw new \LogicException($message);
        }

        return true;
    }
}
