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

<form action="{$action|escape:'html':'UTF-8'}" class="{$id|escape:'html':'UTF-8'}-payment-form globalpayments-payment-form" id="{$id|escape:'html':'UTF-8'}-payment-form" method="post">
    <input type="hidden" name="payment-method-id" value="{$id|escape:'htmlall':'UTF-8'}" />
    {if $envIndicator}
        <div class="globalpayments-sandbox-warning">
            {l s='This page is currently in sandbox/test mode. Do not use real/active card numbers.' d='Modules.Globalpayments.Shop'}
        </div>
    {/if}
    {foreach from=$formData item=formItem}
        <div class="globalpayments {$id|escape:'html':'UTF-8'} {$formItem['class']|escape:'html':'UTF-8'}">
            <label for="{$id|escape:'html':'UTF-8'}-{$formItem['class']|escape:'html':'UTF-8'}">
                {$formItem['label']|escape:'html':'UTF-8'}
                <span class="required"> *</span>
            </label>
            <div id="{$id|escape:'html':'UTF-8'}-{$formItem['class']|escape:'html':'UTF-8'}"></div>
            <ul class="globalpayments-validation-error" style="display: none;">
                <li>{$formItem['messages']['validation']|escape:'html':'UTF-8'}</li>
            </ul>
        </div>
    {/foreach}
    {if $allowCardSaving}
        <div class="enable-vault {$id|escape:'html':'UTF-8'}-save-card">
            <span class="custom-checkbox">
                <input type="checkbox" id="{$id|escape:'html':'UTF-8'}-enable-vault" name="{$id|escape:'html':'UTF-8'}-enable-vault" />
                <span><i class="material-icons rtl-no-flip checkbox-checked"></i></span>
                <label class="enable-vault-label" for="{$id|escape:'html':'UTF-8'}-enable-vault">{l s="Save for later use" d="Modules.Globalpayments.Shop"}</label>
            </span>
        </div>
    {/if}
</form>
