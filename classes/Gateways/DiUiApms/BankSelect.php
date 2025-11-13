<?php
namespace GlobalPayments\PaymentGatewayProvider\Gateways\DiUiApms;
if (!defined('_PS_VERSION_')) { exit; }
use GlobalPayments\Api\Entities\Enums\{
    AlternativePaymentType,
    Environment,
    ServiceEndpoints
};
use GlobalPayments\Api\Entities\GpApi\AccessTokenInfo;
use GlobalPayments\Api\PaymentMethods\AlternativePaymentMethod;
use GlobalPayments\Api\ServiceConfigs\Gateways\GpApiConfig;
use GlobalPayments\Api\ServicesContainer;
use GlobalPayments\PaymentGatewayProvider\Gateways\GpApiGateway;

// GlobalPayments Helper classes
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\CheckoutHelper;
use GlobalPayments\PaymentGatewayProvider\Platform\{TransactionHistory, TransactionManagement, Utils};
use GlobalPayments\PaymentGatewayProvider\Requests\TransactionType;

/**
 * Helper class for DiUi/PayU transactions
 *
 * @package GlobalPayments\PaymentGatewayProvider\Gateways\DiUiApms
 */
class BankSelect
{
    public static function processOpenBankingSale(GpApiGateway $gateway, $orderId, $bank)
    {
        $order = new \Order($orderId);

        $settings = $gateway->getBackendGatewayOptions();

        $config = new GpApiConfig();
        $country = new \Country(\Configuration::get('PS_COUNTRY_DEFAULT'));
        $config->country = $country->iso_code;
        $config->appId = $settings["appId"];
        $config->appKey = $settings["appKey"];

        $config->serviceUrl = $settings["environment"] === Environment::PRODUCTION ?
            ServiceEndpoints::GP_API_PRODUCTION : ServiceEndpoints::GP_API_TEST;
        $config->channel = $settings["channel"];

        $accessTokenInfo = new AccessTokenInfo();

        $sandboxAccountName = null;
        if (isset($settings["accountName"])) {
            $sandboxAccountName = $settings["accountName"];
        }
        $accessTokenInfo->transactionProcessingAccountName = $sandboxAccountName;

        $config->accessTokenInfo = $accessTokenInfo;
        ServicesContainer::configureService($config);

        $paymentMethod = new AlternativePaymentMethod(AlternativePaymentType::OB);

        $address = new \Address($order->id_address_invoice);
        $customer = new \Customer($order->id_customer);
        $country = new \Country($address->id_country);

        $paymentMethod->descriptor = 'ORD' . $order->id;
        $paymentMethod->country = $country->iso_code;
        $paymentMethod->accountHolderName = $customer->firstname . ' ' . $customer->lastname;
        $paymentMethod->bank = $bank;
        $paymentMethod->returnUrl = \Context::getContext()->link->getModuleLink(
            'globalpayments',
            'redirect',
            ['action' => 'ob_redirect_handler', 'order_id' => $order->id]
        );
        $paymentMethod->statusUpdateUrl = \Context::getContext()->link->getModuleLink(
            'globalpayments',
            'webhook',
            ['action' => 'ob_status_handler']
        );
        // currently not supported by gateway
        $paymentMethod->cancelUrl = \Context::getContext()->link->getPageLink('order');

        $obGpResponse = null; // Initialize variable

        try {
            // Get order details
            $orderTotal = $order->total_paid_tax_incl;
            $currency = new \Currency($order->id_currency);
            $currencyCode = $currency->iso_code ?: 'PLN';

            $obGpResponse = $paymentMethod->charge($orderTotal)
                ->withClientTransactionId('PrestaShop_Order_' . $order->id)
                ->withCurrency($currencyCode)
                ->execute();
        } catch (\Exception $e) {
            // Return error response if payment fails
            return [
                'result' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        // Check if payment response is valid
        if (!$obGpResponse || !isset($obGpResponse->transactionId)) {
            return [
                'result' => 'error',
                'message' => 'Invalid payment response received',
            ];
        }

        // Update the existing order payment record (created during order generation)
        $orderPayments = \OrderPayment::getByOrderReference($order->reference);

        if (!empty($orderPayments)) {
            // Update existing payment record
            $orderPayment = $orderPayments[0];
            $orderPayment->payment_method = 'OB';
            $orderPayment->transaction_id = $obGpResponse->transactionId;
            $orderPayment->save();
        } else {
            // Create new payment record if none exists
            $orderPayment = new \OrderPayment();
            $orderPayment->order_reference = $order->reference;
            $orderPayment->id_currency = $order->id_currency;
            $orderPayment->amount = $order->total_paid_tax_incl;
            $orderPayment->payment_method = 'OB';
            $orderPayment->transaction_id = $obGpResponse->transactionId;
            $orderPayment->conversion_rate = 1;
            $orderPayment->date_add = date('Y-m-d H:i:s');
            $orderPayment->add();
        }

        // Set order state to generic 'Waiting for payment' (if available)
        $waitingPaymentStateId = null;
        // Try to find a generic 'Waiting for payment' state
        $states = \OrderState::getOrderStates((int)\Context::getContext()->language->id);
        foreach ($states as $state) {
            if (stripos($state['name'], 'Waiting for payment') !== false) {
                $waitingPaymentStateId = $state['id_order_state'];
                break;
            }
        }
        if ($waitingPaymentStateId) {
            $order->setCurrentState($waitingPaymentStateId);
        }

        // Use PrestaShop's built-in helpers for transaction management
        $module = \GlobalPayments::getModuleInstance();
        $transactionManagement = new TransactionManagement($module);

        // Create transaction record using proper PrestaShop helper
        $transactionManagement->createTransaction(
            $order->id,
            $order->total_paid_tax_incl,
            $currencyCode,
            $obGpResponse->transactionId,
            TransactionType::INITIATE_PAYMENT,
            1 // success
        );

        // Use PrestaShop's built-in logger instead of manual messages
        \PrestaShopLogger::addLog(
            sprintf(
                'Open Banking payment initiated for order %d. Amount: %s%s, Transaction ID: %s',
                $order->id,
                $currency->sign,
                $order->total_paid_tax_incl,
                $obGpResponse->transactionId
            ),
            1, // LOG_SEVERITY_LEVEL_INFORMATIVE
            null,
            'GlobalPayments'
        );

        return [
            'result' => 'success',
            'redirect' => $obGpResponse->alternativePaymentResponse->redirectUrl,
        ];
    }

    /**
     * Handle Open Banking payment status callback from payment gateway
     */
    public static function handleObStatusNotification()
    {
        Utils::validateSignature();
        
        \PrestaShopLogger::addLog('Open Banking status notification received', 1, null, 'GlobalPayments');

        // Get request data (could be GET or POST)
        $requestData = array_merge($_GET, $_POST);

        // Parse JSON body if present
        $rawInput = file_get_contents('php://input');
        if (!empty($rawInput)) {
            $jsonData = json_decode($rawInput, true);
            if ($jsonData) {
                $requestData = array_merge($requestData, $jsonData);
            }
        }

        \PrestaShopLogger::addLog(
            'Open Banking status notification data: ' . print_r($requestData, true),
            1,
            null,
            'GlobalPayments'
        );

        // Extract transaction details using the real callback structure
        $transactionId = self::extractTransactionId($requestData);
        $paymentStatus = self::extractPaymentStatus($requestData);
        $reference = self::extractReference($requestData);

        \PrestaShopLogger::addLog(
            'Open Banking status - Transaction ID: ' . $transactionId . 
            ', Status: ' . $paymentStatus . ', Reference: ' . $reference,
            1,
            null,
            'GlobalPayments'
        );

        $processed = false;

        // Try to find order by reference field (your callback: "reference": "202412191212769")
        if (!empty($reference)) {
            $orderId = self::extractOrderIdFromReference($reference);

            if ($orderId) {
                \PrestaShopLogger::addLog(
                    'Open Banking status - Extracted order ID: ' . $orderId . ' from reference: ' . $reference,
                    1,
                    null,
                    'GlobalPayments'
                );

                $order = new \Order($orderId);
                if (\Validate::isLoadedObject($order)) {
                    self::updateOrderStatusFromNotification(
                        $order, $paymentStatus, $transactionId, $requestData
                    );
                    $processed = true;
                } else {
                    \PrestaShopLogger::addLog(
                        'Open Banking webhook: Order not found with ID: ' . $orderId,
                        2,
                        null,
                        'GlobalPayments'
                    );
                }
            }
        }

        // Fallback: try to find by platforms array (your callback has platforms[0].order_id)
        if (!$processed && !empty($requestData['platforms'])) {
            foreach ($requestData['platforms'] as $platform) {
                if (isset($platform['order_id'])) {
                    $orderId = self::extractOrderIdFromPlatform($platform['order_id']);

                    if ($orderId) {
                        \PrestaShopLogger::addLog(
                            'Open Banking status - Extracted order ID from platform: ' . $orderId,
                            1,
                            null,
                            'GlobalPayments'
                        );

                        $order = new \Order($orderId);
                        if (\Validate::isLoadedObject($order)) {
                            self::updateOrderStatusFromNotification(
                                $order,
                                $paymentStatus,
                                $transactionId,
                                $requestData
                            );
                            $processed = true;
                            break;
                        }
                    }
                }
            }
        }

        // Legacy fallback: check for old format "PrestaShop_Order_X"
        if (!$processed && !empty($reference) && strpos($reference, 'PrestaShop_Order_') !== false) {
            $orderId = str_replace("PrestaShop_Order_", "", $reference);

            // Find the order in PrestaShop
            $order = new \Order($orderId);

            if (\Validate::isLoadedObject($order)) {
                // Get order payments to find matching transaction
                $orderPayments = \OrderPayment::getByOrderReference($order->reference);

                foreach ($orderPayments as $payment) {
                    if ($payment->transaction_id === $transactionId) {
                        self::updateOrderStatusFromNotification($order, $paymentStatus, $transactionId, $requestData);
                        $processed = true;
                        break;
                    }
                }
            }
        }

        // Send appropriate HTTP response
        if ($processed) {
            http_response_code(200);
            echo 'OK';
        } else {
            \PrestaShopLogger::addLog(
                'Open Banking webhook: Could not process notification',
                2,
                null,
                'GlobalPayments'
            );
            http_response_code(400);
            echo 'Bad Request';
        }

        // Important: Exit to prevent further output
        exit;
    }

    /**
     * Handle Open Banking payment redirect back to shop
     */
    public static function handleObRedirect()
    {
        Utils::validateSignature();

        $status = isset($_REQUEST["status"]) ? $_REQUEST["status"] : '';
        $orderId = isset($_REQUEST["order_id"]) ? (int)$_REQUEST["order_id"] : 0;

        // Get module instance and create CheckoutHelper
        $module = \GlobalPayments::getModuleInstance();
        $checkoutHelper = new CheckoutHelper($module);

        if ($status !== "PENDING" && $status !== "CAPTURED" && $status !== "DECLINED") {
            // Payment failed or cancelled, redirect to checkout using PrestaShop's built-in method
            \PrestaShopLogger::addLog(
                'Open Banking payment failed/cancelled, status: ' . $status,
                2,
                null,
                'GlobalPayments'
            );
            $checkoutHelper->redirectToCartPage();
            exit; // Ensure clean exit after redirect
        } else {
            // Payment successful or pending, redirect to success page
            \PrestaShopLogger::addLog(
                'Open Banking payment successful/pending, status: ' . $status,
                1,
                null,
                'GlobalPayments'
            );

            if ($orderId > 0) {
                $order = new \Order($orderId);
                if (\Validate::isLoadedObject($order)) {
                    // Handle CAPTURED status - update order status and add transaction history
                    if (strtoupper($status) === 'CAPTURED') {
                        // Update order status to paid if not already processed
                        if (!in_array(
                            $order->getCurrentState(),
                            [
                                \Configuration::get('PS_OS_PAYMENT'),
                                \Configuration::get('PS_OS_WS_PAYMENT')
                            ]
                        )) {
                            $order->setCurrentState(\Configuration::get('PS_OS_PAYMENT'));

                            \PrestaShopLogger::addLog(
                                'Open Banking payment captured - Order ID: ' . $order->id . ' marked as paid',
                                1,
                                null,
                                'GlobalPayments'
                            );
                        }

                        // Get transaction ID from order payments
                        $transactionId = null;
                        $orderPayments = \OrderPayment::getByOrderReference($order->reference);
                        if (!empty($orderPayments)) {
                            $transactionId = $orderPayments[0]->transaction_id;
                        }

                        // Add transaction history for CAPTURED status
                        if ($transactionId) {
                            try {
                                $transactionHistory = new TransactionHistory();
                                $currency = new \Currency($order->id_currency);

                                $transactionHistory->saveResult(
                                    $order->id,
                                    'charge', // action
                                    $order->total_paid_tax_incl, // amount
                                    $currency->iso_code ?: 'PLN', // currency
                                    $transactionId,
                                    1, // success
                                    'Open Banking Payment Captured via redirect' // result message
                                );

                                \PrestaShopLogger::addLog(
                                    'Open Banking transaction history added for CAPTURED status - Order ID: ' . 
                                    $order->id . ', Transaction ID: ' . $transactionId,
                                    1,
                                    null,
                                    'GlobalPayments'
                                );
                            } catch (\Exception $e) {
                                \PrestaShopLogger::addLog(
                                    'Error adding Open Banking transaction history: ' . $e->getMessage() . 
                                    ' - Order ID: ' . $order->id,
                                    3,
                                    null,
                                    'GlobalPayments'
                                );
                            }
                        }
                    }

                    // Handle DECLINED status - update order status and add transaction history
                    if (strtoupper($status) === 'DECLINED') {
                        // Log current order state for debugging
                        \PrestaShopLogger::addLog(
                            'Open Banking DECLINED - Current order state: ' . $order->getCurrentState() . 
                            ' - Order ID: ' . $order->id,
                            1,
                            null,
                            'GlobalPayments'
                        );

                        // Update order status to cancelled (even if not pending)
                        if ($order->getCurrentState() != \Configuration::get('PS_OS_CANCELED')) {
                            $order->setCurrentState(\Configuration::get('PS_OS_CANCELED'));
                            \PrestaShopLogger::addLog(
                                'Open Banking payment declined - Order status updated to Cancelled - Order ID: ' . $order->id,
                                1,
                                null,
                                'GlobalPayments'
                            );
                        }

                        // Get transaction ID from order payments
                        $transactionId = null;
                        $orderPayments = \OrderPayment::getByOrderReference($order->reference);
                        if (!empty($orderPayments)) {
                            $transactionId = $orderPayments[0]->transaction_id;
                        }

                        // Add transaction history for DECLINED status
                        if ($transactionId) {
                            try {
                                $transactionHistory = new TransactionHistory();
                                $currency = new \Currency($order->id_currency);

                                $transactionHistory->saveResult(
                                    $order->id,
                                    'decline/cancel', // action
                                    $order->total_paid_tax_incl, // amount
                                    $currency->iso_code ?: 'PLN', // currency
                                    $transactionId,
                                    0, // failure
                                    'Open Banking Payment Declined' // result message
                                );
                            } catch (\Exception $e) {
                                \PrestaShopLogger::addLog(
                                    'Error adding Open Banking transaction history: ' . $e->getMessage() . 
                                    ' - Order ID: ' . $order->id,
                                    3,
                                    null,
                                    'GlobalPayments'
                                );
                            }
                        }

                        // For DECLINED status, redirect to cart page without clearing cart
                        \PrestaShopLogger::addLog(
                            'Open Banking payment declined (order cancelled, transaction history updated, user redirected to cart) - '
                            . 'Order ID: ' . $order->id
                            . (isset($transactionId) ? ', Transaction ID: ' . $transactionId : ''),
                            2,
                            null,
                            'GlobalPayments'
                        );
                        $checkoutHelper->redirectToCartPage();
                        exit; // Ensure clean exit after redirect
                    }

                    // For CAPTURED and PENDING status, use CheckoutHelper for proper cart clearing and redirection
                    $context = \Context::getContext();
                    $currentCart = $context->cart;

                    // Create CheckoutHelper with current cart context
                    $checkoutHelper = new CheckoutHelper($module, $currentCart);

                    // Clear the cart using CheckoutHelper's built-in method
                    $checkoutHelper->clearCart();

                    // Redirect to success page
                    $checkoutHelper->redirectToSuccessPage($order->id);
                    exit; // Ensure clean exit after redirect
                }
            }

            // Fallback redirect using PrestaShop's built-in method
            $checkoutHelper->redirectToCartPage();
            exit; // Ensure clean exit after redirect
        }
    }

    /**
     * Static entry point for Open Banking redirect handling - can be called directly or via controller
     *
     * @param array $params Optional parameters (order_id, status, etc.)
     */
    public static function handleRedirectRequest($params = [])
    {
        Utils::validateSignature();

        // Merge with request data if params not provided
        if (empty($params)) {
            $params = array_merge($_GET, $_POST);
        }

        $order_id = isset($params['order_id']) ? (int)$params['order_id'] : 0;
        $status = isset($params['status']) ? $params['status'] : '';

        \PrestaShopLogger::addLog(
            sprintf('Open Banking redirect request: order_id=%d, status=%s', $order_id, $status),
            1,
            null,
            'GlobalPayments'
        );

        return self::handleObRedirect();
    }

    /**
     * Static entry point for Open Banking webhook handling - can be called directly or via controller
     *
     * @param array $params Optional parameters
     */
    public static function handleWebhookRequest($params = [])
    {
        Utils::validateSignature();
        
        // Log incoming webhook
        \PrestaShopLogger::addLog('Open Banking webhook request received', 1, null, 'GlobalPayments');

        return self::handleObStatusNotification();
    }

    /**
     * Extract transaction ID from callback data
     */
    private static function extractTransactionId($data)
    {
        // Your callback structure: {"id": "TRN_sZGlrL8M7fJy0YlouMFTnqiubJmpsS_hop_Order_28", ...}
        $possibleFields = ['id', 'transaction_id', 'reference'];

        foreach ($possibleFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                return \Tools::safeOutput($data[$field]);
            }
        }

        return null;
    }

    /**
     * Extract payment status from callback data
     */
    private static function extractPaymentStatus($data)
    {
        // Common field names for status
        $possibleFields = ['status'];

        foreach ($possibleFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                return \Tools::safeOutput($data[$field]);
            }
        }

        return 'UNKNOWN';
    }

