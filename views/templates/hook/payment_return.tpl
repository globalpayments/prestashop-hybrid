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

{if $error}
    <p class="warning">
        {$error|escape:'htmlall':'UTF-8'}
    </p>
{/if}

{if $installmentsData}
    <div>
        <div class="gp-installments-trigger">
            <button id="gp-installments-btn" class="btn btn-primary" type="button">
                View Installment Plan Details
            </button>
        </div>
        <div id="gp-installments-modal" class="gp-modal-overlay">
            <div class="gp-modal-container">
                <div class="gp-modal-header">
                    <h3>Installment Plan Details</h3>
                    <button class="gp-modal-close" id="gp-modal-close" type="button" aria-label="close">&times;</button>
                </div>

                <div class="gp-modal-content">
                    <div class="gp-installment-summary">
                        <div class="gp-installment-badge">
                            <span class="gp-plan-months">{$installmentsData['terms']['count']|escape:'htmlall':'UTF-8'}</span>
                            <span class="gp-plan-label">{$installmentsData['time_unit']|escape:'htmlall':'UTF-8'} Plan</span>
                        </div>

                        <div class="gp-installment-details">
                            <div class="gp-detail-row">
                                <span class="gp-label">Order amount:</span>
                                <span class="gp-value">
                                    {$installmentsData['order_amount']|escape:'htmlall':'UTF-8'} {$installmentsData['currency']|escape:'htmlall':'UTF-8'}
                                </span>
                            </div>

                            <div class="gp-detail-row">
                                <span class="gp-label">Payment Plan:</span>
                                <span class="gp-value">
                                    {$installmentsData['installments']|escape:'htmlall':'UTF-8'}ly
                                    payments
                                </span>
                            </div>

                            {if $installmentsData['monthly_amount']}
                                <div class="gp-detail-row">
                                    <span class="gp-label">{$installmentsData['time_unit']|escape:'htmlall':'UTF-8'}ly amount:</span>
                                    <span class="gp-value">{$installmentsData['monthly_amount']|escape:'htmlall':'UTF-8'}
                                        {$installmentsData['currency']|escape:'htmlall':'UTF-8'}/{$installmentsData['time_unit']|escape:'htmlall':'UTF-8'}
                                (incl. fee)
                                </span>
                                </div>
                            {/if}

                            {if $installmentsData['terms']['fees']['total_amount']}
                                <div class="gp-detail-row">
                                    <span class="gp-label">Installment Fee:</span>
                                    <span class="gp-value">{$installmentsData['finance_fee']|escape:'htmlall':'UTF-8'} {$installmentsData['currency']|escape:'htmlall':'UTF-8'}</span>
                                </div>
                            {/if}

                            <div class="gp-detail-row gp-highlight">
                                <span class="gp-label">Interest Rate:</span>
                                <span class="gp-value">{$installmentsData['terms']['cost_percentage']|escape:'htmlall':'UTF-8'}% APR</span>
                            </div>

                            {if $installmentsData['terms']['total_amount']}
                                <div class="gp-detail-row">
                                    <span class="gp-label">Total Amount:</span>
                                    <span class="gp-value">{$installmentsData['financed_amount']|escape:'htmlall':'UTF-8'} {$installmentsData['currency']|escape:'htmlall':'UTF-8'}</span>
                                </div>
                            {/if}
                        </div>
                    </div>

                    {if $installmentsData['terms']['description']}
                        <div class="gp-installment-description">
                            <p>{$installmentsData['terms']['description']|escape:'htmlall':'UTF-8'}</p>
                        </div>
                    {/if}

                    {if !empty($installmentsData['terms']['terms_and_conditions_url'])}
                        <div class="gp-installment-terms">
                            <a href="{$installmentsData['terms']['terms_and_conditions_url']|escape:'htmlall':'UTF-8'}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="gp-terms-link">
                                Further information &amp; Privacy policy &rarr;
                            </a>
                        </div>
                    {/if}
                </div>

                <div class="gp-modal-footer">
                    <button class="gp-modal-close-btn" id="gp-modal-close-btn" type="button">
                        Close
                    </button>
                    <div class="gp-powered-by">Powered by GlobalPayments</div>
                </div>
            </div>
        </div>
    </div>
{/if}
