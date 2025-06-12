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

class Clearpay extends AbstractBuyNowPayLater
{
    public const PAYMENT_METHOD_ID = 'globalpayments_clearpay';

    public $id = self::PAYMENT_METHOD_ID;

    public $paymentMethodBNPLProvider = BNPLType::CLEARPAY;

    /**
     * {@inheritdoc}
     */
    public $adminTitle = 'Clearpay';

    /**
     * {@inheritdoc}
     */
    public function getMethodAvailability()
    {
        return [
            'CAD' => ['CA'],
            'USD' => ['US'],
            'GBP' => ['GB'],
            'AUD' => ['AU'],
            'NZD' => ['NZ'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultTitle()
    {
        return $this->translator->trans('Pay with Clearpay', [], 'Modules.Globalpayments.Admin');
    }
}
