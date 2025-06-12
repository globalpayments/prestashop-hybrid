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
    globalpayments_applepay_params,
    helper
) {
    function ApplePayPrestaShop(options) {

        /**
         * Payment method id
         *
         * @type {string}
         */
        this.id = options.id;

        /**
         * The current order
         *
         * @type {object}
         */
        this.order = helper.order;

        /**
         * Payment method options
         *
         * @type {object}
         */
        this.paymentMethodOptions = options.paymentMethodOptions;

        this.initialize(options);
    };

    ApplePayPrestaShop.prototype = {

        initialize: function () {
            if (this.deviceSupported() === false) {
                helper.hidePaymentMethod(this.id);
            }

            this.addApplePayButton();
        },

        /**
         * Add the apple pay button to the DOM
         */
        addApplePayButton: function () {

            helper.createSubmitButtonTarget(this.id);

            var self = this;
            var paymentButton = document.createElement('div');
            paymentButton.className = "apple-pay-button apple-pay-button-" + this.paymentMethodOptions.buttonColor;
            paymentButton.title = "Pay with Apple Pay";
            paymentButton.alt = "Pay with Apple Pay";
            paymentButton.id = self.id;

            paymentButton.addEventListener('click', function (e) {
                e.preventDefault();
                helper.blockOnSubmit();

                $('.globalpayments-checkout-error').remove();

                if (!helper.validateTermsAndConditions(self.id)) {
                    return
                }
                var applePaySession = self.createApplePaySession();
                applePaySession.begin();
            });

            $(helper.getSubmitButtonTargetSelector(this.id)).append(paymentButton);
        },

        createApplePaySession: function() {
            var self = this;
            try {
                var applePaySession = new ApplePaySession(1, self.getPaymentRequest());
            } catch (err) {
                console.error('Unable to create ApplePaySession', err);
                alert("We're unable to take your payment through Apple Pay. Please try again or use an alternative payment method.");
                helper.unblockOnError();
                return false;
            }

            // Handle validate merchant event
            applePaySession.onvalidatemerchant = function (event) {
                self.onApplePayValidateMerchant(event, applePaySession);
            }

            // Attach payment auth event
            applePaySession.onpaymentauthorized = function (event) {
                self.onApplePayPaymentAuthorize(event, applePaySession);
            }

            applePaySession.oncancel = function (event) {
                alert("We're unable to take your payment through Apple Pay. Please try again or use an alternative payment method.")
                helper.unblockOnError();
            }.bind(this);

            return applePaySession;
        },

        onApplePayValidateMerchant: function(event, session) {
            var self = this;

            $.ajax({
                type: "POST",
                cache: false,
                url: this.paymentMethodOptions.validateMerchantUrl,
                data: JSON.stringify({'validationUrl': event.validationURL}),
                dataType: "json",
            }).done(function (response) {
                session.completeMerchantValidation(JSON.parse(response));
            }).fail(function (response) {
                session.abort();
                alert("We're unable to take your payment through Apple Pay. Please try again or use an alternative payment method.");
                helper.unblockOnError();
            });
        },

        onApplePayPaymentAuthorize: function(event, session) {
            var paymentToken = JSON.stringify(event.payment.token.paymentData);
            helper.createInputElement(
                this.id,
                'dw_token',
                paymentToken
            );

            var billingContact = event.payment.billingContact;
            var payerInfo = {};
            if (billingContact) {
                payerInfo.cardHolderName = billingContact.givenName + ' ' +  billingContact.familyName;
                helper.createInputElement(
                    this.id,
                    'payer_info',
                    JSON.stringify(payerInfo)
                );
            }

            session.completePayment(ApplePaySession.STATUS_SUCCESS);
            helper.placeOrder(this.id);
        },

        getPaymentRequest: function () {
            return {
                countryCode: this.getCountryId(),
                currencyCode: this.order.currency,
                merchantCapabilities: [
                    'supports3DS'
                ],
                supportedNetworks: this.getAllowedCardNetworks(),
                total: {
                    label: this.getDisplayName(),
                    amount: this.order.amount.toString()
                },
                requiredBillingContactFields: ['postalAddress', 'name'],
            }
        },

        getCountryId: function () {
            return this.paymentMethodOptions.countryCode;
        },

        getDisplayName: function () {
            return this.paymentMethodOptions.appleMerchantDisplayName;
        },

        getAllowedCardNetworks: function () {
            return this.paymentMethodOptions.ccTypes;
        },

        deviceSupported: function () {
            if (location.protocol !== 'https:') {
                console.warn("Apple Pay requires your checkout be served over HTTPS");
                return false;
            }

            if ((window.ApplePaySession && ApplePaySession.canMakePayments()) !== true) {
                console.warn("Apple Pay is not supported on this device/browser");
                return false;
            }

            return true;
        },
    };
    new ApplePayPrestaShop(globalpayments_applepay_params);
}(
    (window).jQuery,
    (window).globalpayments_applepay_params || {},
    (window).GlobalPaymentsHelper
));
