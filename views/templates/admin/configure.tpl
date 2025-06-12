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

<ul class="nav nav-tabs" id="globalPaymentsTab" role="tablist">
    {foreach from=$gateways key=k item=gateway}
        {if $k === 0}
            <li class="nav-item active">
                <a
                    class="nav-link"
                    id="{$gateway->id|escape:'htmlall':'UTF-8'}-tab"
                    data-toggle="tab"
                    href="#{$gateway->id|escape:'htmlall':'UTF-8'}"
                    role="tab"
                    aria-controls="{$gateway->id|escape:'htmlall':'UTF-8'}"
                    aria-selected="true"
                >
                    {$gateway->adminTitle|escape:'htmlall':'UTF-8'}
                </a>
            </li>
        {else}
            <li class="nav-item">
                <a
                    class="nav-link"
                    id="{$gateway->id|escape:'htmlall':'UTF-8'}-tab"
                    data-toggle="tab"
                    href="#{$gateway->id|escape:'htmlall':'UTF-8'}"
                    role="tab"
                    aria-controls="{$gateway->id|escape:'htmlall':'UTF-8'}"
                    aria-selected="false"
                >
                    {$gateway->adminTitle|escape:'htmlall':'UTF-8'}
                </a>
            </li>
        {/if}
    {/foreach}
</ul>
<div class="tab-content" id="globalPaymentsTabContent">
    {foreach from=$forms key=k item=form}
        {if $k === $firstKey}
            <div
                class="tab-pane active"
                id="{$k|escape:'htmlall':'UTF-8'}"
                aria-labelledby="{$k|escape:'htmlall':'UTF-8'}-tab"
            >
                {$form nofilter}
            </div>
        {else}
            <div
                class="tab-pane fade"
                id="{$k|escape:'htmlall':'UTF-8'}"
                aria-labelledby="{$k|escape:'htmlall':'UTF-8'}-tab"
            >
                {$form nofilter}
            </div>
        {/if}
    {/foreach}
</div>
