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

namespace GlobalPayments\PaymentGatewayProvider\Platform;

use GlobalPayments\Api\Entities\Enums\TransactionStatus;
use PrestaShopBundle\Translation\TranslatorComponent as Translator;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Utils
{
    /**
     * @var Translator
     */
    protected $translator;

    /**
     * Utils constructor.
     */
    public function __construct()
    {
        $this->translator = \Context::getContext()->getTranslator();
    }

    /**
     * Get the text for the card storage page.
     *
     * @return string
     */
    public function getCardStorageText()
    {
        return $this->translator->trans('My Cards - Global Payments', [], 'Modules.Globalpayments.Shop');
    }

    /**
     * Get the currency iso code by currency id.
     *
     * @param int $currencyId
     *
     * @return string
     */
    public function getCurrencyIsoCode($currencyId)
    {
        return (new \Currency($currencyId))->iso_code;
    }

    /**
     * Get the currently used globalpayments.js version
     *
     * @return string
     */
    public static function getJsLibVersion()
    {
        return '3.0.11';
    }

    /**
     * Get translator.
     *
     * @return Translator|null
     */
    public function getTranslator()
    {
        return $this->translator;
    }

    /**
     * Format number to 2 decimal places.
     *
     * @param float $number
     *
     * @return string
     */
    public function formatNumberToTwoDecimalPlaces($number)
    {
        return number_format($number, 2, '.', '');
    }

    /**
     * Converts API response code to user friendly message.
     *
     * @param string $responseCode
     *
     * @return string
     */
    public function mapResponseCodeToFriendlyMessage($responseCode = '')
    {
        switch ($responseCode) {
            case TransactionStatus::DECLINED:
            case 'FAILED':
                return $this->translator->trans(
                    'Your payment was unsuccessful. Please try again or use a different payment method.',
                    [],
                    'Modules.Globalpayments.Shop'
                );
            default:
                return $this->translator->trans(
                    'An error occurred while processing the payment. Please try again or use a different payment method.',
                    [],
                    'Modules.Globalpayments.Shop'
                );
        }
    }

    /**
     * Sanitize string.
     *
     * @param string $string
     *
     * @return string
     */
    public function sanitizeString($string)
    {
        $string = \Tools::replaceAccentedChars($string);

        return preg_replace('/[^a-zA-Z-_.]/', '', $string);
    }
}
