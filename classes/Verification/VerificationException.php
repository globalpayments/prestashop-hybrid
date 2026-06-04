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
 * Verification Exception
 * 
 * Exception thrown when AVS or CVV verification fails.
 * The base getMessage() returns a customer-friendly message.
 * Use getDetailedMessage() for logging/debugging.
 */
class VerificationException extends \Exception
{
    /**
     * Verification result
     *
     * @var VerificationResult|null
     */
    private $verificationResult;

    /**
     * Detailed internal message for logging
     *
     * @var string
     */
    private $detailedMessage;

    /**
     * Constructor
     *
     * @param string $customerMessage The customer-friendly message (returned by getMessage())
     * @param string $detailedMessage The internal/log message
     * @param VerificationResult|null $result The verification result
     * @param int $code Exception code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $customerMessage = '',
        string $detailedMessage = '',
        ?VerificationResult $result = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($customerMessage, $code, $previous);
        $this->detailedMessage = $detailedMessage ?: $customerMessage;
        $this->verificationResult = $result;
    }

    /**
     * Get the verification result
     *
     * @return VerificationResult|null
     */
    public function getVerificationResult(): ?VerificationResult
    {
        return $this->verificationResult;
    }

    /**
     * Get detailed message for logging/debugging
     *
     * @return string
     */
    public function getDetailedMessage(): string
    {
        return $this->detailedMessage;
    }

    /**
     * Get customer-friendly error message (alias for getMessage())
     *
     * @return string
     */
    public function getCustomerMessage(): string
    {
        return $this->getMessage();
    }

    /**
     * Create exception from verification result
     *
     * @param VerificationResult $result
     * @param string $customerMessage
     * @return self
     */
    public static function fromResult(VerificationResult $result, string $customerMessage = ''): self
    {
        $reasons = $result->getDeclineReasons();
        $detailedMessage = sprintf(
            'Transaction declined due to %s verification failure. AVS: %s, CVV: %s',
            implode(' and ', $reasons),
            $result->getAvsResponseCode() ?: 'N/A',
            $result->getCvvResponseCode() ?: 'N/A'
        );

        if (empty($customerMessage)) {
            $customerMessage = 'Your card could not be verified. Please check your billing address and card details, then try again.';
        }

        return new self($customerMessage, $detailedMessage, $result);
    }
}
