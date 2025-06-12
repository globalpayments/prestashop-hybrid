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

use GlobalPayments\Api\Entities\Address as GpAddress;
use GlobalPayments\PaymentGatewayProvider\Platform\Utils;

if (!defined('_PS_VERSION_')) {
    exit;
}

class AddressHelper
{
    /**
     * @var \Cart|null
     */
    protected $cart;

    /**
     * @var array
     */
    protected $countries = ['US', 'CA'];

    /**
     * @var array
     */
    protected $billingAddress;

    /**
     * @var array
     */
    protected $shippingAddress;

    /**
     * @var Utils
     */
    protected $utils;

    public function __construct()
    {
        $this->cart = \Context::getContext()->cart;
        $this->utils = new Utils();
    }

    /**
     * Gets the address based on the provided id.
     *
     * @param $addressId
     *
     * @return array
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function getAddress($addressId)
    {
        $address = new \Address($addressId);
        $stateId = $address->id_state;
        $stateIso = '';
        $country = $address->country;

        if (isset($country) && $country === 'United States') {
            $country = 'United States of America';
        }

        if (isset($stateId)) {
            $stateIso = (new \State($stateId))->iso_code;
        }

        return [
            'firstName' => $address->firstname,
            'lastName' => $address->lastname,
            'streetAddress1' => $address->address1,
            'streetAddress2' => $address->address2,
            'city' => $address->city,
            'state' => $stateIso,
            'phone' => $address->phone,
            'postalCode' => $address->postcode,
            'country' => $country,
            'countryCode' => \Country::getIsoById($address->id_country),
        ];
    }

    /**
     * Get the cart's billing address.
     *
     * @return array
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function getBillingAddress()
    {
        if (!empty($this->billingAddress)) {
            return $this->billingAddress;
        }

        $this->billingAddress = $this->getAddress($this->cart->id_address_invoice);
        return $this->billingAddress;
    }

    /**
     * Get the cart's shipping address.
     *
     * @return array
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function getShippingAddress()
    {
        if ((isset($this->cart) && $this->cart->isVirtualCart()) || $this->isSameAddress()) {
            return $this->getBillingAddress();
        }

        if (!empty($this->shippingAddress)) {
            return $this->shippingAddress;
        }

        $this->shippingAddress = $this->getAddress($this->cart->id_address_delivery);
        return $this->shippingAddress;
    }

    /**
     * Check if the billing address is the same as the shipping one.
     *
     * @return bool
     */
    public function isSameAddress()
    {
        return (int) $this->cart->id_address_invoice === (int) $this->cart->id_address_delivery;
    }

    /**
     * Map the address from PrestaShop to the specific class from the SDK.
     *
     * @param array $address
     * @return GpAddress
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function mapAddress($address)
    {
        $gpAddress = new GpAddress();

        $gpAddress->streetAddress1 = $address['streetAddress1'] ?? null;
        $gpAddress->streetAddress2 = $address['streetAddress2'] ?? null;
        $gpAddress->city = $address['city'] ?? null;
        $gpAddress->postalCode = $address['postalCode'] ?? null;
        $gpAddress->country = $address['country'] ?? null;
        $state = $address['state'] ?? null;

        if (in_array($gpAddress->countryCode, $this->countries) && isset($state)) {
            $gpAddress->state = $state;
        } else {
            $gpAddress->state = $gpAddress->country;
        }

        return $gpAddress;
    }

    /**
     * Map the billing address from PrestaShop to the specific class from the SDK.
     *
     * @return GpAddress
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function mapBillingAddress()
    {
        return $this->mapAddress($this->getBillingAddress());
    }

    /**
     * Map the shipping address from PrestaShop to the specific class from the SDK.
     *
     * @return GpAddress
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function mapShippingAddress()
    {
        return $this->mapAddress($this->getBillingAddress());
    }
}
