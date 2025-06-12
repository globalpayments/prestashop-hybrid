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

namespace GlobalPayments\PaymentGatewayProvider\Platform\Helper;

if (!defined('_PS_VERSION_')) {
    exit;
}

class CheckoutHelper
{
    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * @var \Cart
     */
    protected $cart;

    /**
     * @var int
     */
    protected $cartId;

    /**
     * @var \Context
     */
    protected $context;

    /**
     * @var \Customer
     */
    protected $customer;

    /**
     * @var \GlobalPayments
     */
    protected $module;

    /**
     * Checkout constructor.
     *
     * @param \GlobalPayments|null $module
     * @param \Cart|null $cart
     */
    public function __construct(
        ?\GlobalPayments $module = null,
        ?\Cart $cart = null
    ) {
        $this->module = $module ?? \GlobalPayments::getModuleInstance();
        $this->context = $this->module->getContext();
        $this->cart = $cart ?? $this->context->cart;
        $this->baseUrl = $this->context->shop->getBaseURL(true);

        $customerId = $this->cart->id_customer ?? null;
        $this->customer = new \Customer($customerId);
    }

    /**
     * Clear the current cart.
     *
     * @return void|null
     */
    public function clearCart()
    {
        try {
            if (\Validate::isLoadedObject($this->cart)) {
                $this->cart->delete();
            }
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the URL for the success page.
     *
     * @param int $orderId
     *
     * @return string
     */
    public function getSuccessPageUrl($orderId)
    {
        $order = new \Order($orderId);
        $key = $order ? $order->secure_key : $this->customer->secure_key;

        $orderConfirmation = $this->context->link->getPageLink('order-confirmation');
        $successUrl = $orderConfirmation . '&id_cart=' . $order->id_cart .
            '&id_module=' . (int) $this->module->id . '&id_order=' . $orderId . '&key=' . $key;
        if (\Configuration::get('PS_REWRITING_SETTINGS')) {
            $successUrl = \Tools::strReplaceFirst('&', '?', $successUrl);
        }

        return $successUrl;
    }

    /**
     * Get the JSON data for the success page redirection.
     *
     * @param int $orderId
     *
     * @return void
     */
    public function getSuccessPage($orderId)
    {
        $this->postResponse(false, $this->getSuccessPageUrl($orderId));
    }

    /**
     * Get the URL for the cart page.
     *
     * @return string
     */
    public function getCartPageUrl()
    {
        return $this->baseUrl . 'cart?action=show';
    }

    /**
     * Get the URL for the first step of the checkout.
     *
     * @return string
     */
    public function getCheckoutFirstStepUrl()
    {
        return $this->baseUrl . 'index.php?controller=order&step=1';
    }

    /**
     * States whether the current page is the Checkout page.
     *
     * @return bool
     */
    public function isCheckoutPage()
    {
        return !empty($this->context->controller->page_name)
            && $this->context->controller->page_name === 'checkout';
    }

    /**
     * Post the response back to the client.
     *
     * @param bool $error
     * @param string $redirect
     * @param string|null $errorMessage
     *
     * @return void
     */
    public function postResponse($error, $redirect, $errorMessage = null)
    {
        $response = [
            'error' => $error,
            'redirect' => $redirect,
            'errorMessage' => $errorMessage,
        ];

        echo json_encode($response);
        exit;
    }

    /**
     * Redirect to the cart page.
     *
     * @return void
     */
    public function redirectToCartPage()
    {
        \Tools::redirect($this->getCartPageUrl());
    }

    /**
     * Redirect to the success page.
     *
     * @param int $orderId
     *
     * @return void
     */
    public function redirectToSuccessPage($orderId)
    {
        \Tools::redirect($this->getSuccessPageUrl($orderId));
    }

    /**
     * Restores the cart based on the order id.
     *
     * @param $orderId
     *
     * @return void
     */
    public function restoreCart($orderId)
    {
        $oldCart = new \Cart(\Order::getCartIdStatic($orderId));
        $duplication = $oldCart->duplicate();
        if (!$duplication || !$duplication['success']) {
            return;
        }

        $this->context->cookie->id_cart = $duplication['cart']->id;
        $context = $this->context;
        $context->cart = $duplication['cart'];
        \CartRule::autoAddToCart($context);
        $this->context->cookie->write();
    }

    /**
     * Checkout validations.
     *
     * @return void
     */
    public function validate()
    {
        if (!$this->validateCart()) {
            $this->postResponse(true, $this->getCheckoutFirstStepUrl());
        }
        if (!$this->validateCustomer()) {
            $this->postResponse(true, $this->getCheckoutFirstStepUrl());
        }
    }

    /**
     * Validate the cart object.
     *
     * @return bool
     */
    public function validateCart()
    {
        if ($this->cart->id_customer == 0
            || $this->cart->id_address_delivery == 0
            || $this->cart->id_address_invoice == 0
            || !$this->module->active
        ) {
            return false;
        }

        return true;
    }

    /**
     * Validate the customer object.
     *
     * @return bool
     */
    public function validateCustomer()
    {
        if (!\Validate::isLoadedObject($this->customer)) {
            return false;
        }

        return true;
    }
}
