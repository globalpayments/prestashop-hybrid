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
use GlobalPayments\PaymentGatewayProvider\Platform\OrderAdditionalInfo;
use GlobalPayments\PaymentGatewayProvider\Requests\TransactionType;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ClickToPay extends AbstractDigitalWallet
{
    public const PAYMENT_METHOD_ID = 'globalpayments_clicktopay';

    public $id = self::PAYMENT_METHOD_ID;

    /**
     * {@inheritdoc}
     */
    public $adminTitle = 'Click To Pay';

    /**
     * {@inheritdoc}
     */
    public $defaultTitle;

    /**
     * Refers to the merchant’s account for Click To Pay.
     *
     * @var string
     */
    public $ctpClientId;

    /**
     * Indicates the display mode of Click To Pay.
     *
     * @var bool
     */
    public $buttonless;

    /**
     * Indicates the card brands the merchant accepts for Click To Pay (allowedCardNetworks).
     *
     * @var string
     */
    public $ccTypes;

    /**
     * Indicates whether Canadian Visa debit cards are accepted.
     *
     * @var bool
     */
    public $canadianDebit;

    /**
     * Indicates whether the Global Payments footer is displayed during Click To Pay.
     *
     * @var bool
     */
    public $wrapper;

    /**
     * {@inheritdoc}
     */
    public function getDefaultTitle()
    {
        return $this->translator->trans('Pay with Click To Pay', [], 'Modules.Globalpayments.Admin');
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

        $this->gateway->hostedFieldsScript($module);

        $context->controller->registerStylesheet(
            'globalpayments-clicktopay',
            $path . '/views/css/globalpayments-clicktopay.css'
        );

        $context->controller->registerJavascript(
            'globalpayments-clicktopay',
            $path . '/views/js/digital-wallets/globalpayments-clicktopay.js'
        );

        \Media::addJsDef(
            [
                'globalpayments_clicktopay_params' => [
                    'id' => $this->id,
                    'paymentMethodOptions' => $this->getFrontendPaymentMethodOptions(),
                ],
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getMobileType()
    {
        return EncyptedMobileType::CLICK_TO_PAY;
    }

    public function getPaymentMethodFormFields()
    {
        return [
            $this->id . '_ctpClientId' => [
                'title' => $this->translator->trans('Click To Pay Client ID', [], 'Modules.Globalpayments.Admin'),
                'type' => 'text',
                'description' => $this->translator->trans(
                    'Your Merchant ID provided by Click To Pay',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => '',
                'class' => 'required',
            ],
            $this->id . '_buttonless' => [
                'title' => $this->translator->trans('Render Click To Pay natively', [], 'Modules.Globalpayments.Admin'),
                'type' => 'switch',
                'description' => $this->translator->trans(
                    'Click To Pay will render natively within the payment form',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => 0,
            ],
            $this->id . '_canadianDebit' => [
                'title' => $this->translator->trans('Accept Canadian Visa debit cards', [], 'Modules.Globalpayments.Admin'),
                'type' => 'switch',
                'description' => $this->translator->trans('Accept Canadian Visa debit cards', [], 'Modules.Globalpayments.Admin'),
                'default' => 0,
            ],
            $this->id . '_wrapper' => [
                'title' => $this->translator->trans('Display Global Payments footer', [], 'Modules.Globalpayments.Admin'),
                'type' => 'switch',
                'description' => $this->translator->trans(
                    'Display Global Payments footer within the payment form',
                    [],
                    'Modules.Globalpayments.Admin'
                ),
                'default' => 0,
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
                ],
                'default' => [
                    CardType::VISA,
                    CardType::MASTERCARD,
                    CardType::AMEX,
                    CardType::DISCOVER,
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentActionOptions()
    {
        return [
            TransactionType::SALE => $this->translator->trans('Authorize + Capture', [], 'Modules.Globalpayments.Admin'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentActionIsDisabled()
    {
        return true;
    }

    public function getFrontendPaymentMethodOptions()
    {
        try {
            return array_merge(
                $this->gateway->getFrontendGatewayOptions(),
                [
                    'apms' => [
                        'allowedCardNetworks' => explode(',', $this->ccTypes),
                        'clickToPay' => [
                            'buttonless' => $this->buttonless,
                            'canadianDebit' => $this->canadianDebit,
                            'cardForm' => false,
                            'ctpClientId' => $this->ctpClientId,
                            'wrapper' => $this->wrapper,
                        ],
                    ],
                ]
            );
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function processPaymentAfterGatewayResponse($gatewayResponse, $orderId)
    {
        if (!$gatewayResponse->payerDetails) {
            return null;
        }

        $orderAdditionalInfo = new OrderAdditionalInfo();
        $orderAdditionalInfo->setAdditionalInfo(
            $orderId,
            AbstractDigitalWallet::DIGITAL_WALLET_PAYER_DETAILS,
            json_encode($gatewayResponse->payerDetails)
        );
    }

    public function validateAdminSettings()
    {
        $errors = [];
        if (!\Tools::getValue($this->id . '_enabled')) {
            return $errors;
        }
        if (!\Tools::getValue($this->id . '_ctpClientId')) {
            $errors[] = 'Please provide Click To Pay Client ID.';
            $errors[] = $this->translator->trans('Please provide Click To Pay Client ID.', [], 'Modules.Globalpayments.Admin');
        }
        if (!\Tools::getValue($this->id . '_ccTypes')) {
            $errors[] = 'Please provide at least one accepted card type.';
            $errors[] = $this->translator->trans(
                'Please provide at least one accepted card type.',
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
