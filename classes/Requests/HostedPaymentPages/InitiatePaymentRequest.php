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

namespace GlobalPayments\PaymentGatewayProvider\Requests\HostedPaymentPages;

use GlobalPayments\Api\Builders\HPPBuilder;
use GlobalPayments\Api\Entities\Address;
use GlobalPayments\Api\Entities\Enums\CaptureMode;
use GlobalPayments\Api\Entities\Enums\ChallengeRequestIndicator;
use GlobalPayments\Api\Entities\Enums\Channel;
use GlobalPayments\Api\Entities\Enums\ExemptStatus;
use GlobalPayments\Api\Entities\Enums\HPPAllowedPaymentMethods;
use GlobalPayments\Api\Entities\Enums\PaymentMethodUsageMode;
use GlobalPayments\Api\Entities\Enums\PhoneNumberType;
use GlobalPayments\Api\Entities\PayerDetails;
use GlobalPayments\Api\Entities\PhoneNumber;
use GlobalPayments\Api\Utils\CountryUtils;
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\CustomerHelper;
use GlobalPayments\PaymentGatewayProvider\Requests\AbstractRequest;
use GlobalPayments\PaymentGatewayProvider\Requests\RequestArg;
use GlobalPayments\PaymentGatewayProvider\Requests\TransactionType;

if (!defined('_PS_VERSION_')) {
    exit;
}

class InitiatePaymentRequest extends AbstractRequest
{
    /**
     * Get transaction type
     *
     * @return string Transaction type constant
     */
    public function getTransactionType(): string
    {
        return TransactionType::HPP_TRANSACTION;
    }

    /**
     * Execute HPP initiate payment request
     *
     * @return \GlobalPayments\Api\Entities\Transaction Transaction with redirect URL
     * @throws \Exception If required data is missing or invalid
     */
    public function doRequest(): \GlobalPayments\Api\Entities\Transaction
    {

        $providerData = $this->getArgument(RequestArg::ASYNC_PAYMENT_DATA);

        // Validate provider data
        if (empty($providerData) || !is_array($providerData)) {
            $this->logError('HPP provider data is missing or invalid');
            throw new \Exception('HPP provider data is missing or invalid');
        }

        // Get required data from arguments
        $orderId = $this->getArgument(RequestArg::ORDER_ID);
        $cartId = $this->getArgument(RequestArg::CART_ID);
        $amount = $this->getArgument(RequestArg::AMOUNT);
        $currency = $this->getArgument(RequestArg::CURRENCY);

        $billingAddress = $this->getArgument(RequestArg::BILLING_ADDRESS);
        $shippingAddress = $this->getArgument(RequestArg::SHIPPING_ADDRESS);

        // Validate and get URLs with fallbacks
        $context = \Context::getContext();

        $returnUrl = $context->link->getModuleLink('globalpayments', 'hppReturn', [], true);
        $statusUrl = $context->link->getModuleLink('globalpayments', 'hppStatus', [], true);
        $cancelUrl = $context->link->getPageLink('order', true);

        //Convert accesnted Characters to plain text equivalents, to prevent invaild URL error
        
        if (!mb_check_encoding($returnUrl, 'ASCII')) {
            $returnUrl = $context->link->getModuleLink('globalpayments', 'hppReturn',
             $this->getSanitizedNotificationUrl($returnUrl), true);
        }

        if (!mb_check_encoding($statusUrl, 'ASCII')) {
            $statusUrl = $context->link->getModuleLink('globalpayments', 'hppStatus',
             $this->getSanitizedNotificationUrl($statusUrl), true);
        }

        if (!mb_check_encoding($cancelUrl, 'ASCII')) {
            $cancelUrl = $this->getSanitizedNotificationUrl($cancelUrl);
        }

        // Ensure URLs are valid
        if (empty($returnUrl) || empty($statusUrl) || empty($cancelUrl)) {
            $this->logError('HPP URLs are missing', [
                'return_url' => $returnUrl,
                'status_url' => $statusUrl,
                'cancel_url' => $cancelUrl
            ]);
            throw new \Exception('HPP return and status URLs are required');
        }

        // Get customer data
        $customerHelper = new CustomerHelper();
        $customerData = $customerHelper->getCustomerDataByCartId($cartId);

        // Get store country code
        $storeCountryCode = $billingAddress['countryCode'] ?? 'US';

        // Create reference text
        $shopName = \Configuration::get('PS_SHOP_NAME') ?: 'PrestaShop Store';
        $refText = $shopName . ' Order #' . $orderId;

        // Create payer details
        $payer = $this->createPayerDetails($customerData, $billingAddress, $shippingAddress);

        // Build HPP payment methods array
        $hppPaymentMethods = [HPPAllowedPaymentMethods::CARD];

        // Create HPP builder
        $hppBuilder = HPPBuilder::create()
            ->withName($refText)
            ->withDescription('Payment for Order #' . $orderId)
            ->withReference($refText)
            ->withAmount($amount)
            ->withPayer($payer)
            ->withCurrency($currency)
            ->withOrderReference($refText)
            ->withNotifications(
                $returnUrl,
                $statusUrl,
                $cancelUrl
            )
            ->withBillingAddress($payer->billingAddress)
            ->withShippingAddress($payer->shippingAddress)
            ->withAddressMatchIndicator($payer->billingAddress == $payer->shippingAddress)
            ->withAuthentication(
                ChallengeRequestIndicator::CHALLENGE_PREFERRED,
                ExemptStatus::LOW_VALUE,
                true
            );

        // Add shipping phone if available, allways check for null on this property
        if (property_exists($payer, 'shippingPhone') && $payer->shippingPhone !== null && $payer->shippingPhone !== '') {
            $hppBuilder->withShippingPhone($payer->shippingPhone);
        }

        // Add digital wallets if enabled
        $enabledWallets = $this->getDigitalWallets();
        if (!empty($enabledWallets)) {
            $hppBuilder->withDigitalWallets($enabledWallets);
        }

        // Add alternative payment methods
        $enabledAlternativePayments = $this->getAlternativePaymentMethods();
        if (!empty($enabledAlternativePayments)) {
            $hppPaymentMethods = array_merge($hppPaymentMethods, $enabledAlternativePayments);
        }

        // Configure transaction settings
        $hppBuilder->withTransactionConfig(
            Channel::CardNotPresent,
            $storeCountryCode,
            CaptureMode::AUTO,
            $hppPaymentMethods,
            PaymentMethodUsageMode::SINGLE
        );

        $transaction = $hppBuilder->execute();

        return $transaction;
    }