    /**
     * Extract reference from callback data
     */
    private static function extractReference($data)
    {
        // Your callback structure: {"reference": "202412191212769", ...}
        if (isset($data['reference']) && !empty($data['reference'])) {
            return \Tools::safeOutput($data['reference']);
        }
        return null;
    }

    /**
     * Extract order ID from reference field
     */
    private static function extractOrderIdFromReference($reference)
    {
        // Your callback has reference: "202412191212769"
        // This might be the order ID directly, or we need to map it
        if (is_numeric($reference)) {
            return (int)$reference;
        }

        // Try to extract numeric part if it contains other text
        if (preg_match('/(\d+)/', $reference, $matches)) {
            return (int)$matches[1];
        }

        return null;
    }

    /**
     * Extract order ID from platform order_id field
     */
    private static function extractOrderIdFromPlatform($platformOrderId)
    {
        // Your callback has platforms[0].order_id: "TRN_sZGlrL8M7fJy0YlouMFTnqiubJmpsS_hop_Order_28"
        // Look for pattern like "_Order_28" or "Order_28"
        if (preg_match('/_Order_(\d+)$/', $platformOrderId, $matches)) {
            return (int)$matches[1];
        }

        // Fallback: look for any number at the end
        if (preg_match('/(\d+)$/', $platformOrderId, $matches)) {
            return (int)$matches[1];
        }

        return null;
    }

