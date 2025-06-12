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

if (!defined('_PS_VERSION_')) {
    exit;
}

class GlobalPaymentsRemoveCustomerCardModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $tokenId = Tools::getValue('id');
        $customerId = $this->context->customer->id;
        $token = Token::get($tokenId);
        $link = $this->context->link->getModuleLink($this->module->name, 'customerCards');

        if ($token && $token->id_customer = $customerId) {
            Token::delete($tokenId);

            Tools::redirect($link);
        }

        Tools::redirect($link);
    }
}
