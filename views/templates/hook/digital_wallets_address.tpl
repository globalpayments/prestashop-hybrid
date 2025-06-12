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
{if $canDisplayInfo}
    <div class="card panel mt-2" id="globalPaymentsDigitalWalletsAddress">
        <div class="panel-heading card-header">
            <i class="icon-money"></i>
            {$title|escape:'htmlall':'UTF-8'}
        </div>
        <div class="card-body">
            <div class="row">
                {if $shippingAddress}
                    <div id="globalPaymentsDigitalWalletsAddressShipping" class="info-block-col col-xl-6">
                        <div class="row justify-content-between no-gutters">
                            <strong>
                                {l s='Shipping address' d='Modules.Globalpayments.Admin'}
                            </strong>
                        </div>
                        <p class="mb-0">
                            {$customerName|escape:'htmlall':'UTF-8'}
                        </p>
                        <p class="mb-0">
                            {$customerEmail|escape:'htmlall':'UTF-8'}
                        </p>
                        {foreach from=$shippingAddress item=item}
                            <p class="mb-0">
                                {$item|escape:'htmlall':'UTF-8'}
                            </p>
                        {/foreach}
                    </div>
                {/if}
                <div id="globalPaymentsDigitalWalletsAddressShipping" class="info-block-col col-xl-6">
                    <div class="row justify-content-between no-gutters">
                        <strong>
                            {l s='Billing address' d='Modules.Globalpayments.Admin'}
                        </strong>
                    </div>
                    <p class="mb-0">
                        {$customerName|escape:'htmlall':'UTF-8'}
                    </p>
                    <p class="mb-0">
                        {$customerEmail|escape:'htmlall':'UTF-8'}
                    </p>
                    {foreach from=$shippingAddress item=item}
                        <p class="mb-0">
                            {$item|escape:'htmlall':'UTF-8'}
                        </p>
                    {/foreach}
                </div>
            </div>
        </div>
    </div>
{/if}
