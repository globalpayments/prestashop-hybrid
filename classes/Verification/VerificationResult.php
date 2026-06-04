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
 * Verification Result
 * 
 * Holds the results of AVS and CVV verification checks.
 */
class VerificationResult
{
    /**
     * AVS response code (normalized)
     *
     * @var string
     */
    private $avsResponseCode = '';

    /**
     * CVV response code (normalized)
     *
     * @var string
     */
    private $cvvResponseCode = '';

    /**
     * Whether AVS check passed
     *
     * @var bool
     */
    private $avsValid = true;

    /**
     * Whether CVV check passed
     *
     * @var bool
     */
    private $cvvValid = true;

    /**
     * Whether transaction should be declined
     *
     * @var bool
     */
    private $shouldDecline = false;

    /**
     * Reasons for decline
     *
     * @var array<string>
     */
    private $declineReasons = [];

    /**
     * AVS error messages
     *
     * @var array<string>
     */
    private $avsErrors = [];

    /**
     * CVV error messages
     *
     * @var array<string>
     */
    private $cvvErrors = [];

    /**
     * Raw AVS data from gateway
     *
     * @var array<string, mixed>
     */
    private $rawAvsData = [];

    /**
     * Raw CVV data from gateway
     *
     * @var array<string, mixed>
     */
    private $rawCvvData = [];

    /**
     * Get AVS response code
     *
     * @return string
     */
    public function getAvsResponseCode(): string
    {
        return $this->avsResponseCode;
    }

    /**
     * Set AVS response code
     *
     * @param string $code
     * @return self
     */
    public function setAvsResponseCode(string $code): self
    {
        $this->avsResponseCode = $code;
        return $this;
    }

    /**
     * Get CVV response code
     *
     * @return string
     */
    public function getCvvResponseCode(): string
    {
        return $this->cvvResponseCode;
    }

    /**
     * Set CVV response code
     *
     * @param string $code
     * @return self
     */
    public function setCvvResponseCode(string $code): self
    {
        $this->cvvResponseCode = $code;
        return $this;
    }

    /**
     * Check if AVS is valid
     *
     * @return bool
     */
    public function isAvsValid(): bool
    {
        return $this->avsValid;
    }

    /**
     * Set AVS validity
     *
     * @param bool $valid
     * @return self
     */
    public function setAvsValid(bool $valid): self
    {
        $this->avsValid = $valid;
        return $this;
    }

    /**
     * Check if CVV is valid
     *
     * @return bool
     */
    public function isCvvValid(): bool
    {
        return $this->cvvValid;
    }

    /**
     * Set CVV validity
     *
     * @param bool $valid
     * @return self
     */
    public function setCvvValid(bool $valid): self
    {
        $this->cvvValid = $valid;
        return $this;
    }

    /**
     * Check if transaction should be declined
     *
     * @return bool
     */
    public function shouldDecline(): bool
    {
        return $this->shouldDecline;
    }

    /**
     * Set whether transaction should be declined
     *
     * @param bool $decline
     * @return self
     */
    public function setShouldDecline(bool $decline): self
    {
        $this->shouldDecline = $decline;
        return $this;
    }

    /**
     * Get decline reasons
     *
     * @return array<string>
     */
    public function getDeclineReasons(): array
    {
        return $this->declineReasons;
    }

    /**
     * Set decline reasons
     *
     * @param array<string> $reasons
     * @return self
     */
    public function setDeclineReasons(array $reasons): self
    {
        $this->declineReasons = $reasons;
        return $this;
    }

    /**
     * Get AVS errors
     *
     * @return array<string>
     */
    public function getAvsErrors(): array
    {
        return $this->avsErrors;
    }

    /**
     * Add AVS error
     *
     * @param string $error
     * @return self
     */
    public function addAvsError(string $error): self
    {
        $this->avsErrors[] = $error;
        return $this;
    }

    /**
     * Get CVV errors
     *
     * @return array<string>
     */
    public function getCvvErrors(): array
    {
        return $this->cvvErrors;
    }

    /**
     * Add CVV error
     *
     * @param string $error
     * @return self
     */
    public function addCvvError(string $error): self
    {
        $this->cvvErrors[] = $error;
        return $this;
    }

    /**
     * Get all errors (AVS + CVV)
     *
     * @return array<string>
     */
    public function getAllErrors(): array
    {
        return array_merge($this->avsErrors, $this->cvvErrors);
    }

    /**
     * Check if verification passed overall
     *
     * @return bool
     */
    public function isPassed(): bool
    {
        return !$this->shouldDecline;
    }

    /**
     * Get raw AVS data
     *
     * @return array<string, mixed>
     */
    public function getRawAvsData(): array
    {
        return $this->rawAvsData;
    }

    /**
     * Set raw AVS data
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public function setRawAvsData(array $data): self
    {
        $this->rawAvsData = $data;
        return $this;
    }

    /**
     * Get raw CVV data
     *
     * @return array<string, mixed>
     */
    public function getRawCvvData(): array
    {
        return $this->rawCvvData;
    }

    /**
     * Set raw CVV data
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public function setRawCvvData(array $data): self
    {
        $this->rawCvvData = $data;
        return $this;
    }

    /**
     * Convert result to array for storage
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'avs' => [
                'code' => $this->avsResponseCode,
                'description' => AvsResponseCodes::getDescription($this->avsResponseCode),
                'valid' => $this->avsValid,
                'raw' => $this->rawAvsData,
            ],
            'cvv' => [
                'code' => $this->cvvResponseCode,
                'description' => CvvResponseCodes::getDescription($this->cvvResponseCode),
                'valid' => $this->cvvValid,
                'raw' => $this->rawCvvData,
            ],
            'declined' => $this->shouldDecline,
            'decline_reasons' => $this->declineReasons,
            'errors' => $this->getAllErrors(),
        ];
    }

    /**
     * Get formatted summary for logging/display
     *
     * @return string
     */
    public function getSummary(): string
    {
        $parts = [];
        
        if (!empty($this->avsResponseCode)) {
            $parts[] = sprintf(
                'AVS: %s (%s)',
                $this->avsResponseCode,
                $this->avsValid ? 'PASS' : 'FAIL'
            );
        }
        
        if (!empty($this->cvvResponseCode)) {
            $parts[] = sprintf(
                'CVV: %s (%s)',
                $this->cvvResponseCode,
                $this->cvvValid ? 'PASS' : 'FAIL'
            );
        }

        if ($this->shouldDecline) {
            $parts[] = 'Transaction should be DECLINED';
        }

        return implode(' | ', $parts);
    }
}
