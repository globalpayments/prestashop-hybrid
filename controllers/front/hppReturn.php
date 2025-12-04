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
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\CheckoutHelper;
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\HppHelper;
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\HppSignatureValidator;
use GlobalPayments\PaymentGatewayProvider\Platform\Utils\HppResponseParser;


if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * HPP Return URL Controller
 *
 * This controller receives the POST callback from Global Payments HPP after payment completion.
 * It validates the signature, processes the payment, and redirects the customer.
 * This matches the Magento ReturnUrl controller pattern.
 */
class GlobalPaymentsHppReturnModuleFrontController extends AbstractUrl
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
     * @var HppResponseParser parses HPP data
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
     * Process HPP return callback
     *
     * {@inheritdoc}
     */
    public function postProcess(): void
    {
        $checkoutHelper = new CheckoutHelper($this->module, $this->context->cart);

        try {
            // Get the GP signature from headers and raw POST data
            $gpSignature = getallheaders()['X-GP-Signature'] ?? null;
            $rawInput = trim(file_get_contents('php://input'));

            // Validate the request (HPP-specific signature validation)
            if (!$this->signatureValidator->validateSignature($rawInput, $gpSignature)) {
                $this->logError('Invalid signature or missing data');
                http_response_code(403);
                die('Invalid Signature');
            }

            // Parse gateway data
            $gatewayData = $this->hppResponseParser->parseGatewayData($rawInput);

            // Extract order information
            $orderId = $this->hppResponseParser->extractOrderId($gatewayData);

            if (empty($orderId)) {
                throw new \Exception('Order ID not found in gateway response');
            }

            // Load the order
            $order = $this->loadOrder($orderId);

            // Extract transaction details
            $transactionId = $this->hppResponseParser->extractTransactionId($gatewayData) ?? '';
            $paymentStatus = $this->hppResponseParser->extractPaymentStatus($gatewayData);

            // Process based on payment status
            $this->processPaymentStatus($order, $transactionId, $paymentStatus, $gatewayData, $checkoutHelper);
        } catch (\Exception $e) {
            $this->logError('HPP Return Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            http_response_code(500);
            die('An error occurred processing your payment. Please contact support.');
        }
    }

    /**
     * Load order by ID
     *
     * @param int $orderId Order ID
     * @return \Order Loaded order
     * @throws \Exception If order not found
     */
    private function loadOrder(int $orderId): \Order
    {
        $order = new \Order($orderId);

        if (!\Validate::isLoadedObject($order)) {
            $this->logError('Invalid order ID', ['order_id' => $orderId]);
            throw new \Exception('Invalid order ID: ' . $orderId);
        }

        return $order;
    }

    /**
     * Process payment status and take appropriate action
     *
     * @param \Order $order Order to process
     * @param string $transactionId Transaction ID
     * @param string $paymentStatus Payment status
     * @param array<string, mixed> $gatewayData Gateway data
     * @param CheckoutHelper $checkoutHelper Checkout helper
     * @return void
     */
    private function processPaymentStatus(
        \Order $order,
        string $transactionId,
        string $paymentStatus,
        array $gatewayData,
        CheckoutHelper $checkoutHelper
    ): void {
        switch ($paymentStatus) {
            case 'INITIATED':
            case 'PREAUTHORIZED':
            case 'CAPTURED':
                $this->handleSuccessfulPayment($order, $transactionId, $gatewayData, $checkoutHelper);
                break;

            case 'DECLINED':
            case 'CANCELLED':
            case 'FAILED':
                $this->handleFailedPayment($order, $gatewayData);
                break;

            case 'PENDING':
                $this->handleSuccessfulPayment($order, $transactionId, $gatewayData, $checkoutHelper, true);
                break;

            default:
                $this->logError('Unknown payment status', ['status' => $paymentStatus]);
                throw new \Exception('Unknown payment status: ' . $paymentStatus);
        }
    }

    /**
     * Handle successful payment
     *
     * @param \Order $order Order to complete
     * @param string $transactionId Transaction ID
     * @param array<string, mixed> $gatewayData Gateway data
     * @param CheckoutHelper $checkoutHelper Checkout helper
     * @param boolean $isPending Flag to determin if the transaction is in a prending state
     * @return void
     */
    private function handleSuccessfulPayment(
        \Order $order,
        string $transactionId,
        array $gatewayData,
        CheckoutHelper $checkoutHelper,
        bool $isPending = false
    ): void {

        if ($isPending) {
            // Pending payment - return to success screen but keep order in pending state
            $this->hppHelper->handlePendingPayment($order, $transactionId, $gatewayData);
        } else {
            // Payment successful - complete the order
            $this->hppHelper->completePayment($order, $transactionId, $gatewayData);
        }

        $checkoutHelper->clearCart();

        // Show success page with redirect
        $this->renderSuccessPage($order);
    }

    /**
     * Handle failed payment
     *
     * @param \Order $order Order to cancel
     * @param array<string, mixed> $gatewayData Gateway data
     * @return void
     */
    private function handleFailedPayment(\Order $order, array $gatewayData): void {
        // Payment failed - cancel order and restore cart
        $this->hppHelper->handleFailedPayment($order);

        // Get error message
        $errorMessage = $gatewayData['payment_method']['message'] ?? 'Payment was declined or failed. Please try again.';

        // Show error page with redirect
        $this->renderErrorPage($errorMessage);
    }

    /**
     * Render success page and redirect to order confirmation
     *
     * @param \Order $order Completed order
     * @return void
     */
    private function renderSuccessPage(\Order $order): void
    {
        // Use the order's secure_key directly - works for both guest and registered customers
        $successUrl = $this->context->link->getPageLink(
            'order-confirmation',
            true,
            null,
            [
                'id_cart' => $order->id_cart,
                'id_module' => $this->module->id,
                'id_order' => $order->id,
                'key' => $order->secure_key,
            ]
        );
        
        $successUrl = $this->encodeAccentedParts($successUrl);

        echo $this->generateHtmlPage(
            'Payment Successful',
            '&#x2714;',
            '#28a745',
            'success',
            'Payment Successful',
            'Your payment has been processed successfully.',
            sprintf('Order #%s', $order->reference),
            $successUrl,
            'order confirmation'
        );

        exit;
    }

    /**
     * Render error page and redirect to cart
     *
     * @param string $message Error message to display
     * @return void
     */
    private function renderErrorPage(string $message): void
    {
        $cartUrl = $this->context->link->getPageLink('order', true);
        $cartUrl = $this->encodeAccentedParts($cartUrl);
       
        echo $this->generateHtmlPage(
            'Payment Error',
            '&#x2716;',
            '#dc3545',
            'error',
            'Payment Error',
            $message,
            '',
            $cartUrl,
            'checkout'
        );

        exit;
    }

    /**
     * Generate HTML page for success/error display
     *
     * @param string $title Page title
     * @param string $icon Icon character
     * @param string $iconColor Icon color
     * @param string $iconClass CSS class for icon (success/error)
     * @param string $heading Main heading
     * @param string $message Main message
     * @param string $subMessage Sub message (optional)
     * @param string $redirectUrl URL to redirect to
     * @param string $redirectLabel Label for redirect destination
     * @return string HTML content
     */
    private function generateHtmlPage(
        string $title,
        string $icon,
        string $iconColor,
        string $iconClass,
        string $heading,
        string $message,
        string $subMessage,
        string $redirectUrl,
        string $redirectLabel
    ): string {
        $storeName = $this->getStoreName();
        $storeLogo = $this->getStoreLogo();
        $globalPaymentsLogo = $this->getGlobalPaymentsLogo();

        $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $escapedHeading = htmlspecialchars($heading, ENT_QUOTES, 'UTF-8');
        $escapedMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $escapedSubMessage = $subMessage ? '<p>' . htmlspecialchars($subMessage, ENT_QUOTES, 'UTF-8') . '</p>' : '';
        $escapedLabel = htmlspecialchars($redirectLabel, ENT_QUOTES, 'UTF-8');
        $escapedStoreName = htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8');

               // Build header with logos
        $headerContent = '<div class="header">';
        if ($storeLogo) {
            $headerContent .= '<img src="' . htmlspecialchars($storeLogo, ENT_QUOTES, 'UTF-8') . '" alt="' . $escapedStoreName . '" class="logo">';
        } else {
            $headerContent .= '<h1 class="store-name">' . $escapedStoreName . '</h1>';
        }
        if ($globalPaymentsLogo) {
            $headerContent .= '<img src="' . htmlspecialchars($globalPaymentsLogo, ENT_QUOTES, 'UTF-8') . '" alt="GlobalPayments" class="logo">';
        }
        $headerContent .= '</div>';

        $ajaxRedirectUrl = $this->context->link->getModuleLink(
            'globalpayments',
            'redirect',
            ['action' => 'hpp_return', 'url' => $redirectUrl, 'isError' => $iconClass === 'error' ? '1' : '0'],
            true
        );

        return sprintf(
            '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>%s</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            background: white;
            padding: 3rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            width: 100%%;
            max-width: 600px;
        }
        .container::before {
            content: "";
            background-color: #fff;
            position: absolute;
            width: 100vw;
            height: 100vh;
            left: 0;
            top: 0;
            z-index: -1;
        }
        .header {
            margin-bottom: 2rem;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
        }
        .logo {
            max-height: 60px;
            max-width: 200px;
        }
        .store-name {
            color: #333;
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }
        .content {
            margin: 2rem 0;
        }
        .status-icon {
            font-size: 4rem;
            margin: 1rem 0;
            font-weight: bold;
        }
        .status-icon.success {
            color: #28a745;
        }
        .status-icon.error {
            color: #dc3545;
        }
        h2 {
            color: #333;
            margin-bottom: 1rem;
            font-size: 1.8rem;
            font-weight: 600;
        }
        p {
            color: #666;
            margin-bottom: 1.5rem;
            font-size: 1rem;
        }
        .footer {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
        }
        .powered-by {
            color: #999;
            font-size: 0.875rem;
        }
        .redirect-message {
            color: #666;
            font-style: italic;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        %s
        
        <div class="content">
            <div class="status-icon %s">%s</div>
            <h2>%s</h2>
            <p>%s</p>
            %s
            
            <p class="redirect-message">Redirecting to %s in <span id="countdown">3</span> seconds...</p>
        </div>
        
        <div class="footer">
            <div class="powered-by">Powered by GlobalPayments</div>
        </div>
    </div>
    
    <script>
        var countdown = 3;
        var countdownElement = document.getElementById("countdown");
        var ajaxRedirectUrl = "%s";
        var timer = setInterval(function() {
            countdown--;
            if (countdownElement) {
                countdownElement.textContent = countdown;
            }
            if (countdown <= 0) {
                clearInterval(timer);
                // Call PHP redirect method via current page navigation
                window.location.href = ajaxRedirectUrl;
            }
        }, 1000);
    </script>
</body>
</html>',
            $escapedTitle,
            $headerContent,
            $iconClass,
            $icon,
            $escapedHeading,
            $escapedMessage,
            $escapedSubMessage,
            $escapedLabel,
            $ajaxRedirectUrl
        );
    }

    /**
     * Get store name for branding
     *
     * @return string Store name or default fallback
     */
    private function getStoreName(): string
    {
        try {
            $shopName = \Configuration::get('PS_SHOP_NAME');
            return $shopName ?: 'Store';
        } catch (\Exception $e) {
            $this->logError('Could not get store name', ['error' => $e->getMessage()]);
            return 'Store';
        }
    }

    /**
     * Get store logo URL for branding
     *
     * Attempts to retrieve store logo from PrestaShop configuration
     *
     * @return string|null Logo URL or null if not available
     */
    private function getStoreLogo(): ?string
    {
        try {
            // Get logo from shop configuration
            $logo = \Configuration::get('PS_LOGO');
            
            if ($logo) {
                // Build full URL to logo
                $imgDir = $this->context->link->getBaseLink() . 'img/';
                return $imgDir . $logo;
            }

            // Try alternative: check theme logo
            $themeLogo = \Configuration::get('PS_LOGO_MOBILE');
            if ($themeLogo) {
                $imgDir = $this->context->link->getBaseLink() . 'img/';
                return $imgDir . $themeLogo;
            }
        } catch (\Exception $e) {
            $this->logError('Could not get store logo', ['error' => $e->getMessage()]);
        }

        return null;
    }

     /**
     * Get GlobalPayments logo URL for branding
     *
     * @return string|null Logo URL or null if not available
     */
    private function getGlobalPaymentsLogo(): ?string
    {
        try {
            // Verify file exists
            $fullPath = _PS_MODULE_DIR_ . 'globalpayments/views/img/globalpayments-logo.png';

            if (file_exists($fullPath)) {
                return $this->context->link->getBaseLink() . '/modules/globalpayments/views/img/globalpayments-logo.png';
            }
        } catch (\Exception $e) {
            $this->logError('Could not get GlobalPayments logo', ['error' => $e->getMessage()]);
        }

        return null;
    }
    
    /**
     * Encode only accented characters in a PrestaShop URL
     *
     * @param string $url The original PrestaShop URL
     * @return string The URL with problematic characters safely encoded
     */
    function encodeAccentedParts($url)
    {
        // Parse the URL into components
        $parsed = parse_url($url);

        // Start rebuilding the URL
        $encodedUrl = '';

        // Scheme and host
        if (isset($parsed['scheme'])) {
            $encodedUrl .= $parsed['scheme'] . '://';
        }
        if (isset($parsed['host'])) {
            $encodedUrl .= $parsed['host'];
        }

        // Path: encode only accented characters, keep slashes
        if (isset($parsed['path'])) {
            $segments = explode('/', $parsed['path']);
            foreach ($segments as &$segment) {
                if (preg_match('/[^\x00-\x7F]/', $segment)) {
                    $segment = rawurlencode($segment);
                }
            }
            $encodedUrl .= implode('/', $segments);
        }

        // Query: encode only values with accents
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $params);
            foreach ($params as $key => $value) {
                if (preg_match('/[^\x00-\x7F]/', $value)) {
                    $params[$key] = rawurlencode($value);
                }
            }
            $encodedUrl .= '?' . http_build_query($params);
        }

        return $encodedUrl;
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
        $logMessage = sprintf('HPP Return: %s', $message);

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
