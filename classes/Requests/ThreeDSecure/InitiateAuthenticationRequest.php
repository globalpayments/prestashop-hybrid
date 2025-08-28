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

namespace GlobalPayments\PaymentGatewayProvider\Requests\ThreeDSecure;

use GlobalPayments\Api\Entities\Address;
use GlobalPayments\Api\Entities\BrowserData;
use GlobalPayments\Api\Entities\Enums\AddressType;
use GlobalPayments\Api\Entities\Enums\MethodUrlCompletion;
use GlobalPayments\Api\Entities\ThreeDSecure;
use GlobalPayments\Api\PaymentMethods\CreditCardData;
use GlobalPayments\Api\Services\Secure3dService;
use GlobalPayments\Api\Utils\CountryUtils;
use GlobalPayments\PaymentGatewayProvider\Requests\RequestArg;
use GlobalPayments\PaymentGatewayProvider\Requests\TransactionType;

if (!defined('_PS_VERSION_')) {
    exit;
}

class InitiateAuthenticationRequest extends AbstractAuthenticationsRequest
{
    /**
     * Country codes to send the state for
     * CA: "124", US: "840"
     *
     * @var array
     */
    private $country_codes = [124, 840];

    public function getTransactionType()
    {
        return TransactionType::INITIATE_AUTHENTICATION;
    }

    public function doRequest()
    {
        $response = [];
        $requestData = $this->getArguments();

        try {
            $paymentMethod = new CreditCardData();
            $paymentMethod->token = $this->getToken($requestData);

            $threeDSecureRequestData = $requestData[RequestArg::THREE_D_SECURE_DATA];
            $threeDSecureData = new ThreeDSecure();
            $threeDSecureData->serverTransactionId = $threeDSecureRequestData->versionCheckData->serverTransactionId ?? null;
            // Since we skip method step, always set to NO
            $methodUrlCompletion = MethodUrlCompletion::NO;

            // Ensure we always have a valid email
            $emailAddress = $requestData[RequestArg::EMAIL_ADDRESS] ?? null;
            if (empty($emailAddress) || !filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
                $emailAddress = 'customer@example.com';
            }

            $threeDSecureData = Secure3dService::initiateAuthentication($paymentMethod, $threeDSecureData)
                ->withAmount($requestData[RequestArg::AMOUNT])
                ->withCurrency($requestData[RequestArg::CURRENCY])
                ->withOrderCreateDate(date('Y-m-d H:i:s'))
                ->withAddress($this->mapAddress($requestData[RequestArg::BILLING_ADDRESS] ?? null), AddressType::BILLING)
                ->withAddress($this->mapAddress($requestData[RequestArg::SHIPPING_ADDRESS] ?? null), AddressType::SHIPPING)
                ->withCustomerEmail($emailAddress)
                ->withAuthenticationSource($threeDSecureRequestData->authenticationSource ?? 'BROWSER')
                ->withAuthenticationRequestType($threeDSecureRequestData->authenticationRequestType ?? 'PAYMENT_TRANSACTION')
                ->withMessageCategory($threeDSecureRequestData->messageCategory ?? 'PAYMENT_AUTHENTICATION')
                ->withChallengeRequestIndicator($threeDSecureRequestData->challengeRequestIndicator ?? 'NO_PREFERENCE')
                ->withBrowserData($this->getBrowserData($requestData))
                ->withMethodUrlCompletion($methodUrlCompletion)
                ->execute();

            $response['liabilityShift'] = $threeDSecureData->liabilityShift;
            // frictionless flow
            if ($threeDSecureData->status !== 'CHALLENGE_REQUIRED') {
                $response['result'] = $threeDSecureData->status;
                $response['authenticationValue'] = $threeDSecureData->authenticationValue;
                $response['serverTransactionId'] = $threeDSecureData->serverTransactionId;
                $response['messageVersion'] = $threeDSecureData->messageVersion;
                $response['eci'] = $threeDSecureData->eci;
            } else { // challenge flow
                $response['status'] = $threeDSecureData->status;
                $response['challengeMandated'] = $threeDSecureData->challengeMandated;
                $response['challenge']['requestUrl'] = $threeDSecureData->issuerAcsUrl;
                $response['challenge']['encodedChallengeRequest'] = $threeDSecureData->payerAuthenticationRequest;
                $response['challenge']['messageType'] = $threeDSecureData->messageType;
            }
        } catch (\Exception $e) {
            $response = [
                'error' => true,
                'message' => 'Authentication failed: ' . $e->getMessage(),
            ];
        }        
        return $response;
    }

    private function getBrowserData($requestData)
    {
        $browserDataRequest = $requestData[RequestArg::THREE_D_SECURE_DATA]->browserData ?? null;
        $browserData = new BrowserData();
        $browserData->acceptHeader = isset($_SERVER['HTTP_ACCEPT']) ?
            \Tools::safeOutput($_SERVER['HTTP_ACCEPT']) : 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
        $browserData->colorDepth = $browserDataRequest->colorDepth ?? 24;
        $browserData->ipAddress = isset($_SERVER['REMOTE_ADDR']) ?
            \Tools::safeOutput($_SERVER['REMOTE_ADDR']) : '127.0.0.1';
        $browserData->javaEnabled = $browserDataRequest->javaEnabled ?? false;
        $browserData->javaScriptEnabled = $browserDataRequest->javascriptEnabled ?? true;
        $browserData->language = $browserDataRequest->language ?? 'en-US';
        $browserData->screenHeight = $browserDataRequest->screenHeight ?? 1080;
        $browserData->screenWidth = $browserDataRequest->screenWidth ?? 1920;
        $browserData->challengWindowSize = $requestData[RequestArg::THREE_D_SECURE_DATA]->challengeWindow->windowSize ?? 'WINDOWED_500X600';
        $browserData->timeZone = 0;
        $browserData->userAgent = $browserDataRequest->userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0 (compatible)';

        return $browserData;
    }

    private function mapAddress($addressData)
    {
        $address = new Address();
        // Handle null or empty address data
        if (empty($addressData) || !is_object($addressData)) {
            // Set minimal required fields for 3DS
            $address->countryCode = 840; // Default to US
            $address->streetAddress1 = 'N/A';
            $address->city = 'N/A';
            $address->postalCode = '00000';
            return $address;
        }
        $address->countryCode = CountryUtils::getNumericCodeByCountry($addressData->country ?? 'US');

        foreach ($addressData as $key => $value) {
            if (property_exists($address, $key) && !empty($value)) {
                if ('state' == $key && !in_array($address->countryCode, $this->country_codes)) {
                    continue;
                }
                $address->{$key} = $value;
            }
        }

        return $address;
    }
}
