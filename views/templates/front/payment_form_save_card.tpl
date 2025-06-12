{*
* NOTICE OF LICENSE
*
* This file is licenced under the Software License Agreement.
* With the purchase or the installation of the software in your application
* you accept the licence agreement.
*
* DISCLAIMER
*
* @author    GlobalPayments
* @copyright Since 2021 GlobalPayments
* @license   LICENSE
*}

<form class="globalpayments-save-card globalpayments-{$cardId|escape:'htmlall':'UTF-8'}" action="{$action|escape:'html':'UTF-8'}" method="post">
    <input type="hidden" name="payment-method-id" value="{$id|escape:'htmlall':'UTF-8'}" />
    <input type="hidden" name="globalpayments-payment-method" value="{$cardId|escape:'htmlall':'UTF-8'}" />
</form>
