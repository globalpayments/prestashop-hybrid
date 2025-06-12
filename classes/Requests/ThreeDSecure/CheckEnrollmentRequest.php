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

use GlobalPayments\Api\Entities\Enums\Secure3dStatus;
use GlobalPayments\Api\Entities\Enums\Secure3dVersion;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\PaymentMethods\CreditCardData;
use GlobalPayments\Api\Services\Secure3dService;
use GlobalPayments\PaymentGatewayProvider\Platform\Utils;
use GlobalPayments\PaymentGatewayProvider\Requests\RequestArg;
use GlobalPayments\PaymentGatewayProvider\Requests\TransactionType;

if (!defined('_PS_VERSION_')) {
    exit;
}

class CheckEnrollmentRequest extends AbstractAuthenticationsRequest
{
    public const NO_RESPONSE = 'NO_RESPONSE';

    public function getTransactionType()
    {
        return TransactionType::CHECK_ENROLLMENT;
    }

    public function doRequest()
    {
        $response = [];
        $requestData = $this->getArguments();
        $translator = (new Utils())->getTranslator();
        $errorMessage = $translator->trans(
            'Please try again with another card.',
            [],
            'Modules.Globalpayments.Shop'
        );

        try {
            $paymentMethod = new CreditCardData();
            $paymentMethod->token = $this->getToken($requestData);

            $threeDSecureData = Secure3dService::checkEnrollment($paymentMethod)
                ->withAmount($requestData[RequestArg::AMOUNT])
                ->withCurrency($requestData[RequestArg::CURRENCY])
                ->execute();

            $response['enrolled'] = $threeDSecureData->enrolled ?? Secure3dStatus::NOT_ENROLLED;
            $response['version'] = $threeDSecureData->getVersion();
            $response['status'] = $threeDSecureData->status;
            $response['liabilityShift'] = $threeDSecureData->liabilityShift;
            $response['serverTransactionId'] = $threeDSecureData->serverTransactionId;
            $response['sessionDataFieldName'] = $threeDSecureData->sessionDataFieldName;

            if (Secure3dStatus::ENROLLED !== $threeDSecureData->enrolled) {
                return $response;
            }

            if (Secure3dVersion::TWO === $threeDSecureData->getVersion()) {
                $response['methodUrl'] = $threeDSecureData->issuerAcsUrl;
                $response['methodData'] = $threeDSecureData->payerAuthenticationRequest;
                $response['messageType'] = $threeDSecureData->messageType;

                return $response;
            }

            if (Secure3dVersion::ONE === $threeDSecureData->getVersion()) {
                throw new \Exception($errorMessage);
            }
        } catch (ApiException $e) {
            \PrestaShopLogger::addLog($e->getMessage());
            if ('50022' === $e->getCode()) {
                throw new \Exception($errorMessage);
            }

            throw new \Exception($e->getMessage());
        } catch (\Exception $e) {
            $response = [
                'error' => true,
                'message' => $e->getMessage(),
                'enrolled' => self::NO_RESPONSE,
            ];
        }

        return $response;
    }
}
