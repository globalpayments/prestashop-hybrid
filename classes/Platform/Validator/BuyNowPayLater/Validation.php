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

namespace GlobalPayments\PaymentGatewayProvider\Platform\Validator\BuyNowPayLater;

use PrestaShopBundle\Translation\TranslatorComponent as Translator;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Validation
{
    /**
     * @var \Cart|null
     */
    protected $cart;

    /**
     * @var array
     */
    protected $errorMessages;

    /**
     * @var \Module
     */
    protected $module;

    /**
     * @var Translator
     */
    protected $translator;

    public function __construct(
        ?\Module $module = null,
        ?\Cart $cart = null
    ) {
        $this->module = $module ?? \GlobalPayments::getModuleInstance();
        $this->cart = $cart ?? \Context::getContext()->cart;
        $this->translator = $this->module->getTranslator();

        $this->errorMessages = [
            'invalidShippingAddress' => $this->translator->trans(
                'Please check the Shipping address. ',
                [],
                'Modules.Globalpayments.Shop'
            ),
            'invalidBillingAddress' => $this->translator->trans(
                'Please check the Billing address. ',
                [],
                'Modules.Globalpayments.Shop'
            ),
            'invalidZipCode' => $this->translator->trans(
                'Zip/Postal Code is mandatory.',
                [],
                'Modules.Globalpayments.Shop'
            ),
            'invalidPhone' => $this->translator->trans(
                'Telephone is mandatory.',
                [],
                'Modules.Globalpayments.Shop'
            ),
        ];
    }

    /**
     * Validate shipping and billing address.
     *
     * @param array $billingAddress
     * @param array $shippingAddress
     * @param bool $isShippingRequired
     *
     * @return bool
     */
    public function validate($billingAddress, $shippingAddress, $isShippingRequired)
    {
        if ($isShippingRequired && $this->cart && !$this->cart->isVirtualCart()) {
            return $this->isValidAddress($shippingAddress, $this->errorMessages['invalidShippingAddress'])
                && $this->isValidAddress($billingAddress, $this->errorMessages['invalidBillingAddress']);
        }

        return $this->isValidAddress($billingAddress, $this->errorMessages['invalidBillingAddress']);
    }

    /**
     * Validate address.
     *
     * @param array $address
     * @param string $errorMessagePrefix
     *
     * @return bool
     */
    private function isValidAddress($address, $errorMessagePrefix)
    {
        if (!$this->isValidZipCode($address['postalCode'])) {
            $this->showError($errorMessagePrefix . $this->errorMessages['invalidZipCode']);
        }
        if (!$this->isValidPhone($address['phone'])) {
            $this->showError($errorMessagePrefix . $this->errorMessages['invalidPhone']);
        }

        return true;
    }

    /**
     * Validate zipcode.
     *
     * @param string $zipcode
     *
     * @return bool
     */
    private function isValidZipCode($zipcode)
    {
        return !empty($zipcode);
    }

    /**
     * Validate phone.
     *
     * @param string $phone
     *
     * @return bool
     */
    private function isValidPhone($phone)
    {
        return !empty($phone);
    }

    /**
     * Show error to the customer.
     *
     * @param string $errorMessage
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    private function showError($errorMessage)
    {
        throw new \InvalidArgumentException($errorMessage);
    }
}
