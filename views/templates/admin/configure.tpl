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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const currentEndpointLabel = "{l s='Current Endpoint:' mod='globalpayments' js=1}";
    const eratyTooltipMessage = "{l s='This payment method must be enabled at the merchant account level.' mod='globalpayments' js=1}";

    // Transaction endpoints from server-side data
    const transactionEndpoints = {
        {foreach from=$gateways item=gateway}
            {if $gateway->id === 'globalpayments_ucp'}
                {foreach from=$gateway->transactionEndpoints key=key item=endpoint}
                    '{$key|escape:'javascript':'UTF-8'}': '{$endpoint|escape:'javascript':'UTF-8'}',
                {/foreach}
            {/if}
        {/foreach}
    };

    // Get endpoint based on live mode and region
    function getEndpointDynamically(liveMode, region) {
        // Map region names to endpoint abbreviations
        const regionMap = {
            'global': 'global',
            'europe': 'eu'
        };

        const mappedRegion = regionMap[region] || region;
        const endpointKey = '%' + mappedRegion + '_' + (liveMode ? 'prod' : 'sandbox') + '%';

        return transactionEndpoints[endpointKey] || 'Error: Endpoint not found for ' + endpointKey;
    }

    // Update endpoint display
    function updateEndpointDisplay() {
        const liveModeRadios = document.querySelectorAll('input[name="globalpayments_ucp_isProduction"]');
        const regionSelect = document.getElementById('globalpayments_ucp_transactionRegion');

        if (!liveModeRadios.length || !regionSelect) {
            return;
        }

        const liveMode = Array.from(liveModeRadios).find(r => r.checked)?.value === '1';
        const region = regionSelect.value || 'global';
        const endpoint = getEndpointDynamically(liveMode, region);
        const displayEndpoint = endpoint.replace(/\/ucp\/?$/, '');

        const legacyEndpointBlock = document.getElementById('current-endpoint-display');
        if (legacyEndpointBlock) {
            legacyEndpointBlock.remove();
        }

        const regionFormGroup = regionSelect.closest('.form-group');
        if (!regionFormGroup) {
            return;
        }

        let endpointDescription = regionFormGroup.querySelector('.gp-endpoint-description');
        if (!endpointDescription) {
            endpointDescription = document.createElement('p');
            endpointDescription.className = 'help-block gp-endpoint-description';
            endpointDescription.style.marginTop = '8px';

            const contentContainer = regionFormGroup.querySelector('.col-lg-9, .col-md-9, .col-sm-9, .col-lg-8, .col-md-8, .col-sm-8') || regionFormGroup;
            contentContainer.appendChild(endpointDescription);
        }

        endpointDescription.innerHTML = '<strong>' + currentEndpointLabel + '</strong> ' + displayEndpoint;
    }

    // Listen for changes on Live Mode and Transaction Region fields
    document.addEventListener('change', function(e) {
        if (e.target.name === 'globalpayments_ucp_isProduction' || e.target.id === 'globalpayments_ucp_transactionRegion') {
            updateEndpointDisplay();
        }
    });

    // Add tooltip to Transaction Region label
    function addTransactionRegionTooltip() {
        // First check if the field exists
        const regionField = document.getElementById('globalpayments_ucp_transactionRegion');
        if (!regionField) {
            return false;
        }

        // Find the label by looking for the closest form-group and then the label
        const formGroup = regionField.closest('.form-group');
        if (!formGroup) {
            return false;
        }

        const regionLabel = formGroup.querySelector('label');
        if (!regionLabel || regionLabel.querySelector('.gp-region-tooltip-wrapper')) {
            return false;
        }

        const tooltipMessage = 'Choose where your payment data will be processed and hosted. This may affect compliance and performance.';

        const tooltipWrapper = document.createElement('span');
        tooltipWrapper.className = 'gp-region-tooltip-wrapper';
        tooltipWrapper.style.cssText = 'margin-left: 8px; position: relative; display: inline-block; vertical-align: middle;';

        const icon = document.createElement('span');
        icon.textContent = '?';
        icon.style.cssText = 'display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; border-radius: 50%; background-color: #fff; color: #333; border: 1.5px solid #333; font-size: 12px; font-weight: bold; cursor: help; line-height: 18px;';

        const bubble = document.createElement('span');
        bubble.textContent = tooltipMessage;
        bubble.style.cssText = 'visibility: hidden; opacity: 0; position: absolute; left: calc(100% + 10px); top: 50%; transform: translateY(-50%); width: 350px; background-color: #fff; color: #333; padding: 12px 14px; border-radius: 6px; font-size: 13px; line-height: 1.5; box-shadow: 0 3px 12px rgba(0,0,0,0.15); border: 1px solid #ddd; z-index: 10000; transition: opacity 0.3s ease; white-space: normal; text-align: left;';

        const arrowBorder = document.createElement('span');
        arrowBorder.style.cssText = 'content: ""; position: absolute; right: 100%; top: 50%; transform: translateY(-50%); border: 7px solid transparent; border-right-color: #ddd;';
        bubble.appendChild(arrowBorder);

        const arrow = document.createElement('span');
        arrow.style.cssText = 'content: ""; position: absolute; right: 100%; top: 50%; transform: translateY(-50%); border: 6px solid transparent; border-right-color: #fff; margin-right: -1px;';
        bubble.appendChild(arrow);

        tooltipWrapper.addEventListener('mouseenter', function() {
            bubble.style.visibility = 'visible';
            bubble.style.opacity = '1';
        });

        tooltipWrapper.addEventListener('mouseleave', function() {
            bubble.style.visibility = 'hidden';
            bubble.style.opacity = '0';
        });

        tooltipWrapper.appendChild(icon);
        tooltipWrapper.appendChild(bubble);
        regionLabel.appendChild(tooltipWrapper);

        return true;
    }

    // Initialize with retries
    let tooltipAttempts = 0;
    function tryAddTooltip() {
        if (addTransactionRegionTooltip() || tooltipAttempts >= 10) {
            return;
        }
        tooltipAttempts++;
        setTimeout(tryAddTooltip, 200);
    }
    tryAddTooltip();

    // Add tooltip to eRaty info label
    function addEratyTooltip() {
        // First check if the field exists
        const eratyField = document.getElementById('globalpayments_ucp_hppEratyInfo');
        if (!eratyField) {
            return false;
        }

        // Find the label by looking for the closest form-group and then the label
        const formGroup = eratyField.closest('.form-group');
        if (!formGroup) {
            return false;
        }

        const eratyLabel = formGroup.querySelector('label');
        if (!eratyLabel || eratyLabel.querySelector('.gp-eraty-tooltip-wrapper')) {
            return false;
        }

        const tooltipWrapper = document.createElement('span');
        tooltipWrapper.className = 'gp-eraty-tooltip-wrapper';
        tooltipWrapper.style.cssText = 'margin-left: 8px; position: relative; display: inline-block; vertical-align: middle;';

        const icon = document.createElement('span');
        icon.textContent = '?';
        icon.style.cssText = 'display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; border-radius: 50%; background-color: #fff; color: #333; border: 1.5px solid #333; font-size: 12px; font-weight: bold; cursor: help; line-height: 18px;';

        const bubble = document.createElement('span');
        bubble.textContent = eratyTooltipMessage;
        bubble.style.cssText = 'visibility: hidden; opacity: 0; position: absolute; left: calc(100% + 10px); top: 50%; transform: translateY(-50%); width: 350px; background-color: #fff; color: #333; padding: 12px 14px; border-radius: 6px; font-size: 13px; line-height: 1.5; box-shadow: 0 3px 12px rgba(0,0,0,0.15); border: 1px solid #ddd; z-index: 10000; transition: opacity 0.3s ease; white-space: normal; text-align: left;';

        const arrowBorder = document.createElement('span');
        arrowBorder.style.cssText = 'content: ""; position: absolute; right: 100%; top: 50%; transform: translateY(-50%); border: 7px solid transparent; border-right-color: #ddd;';
        bubble.appendChild(arrowBorder);

        const arrow = document.createElement('span');
        arrow.style.cssText = 'content: ""; position: absolute; right: 100%; top: 50%; transform: translateY(-50%); border: 6px solid transparent; border-right-color: #fff; margin-right: -1px;';
        bubble.appendChild(arrow);

        tooltipWrapper.addEventListener('mouseenter', function() {
            bubble.style.visibility = 'visible';
            bubble.style.opacity = '1';
        });

        tooltipWrapper.addEventListener('mouseleave', function() {
            bubble.style.visibility = 'hidden';
            bubble.style.opacity = '0';
        });

        tooltipWrapper.appendChild(icon);
        tooltipWrapper.appendChild(bubble);
        eratyLabel.appendChild(tooltipWrapper);

        return true;
    }

    // Initialize eRaty tooltip with retries
    let eratyTooltipAttempts = 0;
    function tryAddEratyTooltip() {
        if (addEratyTooltip() || eratyTooltipAttempts >= 10) {
            return;
        }
        eratyTooltipAttempts++;
        setTimeout(tryAddEratyTooltip, 200);
    }
    tryAddEratyTooltip();

    updateEndpointDisplay();
});
</script>
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
                {* nofilter: $form contains trusted HTML from HelperForm::generateForm() *}
                {$form nofilter}
            </div>
        {else}
            <div
                class="tab-pane fade"
                id="{$k|escape:'htmlall':'UTF-8'}"
                aria-labelledby="{$k|escape:'htmlall':'UTF-8'}-tab"
            >
                {* nofilter: $form contains trusted HTML from HelperForm::generateForm() *}
                {$form nofilter}
            </div>
        {/if}
    {/foreach}
</div>
