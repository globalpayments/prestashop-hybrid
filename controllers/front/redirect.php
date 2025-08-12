<?php
/**
 * Global Payments Redirect Controller
 * Handles redirects from payment gateways
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

use GlobalPayments\PaymentGatewayProvider\Gateways\DiUiApms\Blik;
use GlobalPayments\PaymentGatewayProvider\Gateways\DiUiApms\BankSelect;
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\CheckoutHelper;

class GlobalpaymentsRedirectModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();
        
        $action = Tools::getValue('action');
        $order_id = Tools::getValue('order_id');

        // Log the incoming request for debugging
        PrestaShopLogger::addLog(
            'BLIK Redirect: action=' . $action . ', order_id=' . $order_id, 1, null, 'GlobalPayments'
        );

        switch ($action) {
            case 'blik_redirect_handler':
                $this->handleBlikRedirect($order_id);
                break;
            case 'ob_redirect_handler':
                $this->handleObRedirect($order_id);
                break;
            default:
                PrestaShopLogger::addLog('BLIK Redirect: Unknown action: ' . $action, 3, null, 'GlobalPayments');
                // Use PrestaShop's built-in method instead of hardcoded URL
                $module = GlobalPayments::getModuleInstance();
                $checkoutHelper = new CheckoutHelper($module);
                $checkoutHelper->redirectToCartPage();
                break;
        }
    }

    private function handleBlikRedirect($order_id)
    {
        // Get module instance and create CheckoutHelper
        $module = GlobalPayments::getModuleInstance();
        $checkoutHelper = new CheckoutHelper($module);

        if (!$order_id) {
            PrestaShopLogger::addLog('BLIK Redirect: Missing order_id', 3, null, 'GlobalPayments');
            // Use PrestaShop's built-in method instead of hardcoded URL
            $checkoutHelper->redirectToCartPage();
            return;
        }

        try {
            // Call the static method in Blik class
            Blik::handleBlikRedirect();
        } catch (Exception $e) {
            PrestaShopLogger::addLog('BLIK Redirect Error: ' . $e->getMessage(), 3, null, 'GlobalPayments');
            // Use PrestaShop's built-in method instead of hardcoded URL
            $checkoutHelper->redirectToCartPage();
        }
    }

    private function handleObRedirect($order_id)
    {
        // Get module instance and create CheckoutHelper
        $module = GlobalPayments::getModuleInstance();
        $checkoutHelper = new CheckoutHelper($module);

        if (!$order_id) {
            PrestaShopLogger::addLog('Open Banking Redirect: Missing order_id', 3, null, 'GlobalPayments');
            // Use PrestaShop's built-in method instead of hardcoded URL
            $checkoutHelper->redirectToCartPage();
            return;
        }

        try {
            // Call the static method in BankSelect class
            BankSelect::handleObRedirect();
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Open Banking Redirect Error: ' . $e->getMessage(), 3, null, 'GlobalPayments');
            // Use PrestaShop's built-in method instead of hardcoded URL
            $checkoutHelper->redirectToCartPage();
        }
    }
}
