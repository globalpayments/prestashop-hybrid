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

use GlobalPayments\Api\Entities\Enums\{ShaHashType, TransactionStatus};
use GlobalPayments\PaymentGatewayProvider\Gateways\GpApiGateway;
use LogicException;
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
        return '4.1.11';
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

    /**
     * Validate that querry string hasn't been tamperred with. This requires us to take the querry string
     * minus the signature at the beginning, concatenating the merchant's app key to the end of that, and
     * calculating the SHA512 hash. That string should equal the request signature at the beginning of the
     * querry string.
     * 
     * @return void 
     * @throws LogicException 
     */
    public static function validateSignature(): void
    {
        $gateway = new GpApiGateway();

        $querryString = substr($_SERVER["QUERY_STRING"], strpos($_SERVER["QUERY_STRING"], 'X-GP-Signature'));

        $signature = substr(substr($querryString, 0, strpos($querryString, 'id=') - 1), 15);

        $querryStringSubString = substr($_SERVER["QUERY_STRING"], strpos($_SERVER["QUERY_STRING"], '&id=') + 1);

        $calculatedSignature = hash(
            ShaHashType::SHA512,
            $querryStringSubString . $gateway->getCredentialSetting('appKey')
        );

        if ($signature !== $calculatedSignature) {
            throw new LogicException('Invalid request signature.');
        };
    }
}
