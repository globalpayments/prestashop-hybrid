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
use GlobalPayments\PaymentGatewayProvider\Requests\ThreeDSecure\CheckEnrollmentRequest;

if (!defined('_PS_VERSION_')) {
    exit;
}

class GlobalPaymentsCheckEnrollmentModuleFrontController extends ModuleFrontController
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
        $mediaType = strtolower(trim(explode(';', $contentType)[0] ?? ''));
        
        if ($mediaType !== 'application/json') {
            http_response_code(415);
            echo json_encode([
                'error' => true,
                'message' => 'Unsupported Media Type',
            ]);
            exit;
        }

        $gateway = $this->module->getActiveGateway();

        header('Content-Type: application/json');

        if (!$gateway || $gateway->id !== GatewayId::GP_UCP) {
            http_response_code(503);
            echo json_encode([
                'error' => true,
                'message' => 'Gateway not available',
                'enrolled' => CheckEnrollmentRequest::NO_RESPONSE,
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
                'enrolled' => CheckEnrollmentRequest::NO_RESPONSE,
            ]);
            exit;
        }

        $data = json_decode(Tools::file_get_contents('php://input'));

        $amount = $data->amount ?? null;
        $cardData = isset($data->tokenResponse) ? json_decode($data->tokenResponse) : null;
        $currency = $data->currency ?? null;
        $muTokenId = $data->tokenId ?? null;

        $order = $this->order->generateOrder([
            'amount' => $amount,
            'cardData' => $cardData,
            'currency' => $currency,
            'multiUseTokenId' => $muTokenId,
        ]);

        try {
            $response = $gateway->processThreeDSecureCheckEnrollment($order);
        } catch (Exception $e) {
            // Log the actual error for debugging (server-side only)
            PrestaShopLogger::addLog(
                '3DS Check Enrollment Error: ' . $e->getMessage(),
                PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR,
                $e->getCode(),
                'Order',
                null,
                true
            );
            $response = [
                'error' => true,
                'message' => 'Unable to verify card enrollment. Please try again or contact support.',
                'enrolled' => CheckEnrollmentRequest::NO_RESPONSE,
            ];
        }

        echo json_encode($response);
        exit;
    }
}