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

{if $activeGateway}
<a class="col-lg-4 col-md-6 col-sm-6 col-xs-12" href="{$link->getModuleLink('globalpayments', 'customerCards')|escape:'html':'UTF-8'}" title="{$title|escape:'html':'UTF-8'}">
    <span class="link-item">
        <i class="material-icons md-36">payment</i>
        {$title|escape:'html':'UTF-8'}
    </span>
</a>
{/if}
