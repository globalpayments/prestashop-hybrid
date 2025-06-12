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

namespace GlobalPayments\PaymentGatewayProvider\Requests\DigitalWallets;

use GlobalPayments\Api\Entities\Enums\TransactionModifier;
use GlobalPayments\Api\PaymentMethods\CreditCardData;
use GlobalPayments\PaymentGatewayProvider\Requests\AbstractRequest;
use GlobalPayments\PaymentGatewayProvider\Requests\RequestArg;

if (!defined('_PS_VERSION_')) {
    exit;
}

class AuthorizationRequest extends AbstractRequest
{
    public function getTransactionType()
    {
        return $this->getArgument(RequestArg::PAYMENT_ACTION);
    }

    public function doRequest()
    {
        $paymentMethod = new CreditCardData();

        $paymentMethod->token = $this->getArgument(RequestArg::DW_TOKEN);
        $paymentMethod->mobileType = $this->getArgument(RequestArg::MOBILE_TYPE);

        $cardHolderName = $this->getArgument(RequestArg::PAYER_INFO)['cardHolderName'] ?? null;
        if ($cardHolderName) {
            $paymentMethod->cardHolderName = $cardHolderName;
        }

        $cartId = $this->getArgument(RequestArg::CART_ID) ?
            'CART#' . $this->getArgument(RequestArg::CART_ID) : null;

        return $paymentMethod
            ->{$this->getArgument(RequestArg::PAYMENT_ACTION)}($this->getArgument(RequestArg::AMOUNT))
            ->withCurrency($this->getArgument(RequestArg::CURRENCY))
            ->withOrderId($cartId)
            ->withModifier(TransactionModifier::ENCRYPTED_MOBILE)
            ->withDynamicDescriptor($this->getArgument(RequestArg::DYNAMIC_DESCRIPTOR))
            ->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function getArgumentList()
    {
        return [];
    }
}
