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
{if $displayMgmTab}
    <div class="card panel">
        <div class="panel-heading card-header">
            <i class="icon-money"></i>
            {l s='Global Payments Transaction Management' d='Modules.Globalpayments.Admin'}
        </div>

        <div class="card-body">
            <form
                id="formGlobalPayments"
                action="{$adminLink|escape:'htmlall':'UTF-8'}#globalPaymentsTransactionHistory"
                method="post">
                <div class="table-responsive">
                    {if $canCapture}
                        <table class="table">
                            <thead>
                            <tr>
                                <th><span class="title_box">{l s='Amount' d='Modules.Globalpayments.Admin'}</span></th>
                                <th><span class="title_box">{l s='Currency' d='Modules.Globalpayments.Admin'}</span></th>
                                <th><span class="title_box">{l s='Action' d='Modules.Globalpayments.Admin'}</span></th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td>
                                    <input
                                        type="text"
                                        name="globalpayments_amount"
                                        value="{$amount|escape:'htmlall':'UTF-8'}"
                                        class="form-control fixed-width-md pull-left"/>
                                </td>
                                <td>
                                    {$currency|escape:'htmlall':'UTF-8'}
                                </td>
                                <td class="actions">
                                    <button
                                        class="btn btn-primary"
                                        type="submit"
                                        name="globalpayments_transaction"
                                        value="capture">
                                        {l s='Capture' d='Modules.Globalpayments.Admin'}
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3">
                                    <em>
                                        {l s='You can Capture a transaction for any amount up to 115% or the
                                            original value.' d='Modules.Globalpayments.Admin'}
                                    </em>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    {/if}
                    {if $waitingPayment}
                        <a href="#globalpayments-pay-form" id="globalpayments-form-show" class="openform btn btn-primary">
                            {l s='Pay for Order' d='Modules.Globalpayments.Admin'}
                        </a>
                        {if $hasTxnId}
                            <a
                                href="#globalpayments-get-transaction-details-modal"
                                id="globalpayments-get-transaction-details"
                                class="openform btn btn-primary"
                                data-toggle="modal"
                                data-target="#globalpayments-get-transaction-details-modal"
                                data-url="{$getTransactionDetailsUrl|escape:'htmlall':'UTF-8'}"
                                data-transaction-id="{$transactionId|escape:'htmlall':'UTF-8'}">
                                {l s='Get Transaction Details' d='Modules.Globalpayments.Admin'}
                            </a>
                        {/if}

                        <script>
                            var gpOrderId = "{$orderId|escape:'htmlall':'UTF-8'}"
                        </script>
                    {/if}
                </div>
            </form>
            {include file="./../admin/_partials/get_transaction_details_modal.tpl"}
            <div class="cform" id="globalpayments-pay-form">
                <div class="payment-options">
                    {foreach from=$ucpOptions key=$k item=ucpOption}
                        <div
                            id="payment-option-{$k|escape:'htmlall':'UTF-8'}-container"
                            class="payment-option clearfix {if count($ucpOptions) === 1} hide{/if}">
                            <span class="custom-radio float-xs-left">
                                <input
                                    class="ps-shown-by-js"
                                    id="payment-option-{$k|escape:'htmlall':'UTF-8'}"
                                    data-module-name="{$ucpOption->getModuleName()|escape:'htmlall':'UTF-8'}"
                                    name="payment-option"
                                    type="radio"
                                    required=""
                                    {if $k === 0}checked="checked"{/if}/>
                            </span>
                            <label for="payment-option-{$k|escape:'htmlall':'UTF-8'}">
                                <span>{$ucpOption->getCallToActionText()|escape:'htmlall':'UTF-8'}</span>
                            </label>
                            <div
                                id="pay-with-payment-option-{$k|escape:'htmlall':'UTF-8'}-form"
                                class="js-payment-option-form">
                                {$ucpOption->getForm()}
                            </div>
                        </div>
                    {/foreach}
                </div>
                <div id="payment-confirmation" class="js-payment-confirmation">
                    <div class="ps-shown-by-js">
                        <button type="submit" class="btn btn-primary center-block">
                            {l s='Pay' d='Modules.Globalpayments.Admin'}
                        </button>
                    </div>
                    <div class="ps-hidden-by-js"></div>
                </div>
            </div>
        </div>
    </div>
{/if}
<div class="card panel mt-2" id="globalPaymentsTransactionHistory">
    <div class="panel-heading card-header">
        <i class="icon-money"></i>
        {$transaction_history_title|escape:'htmlall':'UTF-8'}
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
            <tr>
                <th><span class="title_box ">{l s='Date'   d='Modules.Globalpayments.Admin'}</span></th>
                <th><span class="title_box ">{l s='Action' d='Modules.Globalpayments.Admin'}</span></th>
                <th><span class="title_box ">{l s='Amount' d='Modules.Globalpayments.Admin'}</span></th>
                <th><span class="title_box ">{l s='Result' d='Modules.Globalpayments.Admin'}</span></th>
            </tr>
            </thead>
            <tbody>
                {foreach from=$transaction_history item=row key=key}
                    <tr class="{if $row['success']}success{else}danger{/if}">
                        <td>{dateFormat date=$row['date_add'] full=true}</td>
                        <td>{$row['action']|escape:'htmlall':'UTF-8'}</td>
                        <td>
                            {$row['amount']|escape:'htmlall':'UTF-8'}
                            {if !empty($row['currency'])}
                                {$row['currency']|escape:'htmlall':'UTF-8'}
                            {/if}
                        </td>
                        <td>{$row['result']|escape:'htmlall':'UTF-8'}</td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
</div>
