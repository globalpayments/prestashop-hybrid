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

if (!defined('_PS_VERSION_')) {
    exit;
}

class ApplePay extends AbstractDigitalWallet
{
    public const PAYMENT_METHOD_ID = 'globalpayments_applePay';

    public $id = self::PAYMENT_METHOD_ID;

    /**
     * {@inheritdoc}
     */
    public $adminTitle = 'Apple Pay';

    /**
     * {@inheritdoc}
     */
    public $defaultTitle;

    /**
     * Apple Merchant Id
     *
     * @var string
     */
    public $appleMerchantId;

    /**
     * Apple Merchant Cert Path
     *
     * @var string
     */
    public $appleMerchantCertPath;

    /**
     * Apple Merchant Key Path
     *
     * @var string
     */
    public $appleMerchantKeyPath;

    /**
     * Apple Merchant Key Passphrase
     *
     * @var string
     */
    public $appleMerchantKeyPassphrase;

    /**
     * Apple Merchant Domain
     *
     * @var string
     */
    public $appleMerchantDomain;

    /**
     * Apple Merchant Display Name
     *
     * @var string
     */
    public $appleMerchantDisplayName;

    /**
     * Indicates the card brands the merchant accepts for Click To Pay (allowedCardNetworks).
     *
     * @var string
     */
    public $ccTypes;

    /**
     * Supported Apple Pay Button Styles
     *
     * @var string
     */
    public $buttonColor;

