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

namespace GlobalPayments\PaymentGatewayProvider\Data;

use GlobalPayments\PaymentGatewayProvider\Platform\Token;
use GlobalPayments\PaymentGatewayProvider\Requests\RequestArg;

if (!defined('_PS_VERSION_')) {
    exit;
}

class PaymentTokenData
{
    protected $cardTypeMap = [
        'mastercard' => 'mastercard',
        'visa' => 'visa',
        'discover' => 'discover',
        'amex' => 'american express',
        'diners' => 'diners',
        'jcb' => 'jcb',
    ];

    /**
     * Current order's data
     *
     * @var array<string,mixed>
     */
    protected $data;

    /**
     * Standardize getting single- and multi-use token data
     *
     * @param array<string,mixed> $data
     */
    public function __construct($data = null)
    {
        $this->data = $data;
    }

    public function getToken()
    {
        $token = $this->getMultiUseToken();

        if (null === $token) {
            $token = $this->getSingleUseToken();
        }

        return $token;
    }

    public function saveNewToken($multiUseToken, $cardBrandTxnId = null)
    {
        $userId = \Context::getContext()->customer->id;
        $gatewayId = $this->data[RequestArg::GATEWAY_PROVIDER_ID];
        $currentTokens = Token::getCustomerTokens($userId, $gatewayId);

        $token = $this->getSingleUseToken();

        if (!empty($token)) {
            // a card number should only have a single token stored
            foreach ($currentTokens as $t) {
                if ($t->paymentReference === $multiUseToken) {
                    Token::delete($t->id_globalpayments_token);
                }
            }

            $token->setToken($multiUseToken);
            $token->setUserId($userId);
            $token->setGatewayId($gatewayId);
            $token->save();
        }
    }

    public function getSingleUseToken()
    {
        if (null === $this->data) {
            return null;
        }

        $requestData = $this->data[RequestArg::CARD_DATA];

        if (!isset($requestData)) {
            return null;
        }

        if (empty((array) $requestData)) {
            return null;
        }

        $token = new Token();

        $token->setToken($requestData->paymentReference);

        if (isset($requestData->details->cardLast4)) {
            $token->setLast4($requestData->details->cardLast4);
        }

        if (isset($requestData->details->expiryYear)) {
            $token->setExpiryYear($requestData->details->expiryYear);
        }

        if (isset($requestData->details->expiryMonth)) {
            $token->setExpiryMonth($requestData->details->expiryMonth);
        }

        if (isset($requestData->details->cardType) && isset($this->cardTypeMap[$requestData->details->cardType])) {
            $token->setCardtype($this->cardTypeMap[$requestData->details->cardType]);
        }

        return $token;
    }

    public function getMultiUseToken()
    {
        if (null === $this->data) {
            return null;
        }

        $tokenId = $this->data[RequestArg::MULTI_USE_TOKEN_ID];

        if (null === $tokenId) {
            return null;
        }

        $token = Token::get($tokenId);
        $userId = \Context::getContext()->customer->id;

        if (null === $token && $token->id_customer !== $userId) {
            return null;
        }

        return $token;
    }
}
