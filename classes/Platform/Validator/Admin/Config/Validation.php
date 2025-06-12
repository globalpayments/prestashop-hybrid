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

namespace GlobalPayments\PaymentGatewayProvider\Platform\Validator\Admin\Config;

if (!defined('_PS_VERSION_')) {
    exit;
}

use GlobalPayments\PaymentGatewayProvider\Platform\Utils;
use PrestaShopBundle\Translation\TranslatorComponent as Translator;

class Validation
{
    /**
     * @var Translator
     */
    protected $translator;

    /**
     * Config Validation constructor.
     */
    public function __construct()
    {
        $this->translator = (new Utils())->getTranslator();
    }

    /**
     * Validate the sort order.
     *
     * @param string $id
     *
     * @return string|null
     */
    public function validateSortOrder(string $id)
    {
        if (!\Tools::getIsset($id . '_sortOrder') || is_numeric(\Tools::getValue($id . '_sortOrder'))) {
            return null;
        }

        return $this->translator->trans('Sort Order must have a numeric value', [], 'Modules.Globalpayments.Admin');
    }
}