    /**
     * {@inheritdoc}
     */
    public function getMobileType()
    {
        return EncyptedMobileType::APPLE_PAY;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultTitle()
    {
        return $this->translator->trans('Pay with Apple Pay', [], 'Modules.Globalpayments.Admin');
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

        $context->controller->registerStylesheet(
            'globalpayments-applepay',
            $path . '/views/css/globalpayments-applepay.css'
        );

        $context->controller->registerJavascript(
            'globalpayments-applepay',
            $path . '/views/js/digital-wallets/globalpayments-applepay.js'
        );

        \Media::addJsDef(
            [
                'globalpayments_applepay_params' => [
                    'id' => $this->id,
                    'paymentMethodOptions' => array_merge(
                        $this->getFrontendPaymentMethodOptions(),
                        [
                            'countryCode' => $context->country->iso_code,
                            'validateMerchantUrl' => $context->link->getModuleLink(
                                $module->name,
                                'validateMerchant'
                            ),
                        ]
                    ),
                ],
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentMethodFormFields()
    {
        return [
            $this->id . '_appleMerchantId' => [
                'title' => $this->translator->trans('Apple Merchant ID', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'description' => $this->translator->trans('Apple Merchant ID', [], 'Modules.Globalpayments.Admin'),
                'default' => '',
                'required' => true,
            ],
            $this->id . '_appleMerchantCertPath' => [
                'title' => $this->translator->trans('Apple Merchant Cert Path', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'description' => $this->translator->trans('Apple Merchant Cert Path', [], 'Modules.Globalpayments.Admin'),
                'default' => '',
                'required' => true,
            ],
            $this->id . '_appleMerchantKeyPath' => [
                'title' => $this->translator->trans('Apple Merchant Key Path', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'description' => $this->translator->trans('Apple Merchant Key Path', [], 'Modules.Globalpayments.Admin'),
                'default' => '',
                'required' => true,
            ],
            $this->id . '_appleMerchantKeyPassphrase' => [
                'title' => $this->translator->trans('Apple Merchant Key Passphrase', [], 'Modules.Globalpayments.Admin'),
                'type' => 'password',
                'description' => $this->translator->trans('Apple Merchant Key Passphrase', [], 'Modules.Globalpayments.Admin'),
                'default' => '',
            ],
            $this->id . '_appleMerchantDomain' => [
                'title' => $this->translator->trans('Apple Merchant Domain', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'description' => $this->translator->trans('Apple Merchant Domain', [], 'Modules.Globalpayments.Admin'),
                'default' => '',
                'required' => true,
            ],
            $this->id . '_appleMerchantDisplayName' => [
                'title' => $this->translator->trans('Apple Merchant Display Name', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'description' => $this->translator->trans('Apple Merchant Display Name', [], 'Modules.Globalpayments.Admin'),
                'default' => '',
                'required' => true,
            ],
            $this->id . '_ccTypes' => [
                'title' => $this->translator->trans('Accepted cards', [], 'Modules.Globalpayments.Admin'),
                'type' => 'select',
                'multiple' => true,
                'class' => 'required',
                'description' => '',
                'options' => [
                    CardType::VISA => 'Visa',
                    CardType::MASTERCARD => 'MasterCard',
                    CardType::AMEX => 'AMEX',
                    CardType::DISCOVER => 'Discover',
                ],
                'default' => [
                    CardType::VISA,
                    CardType::MASTERCARD,
                    CardType::AMEX,
                    CardType::DISCOVER,
                ],
            ],
            $this->id . '_buttonColor' => [
                'title' => $this->translator->trans('Apple Pay Button Styles', [], 'Modules.Globalpayments.Admin'),
                'type' => 'select',
                'description' => $this->translator->trans(
                    'Control the Apple Pay button appearance.',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => 'black',
                'options' => [
                    'white' => $this->translator->trans('White', [], 'Modules.Globalpayments.Admin'),
                    'white-with-line' => $this->translator->trans('White with Outline', [], 'Modules.Globalpayments.Admin'),
                    'black' => $this->translator->trans('Black', [], 'Modules.Globalpayments.Admin'),
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFrontendPaymentMethodOptions()
    {
        return [
            'appleMerchantDisplayName' => $this->appleMerchantDisplayName,
            'ccTypes' => explode(',', $this->ccTypes),
            'buttonColor' => $this->buttonColor,
            'messages' => [
                'createSession' => $this->translator->trans(
                    'Unable to create ApplePay Session',
                    [],
                    'Modules.Globalpayments.Logs'
                ),
                'failedPayment' => $this->translator->trans(
                    'We\'re unable to take your payment through Apple Pay. Please try again or use an alternative payment method.',
                    [],
                    'Modules.Globalpayments.Shop'
                ),
                'httpsRequired' => $this->translator->trans(
                    'Apple Pay requires your checkout be served over HTTPS',
                    [],
                    'Modules.Globalpayments.Logs'
                ),
                'notSupported' => $this->translator->trans(
                    'Apple Pay is not supported on this device/browser',
                    [],
                    'Modules.Globalpayments.Logs'
                ),
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function validateAdminSettings()
    {
        $errors = [];
        if (!\Tools::getValue($this->id . '_enabled')) {
            return $errors;
        }
        if (!\Tools::getValue($this->id . '_appleMerchantId')) {
            $errors[] = $this->translator->trans('Please provide Apple Merchant Id.', [], 'Modules.Globalpayments.Admin');
        }
        if (!\Tools::getValue($this->id . '_appleMerchantCertPath')) {
            $errors[] = $this->translator->trans('Please provide Apple Merchant Cert Path.', [], 'Modules.Globalpayments.Admin');
        }
        if (!\Tools::getValue($this->id . '_appleMerchantKeyPath')) {
            $errors[] = $this->translator->trans('Please provide Apple Merchant Key Path.', [], 'Modules.Globalpayments.Admin');
        }
        if (!\Tools::getValue($this->id . '_appleMerchantDomain')) {
            $errors[] = $this->translator->trans('Please provide Apple Merchant Domain.', [], 'Modules.Globalpayments.Admin');
        }
        if (!\Tools::getValue($this->id . '_appleMerchantDisplayName')) {
            $errors[] = $this->translator->trans('Please provide Apple Merchant Display Name.', [], 'Modules.Globalpayments.Admin');
        }
        if (!\Tools::getValue($this->id . '_ccTypes')) {
            $errors[] = $this->translator->trans('Please provide at least one accepted card type.', [], 'Modules.Globalpayments.Admin');
        }

        $sortOrderValidation = $this->configValidation->validateSortOrder($this->id);
        if ($sortOrderValidation) {
            $errors[] = $sortOrderValidation;
        }

        return $errors;
    }
}
