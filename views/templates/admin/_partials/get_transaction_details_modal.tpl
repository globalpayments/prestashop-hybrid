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
<div class="modal fade" id="globalpayments-get-transaction-details-modal" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    {l s='Transaction details' d='Modules.Globalpayments.Admin'}
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <table class="globalpayments-get-transaction-details-information-table">
                    <tbody></tbody>
                </table>
                <p class="globalpayments-error">
                    {l s='There was something wrong, please try again later.' d='Modules.Globalpayments.Admin'}
                </p>
            </div>
            <div class="modal-footer"></div>
        </div>
    </div>
</div>
