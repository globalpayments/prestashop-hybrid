<?php
/**
 * Global Payments Webhook Controller
 * Handles webhook/status notifications from payment gateways
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

use GlobalPayments\PaymentGatewayProvider\Gateways\DiUiApms\Blik;
use GlobalPayments\PaymentGatewayProvider\Gateways\DiUiApms\BankSelect;

class GlobalpaymentsWebhookModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();
        
        $action = Tools::getValue('action');
        
        // Log the incoming request for debugging
        PrestaShopLogger::addLog('BLIK Webhook: action=' . $action, 1, null, 'GlobalPayments');
        
        switch ($action) {
            case 'blik_status_handler':
                $this->handleBlikStatusNotification();
                break;
            case 'ob_status_handler':
                $this->handleObStatusNotification();
                break;
            default:
                PrestaShopLogger::addLog('BLIK Webhook: Unknown action: ' . $action, 3, null, 'GlobalPayments');
                http_response_code(400);
                echo 'Unknown action';
                exit;
        }
    }
    
    private function handleBlikStatusNotification()
    {
        try {
            // Call the static method in Blik class
            Blik::handleBlikStatusNotification();
        } catch (Exception $e) {
            PrestaShopLogger::addLog('BLIK Webhook Error: ' . $e->getMessage(), 3, null, 'GlobalPayments');
            http_response_code(500);
            echo 'Internal Server Error';
            exit;
        }
    }

    private function handleObStatusNotification()
    {
        try {
            // Call the static method in BankSelect class
            BankSelect::handleObStatusNotification();
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Open Banking Webhook Error: ' . $e->getMessage(), 3, null, 'GlobalPayments');
            http_response_code(500);
            echo 'Internal Server Error';
            exit;
        }
    }
    
    public function postProcess()
    {
        // Handle POST requests (webhooks are typically POST)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->initContent();
        }
        parent::postProcess();
    }
}