    /**
     * Update order status based on payment notification
     */
    private static function updateOrderStatusFromNotification(
        $order,
        $paymentStatus,
        $transactionId,
        $callbackData
    ) {
        $statusUpper = strtoupper($paymentStatus);

        \PrestaShopLogger::addLog(
            'Updating Open Banking order status - Order ID: ' . $order->id . ', Status: ' . $statusUpper,
            1,
            null,
            'GlobalPayments'
        );

        // Create order note with callback details
        $callbackSummary = "Status: $paymentStatus, Transaction ID: $transactionId";
        if (isset($callbackData['amount'])) {
            $callbackSummary .= ", Amount: " . $callbackData['amount'];
        }

        switch ($statusUpper) {
            case 'CAPTURED':
            case 'COMPLETED':
                // Check if order is not already processed
                if (!in_array(
                    $order->getCurrentState(),
                    [
                        \Configuration::get('PS_OS_PAYMENT'),
                        \Configuration::get('PS_OS_WS_PAYMENT')
                    ]
                )) {
                    // Use PrestaShop's built-in logger
                    \PrestaShopLogger::addLog(
                        sprintf('Open Banking payment completed via status notification. %s', $callbackSummary),
                        1,
                        null,
                        'GlobalPayments'
                    );

                    // Update order state to paid
                    $order->setCurrentState(\Configuration::get('PS_OS_PAYMENT'));

                    // Create transaction history entry for successful payment
                    self::createTransactionHistory($order->id, $transactionId, $paymentStatus, $callbackData);

                    \PrestaShopLogger::addLog(
                        'Open Banking order marked as paid - Order ID: ' . $order->id,
                        1,
                        null,
                        'GlobalPayments'
                    );
                }
                break;

            case 'DECLINED':
            case 'FAILED':
            case 'CANCELLED':
                // Check if order is still pending
                if (in_array(
                    $order->getCurrentState(),
                    [
                        \Configuration::get('PS_OS_PREPARATION'),
                        \Configuration::get('PS_OS_BANKWIRE')
                    ]
                )) {
                    // Use PrestaShop's built-in logger
                    \PrestaShopLogger::addLog(
                        sprintf('Open Banking payment failed/declined via status notification. %s', $callbackSummary),
                        2, // LOG_SEVERITY_LEVEL_WARNING
                        null,
                        'GlobalPayments'
                    );

                    // Update order state to cancelled
                    $order->setCurrentState(\Configuration::get('PS_OS_CANCELED'));

                    // Create transaction history entry for failed payment
                    self::createTransactionHistory($order->id, $transactionId, $paymentStatus, $callbackData);

                    \PrestaShopLogger::addLog(
                        'Open Banking order marked as cancelled - Order ID: ' . $order->id,
                        2,
                        null,
                        'GlobalPayments'
                    );
                }
                break;

            case 'PENDING':
                // Use PrestaShop's built-in logger
                \PrestaShopLogger::addLog(
                    sprintf('Open Banking payment is pending. %s', $callbackSummary),
                    1,
                    null,
                    'GlobalPayments'
                );

                // Create transaction history entry for pending status
                self::createTransactionHistory($order->id, $transactionId, $paymentStatus, $callbackData);

                \PrestaShopLogger::addLog(
                    'Open Banking order pending - Order ID: ' . $order->id,
                    1,
                    null,
                    'GlobalPayments'
                );
                break;
        }
    }

