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

namespace GlobalPayments\PaymentGatewayProvider\Platform\Utils;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * HPP Response Parser
 * 
 * Utility class for parsing HPP gateway responses
 * Contains static methods for extracting data from gateway callbacks
 * Used by both HppReturn and HppStatus as the data structure is
 * allmost identical
 */
class HppResponseParser
{
    /**
     * Extract order ID from gateway response
     * 
     * @param array $gatewayData
     * @return int
     */
    public static function extractOrderId(array $gatewayData)
    {
        // Try to extract from link_data.reference field first (nested structure)
        if (!empty($gatewayData['link_data']['reference'])) {
            $reference = $gatewayData['link_data']['reference'];
            
            if (preg_match('/Order #(\d+)/', $reference, $matches)) {
                return (int) $matches[1];
            }
        }
        
        // Fallback: Try top-level reference field
        if (!empty($gatewayData['reference'])) {
            $reference = $gatewayData['reference'];
            
            if (preg_match('/Order #(\d+)/', $reference, $matches)) {
                return (int) $matches[1];
            }
        }

        // Last resort: try to get from GET parameters
        return isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
    }

    /**
     * Extract transaction ID from HPP callback data
     * 
     * @param array $data 
     * @return string|null 
     */
    public static function extractTransactionId(array $data)
    {
        // The transaction ID is always in the top-level 'id' field
        if (isset($data['id']) && !empty($data['id'])) {
            return $data['id'];
        }

        return null;
    }

    /**
     * Extract payment status from HPP callback data
     * 
     * @param array $data 
     * @return string 
     */
    public static function extractPaymentStatus(array $data)
    {
        if (isset($data['status']) && !empty($data['status'])) {
            return strtoupper($data['status']);
        }

        return 'UNKNOWN';
    }

    /**
     * Extract payment method type from HPP callback data
     * 
     * @param array $data 
     * @return string|null 
     */
    public static function extractPaymentMethodType(array $data)
    {
        // Extract entry_mode which indicates the payment method type
        if (!empty($data['payment_method']['entry_mode'])) {
            return $data['payment_method']['entry_mode'];
        }

        return null;
    }

    /**
     * Extract payment method result code from HPP callback data
     * 
     * @param array $data 
     * @return string|null 
     */
    public static function extractPaymentResultCode(array $data)
    {
        if (!empty($data['payment_method']['result'])) {
            return $data['payment_method']['result'];
        }

        return null;
    }

    /**
     * Extract payment method message from HPP callback data
     * 
     * @param array $data 
     * @return string|null 
     */
    public static function extractPaymentMessage(array $data)
    {
        if (!empty($data['payment_method']['message'])) {
            return $data['payment_method']['message'];
        }

        return null;
    }

    /**
     * Determine if HPP payment was successful from gateway response
     *
     * @param array $gatewayData
     * @return bool true if successful, false otherwise
     */
    public static function isSuccessfulPayment(array $gatewayData)
    {
        $status = $gatewayData['status'] ?? '';
        $resultCode = $gatewayData['payment_method']['result'] ?? '';
        $actionResult = $gatewayData['action']['result_code'] ?? '';
        
        $isSuccessful = strtoupper($status) === 'CAPTURED' && 
                       $resultCode === '00' && 
                       strtoupper($actionResult) === 'SUCCESS';
        
        return $isSuccessful;
    }
     /**
     * Parse gateway data from raw JSON input
     *
     * @param string $rawInput Raw JSON string
     * @return array Parsed gateway data
     * @throws \Exception If parsing fails
     */
    public static function parseGatewayData(string $rawInput): array
    {
        $gatewayData = json_decode($rawInput, true);

        if (!is_array($gatewayData)) {
            $this->logError('Invalid gateway response data', [
                'json_error' => json_last_error_msg(),
            ]);
            throw new \Exception('Invalid gateway response data');
        }

        return $gatewayData;
    }
    /**
     * Get error message from gateway response
     *
     * @param array $gatewayData
     * @return string containing error message
     */
    public static function getErrorMessage(array $gatewayData)
    {
        return $gatewayData['payment_method']['message'] ?? 'Payment failed. Please try again.';
    }
}
