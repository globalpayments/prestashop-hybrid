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

<form class="globalpayments-digital-wallet" action="{$action|escape:'html':'UTF-8'}" id="{$id|escape:'html':'UTF-8'}-payment-form" method="post">
  <div id="{$id|escape:'htmlall':'UTF-8'}"></div>
  <input type="hidden" name="payment-method-id" value="{$id|escape:'htmlall':'UTF-8'}" />
</form>
