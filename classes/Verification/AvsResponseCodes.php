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
 * AVS (Address Verification Service) Response Codes
 * 
 * Contains response codes returned by GP-API for address verification.
 * Uses standard industry AVS response codes.
 */
class AvsResponseCodes
{
    // Standard AVS Response Codes
    public const A = 'A'; // Address matches, ZIP does not
    public const N = 'N'; // Neither address nor ZIP matches
    public const R = 'R'; // Retry - system unable to respond
    public const U = 'U'; // Visa/Discover card AVS not supported
    public const S = 'S'; // Master/Amex card AVS not supported
    public const Z = 'Z'; // 9-digit ZIP matches, address does not
    public const W = 'W'; // 9-digit ZIP matches, address does not (Master/Amex)
    public const Y = 'Y'; // 5-digit ZIP and address match (Visa/Discover)
    public const X = 'X'; // 5-digit ZIP and address match (Master/Amex)
    public const G = 'G'; // Address not verified for international transaction
    public const B = 'B'; // Address matches, postal code not verified
    public const C = 'C'; // Address and postal code not verified
    public const D = 'D'; // Address and postal code match (international)
    public const I = 'I'; // Address not verified (international)
    public const M = 'M'; // Address and postal code match (international)
    public const P = 'P'; // Postal code matches, address not verified

    /**
     * Get all available AVS response codes with descriptions
     *
     * @return array<string, string>
     */
    public static function getAll(): array
    {
        return [
            self::A => 'A - Address matches, ZIP No Match',
            self::N => 'N - Neither address or zip code match',
            self::R => 'R - Retry - system unable to respond',
            self::U => 'U - Visa / Discover card AVS not supported',
            self::S => 'S - Master / Amex card AVS not supported',
            self::Z => 'Z - Visa / Discover card 9-digit zip code match, address no match',
            self::W => 'W - Master / Amex card 9-digit zip code match, address no match',
            self::Y => 'Y - Visa / Discover card 5-digit zip code and address match',
            self::X => 'X - Master / Amex card 5-digit zip code and address match',
            self::G => 'G - Address not verified for International transaction',
            self::B => 'B - Address matches, postal code not verified',
            self::C => 'C - Address and postal code not verified',
            self::D => 'D - Address and postal code match (international)',
            self::I => 'I - Address not verified (international)',
            self::M => 'M - Address and postal code match (international)',
            self::P => 'P - Postal code matches, address not verified',
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
            self::N, // Neither address or zip code match
            self::R, // Retry - system unable to respond
            self::U, // AVS not supported
            self::S, // AVS not supported
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
        return array_key_exists(strtoupper($code), self::getAll());
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
        return $codes[strtoupper($code)] ?? 'Unknown AVS response code: ' . $code;
    }

    /**
     * Normalize AVS response from GP-API to standard single-letter code
     * Maps various response formats to standard codes
     *
     * @param string|null $postalCodeResult
     * @param string|null $addressResult
     * @return string
     */
    public static function normalizeResponse(?string $postalCodeResult, ?string $addressResult): string
    {
        // Handle null or empty values
        if (empty($postalCodeResult) && empty($addressResult)) {
            return self::U; // Not verified/unavailable
        }

        // Normalize to uppercase
        $postalCodeResult = strtoupper(trim($postalCodeResult ?? ''));
        $addressResult = strtoupper(trim($addressResult ?? ''));

        // If the response is already a single letter code, return it
        if (strlen($postalCodeResult) === 1 && self::isValid($postalCodeResult)) {
            return $postalCodeResult;
        }

        // Map GP-API descriptive responses to standard codes
        $postalMatch = in_array($postalCodeResult, ['MATCHED', 'M', 'Y', 'X', 'Z', 'W']);
        $addressMatch = in_array($addressResult, ['MATCHED', 'M', 'Y', 'X', 'A', 'B', 'D']);
        
        // Both match
        if ($postalMatch && $addressMatch) {
            return self::Y;
        }
        
        // Address matches, postal doesn't
        if ($addressMatch && !$postalMatch) {
            return self::A;
        }
        
        // Postal matches, address doesn't
        if ($postalMatch && !$addressMatch) {
            return self::Z;
        }

        // Neither match
        if (in_array($postalCodeResult, ['NOT_MATCHED', 'N']) || 
            in_array($addressResult, ['NOT_MATCHED', 'N'])) {
            return self::N;
        }

        // Not checked / Not verified
        if (in_array($postalCodeResult, ['NOT_CHECKED', 'U', 'UNAVAILABLE']) ||
            in_array($addressResult, ['NOT_CHECKED', 'U', 'UNAVAILABLE'])) {
            return self::U;
        }

        // International not verified
        if ($postalCodeResult === 'G' || $addressResult === 'G') {
            return self::G;
        }

        return self::U; // Default to unavailable
    }
}
