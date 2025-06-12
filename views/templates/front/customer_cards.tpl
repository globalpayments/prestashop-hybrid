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

{extends file='customer/page.tpl'}

{block name='page_title'}
    {$title|escape:'htmlall':'UTF-8'}
{/block}

{block name='page_content'}
    {if $cards|@count > 0}
        <table class="table table-striped table-bordered table-labeled table-responsive-lg">
            <thead class="thead-default">
            <tr>
                <th class="text-sm-center">{l s='Type' d='Modules.Globalpayments.Shop'}</th>
                <th class="text-sm-center">{l s='Card number' d='Modules.Globalpayments.Shop'}</th>
                <th class="text-sm-center">{l s='Validity' d='Modules.Globalpayments.Shop'}</th>
                <th class="text-sm-center">{l s='Delete' d='Modules.Globalpayments.Shop'}</th>
            </tr>
            </thead>
            <tbody>
            {foreach from=$cards item=card}
                {$params = ['id' => $card->id_globalpayments_token]}
                <tr>
                    <td class="text-sm-center" data-label="{l s='Type' d='Modules.Globalpayments.Shop'}">
                        {$card->details->cardType|capitalize|escape:'htmlall':'UTF-8'}
                    </td>
                    <td class="text-sm-center" data-label="{l s='Card number' d='Modules.Globalpayments.Shop'}">
                        **** **** **** {$card->details->last4|escape:'htmlall':'UTF-8'}
                    </td>
                    <td class="text-sm-center" data-label="{l s='Validity' d='Modules.Globalpayments.Shop'}">
                        {$card->details->expiryMonth|escape:'htmlall':'UTF-8'}/{$card->details->expiryYear|escape:'htmlall':'UTF-8'}
                    </td>
                    <td class="text-sm-center" data-label="{l s='Type' d='Modules.Globalpayments.Shop'}">
                        <a
                            class="remove_card"
                            href="{$link->getModuleLink('globalpayments', 'removeCustomerCard', $params)|escape:'html':'UTF-8'}"
                        >
                            <i class="material-icons md-36">delete_forever</i>
                        </a>
                    </td>
                </tr>
            {/foreach}
            </tbody>
        </table>
    {else}
        <p>{l s='You haven\'t registered a card yet.' d='Modules.Globalpayments.Shop'}</p>
    {/if}
{/block}
