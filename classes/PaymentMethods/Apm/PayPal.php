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

namespace GlobalPayments\PaymentGatewayProvider\PaymentMethods\Apm;

use GlobalPayments\Api\Entities\Enums\AlternativePaymentType;

if (!defined('_PS_VERSION_')) {
    exit;
}

class PayPal extends AbstractApm
{
    public const PAYMENT_METHOD_ID = 'globalpayments_paypal';

    public $id = self::PAYMENT_METHOD_ID;

    /**
     * @var string
     */
    public $apmProvider = AlternativePaymentType::PAYPAL;

    /**
     * {@inheritdoc}
     */
    public $adminTitle = 'PayPal';

    /**
     * {@inheritdoc}
     */
    public function getDefaultTitle()
    {
        return $this->translator->trans('Pay with PayPal', [], 'Modules.Globalpayments.Admin');
    }
}
