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
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\RequestHelper;

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

        // Check request method
        if ('POST' !== $_SERVER['REQUEST_METHOD']) {
            return;
        }

        // Check Content-Type (allow charset parameter)
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') === false) {
            return;
        }

        $gateway = $this->module->getActiveGateway();

        header('Content-Type: application/json');

        if (!$gateway || $gateway->id !== GatewayId::GP_UCP) {
            http_response_code(503);
            echo json_encode([
                'error' => true,
                'message' => 'Gateway not available',
            ]);
            exit;
        }

        // Security verification to prevent carding attacks
        $securityCheck = $gateway->verifyThreeDSecureRequestSecurity();
        if ($securityCheck !== true) {
            http_response_code(429);
            echo json_encode([
                'error' => true,
                'message' => $securityCheck['message'],
            ]);
            exit;
        }

        $requestBody = RequestHelper::getRequestBody();
        if (empty($requestBody)) {
            http_response_code(400);
            echo json_encode([
                'error' => true,
                'message' => 'Invalid request body',
            ]);
            exit;
        }

        $data = json_decode($requestBody);
        if (json_last_error() !== JSON_ERROR_NONE || !is_object($data)) {
            http_response_code(400);
            echo json_encode([
                'error' => true,
                'message' => 'Invalid JSON data',
            ]);
            exit;
        }

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
            // Log the actual error server-side for debugging
            PrestaShopLogger::addLog(
                'GlobalPayments 3DS Authentication Error: ' . $e->getMessage(),
                PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR,
                $e->getCode(),
                'GlobalPayments',
                null,
                true
            );
            $response = [
                'error' => true,
                'message' => 'Unable to initiate authentication. Please try again or contact support.',
            ];
        }

        echo json_encode($response);
        exit;
    }
}
