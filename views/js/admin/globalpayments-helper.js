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

(function () {
    function Helper() {
        this.attachEventHandlers();
    };

    Helper.prototype = {
        /**
         * Add important event handlers for controlling the payment experience during checkout
         *
         * @returns
         */
        attachEventHandlers: function() {
            var self = this;
            // General
            $(document).ready(function() {
                $('.payment-options input.ps-shown-by-js').change(function() {
                    self.toggleSubmitButtons();
                });

                $('#globalpayments-form-show').on('click', self.toggleSubmitButtons.bind(self));
            });
        },

        /**
         * Convenience function to get CSS selector for the built-in 'Place Order' button
         *
         * @returns {string}
         */
        getPlaceOrderButtonSelector: function() {
            return '#payment-confirmation button';
        },

        /**
         * Convenience function to get CSS selector for the custom 'Place Order' button's parent element
         *
         * @param {string} id
         * @returns {string}
         */
        getSubmitButtonTargetSelector: function(id) {
            return '#' + id + '-card-submit';
        },

        /**
         * Blocks checkout UI
         *
         * @returns
         */
        blockOnSubmit: function() {
            var $payForm = $('#globalpayments-pay-form');
            var payForm_data = $payForm.data();

            if (1 !== payForm_data['blockUI.isBlocked']) {
                $payForm.block(
                    {
                        message: null,
                        overlayCSS: {
                            background: '#fff',
                            opacity: 0.6
                        }
                    }
                );
            }
        },

        /**
         * Unblocks checkout UI
         *
         * @returns
         */
        unblockOnError: function() {
            var $payForm = $('#globalpayments-pay-form');
            $payForm.unblock();
        },

        /**
         * Places/submits the order to PrestaShop
         *
         * @param {string} id
         *
         * @returns
         */
        placeOrder: function(id) {
            this.blockOnSubmit();

            var that = this;

            var selectedOptionForm = $("[name='payment-option']:checked").parent().parent().find('form');
            var paymentDetails = selectedOptionForm.serializeArray();
            paymentDetails.push({name: 'orderId', value: window.gpOrderId});

            $.ajax({
                type: 'POST',
                cache: false,
                url: selectedOptionForm.attr('action'),
                data: $.param(paymentDetails),
                success: function (data) {
                    data = JSON.parse(data);
                    if (!data.error) {
                        window.location.reload();
                    } else {
                        that.showPaymentError(id, data.message);
                    }
                }
            });
        },

        /**
         *  Shows payment error and scrolls to it
         * 
         * @param {string} id 
         * @param {string} message 
         * 
         * @returns
         */
        showPaymentError: function(id, message) {
            var $form = $(this.getForm(id));

            // Remove notices from all sources
            $('.globalpayments-checkout-error').remove();

            if (-1 === message.indexOf('globalpayments-validation-error')) {
                message = '<ul class="globalpayments-validation-error"><li>' + message + '</li></ul>';
            }
            $form.prepend('<div class="globalpayments-checkout-error">' + message + '</div>');

            $('html, body').animate({
                scrollTop: ($form.offset().top - 100)
            }, 1000);

            this.unblockOnError();
        },

        /**
         * Gets the current checkout form
         *
         * In Prestashop, each payment option has a separate form
         *
         * @returns {Element}
         */
        getForm: function(id) {
            var tokenId = this.getTokenId(id);

            var checkoutForms = [
                'form#' + id + '-payment-form',
                'form.globalpayments-save-card'
            ];
            var forms = document.querySelectorAll(checkoutForms.join(','));

            if (!tokenId) {
                return forms.item(0)
            } else { //get the form for a tokenized card
                var cardForm = null;

                [].forEach.call(forms, function (form) {
                    if (form.classList.contains('globalpayments-' + tokenId)) {
                        cardForm = form;
                    }
                });

                return cardForm;
            }
        },

        /**
         * 
         * @param {string} id 
         * @returns 
         */
        getTokenId: function(id) {
            var inputId = $('input[data-module-name="' + id + '"]:checked').attr('id');
            return $('#pay-with-' + inputId + '-form input[name="globalpayments-payment-method"]').attr('value');
        },

        /**
         * Creates the parent for the submit button
         *
         * @returns
         */
        createSubmitButtonTarget: function(id) {
            var el       = document.createElement('div');
            el.id        = this.getSubmitButtonTargetSelector(id).replace('#', '');
            el.className = 'globalpayments ' + id + ' card-submit';
            $(this.getPlaceOrderButtonSelector()).parent().parent().after(el);
            $(this.getSubmitButtonTargetSelector(id)).hide();
        },

        createInputElement: function(id, name, value) {
            var inputElement = (document.getElementById(id + '-' + name));

            if (!inputElement) {
                inputElement      = document.createElement('input');
                inputElement.id   = id + '-' + name;
                inputElement.name = id + '[' + name + ']';
                inputElement.type = 'hidden';
                this.getForm(id).appendChild(inputElement);
            }

            inputElement.value = value;
        },
        
        /**
         * Swaps the default PrestaShop 'Place Order' button for our iframe-d
         * or digital wallet buttons when one of our gateways is selected.
         *
         * @returns
         */
        toggleSubmitButtons: function() {
            var paymentGatewaySelected = $('.payment-options input.ps-shown-by-js:checked').attr('data-module-name');
            var radioSelector = $(this.getPaymentMethodRadioSelector(paymentGatewaySelected));
            var isPaymentGatewaySelected = $(radioSelector.first()).is(':checked');
            var gatewayIds = ['globalpayments_ucp'];

            $('.globalpayments.card-submit').hide();

            if (isPaymentGatewaySelected && gatewayIds.includes(paymentGatewaySelected)) {
                // our gateway was selected
                $('.payment-options .js-payment-option-form').show();
                $(this.getPlaceOrderButtonSelector()).hide();
                $(this.getSubmitButtonTargetSelector(paymentGatewaySelected)).show();
            } else {
                // another gateway was selected
                $(this.getPlaceOrderButtonSelector()).show();
                $('.payment-options .js-payment-option-form').hide();
            }
        },

        /**
         * Convenience function to get CSS selector for the radio input associated with our payment method
         *
         * @returns {string}
         */
        getPaymentMethodRadioSelector: function(id) {
            return '.payment-options input.ps-shown-by-js[data-module-name="' + id + '"]';
        },
    };
    if(!window.GlobalPaymentsHelper) {
        window.GlobalPaymentsHelper = new Helper();
    }
}());
