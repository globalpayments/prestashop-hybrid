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

class Token
{
    private $userId;

    private $gatewayId;

    private $token;

    private $last4;

    private $expiryYear;

    private $expiryMonth;

    private $cardType;

    public function getUserId()
    {
        return $this->userId;
    }

    public function setUserId($userId)
    {
        $this->userId = $userId;
    }

    public function getGatewayId()
    {
        return $this->gatewayId;
    }

    public function setGatewayId($gatewayId)
    {
        $this->gatewayId = $gatewayId;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function setToken($token)
    {
        $this->token = $token;
    }

    public function getLast4()
    {
        return $this->last4;
    }

    public function setLast4($last4)
    {
        $this->last4 = $last4;
    }

    public function getExpiryYear()
    {
        return $this->expiryYear;
    }

    public function setExpiryYear($expiryYear)
    {
        $this->expiryYear = $expiryYear;
    }

    public function getExpiryMonth()
    {
        return $this->expiryMonth;
    }

    public function setExpiryMonth($expiryMonth)
    {
        $this->expiryMonth = $expiryMonth;
    }

    public function getCardType()
    {
        return $this->cardType;
    }

    public function setCardType($cardType)
    {
        $this->cardType = $cardType;
    }

    public function save()
    {
        $details = new \stdClass();
        $details->last_4 = pSQL($this->last4);
        $details->expiry_year = pSQL($this->expiryYear);
        $details->expiry_month = pSQL($this->expiryMonth);
        $details->card_type = pSQL($this->cardType);

        \Db::getInstance()->insert('globalpayments_token', [
            'id_customer' => (int) $this->userId,
            'id_gateway' => pSQL($this->gatewayId),
            'token' => pSQL($this->token),
            'type' => 'card',
            'details' => json_encode($details),
        ]);

        return true;
    }

    /**
     * @param array $tokenData
     *
     * @return \stdClass
     *
     * Creates a new stdClass from the data given
     */
    public static function processToken($tokenData)
    {
        $token = new \stdClass();

        foreach ($tokenData as $key => $value) {
            if ($key === 'details') {
                $value = json_decode($value);
                $details = new \stdClass();
                $details->last4 = $value->last_4;
                $details->expiryYear = $value->expiry_year;
                $details->expiryMonth = $value->expiry_month;
                $details->cardType = $value->card_type;
                $value = $details;
            }

            if ($key !== 'token') {
                $token->{$key} = $value;
            } else {
                $token->{'paymentReference'} = $value;
            }
        }

        return $token;
    }

    /**
     * Gets token data based on the token id
     *
     * @param $tokenId
     *
     * @return \stdClass
     *
     * @throws \PrestaShopDatabaseException
     */
    public static function get($tokenId)
    {
        $query = sprintf(
            'SELECT * FROM `' . _DB_PREFIX_ . 'globalpayments_token` pt
            WHERE pt.`id_globalpayments_token` = %s',
            (int) $tokenId
        );

        $rawToken = \Db::getInstance()->ExecuteS($query);

        if (!$rawToken) {
            return null;
        }

        return self::processToken($rawToken[0]);
    }

    /**
     * Gets all the tokens for a user
     *
     * @param int $userId
     * @param int $gatewayId
     *
     * @return array
     *
     * @throws \PrestaShopDatabaseException
     */
    public static function getCustomerTokens($userId, $gatewayId)
    {
        $query = sprintf(
            'SELECT * FROM `' . _DB_PREFIX_ . 'globalpayments_token`
            WHERE `id_customer` = %s AND `id_gateway` = \'%s\'',
            (int) $userId,
            pSQL($gatewayId)
        );

        $rawTokens = \Db::getInstance()->ExecuteS($query);

        $tokens = [];

        foreach ($rawTokens as $rawToken) {
            $tokens[] = self::processToken($rawToken);
        }

        return $tokens;
    }

    public static function delete($tokenId)
    {
        return \Db::getInstance()->delete('globalpayments_token', 'id_globalpayments_token=' . (int) $tokenId);
    }

    public static function installTokenDb()
    {
        if (!\Db::getInstance(_PS_USE_SQL_SLAVE_)->Execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'globalpayments_token` (
                `id_globalpayments_token` INT(10) NOT NULL AUTO_INCREMENT,
                `id_customer` INT(10) NOT NULL,
                `id_gateway` VARCHAR(50) NOT NULL,
                `token` VARCHAR(50) NOT NULL,
                `type` VARCHAR(50) NOT NULL,
                `details` TEXT,
                PRIMARY KEY (`id_globalpayments_token`),
                INDEX `id_customer` (`id_customer`)
            )
            ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8'
        )) {
            return false;
        }

        return true;
    }
}
