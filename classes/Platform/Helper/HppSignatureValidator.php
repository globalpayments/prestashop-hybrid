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

declare(strict_types=1);

namespace GlobalPayments\PaymentGatewayProvider\Platform\Helper;

use GlobalPayments\PaymentGatewayProvider\Gateways\GpApiGateway;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * HPP Signature Validator
 *
 * Handles signature validation for HPP payment callbacks and webhooks.
 */
class HppSignatureValidator
{
    /**
     * @var GpApiGateway Gateway instance for credential access
     */
    private GpApiGateway $gateway;

    /**
     * Constructor
     *
     * @param GpApiGateway $gateway Gateway instance
     */
    public function __construct(GpApiGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * Validate HPP request signature
     *
     * @param string|null $rawInput Raw POST data (JSON)
     * @param string|null $gpSignature Signature from X-GP-Signature header
     * @return bool True if signature is valid
     */
    public function validateSignature(
        ?string $rawInput,
        ?string $gpSignature,
    ): bool {
        // Validate input parameters
        if (empty($rawInput) || empty($gpSignature)) {
            $this->logError(
                "HP_HPP",
                'Missing raw input or signature',
                [
                    'raw_input_length' => $rawInput ? strlen($rawInput) : 0,
                    'signature_present' => !empty($gpSignature),
                ]
            );
            return false;
        }

        // Parse JSON input
        $parsedInput = $this->parseJson($rawInput);
        if ($parsedInput === null) {
            return false;
        }

        // Get app key from gateway
        $appKey = $this->getAppKey();
        if ($appKey === null) {
            return false;
        }

        // Re-encode to minified JSON to match GP-API formatting
        $minifiedInput = $this->minifyJson($parsedInput);
        if ($minifiedInput === null) {
            return false;
        }

        // Calculate expected signature using SHA512 (HPP specific)
        $expectedSignature = $this->calculateSignature($minifiedInput, $appKey);


        // Compare signatures (case-insensitive, timing-safe)
        $isValid = hash_equals(strtolower($expectedSignature), strtolower($gpSignature));


        return $isValid;
    }

    /**
     * Parse JSON string
     *
     * @param string $rawInput Raw JSON string
     * @return array|null Parsed data or null on error
     */
    private function parseJson(string $rawInput): ?array
    {
        $parsedInput = json_decode($rawInput, true);

        if (!is_array($parsedInput) || json_last_error() !== JSON_ERROR_NONE) {
            $this->logError(
                "GP_HPP",
                'Failed to parse JSON input',
                [
                    'json_error' => json_last_error_msg(),
                    'json_error_code' => json_last_error(),
                ]
            );
            return null;
        }

        return $parsedInput;
    }

    /**
     * Get app key from gateway
     *
     * @return string|null App key or null if not configured
     */
    private function getAppKey(): ?string
    {
        $appKey = $this->gateway->getCredentialSetting('appKey');

        if (empty($appKey)) {
            return null;
        }

        return $appKey;
    }

    /**
     * Minify JSON (re-encode with specific flags)
     *
     * @param array $data Data to encode
     * @return string|null Minified JSON or null on error
     */
    private function minifyJson(array $data): ?string
    {
        $minified = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($minified === false || json_last_error() !== JSON_ERROR_NONE) {
            $this->logError(
                "GP_HPP",
                'Failed to minify JSON',
                [
                    'json_error' => json_last_error_msg(),
                ]
            );
            return null;
        }

        return $minified;
    }

    /**
     * Calculate SHA512 signature
     *
     * @param string $minifiedInput Minified JSON input
     * @param string $appKey App key
     * @return string SHA512 hash
     */
    private function calculateSignature(string $minifiedInput, string $appKey): string
    {
        return hash('sha512', $minifiedInput . $appKey);
    }

    /**
     * Log signature mismatch
     *
     * @param string $context Context
     * @param string $expected Expected signature
     * @param string $received Received signature
     * @param string $appKey App key
     * @return void
     */
    private function logSignatureMismatch(
        string $context,
        string $expected,
        string $received,
        string $appKey
    ): void {
        $this->logError(
            $context,
            'Signature mismatch',
            [
                'expected_length' => strlen($expected),
                'received_length' => strlen($received),
                'app_key_last_4' => substr($appKey, -4),
                'expected_first_20' => substr($expected, 0, 20),
                'received_first_20' => substr($received, 0, 20),
            ]
        );
    }

    /**
     * Log informational message
     *
     * @param string $context Context
     * @param string $message Message
     * @param array $data Additional data
     * @return void
     */
    private function logInfo(string $context, string $message, array $data = []): void
    {
        $logMessage = sprintf('%s: %s', $context, $message);

        if (!empty($data)) {
            $logMessage .= ' | ' . json_encode($data);
        }

        \PrestaShopLogger::addLog(
            $logMessage,
            \PrestaShopLogger::LOG_SEVERITY_LEVEL_INFORMATIVE,
            null,
            'GlobalPayments'
        );
    }

    /**
     * Log error message
     *
     * @param string $context Context
     * @param string $message Message
     * @param array $data Additional data
     * @return void
     */
    private function logError(string $context, string $message, array $data = []): void
    {
        $logMessage = sprintf('%s: %s', $context, $message);

        if (!empty($data)) {
            $logMessage .= ' | ' . json_encode($data);
        }

        \PrestaShopLogger::addLog(
            $logMessage,
            \PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR,
            null,
            'GlobalPayments'
        );
    }
}