    /**
     * Create payer details from customer data and addresses
     *
     * @param object $customerData Customer data
     * @param array $billingAddress Billing address
     * @param array $shippingAddress Shipping address
     * @return PayerDetails Payer details with addresses and contact info
     */
    protected function createPayerDetails(
        object $customerData,
        array $billingAddress,
        array $shippingAddress
    ): PayerDetails {

        $payer = new PayerDetails();
        $payer->firstName = $customerData->firstName ?? '';
        $payer->lastName = $customerData->lastName ?? '';
        $payer->email = $customerData->email ?? '';
        $payer->status = 'NEW';
        //This dictates the external HPP page language
        $payer->language = strtoupper(\Context::getContext()->language->iso_code) ?? 'EN';

        // Get country info for phone number
        $payerCountryInfo = CountryUtils::getCountryInfo($billingAddress['countryCode'] ?? 'US');

        // Set mobile phone if available
        $billingPhone = $billingAddress['phone'] ?? '';
        $shippingPhone = $shippingAddress['phone'] ?? '';

        if (!empty($billingPhone) || !empty($shippingPhone)) {
            $phoneNumber = !empty($billingPhone) ? $billingPhone : $shippingPhone;
            $phoneNumber = $this->stripPhoneNumberAreaCode($phoneNumber, $payerCountryInfo['phoneCode'][0]);
            $payer->mobilePhone = new PhoneNumber(
                $payerCountryInfo['phoneCode'][0],
                $phoneNumber,
                PhoneNumberType::MOBILE
            );
        }

        // Set billing address
        $billingAddr = new Address();
        $billingAddr->streetAddress1 = $billingAddress['address1'] ?? '';
        $billingAddr->streetAddress2 = $billingAddress['address2'] ?? '';
        $billingAddr->city = $billingAddress['city'] ?? '';
        // Only set the state if provided, cannot be an empty string
        if(isset($billingAddress['state']) &&
           !empty($billingAddress['state']) &&
           strlen($billingAddress['state']) < 4
           ){
             $billingAddr->state = $billingAddress['state'];
        }
        $billingAddr->postalCode = $billingAddress['postalCode'] ?? '';
        $billingAddr->countryCode = $billingAddress['countryCode'] ?? '';
        $billingAddr->country = $billingAddress['countryCode'] ?? '';
        $payer->billingAddress = $billingAddr;

        // Set shipping address
        if (!empty($shippingAddress) && isset($shippingAddress['address1'])) {
            $shippingAddr = new Address();
            $shippingAddr->streetAddress1 = $shippingAddress['address1'] ?? '';
            $shippingAddr->streetAddress2 = $shippingAddress['address2'] ?? '';
            $shippingAddr->city = $shippingAddress['city'] ?? '';

             // Only set the state if provided, cannot be an empty string
            if(isset($shippingAddress['state']) &&
               !empty($shippingAddress['state']) &&
               strlen($shippingAddr['state'] < 4 )
               ){
                 $shippingAddr->state = $shippingAddress['state'];
            }
            $shippingAddr->postalCode = $shippingAddress['postalCode'] ?? '';
            $shippingAddr->countryCode = $shippingAddress['countryCode'] ?? '';
            $shippingAddr->country = $shippingAddress['countryCode'] ?? '';
            $payer->shippingAddress = $shippingAddr;

            // Set shipping phone if available
            if (!empty($billingPhone) || !empty($shippingPhone)) {
                $phoneNumber = !empty($shippingPhone) ? $shippingPhone : $billingPhone;
                $phoneNumber = $this->stripPhoneNumberAreaCode($phoneNumber, $payerCountryInfo['phoneCode'][0]);
                $payer->shippingPhone = new PhoneNumber(
                    $payerCountryInfo['phoneCode'][0],
                    $phoneNumber,
                    PhoneNumberType::SHIPPING
                );
            }
        } else {
            $payer->shippingAddress = $billingAddr;
            if (!empty($billingPhone) || !empty($shippingPhone)) {
                $phoneNumber = !empty($billingPhone) ? $billingPhone : $shippingPhone;
                $phoneNumber = $this->stripPhoneNumberAreaCode($phoneNumber, $payerCountryInfo['phoneCode'][0]);

                $payer->shippingPhone = new PhoneNumber(
                    $payerCountryInfo['phoneCode'][0],
                    $phoneNumber,
                    PhoneNumberType::SHIPPING
                );
            }
        }

        return $payer;
    }

