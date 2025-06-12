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

use GlobalPayments\PaymentGatewayProvider\PaymentMethods\DigitalWallets\ApplePay;

if (!defined('_PS_VERSION_')) {
    exit;
}

class GlobalPaymentsValidateMerchantModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $data = json_decode(Tools::file_get_contents('php://input'));
        $validationUrl = $data->validationUrl;
        $applePayGateway = new ApplePay();

        if (!$applePayGateway->appleMerchantId
            || !$applePayGateway->appleMerchantCertPath
            || !$applePayGateway->appleMerchantKeyPath
            || !$applePayGateway->appleMerchantDomain
            || !$applePayGateway->appleMerchantDisplayName
        ) {
            return null;
        }
        $pemCrtPath = _PS_ROOT_DIR_ . '/' . $applePayGateway->appleMerchantCertPath;
        $pemKeyPath = _PS_ROOT_DIR_ . '/' . $applePayGateway->appleMerchantKeyPath;

        $validationPayload = [];
        $validationPayload['merchantIdentifier'] = $applePayGateway->appleMerchantId;
        $validationPayload['displayName'] = $applePayGateway->appleMerchantDisplayName;
        $validationPayload['initiative'] = 'web';
        $validationPayload['initiativeContext'] = $applePayGateway->appleMerchantDomain;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $validationUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($validationPayload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        curl_setopt($ch, CURLOPT_DNS_USE_GLOBAL_CACHE, false);
        curl_setopt($ch, CURLOPT_SSLCERT, $pemCrtPath);
        curl_setopt($ch, CURLOPT_SSLKEY, $pemKeyPath);

        if ($applePayGateway->appleMerchantKeyPassphrase !== null) {
            curl_setopt($ch, CURLOPT_KEYPASSWD, $applePayGateway->appleMerchantKeyPassphrase);
        }

        $validationResponse = curl_exec($ch);

        if (false == $validationResponse) {
            echo curl_error($ch);
            exit;
        }

        curl_close($ch);

        echo json_encode($validationResponse);
        exit;
    }
}
