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
 * @copyright Since 2025 GlobalPayments
 * @license   LICENSE
 */

declare(strict_types=1);

use GlobalPayments\PaymentGatewayProvider\Controllers\AsyncPaymentMethod\AbstractUrl;
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\HppHelper;
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\HppSignatureValidator;
use GlobalPayments\PaymentGatewayProvider\Platform\Utils\HppResponseParser;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * HPP Status Controller 
 *
 * This controller handles asynchronous status updates from GPAPI.
 */
class GlobalPaymentsHppStatusModuleFrontController extends AbstractUrl
{
    /**
     * @var HppHelper HPP business logic helper
     */
    protected HppHelper $hppHelper;

    /**
     * @var HppSignatureValidator Signature validation helper
     */
    protected HppSignatureValidator $signatureValidator;

    /**
     * @var HppResponseParser Extracts Data from the HPP response
     */
    protected HppResponseParser $hppResponseParser;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->hppHelper = new HppHelper();
        $this->signatureValidator = new HppSignatureValidator($this->gateway);
        $this->hppResponseParser = new HppResponseParser();
    }

    /**
     * Process HPP status webhook
     * 
     * @return Void calls other methods before returning void 
     */
    public function postProcess(): void
    {
        try {
            // Get signature from headers and raw POST data (webhook format)
            $headers = getallheaders();
            $gpSignature = $headers['X-GP-Signature'] ?? $headers['x-gp-signature'] ?? null;
            $rawInput = trim(file_get_contents('php://input'));


            // Validate the request
            if (!$this->signatureValidator->validateSignature($rawInput, $gpSignature)) {
                $this->logError('Invalid signature or missing data on status URL');
                $this->sendWebhookResponse(403, 'ERROR', 'Invalid signature');
                return;
            }

            // Parse gateway response
            $gatewayData = $this->hppResponseParser->parseGatewayData($rawInput);

            // Extract order information
            $orderId = $this->hppResponseParser->extractOrderId($gatewayData);

            if (empty($orderId)) {
                // Return 200 OK even if order not found
                $this->sendWebhookResponse(200, 'OK', 'Order not found');
                return;
            }

            // Load the order
            $order = $this->loadOrder($orderId);

            if ($order === null) {
                $this->sendWebhookResponse(200, 'OK', 'Invalid order');
                return;
            }

            // Check if order already processed
            if ($this->isOrderAlreadyProcessed($order)) {
                $this->logInfo('Order already processed, skipping', [
                    'order_id' => $order->id,
                    'current_state' => $order->getCurrentState(),
                ]);

                $this->sendWebhookResponse(200, 'OK', 'Already processed');

                return;
            }

            // Extract transaction details and process
            $transactionId = $this->hppResponseParser->extractTransactionId($gatewayData);
            $paymentStatus = $this->hppResponseParser->extractPaymentStatus($gatewayData);
            
            $this->processPaymentStatus($order, $transactionId, $paymentStatus, $gatewayData);

            // Return 200 OK to acknowledge webhook
            $this->sendWebhookResponse(200, 'OK', 'Processed successfully');
        } catch (\Exception $e) {
            $this->logError('HPP Status webhook error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            // Return 200 OK even on error (webhook best practice)
            $this->sendWebhookResponse(200, 'ERROR', 'Internal error');
        }
    }


    /**
     * Load order by ID
     *
     * @param int $orderId Order ID
     * @return \Order|null Loaded order or null if not found
     */
    private function loadOrder(int $orderId): ?\Order
    {
        $order = new \Order($orderId);

        if (!\Validate::isLoadedObject($order)) {
            $this->logError('Invalid order ID', ['order_id' => $orderId]);
            return null;
        }

        return $order;
    }

    /**
     * Check if order is already processed
     *
     * @param \Order $order Order to check
     * @return bool True if already processed
     */
    private function isOrderAlreadyProcessed(\Order $order): bool
    {
        $currentState = $order->getCurrentState();
        $processedStates = [
            (int)\Configuration::get('PS_OS_PAYMENT'),
            (int)\Configuration::get('PS_OS_CANCELED'),
            (int)\Configuration::get('GLOBALPAYMENTS_PAYMENT_DECLINED'),
        ];

        return in_array($currentState, $processedStates, true);
    }

    /**
     * Process payment status and take appropriate action
     *
     * @param \Order $order Order to process
     * @param string $transactionId Transaction ID
     * @param string $paymentStatus Payment status
     * @param array $gatewayData Gateway data
     * @return void
     */
    private function processPaymentStatus(
        \Order $order,
        string $transactionId,
        string $paymentStatus,
        array $gatewayData
    ): void {
        switch ($paymentStatus) {
            case 'INITIATED':
            case 'PREAUTHORIZED':
            case 'CAPTURED':
                $this->hppHelper->completePayment($order, $transactionId, $gatewayData, false);
                break;

            case 'PENDING':
                $this->handlePendingPayment($order);
                break;

            case 'DECLINED':
            case 'CANCELLED':
            case 'FAILED':
                $this->hppHelper->handleFailedPayment($order);
                break;

            default:
                $this->logWarning('Unknown payment status', [
                    'status' => $paymentStatus,
                    'order_id' => $order->id,
                ]);
        }
    }

    /**
     * Handle pending payment
     *
     * @param \Order $order Order to update
     * @return void
     */
    private function handlePendingPayment(\Order $order): void {
        $this->hppHelper->handlePendingPayment($order);
    }


    /**
     * Send webhook response
     *
     * @param int $httpCode HTTP status code
     * @param string $status Status message
     * @param string $message Detailed message
     * @return void
     */
    private function sendWebhookResponse(int $httpCode, string $status, string $message): void
    {
        header('Content-Type: application/json');
        http_response_code($httpCode);
        die(json_encode([
            'status' => $status,
            'message' => $message,
        ]));
    }

    /**
     * Log informational message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    private function logInfo(string $message, array $context = []): void
    {
        $this->log($message, \PrestaShopLogger::LOG_SEVERITY_LEVEL_INFORMATIVE, $context);
    }

    /**
     * Log warning message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    private function logWarning(string $message, array $context = []): void
    {
        $this->log($message, \PrestaShopLogger::LOG_SEVERITY_LEVEL_WARNING, $context);
    }

    /**
     * Log error message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    private function logError(string $message, array $context = []): void
    {
        $this->log($message, \PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR, $context);
    }

    /**
     * Log message
     *
     * @param string $message Log message
     * @param int $severity Log severity
     * @param array $context Additional context
     * @return void
     */
    private function log(string $message, int $severity, array $context = []): void
    {
        $logMessage = sprintf('HPP Status: %s', $message);

        if (!empty($context)) {
            $logMessage .= ' | ' . json_encode($context);
        }

        \PrestaShopLogger::addLog(
            $logMessage,
            $severity,
            null,
            'GlobalPayments'
        );
    }
}