    /**
     * Create a transaction history entry for Open Banking status updates
     */
    private static function createTransactionHistory($orderId, $transactionId, $paymentStatus, $callbackData)
    {
        try {
            $transactionHistory = new TransactionHistory();

            // Determine amount and currency from callback data
            $amount = isset($callbackData['amount']) ? floatval($callbackData['amount']) : 0.0;
            $currency = isset($callbackData['currency']) ? $callbackData['currency'] : 'PLN';

            // Map Open Banking status to transaction action
            $action = 'initiate payment'; // Default action
            $success = 0; // Default to failure

            switch (strtoupper($paymentStatus)) {
                case 'CAPTURED':
                case 'COMPLETED':
                    $action = 'charge';
                    $success = 1;
                    break;
                case 'DECLINED':
                case 'FAILED':
                    $action = 'decline/cancel';
                    $success = 0;
                    break;
                case 'CANCELLED':
                    $action = 'customer cancel';
                    $success = 0;
                    break;
                case 'PENDING':
                    $action = 'initiate payment';
                    $success = 0;
                    break;
            }

            // Create a result message with callback details
            $resultMessage = sprintf(
                'Open Banking Status Update: %s (Transaction ID: %s)',
                $paymentStatus,
                $transactionId
            );

            // Save the transaction history using the correct method
            $transactionHistory->saveResult(
                $orderId,
                $action,
                $amount,
                $currency,
                $transactionId,
                $success,
                $resultMessage
            );

            \PrestaShopLogger::addLog(
                'Open Banking transaction history created - Order ID: ' . $orderId . 
                ', Transaction ID: ' . $transactionId . ', Status: ' . $paymentStatus,
                1,
                null,
                'GlobalPayments'
            );
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog(
                'Error creating Open Banking transaction history: ' . $e->getMessage() . ' - Order ID: ' . $orderId,
                3, // LOG_SEVERITY_LEVEL_ERROR
                null,
                'GlobalPayments'
            );
        }
    }
}
