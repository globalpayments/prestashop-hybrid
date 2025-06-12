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

<form class="defaultForm form-horizontal globalpayments" action="" method="post" enctype="multipart/form-data">
    <input type="hidden" name="{$formSubmit['name']|escape:'htmlall':'UTF-8'}" value="1" />
    <div class="panel">
        <div class="panel-heading">
            <i class="{$formLegend['icon']|escape:'htmlall':'UTF-8'}"></i>
            {$formLegend['title']|escape:'htmlall':'UTF-8'}
        </div>
        <div class="form-wrapper">
            {foreach from=$formInputs item=input}
                <div class="form-group">
                    <label class="control-label col-lg-3 {$input['class']|escape:'htmlall':'UTF-8'}{if $input['required']} required{/if}">
                        {$input['label']|escape:'htmlall':'UTF-8'}
                    </label>
                    <div class="col-lg-9">
                        {if $input['type'] === 'switch'}
                            <span class="switch prestashop-switch fixed-width-lg">
                            {foreach from=$input['values'] item=val}
                                <input type="radio" name="{$input['name']|escape:'htmlall':'UTF-8'}" id="{$input['name']|escape:'htmlall':'UTF-8'}_{$val['value']|escape:'htmlall':'UTF-8'}" value="{$val['value']|escape:'htmlall':'UTF-8'}" {if $val['value'] === (int) $input['value']}checked="checked"{/if} />
                                <label for="{$input['name']|escape:'htmlall':'UTF-8'}_{$val['value']|escape:'htmlall':'UTF-8'}">{$val['label']|escape:'htmlall':'UTF-8'}</label>
                            {/foreach}
                            <a class="slide-button btn"></a>
                        </span>
                        {elseif $input['type'] === 'button'}
                            <button class="btn btn-default" id="{$input['name']|escape:'htmlall':'UTF-8'}">
                                {$input['title']|escape:'htmlall':'UTF-8'}
                            </button>
                        {elseif $input['type'] === 'select'}
                            {if $input['multiple']=="true"}
                                <input
                                    name="{$input['name']|escape:'htmlall':'UTF-8'}"
                                    type="hidden"
                                    value=""
                                />
                                {foreach from=$input['options']['query'] item=option}
                                    <label>
                                        <input
                                            name="{$input['name']|escape:'htmlall':'UTF-8'}[]"
                                            type="checkbox"
                                            {if $option['selected']}checked{/if}
                                            value="{$option['id_option']|escape:'htmlall':'UTF-8'}"
                                        />
                                        {$option['name']|escape:'htmlall':'UTF-8'}
                                    </label>
                                    <br/>
                                {/foreach}
                            {else}
                                <select
                                    name="{$input['name']|escape:'htmlall':'UTF-8'}"
                                    id="{$input['name']|escape:'htmlall':'UTF-8'}"
                                    class="fixed-width-xl" {$input['class']|escape:'htmlall':'UTF-8'}
                                    {if $input['required']}required{/if}
                                    {if $input['disabled']}disabled{/if}>
                                    {foreach from=$input['options']['query'] item=option}
                                        <option {if $option['selected']}selected{/if} value="{$option['id_option']|escape:'htmlall':'UTF-8'}" {if $option['id_option'] === $input['value']}selected="selected"{/if}>
                                            {$option['name']|escape:'htmlall':'UTF-8'}
                                        </option>
                                    {/foreach}
                                </select>
                            {/if}
                        {else}
                            <input type="{$input['type']|escape:'htmlall':'UTF-8'}"
                                   name="{$input['name']|escape:'htmlall':'UTF-8'}"
                                   id="{$input['name']|escape:'htmlall':'UTF-8'}"
                                   value="{$input['value']|escape:'htmlall':'UTF-8'}"
                                    {if $input['required']} required{/if}
                                    {if $input['maxLength']} maxlength="{$input['maxLength']|escape:'htmlall':'UTF-8'}"{/if}
                                    {if !$input['value']} placeholder="{$input['default']|escape:'htmlall':'UTF-8'}"{/if}/>
                        {/if}
                        <p class="help-block">{$input['desc']|cleanHtml nofilter}</p>
                    </div>
                </div>
            {/foreach}
        </div>
        <div class="panel-footer">
            <button type="submit" value="1" name="{$formSubmit['name']|escape:'htmlall':'UTF-8'}" class="btn btn-default pull-right">
                <i class="process-icon-save"></i>
                {$formSubmit['title']|escape:'htmlall':'UTF-8'}
            </button>
        </div>
    </div>
</form>
