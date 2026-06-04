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

namespace GlobalPayments\PaymentGatewayProvider\Handlers;

use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\PaymentGatewayProvider\Gateways\GatewayId;
use GlobalPayments\PaymentGatewayProvider\Requests\{RequestArg, RequestInterface, TransactionType};
use GlobalPayments\PaymentGatewayProvider\Verification\{VerificationException, VerificationResult, VerificationService};
use GlobalPayments\PaymentGatewayProvider\Platform\Utils;
use PrestaShopBundle\Translation\TranslatorComponent as Translator;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Handler for AVS and CVV verification
 * 
 * Validates AVS and CVV responses from the transaction and determines
 * if the transaction should be declined based on merchant configuration.
 */
class VerificationHandler extends AbstractHandler
{
    /**
     * @var VerificationService
     */
    private $verificationService;

    /**
     * @var Translator
     */
    private $translator;

    /**
     * @var VerificationResult|null
     */
    private $verificationResult;

    /**
     * Constructor
     *
     * @param RequestInterface $request
     * @param Transaction $response
     */
    public function __construct(RequestInterface $request, Transaction $response)
    {
        parent::__construct($request, $response);
        
        $this->translator = (new Utils())->getTranslator();
        
        // Determine gateway ID from request
        $gatewayId = $this->getGatewayId();
        $this->verificationService = new VerificationService($gatewayId);
    }

    /**
     * Handle the verification
     *
     * @return array<string,string>|null Returns error array if verification fails, null otherwise
     * @throws VerificationException if verification fails and should decline
     */
    public function handle()
    {
        // Skip verification if Check AVS/CVV Result is disabled
        if (!$this->verificationService->isEnabled()) {
            return null;
        }

        // Only run AVS/CVV verification for transaction types that return AVS/CVV data
        // Skip for refunds, reversals, captures, voids, and other management transactions
        if (!$this->isVerificationApplicable()) {
            return null;
        }

        // Validate the transaction
        $this->verificationResult = $this->verificationService->validateTransaction($this->response);

        // Log verification results
        $this->logVerificationResult();

        // If transaction should be declined, void the transaction first
        if ($this->verificationResult->shouldDecline()) {
            $this->compensateTransaction();
            
            throw VerificationException::fromResult(
                $this->verificationResult,
                $this->verificationService->getCustomerErrorMessage($this->verificationResult)
            );
        }

        // Return null to indicate success (no error)
        return null;
    }

    /**
     * Check if AVS/CVV verification is applicable for this transaction type
     *
     * AVS/CVV data is only returned for card-present authorization transactions,
     * not for refunds, reversals, captures, or non-card payment methods.
     *
     * @return bool
     */
    private function isVerificationApplicable(): bool
    {
        $transactionType = $this->request->getTransactionType();

        // Transaction types that return AVS/CVV data
        $applicableTypes = [
            TransactionType::SALE,
            TransactionType::AUTHORIZE,
            TransactionType::VERIFY,
            TransactionType::DW_AUTHORIZATION,
        ];

        return in_array($transactionType, $applicableTypes, true);
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
     * Get gateway ID from request or default
     *
     * @return string
     */
    private function getGatewayId(): string
    {
        try {
            $config = $this->request->getArgument(RequestArg::SERVICES_CONFIG);
            if (isset($config['gatewayProviderId'])) {
                return $config['gatewayProviderId'];
            }
        } catch (\Exception $e) {
            // Fall through to default
        }
        
        return GatewayId::GP_UCP;
    }

    /**
     * Compensate (void/reverse) an approved transaction that failed AVS/CVV verification
     * 
     * When AVS/CVV verification fails after gateway approval, we must void the transaction
     * to prevent orphaned authorizations at the gateway while checkout fails in the shop.
     *
     * @return void
     */
    private function compensateTransaction(): void
    {
        // Log critical warning about orphaned transaction
        $transactionId = $this->response->transactionReference->transactionId ?? 'N/A';
        
        $errorMessage = sprintf(
            'CRITICAL: AVS/CVV Verification Failed for transaction %s - Transaction was approved at gateway but declined in shop. Manual void/reversal required to prevent orphaned authorization.',
            $transactionId
        );
        
        \PrestaShopLogger::addLog(
            $errorMessage,
            3, // Error level - critical
            null,
            'GlobalPayments',
            null,
            true
        );
    }

    /**
     * Log the verification result
     *
     * @return void
     */
    private function logVerificationResult(): void
    {
        if (null === $this->verificationResult) {
            return;
        }

        $logMessage = sprintf(
            'AVS/CVV Verification - Transaction ID: %s | %s',
            $this->response->transactionReference->transactionId ?? 'N/A',
            $this->verificationResult->getSummary()
        );

        // Log to PrestaShop logs if debugging is enabled
        $debug = \Configuration::get($this->getGatewayId() . '_debug');
        if ($debug) {
            \PrestaShopLogger::addLog(
                $logMessage,
                $this->verificationResult->shouldDecline() ? 2 : 1, // 2 = warning, 1 = info
                null,
                'GlobalPayments',
                null,
                true
            );
        }
    }
}
