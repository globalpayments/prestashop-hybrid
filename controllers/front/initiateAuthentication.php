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

use GlobalPayments\PaymentGatewayProvider\Data\Order as OrderModel;
use GlobalPayments\PaymentGatewayProvider\Gateways\GatewayId;

if (!defined('_PS_VERSION_')) {
    exit;
}

class GlobalPaymentsInitiateAuthenticationModuleFrontController extends ModuleFrontController
{
    /**
     * @var OrderModel
     */
    private $order;

    public function __construct()
    {
        parent::__construct();

        $this->order = new OrderModel();
    }

    public function initContent()
    {
        parent::initContent();

        $gateway = $this->module->getActiveGateway();

        if (!$gateway || $gateway->id !== GatewayId::GP_UCP) {
            return;
        }

        $data = json_decode(Tools::file_get_contents('php://input'));

        $amount = $data->order->amount ?? null;
        $billingAddress = $data->order->billingAddress ?? null;
        $cardData = isset($data->tokenResponse) ? json_decode($data->tokenResponse) : null;
        $currency = $data->order->currency ?? null;
        $muTokenId = $data->tokenId ?? null;
        $shippingAddress = $data->order->shippingAddress ?? null;
        $customerEmail = $data->order->emailAddress ?? $data->order->email ?? 'customer@example.com';

        $threeDSecureData = new stdClass();
        $threeDSecureData->authenticationSource = $data->authenticationSource ?? null;
        $threeDSecureData->authenticationRequestType = $data->authenticationRequestType ?? null;
        $threeDSecureData->browserData = $data->browserData ?? null;
        $threeDSecureData->challengeRequestIndicator = $data->challengeRequestIndicator ?? null;
        $threeDSecureData->challengeWindow = $data->challengeWindow ?? null;
        $threeDSecureData->messageCategory = $data->messageCategory ?? null;
        $threeDSecureData->versionCheckData = $data->versionCheckData ?? null;

        $order = $this->order->generateOrder([
            'amount' => $amount,
            'billingAddress' => $billingAddress,
            'cardData' => $cardData,
            'currency' => $currency,
            'emailAddress' => $customerEmail,
            'multiUseTokenId' => $muTokenId,
            'shippingAddress' => $shippingAddress,
            'threeDSecureData' => $threeDSecureData,
        ]);

        try {
            $response = $gateway->processThreeDSecureInitiateAuthentication($order);
        } catch (Exception $e) {
            $response = [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }

        echo json_encode($response);
        exit;
    }
}
