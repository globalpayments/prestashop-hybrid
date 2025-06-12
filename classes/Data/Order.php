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

use GlobalPayments\PaymentGatewayProvider\Requests\RequestArg;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Order
{
    /**
     * @var float|int|string
     */
    public $amount;

    /**
     * @var float|int|string
     */
    public $authorizationAmount;

    /**
     * @var array<string,string>
     */
    public $billingAddress;

    /**
     * @var array<string,string>
     */
    public $cardData;

    /**
     * @var string
     */
    public $cardHolderName;

    /**
     * @var int
     */
    public $cartId;

    /**
     * @var string
     */
    public $currency;

    /**
     * @var string
     */
    public $description;

    /**
     * @var string
     */
    public $dwToken;

    /**
     * @var string
     */
    public $dynamicDescriptor;

    /**
     * @var string
     */
    public $emailAddress;

    /**
     * @var string
     */
    public $entryMode;

    /**
     * @var string
     */
    public $gatewayProviderId;

    /**
     * @var string
     */
    public $mobileType;

    /**
     * @var int
     */
    public $multiUseTokenId;

    /**
     * @var int
     */
    public $orderId;

    /**
     * @var array
     */
    public $payerInfo;

    /**
     * @var bool
     */
    public $requestMultiUseToken;

    /**
     * @var int
     */
    public $serverTransId;

    /**
     * @var array<string,string>
     */
    public $shippingAddress;

    /**
     * @var array
     */
    public $threeDSecureData;

    /**
     * @var string
     */
    public $transactionId;

    /**
     * @var string
     */
    public $transactionModifier;

    /**
     * @var string
     */
    public $transactionType;

    /**
     * @return array<string, mixed>
     */
    public function asArray()
    {
        return [
            RequestArg::AMOUNT => $this->amount,
            RequestArg::AUTH_AMOUNT => $this->authorizationAmount,
            RequestArg::BILLING_ADDRESS => $this->billingAddress,
            RequestArg::CARD_DATA => $this->cardData,
            RequestArg::CARD_HOLDER_NAME => $this->cardHolderName,
            RequestArg::CART_ID => $this->cartId,
            RequestArg::CURRENCY => $this->currency,
            RequestArg::DESCRIPTION => $this->description,
            RequestArg::DW_TOKEN => $this->dwToken,
            RequestArg::DYNAMIC_DESCRIPTOR => $this->dynamicDescriptor,
            RequestArg::EMAIL_ADDRESS => $this->emailAddress,
            RequestArg::ENTRY_MODE => $this->entryMode,
            RequestArg::GATEWAY_PROVIDER_ID => $this->gatewayProviderId,
            RequestArg::MOBILE_TYPE => $this->mobileType,
            RequestArg::MULTI_USE_TOKEN_ID => $this->multiUseTokenId,
            RequestArg::ORDER_ID => $this->orderId,
            RequestArg::PAYER_INFO => $this->payerInfo,
            RequestArg::REQUEST_MULTI_USE_TOKEN => $this->requestMultiUseToken,
            RequestArg::SERVER_TRANS_ID => $this->serverTransId,
            RequestArg::SHIPPING_ADDRESS => $this->shippingAddress,
            RequestArg::GATEWAY_ID => $this->transactionId,
            RequestArg::THREE_D_SECURE_DATA => $this->threeDSecureData,
            RequestArg::TXN_MODIFIER => $this->transactionModifier,
            RequestArg::TXN_TYPE => $this->transactionType,
        ];
    }

    /**
     * Generates a new Order based on the data sent.
     *
     * @param array $orderData
     *
     * @return $this
     */
    public function generateOrder($orderData)
    {
        foreach ($orderData as $key => $value) {
            if (!property_exists($this, $key)) {
                continue;
            }

            $this->{$key} = $value;
        }

        return $this;
    }
}
