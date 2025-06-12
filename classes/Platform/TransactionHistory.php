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

use GlobalPayments\PaymentGatewayProvider\Requests\TransactionType;
use PrestaShopBundle\Translation\TranslatorComponent;

if (!defined('_PS_VERSION_')) {
    exit;
}

class TransactionHistory
{
    public const TABLE_NAME = 'globalpayments_transaction_history';

    /**
     * @var string
     */
    private $prefixedTableName;

    /**
     * @var TranslatorComponent
     */
    private $translator;

    public function __construct()
    {
        $this->prefixedTableName = _DB_PREFIX_ . self::TABLE_NAME;
        $this->translator = (new Utils())->getTranslator();
    }

    /**
     * Generate the transaction message.
     *
     * @param $amount
     * @param $currency
     * @param $action
     * @param $transactionId
     *
     * @return string
     */
    public function generateTransactionMessage($amount, $currency, $action, $transactionId)
    {
        return $this->translator->trans(
            '%amount% %currency% was %action%. Transaction ID: %trn_id%',
            ['%amount%' => $amount, '%currency%' => $currency, '%action%' => $action, '%trn_id%' => $transactionId],
            'Modules.Globalpayments.Admin'
        );
    }

    /**
     * Get the transaction history for a specific order.
     *
     * @param int $orderId
     *
     * @return array
     *
     * @throws \PrestaShopDatabaseException
     */
    public function getHistory($orderId)
    {
        $query = sprintf(
            'SELECT * FROM %1$s WHERE id_order=%2$d ORDER BY id_globalpayments_transaction_history DESC',
            $this->prefixedTableName,
            (int) $orderId
        );

        $result = \Db::getInstance()->ExecuteS($query);

        foreach ($result as $key => $resultRow) {
            $paymentAction = $this->getPaymentActions()[$resultRow['action']] ?? $resultRow['action'];
            $result[$key]['action'] = $paymentAction['action'];

            if (is_numeric($resultRow['result'])) {
                $result[$key]['result'] = $this->generateTransactionMessage(
                    $resultRow['amount'],
                    $resultRow['currency'],
                    $paymentAction['result'],
                    $resultRow['id_transaction']
                );
            }
        }

        return $result;
    }

    /**
     * Get the available payment actions.
     *
     * @return array[]
     */
    public function getPaymentActions()
    {
        return [
            [
                'action' => $this->translator->trans('charge', [], 'Modules.Globalpayments.Admin'),
                'result' => $this->translator->trans('charged', [], 'Modules.Globalpayments.Admin'),
            ],
            [
                'action' => $this->translator->trans('authorize', [], 'Modules.Globalpayments.Admin'),
                'result' => $this->translator->trans('authorized', [], 'Modules.Globalpayments.Admin'),
            ],
            [
                'action' => $this->translator->trans('refund/reverse', [], 'Modules.Globalpayments.Admin'),
                'result' => $this->translator->trans('reversed or refunded', [], 'Modules.Globalpayments.Admin'),
            ],
            [
                'action' => $this->translator->trans('capture', [], 'Modules.Globalpayments.Admin'),
                'result' => $this->translator->trans('captured', [], 'Modules.Globalpayments.Admin'),
            ],
            [
                'action' => $this->translator->trans('initiate payment', [], 'Modules.Globalpayments.Admin'),
                'result' => $this->translator->trans('initiated', [], 'Modules.Globalpayments.Admin'),
            ],
            [
                'action' => $this->translator->trans('decline/cancel', [], 'Modules.Globalpayments.Admin'),
                'result' => $this->translator->trans('declined or canceled', [], 'Modules.Globalpayments.Admin'),
            ],
            [
                'action' => $this->translator->trans('customer cancel', [], 'Modules.Globalpayments.Admin'),
                'result' => $this->translator->trans('cancelled by customer', [], 'Modules.Globalpayments.Admin'),
            ],
        ];
    }

    /**
     * States whether an order has a payment transaction.
     *
     * @return bool
     */
    public function hasPaymentTransaction($orderId)
    {
        try {
            foreach ($this->getHistory($orderId) as $transaction) {
                if ($transaction['action'] === TransactionType::AUTHORIZE
                    || $transaction['action'] === TransactionType::SALE
                    || $transaction['action'] === TransactionType::CAPTURE) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Map the payment action.
     *
     * @param $paymentAction
     * @return array|null
     */
    public function mapPaymentAction($paymentAction)
    {
        foreach ($this->getPaymentActions() as $key => $value) {
            if ($paymentAction === $value['action']) {
                $value['id'] = $key;

                return $value;
            }
        }

        return null;
    }

    public function installTable()
    {
        if (!\Db::getInstance(_PS_USE_SQL_SLAVE_)->Execute(
            'CREATE TABLE IF NOT EXISTS `' . $this->prefixedTableName . '` (
                `id_globalpayments_transaction_history` INT(11) NOT NULL AUTO_INCREMENT,
                `id_order` INT(11) NOT NULL,
                `action` VARCHAR(50) NOT NULL,
                `amount` DECIMAL(20,2) NOT NULL,
                `currency` VARCHAR(10) NOT NULL,
                `result` VARCHAR(255) NOT NULL,
                `id_transaction` VARCHAR(255) NOT NULL,
                `success` INT(1) NOT NULL DEFAULT "0",
                `date_add` DATETIME NOT NULL,
                PRIMARY KEY (`id_globalpayments_transaction_history`),
                INDEX `id_order` (`id_order`)
            )
            ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8'
        )
        ) {
            return false;
        }

        return true;
    }

    public function updateTable()
    {
        if (!\Db::getInstance(_PS_USE_SQL_SLAVE_)->Execute(
            'ALTER TABLE `' . $this->prefixedTableName . '`
                ADD COLUMN `currency` VARCHAR(10) NOT NULL,
                ADD COLUMN `id_transaction` VARCHAR(255) NOT NULL,
                MODIFY COLUMN `amount` DECIMAL(20,2);'
        )) {
            return false;
        }

        return true;
    }

    /**
     * Add a record to the transaction history.
     *
     * @param int $orderId
     * @param string $action
     * @param float $amount
     * @param string $result
     * @param int $success
     *
     * @throws \PrestaShopDatabaseException
     */
    public function saveResult($orderId, $action, $amount, $currency, $transactionId, $success, $result = '1')
    {
        $actionId = $this->mapPaymentAction($action)['id'] ?? 1;

        \Db::getInstance()->insert(self::TABLE_NAME, [
            'id_order' => (int) $orderId,
            'action' => pSQL($actionId),
            'amount' => pSQL($amount),
            'currency' => pSQL($currency),
            'result' => pSQL($result),
            'id_transaction' => pSQL($transactionId),
            'success' => (int) $success,
            'date_add' => date('Y-m-d H:i:s'),
        ]);
    }
}
