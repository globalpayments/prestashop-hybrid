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

use GlobalPayments\PaymentGatewayProvider\PaymentMethods\DigitalWallets\ApplePay;
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\RequestHelper;

if (!defined('_PS_VERSION_')) {
    exit;
}

class GlobalPaymentsValidateMerchantModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        // Get raw POST data using safe method with hardcoded stream
        $rawContent = RequestHelper::getRequestBody();
        
        if (empty($rawContent)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request']);
            exit;
        }

        $data = json_decode($rawContent);
        
        if (json_last_error() !== JSON_ERROR_NONE || !is_object($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            exit;
        }

        // Validate validationUrl exists and is a string
        if (!isset($data->validationUrl) || !is_string($data->validationUrl)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing validation URL']);
            exit;
        }

        // Validate URL format
        $validationUrl = filter_var($data->validationUrl, FILTER_VALIDATE_URL);
        if ($validationUrl === false) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid validation URL format']);
            exit;
        }

        // Parse and validate URL components
        $parsedUrl = parse_url($validationUrl);

        // Enforce HTTPS scheme
        if (!isset($parsedUrl['scheme']) || $parsedUrl['scheme'] !== 'https') {
            http_response_code(400);
            echo json_encode(['error' => 'Validation URL must use HTTPS']);
            exit;
        }

        // Allowlist Apple's official validation domains
        $allowedHosts = [
            'apple-pay-gateway.apple.com',          // Global
            'cn-apple-pay-gateway.apple.com',       // China
            'apple-pay-gateway-cert.apple.com',     // Sandbox/cert
        ];

        $host = strtolower($parsedUrl['host'] ?? '');
        if (!in_array($host, $allowedHosts, true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Validation URL must be from Apple Pay Gateway']);
            exit;
        }

        // Reject private/loopback IP addresses (defense in depth)
        $ip = gethostbyname($host);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid validation URL']);
            exit;
        }

        $applePayGateway = new ApplePay();

        if (!$applePayGateway->appleMerchantId
            || !$applePayGateway->appleMerchantCertPath
            || !$applePayGateway->appleMerchantKeyPath
            || !$applePayGateway->appleMerchantDomain
            || !$applePayGateway->appleMerchantDisplayName
        ) {
            return null;
        }
        $pemCrtPath = _PS_ROOT_DIR_ . '/' . $applePayGateway->appleMerchantCertPath;
        $pemKeyPath = _PS_ROOT_DIR_ . '/' . $applePayGateway->appleMerchantKeyPath;

        $validationPayload = [];
        $validationPayload['merchantIdentifier'] = $applePayGateway->appleMerchantId;
        $validationPayload['displayName'] = $applePayGateway->appleMerchantDisplayName;
        $validationPayload['initiative'] = 'web';
        $validationPayload['initiativeContext'] = $applePayGateway->appleMerchantDomain;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $validationUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($validationPayload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        curl_setopt($ch, CURLOPT_DNS_USE_GLOBAL_CACHE, false);
        curl_setopt($ch, CURLOPT_SSLCERT, $pemCrtPath);
        curl_setopt($ch, CURLOPT_SSLKEY, $pemKeyPath);

        if ($applePayGateway->appleMerchantKeyPassphrase !== null) {
            curl_setopt($ch, CURLOPT_KEYPASSWD, $applePayGateway->appleMerchantKeyPassphrase);
        }

        $validationResponse = curl_exec($ch);

        if (false == $validationResponse) {
            echo curl_error($ch);
            exit;
        }

        curl_close($ch);

        echo json_encode($validationResponse);
        exit;
    }
}
