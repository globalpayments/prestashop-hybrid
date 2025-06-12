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

use GlobalPayments\Api\Entities\Customer as GpCustomer;
use GlobalPayments\Api\Entities\Enums\PhoneNumberType;
use GlobalPayments\Api\Entities\PhoneNumber;
use GlobalPayments\Api\Utils\CountryUtils;
use GlobalPayments\PaymentGatewayProvider\Platform\Utils;

if (!defined('_PS_VERSION_')) {
    exit;
}

class CustomerHelper
{
    protected $utils;

    /**
     * CustomerHelper constructor.
     */
    public function __construct()
    {
        $this->utils = new Utils();
    }

    /**
     * Map the customer from PrestaShop, based on the cart id, to the specific class from the SDK.
     *
     * @param $cartId
     * @return GpCustomer
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function getCustomerDataByCartId($cartId)
    {
        $cart = new \Cart($cartId);
        $addressHelper = new AddressHelper();
        $billingAddress = $addressHelper->getBillingAddress();
        $customer = new \Customer($cart->id_customer);

        $gpCustomer = new GpCustomer();
        $gpCustomer->id = (string) $customer->id;
        $gpCustomer->firstName = $this->utils->sanitizeString($billingAddress['firstName']);
        $gpCustomer->lastName = $this->utils->sanitizeString($billingAddress['lastName']);
        $gpCustomer->email = $customer->email;
        $phoneCode = CountryUtils::getPhoneCodesByCountry($billingAddress['country']);
        $gpCustomer->phone = new PhoneNumber($phoneCode[0], $billingAddress['phone'], PhoneNumberType::HOME);

        return $gpCustomer;
    }
}
