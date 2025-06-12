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

abstract class TransactionType
{
    // auth requests
    public const AUTHORIZE = 'authorize';
    public const SALE = 'charge';
    public const VERIFY = 'verify';

    // dw requests
    public const DW_AUTHORIZATION = 'dwAuthorization';

    // bnpl requests
    public const BNPL_AUTHORIZATION = 'bnplAuthorization';

    // open banking requests
    public const OB_AUTHORIZATION = 'obAuthorization';

    // apm requests
    public const APM_AUTHORIZATION = 'apmAuthorization';

    // mgmt requests
    public const REFUND = 'refund';
    public const REVERSAL = 'reverse';
    public const VOID = 'void';
    public const CAPTURE = 'capture';

    // platform requests
    public const CANCEL = 'cancel';
    public const CUSTOMER_CANCEL = 'customer cancel';
    public const INITIATE_PAYMENT = 'initiate payment';
    public const REFUND_REVERSE = 'refund/reverse';

    // transit requests
    public const CREATE_TRANSACTION_KEY = 'getTransactionKey';
    public const CREATE_MANIFEST = 'createManifest';

    // report requests
    public const REPORT_TXN_DETAILS = 'transactionDetail';

    // gp-api requests
    public const GET_ACCESS_TOKEN = 'getAccessToken';

    // 3DS requests
    public const CHECK_ENROLLMENT = 'checkEnrollment';
    public const INITIATE_AUTHENTICATION = 'initiateAuthentication';
}
