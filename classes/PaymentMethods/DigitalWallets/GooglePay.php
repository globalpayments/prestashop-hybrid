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

namespace GlobalPayments\PaymentGatewayProvider\PaymentMethods\DigitalWallets;

use GlobalPayments\Api\Entities\Enums\CardType;
use GlobalPayments\Api\Entities\Enums\EncyptedMobileType;
use GlobalPayments\Api\Entities\Enums\Environment;

if (!defined('_PS_VERSION_')) {
    exit;
}

class GooglePay extends AbstractDigitalWallet
{
    public const PAYMENT_METHOD_ID = 'globalpayments_googlePay';

    public $id = self::PAYMENT_METHOD_ID;

    /**
     * {@inheritdoc}
     */
    public $adminTitle = 'Google Pay';

    /**
     * {@inheritdoc}
     */
    public $defaultTitle;

    /**
     * Indicates the card brands the merchant accepts for Click To Pay (allowedCardNetworks).
     *
     * @var string
     */
    public $ccTypes;

    /**
     * Global Payments Merchant Id
     *
     * @var string
     */
    public $globalPaymentsMerchantId;

    /**
     * Merchant Id
     *
     * @var string
     */
    public $googleMerchantId;

    /**
     * Merchant Name
     *
     * @var string
     */
    public $googleMerchantName;

    /**
     * Google pay button color
     *
     * @var string
     */
    public $buttonColor;

    /**
     * Allowed Card Auth Methods.
     *
     * @var string
     */
    public $acaMethods;

    /**
     * {@inheritdoc}
     */
    public function getMobileType()
    {
        return EncyptedMobileType::GOOGLE_PAY;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultTitle()
    {
        return $this->translator->trans('Pay with Google Pay', [], 'Modules.Globalpayments.Admin');
    }

    /**
     * {@inheritdoc}
     */
    public function enqueuePaymentScripts($module)
    {
        if (!$this->enabled) {
            return;
        }

        $context = $module->getContext();
        $path = $module->getFrontendScriptsPath();

        $context->controller->registerJavascript(
            'pay',
            'https://pay.google.com/gp/p/js/pay.js',
            ['server' => 'remote']
        );

        $context->controller->registerJavascript(
            'globalpayments-googlepay',
            $path . '/views/js/digital-wallets/globalpayments-googlepay.js'
        );

        \Media::addJsDef(
            [
                'globalpayments_googlepay_params' => [
                    'id' => $this->id,
                    'paymentMethodOptions' => $this->getFrontendPaymentMethodOptions(),
                ],
            ]
        );
    }

    public function getPaymentMethodFormFields()
    {
        return [
            $this->id . '_globalPaymentsMerchantId' => [
                'title' => $this->translator->trans('Global Payments Client ID', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'class' => 'required',
                'description' => $this->translator->trans(
                    'Your Client ID provided by Global Payments.',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => '',
            ],
            $this->id . '_googleMerchantId' => [
                'title' => $this->translator->trans('Google Merchant ID', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'description' => $this->translator->trans(
                    'Your Merchant ID provided by Google.',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => '',
                'required' => $this->gateway->isProduction ? true : false,
            ],
            $this->id . '_googleMerchantName' => [
                'title' => $this->translator->trans('Google Merchant Display Name', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'description' => $this->translator->trans(
                    'Displayed to the customer in the Google Pay dialog.',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => '',
            ],
            $this->id . '_ccTypes' => [
                'title' => $this->translator->trans('Accepted Cards', [], 'Modules.Globalpayments.Admin'),
                'type' => 'select',
                'multiple' => true,
                'class' => 'required',
                'description' => '',
                'options' => [
                    CardType::VISA => 'Visa',
                    CardType::MASTERCARD => 'MasterCard',
                    CardType::AMEX => 'AMEX',
                    CardType::DISCOVER => 'Discover',
                    CardType::JCB => 'JCB',
                ],
                'default' => [
                    CardType::VISA,
                    CardType::MASTERCARD,
                    CardType::AMEX,
                    CardType::DISCOVER,
                    CardType::JCB,
                ],
            ],
            $this->id . '_acaMethods' => [
                'title' => $this->translator->trans('Allowed Card Auth Methods', [], 'Modules.Globalpayments.Admin'),
                'type' => 'select',
                'multiple' => true,
                'class' => 'required',
                'description' => $this->translator->trans(
                    '<strong>PAN_ONLY:</strong> This authentication method is associated with payment cards stored
                    on file with the user\'s Google Account. </br>
                    <strong>CRYPTOGRAM_3DS:</strong> This authentication method is associated with cards
                    stored as Android device tokens.
                    </br></br>
                    PAN_ONLY can expose the FPAN, which requires an additional SCA step up to a 3DS check.
                    Currently, Global Payments does not support the Google Pay SCA challenge with an FPAN.
                    For the best acceptance, we recommend that you provide only the CRYPTOGRAM_3DS option.',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'options' => [
                    'PAN_ONLY' => 'PAN_ONLY',
                    'CRYPTOGRAM_3DS' => 'CRYPTOGRAM_3DS',
                ],
                'default' => [
                    'PAN_ONLY',
                    'CRYPTOGRAM_3DS',
                ],
            ],
            $this->id . '_buttonColor' => [
                'title' => $this->translator->trans('Button Color', [], 'Modules.Globalpayments.Admin'),
                'type' => 'select',
                'description' => '',
                'default' => 'white',
                'options' => [
                    'white' => $this->translator->trans('White', [], 'Modules.Globalpayments.Admin'),
                    'black' => $this->translator->trans('Black', [], 'Modules.Globalpayments.Admin'),
                ],
            ],
        ];
    }

    public function getFrontendPaymentMethodOptions()
    {
        return [
            'env' => $this->gateway->isProduction ? Environment::PRODUCTION : Environment::TEST,
            'googleMerchantId' => $this->googleMerchantId,
            'googleMerchantName' => $this->googleMerchantName,
            'globalPaymentsMerchantId' => $this->globalPaymentsMerchantId,
            'ccTypes' => explode(',', $this->ccTypes),
            'btnColor' => $this->buttonColor,
            'acaMethods' => explode(',', $this->acaMethods),
        ];
    }

    public function validateAdminSettings()
    {
        $errors = [];
        if (!\Tools::getValue($this->id . '_enabled')) {
            return $errors;
        }
        if (!\Tools::getValue($this->id . '_globalPaymentsMerchantId')) {
            $errors[] = $this->translator->trans(
                'Please provide Global Payments Client ID.',
                [],
                'Modules.Globalpayments.Admin'
            );
        }
        if (!\Tools::getValue($this->id . '_ccTypes')) {
            $errors[] = $this->translator->trans(
                'Please provide at least one accepted card type.',
                [],
                'Modules.Globalpayments.Admin'
            );
        }
        if (!\Tools::getValue($this->id . '_acaMethods')) {
            $errors[] = 'Please provide at least one allowed card auth method.';
            $errors[] = $this->translator->trans(
                'Please provide at least one allowed card auth method.',
                [],
                'Modules.Globalpayments.Admin'
            );
        }

        $sortOrderValidation = $this->configValidation->validateSortOrder($this->id);
        if ($sortOrderValidation) {
            $errors[] = $sortOrderValidation;
        }

        return $errors;
    }
}
