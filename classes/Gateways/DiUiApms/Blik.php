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
 * Helper class for DiUi/Blik transactions
 *
 * @package GlobalPayments\PaymentGatewayProvider\Gateways\DiUiApms
 */
class Blik
{
    public static function processBlikSale( GpApiGateway $gateway, $order_id )
    {
        $order = new \Order($order_id);

        $settings = $gateway->getBackendGatewayOptions();

        $config = new GpApiConfig();
        $country = new \Country(\Configuration::get('PS_COUNTRY_DEFAULT'));
        $config->country = $country->iso_code;
        $config->appId = $settings["appId"];
        $config->appKey = $settings["appKey"];

        // targeting SIT sandbox URL since the main one may never work with Blik
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

        $paymentMethod = new AlternativePaymentMethod(AlternativePaymentType::BLIK);

        $address = new \Address($order->id_address_invoice);
        $customer = new \Customer($order->id_customer);
        $country = new \Country($address->id_country);

        $paymentMethod->descriptor        = 'ORD' . $order->id;
        $paymentMethod->country           = $country->iso_code;
        $paymentMethod->accountHolderName = $customer->firstname . ' ' . $customer->lastname;

        $paymentMethod->returnUrl = \Context::getContext()->link->getModuleLink(
            'globalpayments',
            'redirect',
            ['action' => 'blik_redirect_handler', 'order_id' => $order->id]
        );
        $paymentMethod->statusUpdateUrl = \Context::getContext()->link->getModuleLink(
            'globalpayments',
            'webhook',
            ['action' => 'blik_status_handler']
        );
        // remove this prior to deployment
        // currently not supported by gateway
        $paymentMethod->cancelUrl = \Context::getContext()->link->getPageLink('order');

        $blikGpResponse = null; // Initialize variable

        try {
            // Get order details
            $orderTotal = $order->total_paid_tax_incl;
            $currency = new \Currency($order->id_currency);
            $currencyCode = $currency->iso_code ?: 'PLN';

            $blikGpResponse = $paymentMethod->charge($orderTotal)
                ->withClientTransactionId( 'PrestaShop_Order_' . $order->id )
                ->withCurrency($currencyCode)
                ->execute();
        } catch (\Exception $e) {
            // Return error response if payment fails
            return array(
                'result' => 'error',
                'message' => $e->getMessage(),
            );
        }

        // Check if payment response is valid
        if (!$blikGpResponse || !isset($blikGpResponse->transactionId)) {
            return array(
                'result' => 'error',
                'message' => 'Invalid payment response received',
            );
        }

        // Update the existing order payment record (created during order generation)
        $orderPayments = \OrderPayment::getByOrderReference($order->reference);

        if (!empty($orderPayments)) {
            // Update existing payment record
            $orderPayment = $orderPayments[0];
            $orderPayment->payment_method = 'BLIK';
            $orderPayment->transaction_id = $blikGpResponse->transactionId;
            $orderPayment->save();
        } else {
            // Create new payment record if none exists
            $orderPayment = new \OrderPayment();
            $orderPayment->order_reference = $order->reference;
            $orderPayment->id_currency = $order->id_currency;
            $orderPayment->amount = $order->total_paid_tax_incl;
            $orderPayment->payment_method = 'BLIK';
            $orderPayment->transaction_id = $blikGpResponse->transactionId;
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
            $blikGpResponse->transactionId,
            TransactionType::INITIATE_PAYMENT,
            1 // success
        );

        // Use PrestaShop's built-in logger instead of manual messages
        \PrestaShopLogger::addLog(
            sprintf(
                'BLIK payment initiated for order %d. Amount: %s%s, Transaction ID: %s',
                $order->id,
                $currency->sign,
                $order->total_paid_tax_incl,
                $blikGpResponse->transactionId
            ),
            1, // LOG_SEVERITY_LEVEL_INFORMATIVE
            null,
            'GlobalPayments'
        );

        return array(
            'result'   => 'success',
            'redirect' => $blikGpResponse->alternativePaymentResponse->redirectUrl,
        );

    }

