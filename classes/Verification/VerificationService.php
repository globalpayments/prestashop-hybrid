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

use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\PaymentGatewayProvider\Gateways\GatewayId;
use PrestaShopBundle\Translation\TranslatorComponent as Translator;
use GlobalPayments\PaymentGatewayProvider\Platform\Utils;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Verification Service for AVS and CVV checks
 * 
 * Handles the validation of AVS and CVV responses based on merchant configuration.
 * Uses Magento-style approach where merchant selects decline codes (codes that should reject the transaction).
 */
class VerificationService
{
    /**
     * Gateway ID for configuration prefix
     *
     * @var string
     */
    private $gatewayId;

    /**
     * @var Translator
     */
    private $translator;

    /**
     * Check AVS/CVV Result enabled (master toggle)
     *
     * @var bool
     */
    private $checkAvsResult;

    /**
     * AVS decline codes (codes that should decline the transaction)
     *
     * @var array<string>
     */
    private $avsDeclineCodes;

    /**
     * CVV decline codes (codes that should decline the transaction)
     *
     * @var array<string>
     */
    private $cvvDeclineCodes;

    /**
     * VerificationService constructor.
     *
     * @param string $gatewayId Gateway ID for configuration prefix
     */
    public function __construct(string $gatewayId = GatewayId::GP_UCP)
    {
        $this->gatewayId = $gatewayId;
        $this->translator = (new Utils())->getTranslator();
        $this->loadConfiguration();
    }

    /**
     * Load verification configuration from PrestaShop settings
     *
     * @return void
     */
    private function loadConfiguration(): void
    {
        // Master toggle for AVS/CVV checking
        $this->checkAvsResult = \Configuration::get($this->gatewayId . '_checkAvsResult') === '1';
        
        // Load AVS decline codes (codes that should trigger a decline)
        $avsCodes = \Configuration::get($this->gatewayId . '_avsDeclineCodes');
        $this->avsDeclineCodes = !empty($avsCodes) 
            ? array_map('trim', explode(',', $avsCodes))
            : AvsResponseCodes::getDefaultDeclineCodes();

        // Load CVV decline codes (codes that should trigger a decline)
        $cvvCodes = \Configuration::get($this->gatewayId . '_cvvDeclineCodes');
        $this->cvvDeclineCodes = !empty($cvvCodes)
            ? array_map('trim', explode(',', $cvvCodes))
            : CvvResponseCodes::getDefaultDeclineCodes();
    }

    /**
     * Check if AVS/CVV verification is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->checkAvsResult;
    }

    /**
     * Validate AVS and CVV responses from a transaction
     *
     * @param Transaction $transaction The transaction response from GP-API
     * @return VerificationResult The validation result
     */
    public function validateTransaction(Transaction $transaction): VerificationResult
    {
        $result = new VerificationResult();

        // Extract AVS data from transaction
        $avsPostalCode = $transaction->avsResponseCode ?? null;
        $avsAddress = $transaction->avsAddressResponse ?? null;
        $avsMessage = $transaction->avsResponseMessage ?? null;

        // Extract CVV data from transaction
        $cvvCode = $transaction->cvnResponseCode ?? null;
        $cvvMessage = $transaction->cvnResponseMessage ?? null;

        // Also check card issuer response for additional data
        if (!empty($transaction->cardIssuerResponse)) {
            $cardIssuer = $transaction->cardIssuerResponse;
            if (empty($avsPostalCode) && !empty($cardIssuer->avsPostalCodeResult)) {
                $avsPostalCode = $cardIssuer->avsPostalCodeResult;
            }
            if (empty($avsAddress) && !empty($cardIssuer->avsAddressResult)) {
                $avsAddress = $cardIssuer->avsAddressResult;
            }
            if (empty($cvvCode) && !empty($cardIssuer->cvvResult)) {
                $cvvCode = $cardIssuer->cvvResult;
            }
        }

        // Store raw response data
        $result->setRawAvsData([
            'postalCode' => $avsPostalCode,
            'address' => $avsAddress,
            'message' => $avsMessage,
        ]);

        $result->setRawCvvData([
            'code' => $cvvCode,
            'message' => $cvvMessage,
        ]);

        // Normalize response codes to standard single-letter codes
        $normalizedAvsCode = AvsResponseCodes::normalizeResponse($avsPostalCode, $avsAddress);
        $normalizedCvvCode = CvvResponseCodes::normalizeResponse($cvvCode);

        $result->setAvsResponseCode($normalizedAvsCode);
        $result->setCvvResponseCode($normalizedCvvCode);

        // If checking is disabled, mark everything as valid
        if (!$this->checkAvsResult) {
            $result->setAvsValid(true);
            $result->setCvvValid(true);
            $result->setShouldDecline(false);
            return $result;
        }

        // Check if AVS code is in the decline list
        $avsInDeclineList = $this->isAvsCodeInDeclineList($normalizedAvsCode);
        $result->setAvsValid(!$avsInDeclineList);
        
        if ($avsInDeclineList) {
            $result->addAvsError($this->translator->trans(
                'AVS verification failed: %code% - %description%',
                [
                    '%code%' => $normalizedAvsCode,
                    '%description%' => AvsResponseCodes::getDescription($normalizedAvsCode),
                ],
                'Modules.Globalpayments.Admin'
            ));
        }

        // Check if CVV code is in the decline list
        $cvvInDeclineList = $this->isCvvCodeInDeclineList($normalizedCvvCode);
        $result->setCvvValid(!$cvvInDeclineList);
        
        if ($cvvInDeclineList) {
            $result->addCvvError($this->translator->trans(
                'CVV verification failed: %code% - %description%',
                [
                    '%code%' => $normalizedCvvCode,
                    '%description%' => CvvResponseCodes::getDescription($normalizedCvvCode),
                ],
                'Modules.Globalpayments.Admin'
            ));
        }

        // Determine if transaction should be declined
        $shouldDecline = $avsInDeclineList || $cvvInDeclineList;
        $declineReasons = [];

        if ($avsInDeclineList) {
            $declineReasons[] = 'AVS';
        }

        if ($cvvInDeclineList) {
            $declineReasons[] = 'CVV';
        }

        $result->setShouldDecline($shouldDecline);
        $result->setDeclineReasons($declineReasons);

        return $result;
    }

