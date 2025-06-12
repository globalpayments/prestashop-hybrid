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

namespace GlobalPayments\PaymentGatewayProvider\Requests;

if (!defined('_PS_VERSION_')) {
    exit;
}

abstract class RequestArg
{
    public const AMOUNT = 'AMOUNT';
    public const ASYNC_PAYMENT_DATA = 'ASYNC_PAYMENT_DATA';
    public const AUTH_AMOUNT = 'AUTH_AMOUNT';
    public const BILLING_ADDRESS = 'BILLING_ADDRESS';
    public const CARD_DATA = 'CARD_DATA';
    public const CARD_HOLDER_NAME = 'CARD_HOLDER_NAME';
    public const CART_ID = 'CART_ID';
    public const CONFIG_DATA = 'CONFIG_DATA';
    public const CURRENCY = 'CURRENCY';
    public const DESCRIPTION = 'DESCRIPTION';
    public const DW_TOKEN = 'DW_TOKEN';
    public const DYNAMIC_DESCRIPTOR = 'DYNAMIC_DESCRIPTOR';
    public const EMAIL_ADDRESS = 'EMAIL_ADDRESS';
    public const ENTRY_MODE = 'ENTRY_MODE';
    public const GATEWAY_ID = 'GATEWAY_ID';
    public const GATEWAY_PROVIDER_ID = 'GATEWAY_PROVIDER_ID';
    public const MOBILE_TYPE = 'MOBILE_TYPE';
    public const MULTI_USE_TOKEN_ID = 'MULTI_USE_TOKEN_ID';
    public const ORDER_ID = 'ORDER_ID';
    public const PAYER_INFO = 'PAYER_INFO';
    public const PAYMENT_ACTION = 'PAYMENT_ACTION';
    public const PERMISSIONS = 'PERMISSIONS';
    public const REQUEST_MULTI_USE_TOKEN = 'REQUEST_MULTI_USE_TOKEN';
    public const SERVER_TRANS_ID = 'SERVER_TRANS_ID';
    public const SERVICES_CONFIG = 'SERVICES_CONFIG';
    public const SHIPPING_ADDRESS = 'SHIPPING_ADDRESS';
    public const THREE_D_SECURE_DATA = 'THREE_D_SECURE_DATA';
    public const TXN_MODIFIER = 'TXN_MODIFIER';
    public const TXN_TYPE = 'TXN_TYPE';
}
