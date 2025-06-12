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
use GlobalPayments\PaymentGatewayProvider\Data\Order;
use GlobalPayments\PaymentGatewayProvider\Requests\RequestInterface;
use GlobalPayments\PaymentGatewayProvider\Requests\TransactionType;

if (!defined('_PS_VERSION_')) {
    exit;
}

interface PaymentMethodInterface
{
    /**
     * Load the payment scripts at checkout.
     *
     * @param \GlobalPayments $module
     *
     * @return mixed
     */
    public function enqueuePaymentScripts($module);

    /**
     * Get the data that will be sent to frontend.
     *
     * @return array
     */
    public function getFrontendPaymentMethodOptions();

    /**
     * Get the form fields displayed in the admin config page.
     *
     * @return array
     */
    public function getPaymentMethodFormFields();

    /**
     * Get the payment options that will be displayed at checkout.
     *
     * @param \GlobalPayments $module
     * @param array $params
     * @param bool $isCheckout
     *
     * @return array
     */
    public function getPaymentOptions($module, $params, $isCheckout);

    /**
     * Get the request type.
     *
     * @return TransactionType
     */
    public function getRequestType();

    /**
     * Provide additional functionality after payment gateway response is received.
     *
     * @param Transaction $gatewayResponse
     * @param int $orderId
     *
     * @return void|null
     */
    public function processPaymentAfterGatewayResponse($gatewayResponse, $orderId);

    /**
     * Provide additional functionality before payment gateway request.
     *
     * @param RequestInterface $request
     * @param Order $order
     *
     * @return void|null
     */
    public function processPaymentBeforeGatewayRequest($request, $order);

    /**
     * Get the title for the payment method.
     *
     * @return string
     */
    public function getDefaultTitle();

    /**
     * Validate the admin settings.
     *
     * @return array
     */
    public function validateAdminSettings();
}
