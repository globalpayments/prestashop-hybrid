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

if (!defined('_PS_VERSION_')) {
    exit;
}

class OrderStateInstaller
{
    public const CAPTURE_WAITING = 'GLOBALPAYMENTS_CAPTURE_WAITING';
    public const PAYMENT_WAITING = 'GLOBALPAYMENTS_PAYMENT_WAITING';

    /**
     * Install all the orders states.
     *
     * @return bool
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function install()
    {
        /*
         * Order state for GlobalPayments Capture Waiting
         */
        $this->installOrderState(self::CAPTURE_WAITING, $this->getCaptureMessages());

        /*
         * Order state for GlobalPayments Payment Waiting
         */
        $this->installOrderState(self::PAYMENT_WAITING, $this->getPaymentMessages());

        return true;
    }

    /**
     * Update the existing order states.
     *
     * @return bool
     */
    public function update()
    {
        try {
            $this->updateOrderState(self::CAPTURE_WAITING, $this->getCaptureMessages());
            $this->updateOrderState(self::PAYMENT_WAITING, $this->getPaymentMessages());

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Remove the unused 'verify' state.
     *
     * @return void
     */
    public function removeVerifyState()
    {
        $verifyId = \Configuration::get('GLOBALPAYMENTS_VERIFY_WAITING');
        \Db::getInstance()->delete('order_state_lang', 'id_order_state=' . $verifyId);
        \Db::getInstance()->delete('order_state', 'id_order_state=' . $verifyId);
    }

    /**
     * Get the capture message for different languages.
     *
     * @return array
     */
    private function getCaptureMessages()
    {
        return [
            'en' => 'Waiting for GlobalPayments capture',
            'fr' => 'En attente de la capture GlobalPayments',
        ];
    }

    /**
     * Get the payment message for different languages.
     *
     * @return array
     */
    private function getPaymentMessages()
    {
        return [
            'en' => 'Waiting for GlobalPayments payment',
            'fr' => 'En attente du paiement GlobalPayments',
        ];
    }

    /**
     * Install order state.
     *
     * @param string $configKey
     * @param array $messages
     *
     * @return void
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private function installOrderState($configKey, $messages)
    {
        if (!\Configuration::get($configKey)
            || !\Validate::isLoadedObject(new \OrderState(\Configuration::get($configKey)))
        ) {
            $orderState = $this->setOrderStateName($messages);
            $orderState->invoice = false;
            $orderState->send_email = false;
            $orderState->logable = true;
            $orderState->color = '#809FFF';

            if ($orderState->add()) {
                $source = _PS_ROOT_DIR_ . '/img/os/' . (int) \Configuration::get('PS_OS_PREPARATION') . '.gif';
                $destination = _PS_ROOT_DIR_ . '/img/os/' . (int) $orderState->id . '.gif';
                copy($source, $destination);
            }

            \Configuration::updateValue($configKey, $orderState->id);
        }
    }

    /**
     * Set order state name based on the language.
     *
     * @param array $messages
     * @param string $id
     *
     * @return \OrderState
     */
    private function setOrderStateName($messages, $id = null)
    {
        $orderState = new \OrderState($id);
        $orderState->name = [];
        foreach (\Language::getLanguages() as $language) {
            switch (\Tools::strtolower($language['iso_code'])) {
                case 'fr':
                case 'qc':
                    $orderState->name[$language['id_lang']] = pSQL($messages['fr']);
                    break;
                default:
                    $orderState->name[$language['id_lang']] = pSQL($messages['en']);
                    break;
            }
        }

        return $orderState;
    }

    /**
     * Update the existing order states.
     *
     * @param string $configKey
     * @param array $messages
     *
     * @return void
     *
     * @throws \PrestaShopException
     */
    private function updateOrderState($configKey, $messages)
    {
        $orderStateId = \Configuration::get($configKey);
        if (!$orderStateId) {
            return;
        }

        $orderState = $this->setOrderStateName($messages, $orderStateId);
        $orderState->update();
    }
}
