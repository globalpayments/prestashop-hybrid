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

class OrderAdditionalInfo
{
    public const TABLE_NAME = 'globalpayments_order_additional_info';

    /**
     * @var string
     */
    private $prefixedTableName;

    public function __construct()
    {
        $this->prefixedTableName = _DB_PREFIX_ . self::TABLE_NAME;
    }

    /**
     * Get the additional info for a specific order.
     *
     * @param int $orderId
     *
     * @return array|string|null
     *
     * @throws \PrestaShopDatabaseException
     */
    public function getAdditionalInfo($orderId, $key = null)
    {
        $query = sprintf(
            'SELECT * FROM %1$s WHERE id_order=%2$d',
            $this->prefixedTableName,
            (int) $orderId
        );

        $rawData = \Db::getInstance()->ExecuteS($query);
        if (!$rawData) {
            return null;
        }

        $additionalData = json_decode($rawData[0]['additional_info'], true);
        if (!$key) {
            return $additionalData;
        }

        return $additionalData[$key] ?? null;
    }

    /**
     * Install the order additional info table.
     *
     * @return bool
     */
    public function installTable()
    {
        if (!\Db::getInstance(_PS_USE_SQL_SLAVE_)->Execute(
            'CREATE TABLE IF NOT EXISTS `' . $this->prefixedTableName . '` (
                `id_globalpayments_order_additional_info` INT(11) NOT NULL AUTO_INCREMENT,
                `id_order` INT(11) NOT NULL,
                `additional_info` TEXT,
                PRIMARY KEY (`id_globalpayments_order_additional_info`),
                INDEX `id_order` (`id_order`)
            )
            ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8'
        )) {
            return false;
        }

        return true;
    }

    /**
     * Add/Update row to/from the order additional info table.
     *
     * @param int $orderId
     * @param string $key
     * @param string $data
     *
     * @return void
     *
     * @throws \PrestaShopDatabaseException
     */
    public function setAdditionalInfo($orderId, $key, $data)
    {
        $additionalInfo = $this->getAdditionalInfo($orderId);
        $insertData = [];
        $insertData[$key] = $data;

        if (!$additionalInfo) {
            $this->insertAdditionalInfo($orderId, $insertData);

            return;
        }

        $insertData = array_merge($additionalInfo, $insertData);
        $this->updateAdditionalInfo($orderId, $insertData);
    }

    /**
     * Insert a new row into the order additional info table.
     *
     * @param int $orderId
     * @param array $data
     *
     * @return void
     *
     * @throws \PrestaShopDatabaseException
     */
    private function insertAdditionalInfo($orderId, $data)
    {
        \Db::getInstance()->insert(self::TABLE_NAME, [
            'id_order' => (int) $orderId,
            'additional_info' => pSQL(json_encode($data)),
        ]);
    }

    /**
     * Update an existing row on the order additional info table, based on the order id.
     *
     * @param int $orderId
     * @param array $data
     *
     * @return void
     */
    private function updateAdditionalInfo($orderId, $data)
    {
        \Db::getInstance()->update(
            self::TABLE_NAME,
            [
                'additional_info' => pSQL(json_encode($data)),
            ],
            'id_order=' . $orderId
        );
    }
}
