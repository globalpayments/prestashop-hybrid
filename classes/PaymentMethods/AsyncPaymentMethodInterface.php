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

namespace GlobalPayments\PaymentGatewayProvider\PaymentMethods;

use GlobalPayments\Api\Entities\Transaction;

if (!defined('_PS_VERSION_')) {
    exit;
}

interface AsyncPaymentMethodInterface
{
    /**
     * States whether the payment method can be displayed at checkout.
     *
     * @return bool
     */
    public function isAvailable();

    /**
     * Get the provider endpoints.
     *
     * @return array
     */
    public function getProviderEndpoints();

    /**
     * Get the Redirect URL based on the transaction summary.
     *
     * @param Transaction $transaction
     *
     * @return string
     */
    public function getRedirectUrl($transaction);

    /**
     * States whether the address has to be validated for the current payment method.
     *
     * @return bool
     */
    public function validateAddress();
}
