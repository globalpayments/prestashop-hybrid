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

namespace GlobalPayments\PaymentGatewayProvider\Platform\Admin\Order\View;

use GlobalPayments\PaymentGatewayProvider\PaymentMethods\DigitalWallets\AbstractDigitalWallet;
use GlobalPayments\PaymentGatewayProvider\Platform\OrderAdditionalInfo;
use PrestaShopBundle\Translation\TranslatorComponent as Translator;

if (!defined('_PS_VERSION_')) {
    exit;
}

class DigitalWalletsAddress
{
    /**
     * @var array
     */
    protected $billingAddress;

    /**
     * @var string
     */
    protected $customerName;

    /**
     * @var string
     */
    protected $customerEmail;

    /**
     * @var \GlobalPayments
     */
    protected $module;

    /**
     * @var \Order
     */
    protected $order;

    /**
     * @var array
     */
    protected $orderAdditionalInfo;

    /**
     * @var array
     */
    protected $shippingAddress;

    /**
     * @var Translator
     */
    protected $translator;

    /**
     * DigitalWalletsAddress constructor.
     *
     * @param \GlobalPayments $module
     * @param \Order $order
     */
    public function __construct(
        \GlobalPayments $module,
        \Order $order
    ) {
        $this->module = $module;
        $this->order = $order;
        $this->translator = $this->module->getTranslator();

        $orderAdditionalInfo = new OrderAdditionalInfo();
        try {
            $this->orderAdditionalInfo = $orderAdditionalInfo->getAdditionalInfo($this->order->id);
        } catch (\Exception $e) {
            return;
        }

        $addressInfo = !empty($this->orderAdditionalInfo[AbstractDigitalWallet::DIGITAL_WALLET_PAYER_DETAILS]) ?
            json_decode($this->orderAdditionalInfo[AbstractDigitalWallet::DIGITAL_WALLET_PAYER_DETAILS], true) : null;

        if (!$addressInfo) {
            return;
        }

        $this->billingAddress = $addressInfo['billingAddress'] ?? null;
        $this->shippingAddress = $addressInfo['shippingAddress'] ?? null;
        $this->customerEmail = $addressInfo['email'] ?? '';

        $firstName = $addressInfo['firstName'] ?? '';
        $lastName = $addressInfo['lastName'] ?? '';
        $this->customerName = $firstName . ' ' . $lastName;
    }

    /**
     * Assign the smarty data for the template.
     *
     * @return void
     */
    private function assignDataToTemplate()
    {
        $this->module->getContext()->smarty->assign([
            'canDisplayInfo' => $this->canDisplayInfo(),
            'customerName' => $this->customerName,
            'customerEmail' => $this->customerEmail,
            'billingAddress' => $this->billingAddress,
            'shippingAddress' => $this->shippingAddress,
            'title' => $this->getTitle(),
        ]);
    }

    /**
     * States whether the payment additional information can be displayed.
     *
     * @return bool
     */
    private function canDisplayInfo()
    {
        return !empty($this->billingAddress);
    }

    /**
     * Get the template.
     *
     * @return string
     */
    public function getTemplate()
    {
        $this->assignDataToTemplate();

        return $this->module->display(
            $this->module->getPath(),
            '/views/templates/hook/digital_wallets_address.tpl'
        );
    }

    /**
     * Get the title of the section.
     *
     * @return string
     */
    private function getTitle()
    {
        return $this->translator->trans(
            '%title% Address Information',
            ['%title%' => $this->order->payment],
            'Modules.Globalpayments.Admin'
        );
    }
}