    /**
     * Handle BLIK payment status callback from payment gateway
     */
    public static function handleBlikStatusNotification()
    {
        Utils::validateSignature();

        \PrestaShopLogger::addLog('BLIK status notification received', 1, null, 'GlobalPayments');

        // Get request data (could be GET or POST)
        $request_data = array_merge($_GET, $_POST);

        // Parse JSON body if present
        $raw_input = file_get_contents('php://input');
        if (!empty($raw_input)) {
            $json_data = json_decode($raw_input, true);
            if ($json_data) {
                $request_data = array_merge($request_data, $json_data);
            }
        }

        \PrestaShopLogger::addLog(
            'BLIK status notification data: ' .
            print_r($request_data, true),
            1,
            null,
            'GlobalPayments'
        );

        // Extract transaction details using the real callback structure
        $transaction_id = self::extractTransactionId($request_data);
        $payment_status = self::extractPaymentStatus($request_data);
        $reference = self::extractReference($request_data);

        \PrestaShopLogger::addLog(
            'BLIK status - Transaction ID: ' . $transaction_id .
            ', Status: ' . $payment_status . ', Reference: ' . $reference,
            1,
            null,
            'GlobalPayments'
        );

        $processed = false;

        // Try to find order by reference field (your callback: "reference": "202412191212769")
        if (!empty($reference)) {
            $order_id = self::extractOrderIdFromReference($reference);

            if ($order_id) {
                \PrestaShopLogger::addLog(
                    'BLIK status - Extracted order ID: ' . $order_id .
                    ' from reference: ' . $reference,
                    1,
                    null,
                    'GlobalPayments'
                );

                $order = new \Order($order_id);
                if (\Validate::isLoadedObject($order)) {
                    self::updateOrderStatusFromNotification($order, $payment_status, $transaction_id, $request_data);
                    $processed = true;
                } else {
                    \PrestaShopLogger::addLog(
                        'BLIK webhook: Order not found with ID: ' . $order_id,
                        2,
                        null,
                        'GlobalPayments'
                    );
                }
            }
        }

        // Fallback: try to find by platforms array (your callback has platforms[0].order_id)
        if (!$processed && !empty($request_data['platforms'])) {
            foreach ($request_data['platforms'] as $platform) {
                if (isset($platform['order_id'])) {
                    $order_id = self::extractOrderIdFromPlatform($platform['order_id']);

                    if ($order_id) {
                        \PrestaShopLogger::addLog(
                            'BLIK status - Extracted order ID from platform: ' . $order_id,
                            1,
                            null,
                            'GlobalPayments'
                        );

                        $order = new \Order($order_id);
                        if (\Validate::isLoadedObject($order)) {
                            self::updateOrderStatusFromNotification(
                                $order,
                                $payment_status,
                                $transaction_id,
                                $request_data
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
            $order_id = str_replace("PrestaShop_Order_", "", $reference);

            // Find the order in PrestaShop
            $order = new \Order($order_id);

            if (\Validate::isLoadedObject($order)) {
                // Get order payments to find matching transaction
                $order_payments = \OrderPayment::getByOrderReference($order->reference);

                foreach ($order_payments as $payment) {
                    if ($payment->transaction_id === $transaction_id) {
                        self::updateOrderStatusFromNotification(
                            $order,
                            $payment_status,
                            $transaction_id,
                            $request_data
                        );
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
            \PrestaShopLogger::addLog('BLIK webhook: Could not process notification', 2, null, 'GlobalPayments');
            http_response_code(400);
            echo 'Bad Request';
        }

        // Important: Exit to prevent further output
        exit;
    }

    /**
     * Handle BLIK payment redirect back to shop
     */
    public static function handleBlikRedirect()
    {
        Utils::validateSignature();

        $status = isset($_REQUEST["status"]) ? $_REQUEST["status"] : '';
        $order_id = isset($_REQUEST["order_id"]) ? (int)$_REQUEST["order_id"] : 0;

        // Get module instance and create CheckoutHelper
        $module = \GlobalPayments::getModuleInstance();
        $checkoutHelper = new CheckoutHelper($module);

        if ($status !== "PENDING" && $status !== "CAPTURED" && $status !== "DECLINED") {
            // Payment failed or cancelled, redirect to checkout using PrestaShop's built-in method
            \PrestaShopLogger::addLog('BLIK payment failed/cancelled, status: ' . $status, 2, null, 'GlobalPayments');
            $checkoutHelper->redirectToCartPage();
            exit; // Ensure clean exit after redirect
        } else {
            // Payment successful or pending, redirect to success page
            \PrestaShopLogger::addLog('BLIK payment successful/pending, status: ' . $status, 1, null, 'GlobalPayments');

            if ($order_id > 0) {
                $order = new \Order($order_id);
                if (\Validate::isLoadedObject($order)) {

                    // Handle CAPTURED status - update order status and add transaction history
                    if (strtoupper($status) === 'CAPTURED') {
                        // Update order status to paid if not already processed
                        if (!in_array(
                            $order->getCurrentState(),
                            array(\Configuration::get('PS_OS_PAYMENT'), \Configuration::get('PS_OS_WS_PAYMENT'))
                        )) {
                            $order->setCurrentState(\Configuration::get('PS_OS_PAYMENT'));

                            \PrestaShopLogger::addLog(
                                'BLIK payment captured - Order ID: ' . $order->id . ' marked as paid',
                                1,
                                null,
                                'GlobalPayments'
                            );
                        }

                        // Get transaction ID from order payments
                        $transaction_id = null;
                        $orderPayments = \OrderPayment::getByOrderReference($order->reference);
                        if (!empty($orderPayments)) {
                            $transaction_id = $orderPayments[0]->transaction_id;
                        }

                        // Add transaction history for CAPTURED status
                        if ($transaction_id) {
                            try {
                                $transactionHistory = new TransactionHistory();
                                $currency = new \Currency($order->id_currency);

                                $transactionHistory->saveResult(
                                    $order->id,
                                    'charge', // action
                                    $order->total_paid_tax_incl, // amount
                                    $currency->iso_code ?: 'PLN', // currency
                                    $transaction_id,
                                    1, // success
                                    'BLIK Payment Captured via redirect' // result message
                                );

                                \PrestaShopLogger::addLog(
                                    'BLIK transaction history added for CAPTURED status - Order ID: ' . $order->id . 
                                    ', Transaction ID: ' . $transaction_id,
                                    1,
                                    null,
                                    'GlobalPayments'
                                );

                            } catch (\Exception $e) {
                                \PrestaShopLogger::addLog(
                                    'Error adding BLIK transaction history: ' . $e->getMessage() . 
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
                            'BLIK DECLINED - Current order state: ' . $order->getCurrentState() .
                            ' - Order ID: ' . $order->id,
                            1,
                            null,
                            'GlobalPayments'
                        );

                        // Update order status to cancelled (even if not pending)
                        if ($order->getCurrentState() != \Configuration::get('PS_OS_CANCELED')) {
                            $order->setCurrentState(\Configuration::get('PS_OS_CANCELED'));
                            \PrestaShopLogger::addLog(
                                'BLIK payment declined - Order status updated to Cancelled - Order ID: ' . $order->id,
                                1,
                                null,
                                'GlobalPayments'
                            );
                        }

                        // Get transaction ID from order payments
                        $transaction_id = null;
                        $orderPayments = \OrderPayment::getByOrderReference($order->reference);
                        if (!empty($orderPayments)) {
                            $transaction_id = $orderPayments[0]->transaction_id;
                        }

                        // Add transaction history for DECLINED status
                        if ($transaction_id) {
                            try {
                                $transactionHistory = new TransactionHistory();
                                $currency = new \Currency($order->id_currency);

                                $transactionHistory->saveResult(
                                    $order->id,
                                    'decline/cancel', // action
                                    $order->total_paid_tax_incl, // amount
                                    $currency->iso_code ?: 'PLN', // currency
                                    $transaction_id,
                                    0, // failure
                                    'BLIK Payment Declined' // result message
                                );
                            } catch (\Exception $e) {
                                \PrestaShopLogger::addLog(
                                    'Error adding BLIK transaction history: ' . $e->getMessage() . 
                                    ' - Order ID: ' . $order->id,
                                    3,
                                    null,
                                    'GlobalPayments'
                                );
                            }
                        }

                        // For DECLINED status, redirect to cart page without clearing cart
                        \PrestaShopLogger::addLog(
                            'BLIK payment declined (order cancelled, transaction history updated, user redirected to cart) - '
                            . 'Order ID: ' . $order->id
                            . (isset($transaction_id) ? ', Transaction ID: ' . $transaction_id : ''),
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
     * Static entry point for BLIK redirect handling - can be called directly or via controller
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
            sprintf('BLIK redirect request: order_id=%d, status=%s', $order_id, $status),
            1,
            null,
            'GlobalPayments'
        );

        return self::handleBlikRedirect();
    }

    /**
     * Static entry point for BLIK webhook handling - can be called directly or via controller
     *
     * @param array $params Optional parameters
     */
    public static function handleWebhookRequest($params = [])
    {
        Utils::validateSignature();

        // Log incoming webhook
        \PrestaShopLogger::addLog('BLIK webhook request received', 1, null, 'GlobalPayments');

        return self::handleBlikStatusNotification();
    }

    /**
     * Extract transaction ID from callback data
     */
    private static function extractTransactionId($data)
    {
        // Your callback structure: {"id": "TRN_....", ...}
        $possible_fields = ['id', 'transaction_id', 'reference'];

        foreach ($possible_fields as $field) {
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
        $possible_fields = ['status'];

        foreach ($possible_fields as $field) {
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
        // Your callback structure: {"reference": "....", ...}
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
    private static function extractOrderIdFromPlatform($platform_order_id)
    {
        // Look for pattern like "_Order_28" or "Order_28"
        if (preg_match('/_Order_(\d+)$/', $platform_order_id, $matches)) {
            return (int)$matches[1];
        }

        // Fallback: look for any number at the end
        if (preg_match('/(\d+)$/', $platform_order_id, $matches)) {
            return (int)$matches[1];
        }

        return null;
    }

    /**
     * Update order status based on payment notification
     */
    private static function updateOrderStatusFromNotification(
        $order,
        $payment_status,
        $transaction_id,
        $callback_data
    ) {
        $status_upper = strtoupper($payment_status);

        \PrestaShopLogger::addLog(
            'Updating BLIK order status - Order ID: ' . $order->id . ', Status: ' . $status_upper,
            1,
            null,
            'GlobalPayments'
        );

        // Create order note with callback details
        $callback_summary = "Status: $payment_status, Transaction ID: $transaction_id";
        if (isset($callback_data['amount'])) {
            $callback_summary .= ", Amount: " . $callback_data['amount'];
        }

        switch ($status_upper) {
            case 'CAPTURED':
            case 'COMPLETED':
                // Check if order is not already processed
                if (!in_array(
                    $order->getCurrentState(),
                    array(\Configuration::get('PS_OS_PAYMENT'), \Configuration::get('PS_OS_WS_PAYMENT'))
                )) {
                    // Use PrestaShop's built-in logger
                    \PrestaShopLogger::addLog(
                        sprintf('BLIK payment completed via status notification. %s', $callback_summary),
                        1,
                        null,
                        'GlobalPayments'
                    );

                    // Update order state to paid
                    $order->setCurrentState(\Configuration::get('PS_OS_PAYMENT'));

                    // Create transaction history entry for successful payment
                    self::createTransactionHistory($order->id, $transaction_id, $payment_status, $callback_data);

                    \PrestaShopLogger::addLog(
                        'BLIK order marked as paid - Order ID: ' . $order->id,
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
                    array(\Configuration::get('PS_OS_PREPARATION'), \Configuration::get('PS_OS_BANKWIRE'))
                )) {
                    // Use PrestaShop's built-in logger
                    \PrestaShopLogger::addLog(
                        sprintf('BLIK payment failed/declined via status notification. %s', $callback_summary),
                        2, // LOG_SEVERITY_LEVEL_WARNING
                        null,
                        'GlobalPayments'
                    );

                    // Update order state to cancelled
                    $order->setCurrentState(\Configuration::get('PS_OS_CANCELED'));

                    // Create transaction history entry for failed payment
                    self::createTransactionHistory($order->id, $transaction_id, $payment_status, $callback_data);

                    \PrestaShopLogger::addLog(
                        'BLIK order marked as cancelled - Order ID: ' . $order->id,
                        2,
                        null,
                        'GlobalPayments'
                    );
                }
                break;

            case 'PENDING':
                // Use PrestaShop's built-in logger
                \PrestaShopLogger::addLog(
                    sprintf('BLIK payment is pending. %s', $callback_summary),
                    1,
                    null,
                    'GlobalPayments'
                );

                // Create transaction history entry for pending status
                self::createTransactionHistory($order->id, $transaction_id, $payment_status, $callback_data);

                \PrestaShopLogger::addLog('BLIK order pending - Order ID: ' . $order->id, 1, null, 'GlobalPayments');
                break;
        }
    }

    /**
     * Create a transaction history entry for BLIK status updates
     */
    private static function createTransactionHistory($order_id, $transaction_id, $payment_status, $callback_data)
    {
        try {
            $transactionHistory = new TransactionHistory();

            // Determine amount and currency from callback data
            $amount = isset($callback_data['amount']) ? floatval($callback_data['amount']) : 0.0;
            $currency = isset($callback_data['currency']) ? $callback_data['currency'] : 'PLN';

            // Map BLIK status to transaction action
            $action = 'initiate payment'; // Default action
            $success = 0; // Default to failure

            switch (strtoupper($payment_status)) {
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
            $result_message = sprintf(
                'BLIK Status Update: %s (Transaction ID: %s)',
                $payment_status,
                $transaction_id
            );

            // Save the transaction history using the correct method
            $transactionHistory->saveResult(
                $order_id,
                $action,
                $amount,
                $currency,
                $transaction_id,
                $success,
                $result_message
            );

            \PrestaShopLogger::addLog(
                'BLIK transaction history created - Order ID: ' . $order_id .
                ', Transaction ID: ' . $transaction_id . ', Status: ' . $payment_status,
                1,
                null,
                'GlobalPayments'
            );

        } catch (\Exception $e) {
            \PrestaShopLogger::addLog(
                'Error creating BLIK transaction history: ' . $e->getMessage() .
                ' - Order ID: ' . $order_id,
                3, // LOG_SEVERITY_LEVEL_ERROR
                null,
                'GlobalPayments'
            );
        }
    }
}