    /**
     * Check if AVS response code is in the decline list
     *
     * @param string $code The AVS response code
     * @return bool True if code should trigger a decline
     */
    public function isAvsCodeInDeclineList(string $code): bool
    {
        return in_array(strtoupper($code), $this->avsDeclineCodes, true);
    }

    /**
     * Check if CVV response code is in the decline list
     *
     * @param string $code The CVV response code
     * @return bool True if code should trigger a decline
     */
    public function isCvvCodeInDeclineList(string $code): bool
    {
        return in_array(strtoupper($code), $this->cvvDeclineCodes, true);
    }

    /**
     * Get the customer-friendly error message for verification failure
     *
     * @param VerificationResult $result
     * @return string
     */
    public function getCustomerErrorMessage(VerificationResult $result): string
    {
        if ($result->shouldDecline()) {
            $declineReasons = $result->getDeclineReasons();

            /**
             * The purpose of this section is to provide a more descriptive customer-facing decline message when
             * there is an AVS and/or CVV decline. These strings are now translation-driven and should be defined
             * for every supported locale.
             */
            $isAvsCvvDeclineOnly = empty(array_diff($declineReasons, ['AVS', 'CVV']));

            if ($isAvsCvvDeclineOnly) {
                $hasAvsFailure = in_array('AVS', $declineReasons, true);
                $hasCvvFailure = in_array('CVV', $declineReasons, true);

                if ($hasAvsFailure && $hasCvvFailure) {
                    return $this->translator->trans(
                        'Transaction declined due to billing address and CVV verification failure. Please check your billing address and card details, then try again.',
                        [],
                        'Modules.Globalpayments.Shop'
                    );
                }

                if ($hasAvsFailure) {
                    return $this->translator->trans(
                        'Transaction declined due to billing address verification failure. Please check your billing address and card details, then try again.',
                        [],
                        'Modules.Globalpayments.Shop'
                    );
                }

                if ($hasCvvFailure) {
                    return $this->translator->trans(
                        'Transaction declined due to CVV verification failure. Please check your billing address and card details, then try again.',
                        [],
                        'Modules.Globalpayments.Shop'
                    );
                }
            }

            return $this->translator->trans(
                'Your card could not be verified. Please check your billing address and card details, then try again.',
                [],
                'Modules.Globalpayments.Shop'
            );
        }

        return '';
    }

    /**
     * Get AVS decline codes configured
     *
     * @return array<string>
     */
    public function getAvsDeclineCodes(): array
    {
        return $this->avsDeclineCodes;
    }

    /**
     * Get CVV decline codes configured
     *
     * @return array<string>
     */
    public function getCvvDeclineCodes(): array
    {
        return $this->cvvDeclineCodes;
    }
}
