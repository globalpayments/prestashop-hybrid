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

namespace GlobalPayments\PaymentGatewayProvider\Verification;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * CVV (Card Verification Value) Response Codes
 * 
 * Contains response codes returned by GP-API for CVV verification.
 * Uses standard industry CVV response codes.
 */
class CvvResponseCodes
{
    // Standard CVV Response Codes
    public const M = 'M'; // CVV Match
    public const N = 'N'; // CVV Not Matching
    public const P = 'P'; // Not Processed
    public const S = 'S'; // Result not present (CVV should be on card but merchant indicated not provided)
    public const U = 'U'; // Issuer not certified
    public const UNKNOWN = '?'; // CVV unrecognized

    /**
     * Get all available CVV response codes with descriptions
     *
     * @return array<string, string>
     */
    public static function getAll(): array
    {
        return [
            self::M => 'M - Match',
            self::N => 'N - Not Matching',
            self::P => 'P - Not Processed',
            self::S => 'S - Result not present',
            self::U => 'U - Issuer not certified',
            self::UNKNOWN => '? - CVV unrecognized',
        ];
    }

    /**
     * Get default decline codes (codes that should trigger a decline by default)
     *
     * @return array<string>
     */
    public static function getDefaultDeclineCodes(): array
    {
        return [
            self::N, // Not Matching
            self::P, // Not Processed
        ];
    }

    /**
     * Check if a response code is valid
     *
     * @param string $code
     * @return bool
     */
    public static function isValid(string $code): bool
    {
        return array_key_exists($code, self::getAll());
    }

    /**
     * Get the description for a response code
     *
     * @param string $code
     * @return string
     */
    public static function getDescription(string $code): string
    {
        $codes = self::getAll();
        return $codes[$code] ?? 'Unknown CVV response code: ' . $code;
    }

    /**
     * Normalize CVV response from GP-API to standard single-letter code
     * Maps various response formats to standard codes
     *
     * @param string|null $cvvResult
     * @return string
     */
    public static function normalizeResponse(?string $cvvResult): string
    {
        // Handle null or empty values
        if (empty($cvvResult)) {
            return self::U; // Issuer not certified / not available
        }

        // Normalize to uppercase and trim
        $cvvResult = strtoupper(trim($cvvResult));

        // If already a standard single-letter code, return it
        if (strlen($cvvResult) === 1 && self::isValid($cvvResult)) {
            return $cvvResult;
        }

        // Map GP-API descriptive responses to standard codes
        switch ($cvvResult) {
            case 'MATCHED':
                return self::M;

            case 'NOT_MATCHED':
                return self::N;

            case 'NOT_CHECKED':
            case 'NOT_PROCESSED':
                return self::P;

            case 'NOT_PRESENT':
                return self::S;

            case 'ISSUER_NOT_CERTIFIED':
            case 'UNAVAILABLE':
                return self::U;

            default:
                return self::UNKNOWN;
        }
    }
}
