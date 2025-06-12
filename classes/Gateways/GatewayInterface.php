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

namespace GlobalPayments\PaymentGatewayProvider\Gateways;

use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\Entities\Reporting\TransactionSummary;
use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\PaymentGatewayProvider\Data\Order;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Shared gateway method implementations
 */
interface GatewayInterface
{
    /**
     * Required options for proper client-side configuration.
     *
     * @return array<string,string>
     */
    public function getFrontendGatewayOptions();

    /**
     * Required options for proper server-side configuration.
     *
     * @return array<string,string>
     */
    public function getBackendGatewayOptions();

    /**
     * Email address of the first-line support team
     *
     * @return string
     */
    public function getFirstLineSupportEmail();

    /**
     * Get the current gateway provider
     *
     * @return string
     */
    public function getGatewayProvider();

    /**
     * Configuration for the secure payment fields. Used on server- and
     * client-side portions of the integration.
     *
     * @return mixed[]
     */
    public function securePaymentFieldsConfiguration();

    /**
     * Handle payment functions
     *
     * @param Order $order
     *
     * @return Transaction
     *
     * @throws ApiException
     */
    public function processPayment(Order $order);

    /**
     * Handle adding new cards
     *
     * @param Order $order
     *
     * @return Transaction
     *
     * @throws ApiException
     */
    public function addPaymentMethod(Order $order);

    /**
     * Handle online refund requests
     *
     * @param Order $order
     *
     * @return Transaction
     *
     * @throws ApiException
     */
    public function processRefund(Order $order);

    /**
     * Get transaction details
     *
     * @param Order $order
     *
     * @return TransactionSummary
     *
     * @throws ApiException
     */
    public function getTransactionDetails(Order $order);
}
