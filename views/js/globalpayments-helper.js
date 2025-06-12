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
    $,
    globalpayments_helper_params
) {
    function Helper(options) {
      /**
       * Helper options.
       *
       * @type {object}
       */
        this.helperOptions = options;

        /**
         * Helper messages.
         */
        this.messages = options.messages;

        /**
         * The current order
         *
         * @type {object}
         */
        this.order = options.order;

        this.attachEventHandlers();
    }

    Helper.prototype = {
        /**
         * Add important event handlers for controlling the payment experience during checkout
         *
         * @returns
         */
        attachEventHandlers: function () {
            var self = this;
            // General
            $(document).ready(function () {
                $('.payment-options input.ps-shown-by-js').change(function() {
                    self.toggleSubmitButtons();
                });
            });
        },

        /**
         * Convenience function to get CSS selector for the built-in 'Place Order' button
         *
         * @returns {string}
         */
        getPlaceOrderButtonSelector: function () {
            return '#payment-confirmation button';
        },

        /**
         * Convenience function to get CSS selector for the custom 'Place Order' button's parent element
         *
         * @param {string} id
         * @returns {string}
         */
        getSubmitButtonTargetSelector: function (id) {
            return '#' + id + '-card-submit';
        },

        /**
         * Convenience function to get CSS selector for terms and conditions input
         *
         * @returns {string}
         */
        getTermsAndConditionsRadioSelector: function () {
            return '#conditions-to-approve input[name*="terms-and-conditions"]'
        },

        /**
         * Blocks checkout UI
         *
         * @returns
         */
        blockOnSubmit: function () {
            var $checkoutStep = $('#checkout-payment-step');
            var checkoutStep_data = $checkoutStep.data();

            if (1 !== checkoutStep_data['blockUI.isBlocked']) {
                $checkoutStep.block(
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
        unblockOnError: function () {
            var $checkoutStep = $('#checkout-payment-step');
            $checkoutStep.unblock();
        },

        /**
         * Places/submits the order to PrestaShop.
         *
         * @param {Number} formId
         * @param {Function} errorCallback
         *
         * @return void
         */
        placeOrder: function (formId, errorCallback) {
            this.blockOnSubmit();

            var self = this;
            var form = $(self.getForm(formId));

            $.ajax({
                type: 'POST',
                cache: false,
                url: form.attr('action') + '?ajax=true',
                data: form.serialize(),
                success: function (data) {
                    data = JSON.parse(data);
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    }
                    if (data.error === false) {
                        return;
                    }

                    self.showPaymentError.bind(self, formId, data.errorMessage)();

                    if (!errorCallback) {
                        return;
                    }

                    errorCallback();
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
        showPaymentError: function (id, message) {
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
        getForm: function (id) {
            var tokenId = this.getTokenId(id);

            if (!tokenId) {
                return document.querySelector('form#' + id + '-payment-form');
            }

            var forms = document.querySelectorAll('form.globalpayments-save-card');
            var cardForm = null;

            [].forEach.call(forms, function (form) {
                if (form.classList.contains('globalpayments-' + tokenId)) {
                    cardForm = form;
                }
            });

            return cardForm;
        },

        /**
         * Get the payment option id;
         *
         * @param id
         * @returns string
         */
        getPaymentOptionId: function(id) {
            return $('input[data-module-name="' + id + '"]:checked').attr('id');
        },

        /**
         * Get the token id for a stored payment method.
         *
         * @param {string} id
         * @returns
         */
        getTokenId: function (id) {
            var paymentOptionId = this.getPaymentOptionId(id);
            return $('#pay-with-' + paymentOptionId + '-form input[name="globalpayments-payment-method"]').attr('value');
        },

        /**
         * Creates the parent for the submit button
         *
         * @returns
         */
        createSubmitButtonTarget: function (id) {
            var el       = document.createElement('div');
            el.id        = this.getSubmitButtonTargetSelector(id).replace('#', '');
            el.className = 'globalpayments ' + id + ' card-submit';
            $(this.getPlaceOrderButtonSelector()).parent().parent().after(el);
            $(this.getSubmitButtonTargetSelector(id)).hide();
        },

        createInputElement: function ( id, name, value ) {
            var inputElement = (document.getElementById(id + '-' + name));

            if ( ! inputElement) {
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
        toggleSubmitButtons: function () {
            var paymentMethodSelected = $('.payment-options input.ps-shown-by-js:checked').attr('data-module-name');
            var isPaymentMethodSelected = $(this.getPaymentMethodRadioSelector(paymentMethodSelected)).first().is(':checked');
            $('.globalpayments.card-submit').hide();

            if (this.helperOptions.hide.includes(paymentMethodSelected)) {
                this.hidePlaceOrderButton();
                return;
            }
            if (!this.helperOptions.toggle.includes(paymentMethodSelected)) {
                this.showPlaceOrderButton();
                return;
            }

            var submitButtonTarget = $(this.getSubmitButtonTargetSelector(paymentMethodSelected));

            if (isPaymentMethodSelected) {
                // our gateway was selected
                submitButtonTarget.show();
                this.hidePlaceOrderButton();

                // PrestaShop bug: show form when the GP payment option is the only one
                var form = $('.js-payment-option-form');
                if (form.length === 1) {
                    form.show();
                }
            } else {
                // another gateway was selected
                submitButtonTarget.hide();
                this.showPlaceOrderButton();
            }
        },

        /**
         * Hide the default PrestaShop 'Place Order' button.
         */
        hidePlaceOrderButton: function() {
            $(this.getPlaceOrderButtonSelector()).hide();
        },

        /**
         * Show the default PrestaShop 'Place Order' button.
         */
        showPlaceOrderButton: function() {
            $(this.getPlaceOrderButtonSelector()).show();
        },

        hidePaymentMethod: function(id) {
            var paymentOptions = $('input[data-module-name="' + id + '"]');
            paymentOptions.each(function(index, element) {
                var paymentOptionId = $(element).attr('id');
                $('#' + paymentOptionId + '-container').hide();
            });
        },

        /**
         * Convenience function to get CSS selector for the radio input associated with our payment method
         *
         * @returns {string}
         */
        getPaymentMethodRadioSelector: function ( id ) {
            return '.payment-options input.ps-shown-by-js[data-module-name="' + id + '"]';
        },

        validateTermsAndConditions: function(id) {
            if ($(this.getTermsAndConditionsRadioSelector()).length === 0) {
                return true;
            }
            if (!$(this.getTermsAndConditionsRadioSelector()).is(':checked')) {
                this.showPaymentError(id, this.messages.termsOfService);
                return false;
            }
            return true;
        },
    };

    if (!window.GlobalPaymentsHelper) {
        window.GlobalPaymentsHelper = new Helper(globalpayments_helper_params);
    }
} (
    (window).jQuery,
    (window).globalpayments_helper_params || {}
));
