/**
 * NOTICE OF LICENSE
 *
 * This file is licenced under the Software License Agreement.
 * With the purchase or the installation of the software in your application
 * you accept the licence agreement.
 *
 * You must not modify, adapt or create derivative works of this source code
 *
 * @author    GlobalPayments
 * @copyright Since 2021 GlobalPayments
 * @license   LICENSE
 */

(function (
    $
) {
    function GlobalPaymentsGetTransactionDetails() {
        this.transactionDetails = null;
        this.transactionDetailsUrl = null;
        this.transactionId = null;

        this.attachEventHandlers();
    }
    GlobalPaymentsGetTransactionDetails.prototype = {
        attachEventHandlers: function() {
            var self = this;

            $(document).ready(function() {
                var getTransactionDetailsButton = $('#globalpayments-get-transaction-details');

                self.transactionDetailsUrl = getTransactionDetailsButton.data('url');
                self.transactionId = getTransactionDetailsButton.data('transaction-id');

                getTransactionDetailsButton.click(self.getTransactionDetails.bind(self))
            });
        },

        getTransactionDetails: function() {
            var self = this;
            self.blockOnSubmit();
            if (self.transactionDetails) {
                self.hideError();
                return self.unblockOnError();
            }

            var payload = {
                id: this.transactionId
            };

            $.ajax({
                url: this.transactionDetailsUrl,
                type: 'POST',
                showLoader: true,
                data: payload
            }).done(function(response) {
                if (!response.error) {
                    self.transactionDetails = true;
                    self.displayTransactionDetails(response);
                    self.unblockOnError();
                } else {
                    self.displayError();
                    self.unblockOnError();
                    console.log(response.message)
                }
            }).fail(function(xhr, status, errorThrown) {
                self.displayError();
                self.unblockOnError();
                console.log(xhr.responseJSON.message);
                console.log(errorThrown);
            });
        },

        displayTransactionDetails: function(transactionDetails) {
            this.hideError();

            transactionDetails.forEach((transaction) => {
                this.addTableRow(transaction.label, transaction.value);
            });
        },

        displayError: function() {
            $('.globalpayments-error').show();
            this.getTableBody().hide();
        },

        hideError: function() {
            $('.globalpayments-error').hide();
            this.getTableBody().show();
        },

        getTableBody: function() {
            return $('.globalpayments-get-transaction-details-information-table tbody');
        },

        addTableRow: function(label, value) {
            var tableBody = this.getTableBody();
            tableBody.append('<tr><th>' + label + '</th><td>' + value + '</td></tr>');
        },

        blockOnSubmit: function() {
            var modal = $('#globalpayments-get-transaction-details-modal');
            if (modal.data('blockUI.isBlocked') !== 1) {
                modal.block(
                    {
                        message: null,
                        overlayCSS: {
                            background: '#fff',
                            opacity: 0.6
                        }
                    }
                )
            }
        },

        unblockOnError: function() {
            var modal = $('#globalpayments-get-transaction-details-modal');
            modal.unblock();
        }
    }
    new GlobalPaymentsGetTransactionDetails()
} (
    (window).jQuery
));
