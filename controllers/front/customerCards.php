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

use GlobalPayments\PaymentGatewayProvider\Platform\Token;
use GlobalPayments\PaymentGatewayProvider\Platform\Utils;

if (!defined('_PS_VERSION_')) {
    exit;
}

class GlobalPaymentsCustomerCardsModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $customer = $this->context->customer->id;

        $activeGateway = $this->module->getActiveGateway();

        $this->context->smarty->assign([
            'cards' => Token::getCustomerTokens($customer, $activeGateway->id),
            'title' => (new Utils())->getCardStorageText(),
        ]);

        $this->setTemplate('module:globalpayments/views/templates/front/customer_cards.tpl');
    }

    public function getBreadcrumbLinks()
    {
        $breadcrumb = parent::getBreadcrumbLinks();
        $breadcrumb['links'][] = $this->addMyAccountToBreadcrumb();

        return $breadcrumb;
    }
}
