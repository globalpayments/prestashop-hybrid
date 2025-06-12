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
if (!defined('_PS_VERSION_')) {
    exit;
}

class GlobalPaymentsMethodNotificationModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        if ('POST' !== $_SERVER['REQUEST_METHOD']) {
            return;
        }

        if ('application/x-www-form-urlencoded' !== $_SERVER['CONTENT_TYPE']) {
            return;
        }

        // disallow writing cookies for this page in order to stop customer logouts in certain scenarios
        $this->context->cookie->disallowWriting();

        if (Tools::usingSecureMode()) {
            $domain = Tools::getShopDomainSsl(true, true);
        } else {
            $domain = Tools::getShopDomain(true, true);
        }

        $convertedThreeDSMethodData = Tools::getIsset('threeDSMethodData') ?
            json_decode(base64_decode(Tools::getValue('threeDSMethodData'))) : null;
        $response = json_encode([
            'threeDSServerTransID' => $convertedThreeDSMethodData->threeDSServerTransID,
        ]);

        $this->context->smarty->assign([
            'jsPath' => $domain . __PS_BASE_URI__ . basename(_PS_MODULE_DIR_) . '/' . $this->module->name .
                '/views/js/globalpayments-3ds' . (defined('_PS_MODE_DEV_') && _PS_MODE_DEV_ ? '' : '.min') . '.js',
            'response' => $response,
        ]);

        $this->setTemplate('module:globalpayments/views/templates/front/method_notification.tpl');
    }
}