    /**
     * Get enabled digital wallets from configuration
     *
     * @return array Array of enabled wallet identifiers
     */
    protected function getDigitalWallets(): array
    {
        $enabledWallets = [];

        // Check configuration for enabled wallets
        if (\Configuration::get('globalpayments_ucp_hppEnableGooglePay')) {
            $enabledWallets[] = 'googlepay';
        }

        if (\Configuration::get('globalpayments_ucp_hppEnableApplePay')) {
            $enabledWallets[] = 'applepay';
        }

        return $enabledWallets;
    }

    /**
     * Get enabled alternative payment methods from configuration
     *
     * @return array Array of enabled payment method constants
     */
    protected function getAlternativePaymentMethods(): array
    {
        $enabledAlternativePayments = [];

        // Check configuration for enabled APMs
        if (\Configuration::get('globalpayments_ucp_hppEnableBlik')) {
            $enabledAlternativePayments[] = HPPAllowedPaymentMethods::BLIK;
        }

        if (\Configuration::get('globalpayments_ucp_hppEnableOpenBanking')) {
            $enabledAlternativePayments[] = HPPAllowedPaymentMethods::BANK_PAYMENT;
        }
        if (\Configuration::get('globalpayments_ucp_hppEnablePayu')) {
            $enabledAlternativePayments[] = HPPAllowedPaymentMethods::PAYU;
        }

        return $enabledAlternativePayments;
    }

    /**
     * Sanitize notification URL by encoding special characters
     *
     * @return array Array of enabled payment method constants
     */
    function getSanitizedNotificationUrl($url) {
        // Ensure UTF-8 encoding
        $utf8Url = mb_convert_encoding($url, 'UTF-8', mb_detect_encoding($url));

        // Parse URL into components
        $parts = parse_url($utf8Url);

        $encodedUrl = '';

        // Scheme and host
        if (isset($parts['scheme'])) {
            $encodedUrl .= $parts['scheme'] . '://';
        }
        if (isset($parts['host'])) {
            $encodedUrl .= $parts['host'];
        }

        // Path
        if (isset($parts['path'])) {
            $segments = explode('/', $parts['path']);
            $segments = array_map('rawurlencode', $segments);
            $encodedUrl .= implode('/', $segments);
        }

        // Query
        if (isset($parts['query'])) {
            parse_str($parts['query'], $queryArray);
            $encodedQuery = http_build_query($queryArray, '', '&', PHP_QUERY_RFC3986);
            $encodedUrl .= '?' . $encodedQuery;
        }

        // Fragment
        if (isset($parts['fragment'])) {
            $encodedUrl .= '#' . rawurlencode($parts['fragment']);
        }

        return $encodedUrl;
    }

    /**
     * Strip area code prefix from phone number
     *
     * @return array Array of enabled payment method constants
     */
    function stripPhoneNumberAreaCode(string $phoneNumber, string $areaCodePrefix): string {
        if (empty($areaCodePrefix)) {
            return $phoneNumber;
        }

        $originalHasLeadingZero = preg_match('/^\s*\(?0/', $phoneNumber);
        $escapedPrefix = preg_quote($areaCodePrefix, '/');
        $pattern = '/^(?:\+|00)?' . $escapedPrefix . '[\s()-]*/';
        $cleaned = preg_replace($pattern, '', $phoneNumber);
        $cleaned = preg_replace('/[\s()-]/', '', $cleaned);

        if ($originalHasLeadingZero && !str_starts_with($cleaned, '0')) {
            $cleaned = '0' . $cleaned;
        }

        return $cleaned;
    }

    /**
     * Get list of required arguments
     *
     * @return array Array of required argument constants
     */
    public function getArgumentList(): array
    {
        return [
            RequestArg::ORDER_ID,
            RequestArg::CART_ID,
            RequestArg::AMOUNT,
            RequestArg::CURRENCY,
            RequestArg::BILLING_ADDRESS,
            RequestArg::SHIPPING_ADDRESS,
            RequestArg::ASYNC_PAYMENT_DATA,
        ];
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
     * Log message with context
     *
     * @param string $message Log message
     * @param int $severity Severity level
     * @param array $context Additional context
     * @return void
     */
    private function log(string $message, int $severity, array $context = []): void
    {
        $logMessage = sprintf('HPP InitiatePaymentRequest: %s', $message);

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
