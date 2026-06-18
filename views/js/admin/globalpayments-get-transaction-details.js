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
        /**
         * Sanitize a string to prevent XSS attacks
         * 
         * @param {string} str
         * @returns {string}
         */
        sanitizeString: function(str) {
            if (typeof str !== 'string') {
                // Convert to string if not already, or return empty string for null/undefined
                if (str === null || str === undefined) {
                    return '';
                }
                str = String(str);
            }
            // Remove any potential script tags and event handlers
            return str
                .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '')
                .replace(/on\w+\s*=/gi, '')
                .replace(/javascript:/gi, '')
                .replace(/data:/gi, '');
        },

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
                }
            }).fail(function(xhr, status, errorThrown) {
                self.displayError();
                self.unblockOnError();
            });
        },

        displayTransactionDetails: function(transactionDetails) {
            var self = this;
            this.hideError();

            // Validate that transactionDetails is an array
            if (!Array.isArray(transactionDetails)) {
                return;
            }

            transactionDetails.forEach(function(transaction) {
                // Validate transaction object has expected properties
                if (transaction && typeof transaction === 'object') {
                    var label = transaction.label !== undefined ? transaction.label : '';
                    var value = transaction.value !== undefined ? transaction.value : '';
                    self.addTableRow(label, value);
                }
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
            
            // Sanitize inputs to prevent XSS
            var sanitizedLabel = this.sanitizeString(label);
            var sanitizedValue = this.sanitizeString(value);
            
            // Create DOM elements safely using native methods
            var row = document.createElement('tr');
            var th = document.createElement('th');
            var td = document.createElement('td');
            
            // Use textContent which safely escapes HTML entities
            th.textContent = sanitizedLabel;
            td.textContent = sanitizedValue;
            
            row.appendChild(th);
            row.appendChild(td);
            
            // Append to table body
            tableBody.append(row);
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
