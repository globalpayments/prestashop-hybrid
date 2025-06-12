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

use GlobalPayments\PaymentGatewayProvider\Platform\Token;
use GlobalPayments\PaymentGatewayProvider\Platform\Utils;
use GlobalPayments\PaymentGatewayProvider\Requests\AbstractRequest;
use GlobalPayments\PaymentGatewayProvider\Requests\RequestArg;

if (!defined('_PS_VERSION_')) {
    exit;
}

abstract class AbstractAuthenticationsRequest extends AbstractRequest
{
    public const YES = 'YES';

    public function getArgumentList()
    {
        return [];
    }

    protected function getToken($requestData)
    {
        $muTokenId = $requestData[RequestArg::MULTI_USE_TOKEN_ID];
        $translator = (new Utils())->getTranslator();
        $errorMessage = $translator->trans(
            'Not enough data to perform 3DS. Unable to retrieve token.',
            [],
            'Modules.Globalpayments.Shop'
        );

        if (!isset($requestData[RequestArg::CARD_DATA]) && !isset($muTokenId)) {
            throw new \Exception($errorMessage);
        }

        if (isset($muTokenId)) {
            $tokenResponse = Token::get($muTokenId);

            if (empty($tokenResponse)) {
                throw new \Exception($errorMessage);
            }

            return $tokenResponse->paymentReference;
        }

        $tokenResponse = $requestData[RequestArg::CARD_DATA];
        if (empty($tokenResponse->paymentReference)) {
            throw new \Exception($errorMessage);
        }

        return $tokenResponse->paymentReference;
    }
}
