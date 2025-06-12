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
            $threeDSecureData->serverTransactionId = $threeDSecureRequestData->versionCheckData->serverTransactionId;
            $methodUrlCompletion = ($threeDSecureRequestData->versionCheckData->methodData
                && $threeDSecureRequestData->versionCheckData->methodUrl) ?
                    MethodUrlCompletion::YES : MethodUrlCompletion::NO;

            $threeDSecureData = Secure3dService::initiateAuthentication($paymentMethod, $threeDSecureData)
                ->withAmount($requestData[RequestArg::AMOUNT])
                ->withCurrency($requestData[RequestArg::CURRENCY])
                ->withOrderCreateDate(date('Y-m-d H:i:s'))
                ->withAddress($this->mapAddress($requestData[RequestArg::BILLING_ADDRESS]), AddressType::BILLING)
                ->withAddress($this->mapAddress($requestData[RequestArg::SHIPPING_ADDRESS]), AddressType::SHIPPING)
                ->withCustomerEmail($requestData[RequestArg::EMAIL_ADDRESS])
                ->withAuthenticationSource($threeDSecureRequestData->authenticationSource)
                ->withAuthenticationRequestType($threeDSecureRequestData->authenticationRequestType)
                ->withMessageCategory($threeDSecureRequestData->messageCategory)
                ->withChallengeRequestIndicator($threeDSecureRequestData->challengeRequestIndicator)
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
                'message' => $e->getMessage(),
            ];
        }

        return $response;
    }

    private function getBrowserData($requestData)
    {
        $browserDataRequest = $requestData[RequestArg::THREE_D_SECURE_DATA]->browserData;
        $browserData = new BrowserData();
        $browserData->acceptHeader = isset($_SERVER['HTTP_ACCEPT']) ?
            \Tools::safeOutput($_SERVER['HTTP_ACCEPT']) : '';
        $browserData->colorDepth = $browserDataRequest->colorDepth;
        $browserData->ipAddress = isset($_SERVER['REMOTE_ADDR']) ?
            \Tools::safeOutput($_SERVER['REMOTE_ADDR']) : '';
        $browserData->javaEnabled = $browserDataRequest->javaEnabled ?? false;
        $browserData->javaScriptEnabled = $browserDataRequest->javascriptEnabled;
        $browserData->language = $browserDataRequest->language;
        $browserData->screenHeight = $browserDataRequest->screenHeight;
        $browserData->screenWidth = $browserDataRequest->screenWidth;
        $browserData->challengWindowSize = $requestData[RequestArg::THREE_D_SECURE_DATA]->challengeWindow->windowSize;
        $browserData->timeZone = 0;
        $browserData->userAgent = $browserDataRequest->userAgent;

        return $browserData;
    }

    private function mapAddress($addressData)
    {
        $address = new Address();
        $address->countryCode = CountryUtils::getNumericCodeByCountry($addressData->country);

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
