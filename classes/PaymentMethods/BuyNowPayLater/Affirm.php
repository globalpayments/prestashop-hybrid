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

namespace GlobalPayments\PaymentGatewayProvider\PaymentMethods\BuyNowPayLater;

use GlobalPayments\Api\Entities\Enums\BNPLType;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Affirm extends AbstractBuyNowPayLater
{
    public const PAYMENT_METHOD_ID = 'globalpayments_affirm';

    public $id = self::PAYMENT_METHOD_ID;

    /**
     * {@inheritdoc}
     */
    public $adminTitle = 'Affirm';

    public $paymentMethodBNPLProvider = BNPLType::AFFIRM;

    /**
     * {@inheritdoc}
     */
    public function getMethodAvailability()
    {
        return [
            'USD' => ['US'],
            'CAD' => ['CA'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultTitle()
    {
        return $this->translator->trans('Pay with Affirm', [], 'Modules.Globalpayments.Admin');
    }

    /**
     * {@inheritdoc}
     */
    public function isShippingRequired()
    {
        return true;
    }
}
