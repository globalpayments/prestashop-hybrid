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
    globalpayments_googlepay_params,
    helper
) {
    function GooglePayPrestaShop(options) {

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

        /**
         * Payments client
         *
         * @type {string}
         */
        this.paymentsClient = null;

        this.initialize();
    };

    GooglePayPrestaShop.prototype = {

        initialize: function () {
            var self = this;

            self.setGooglePaymentsClient();

            self.paymentsClient.isReadyToPay(
                self.getGoogleIsReadyToPayRequest()
            ).then(function (response) {
                if (response.result) {
                    self.addGooglePayButton();
                } else {
                    helper.hidePaymentMethod(self.id);
                }
            }).catch(function (err) {
                console.error(err);
                helper.unblockOnError();
            });
        },

        getBaseRequest: function () {
            return {
                apiVersion: 2,
                apiVersionMinor: 0
            }
        },

        /**
         * Google Merchant Id
         */
        getGoogleMerchantId: function () {
            return this.paymentMethodOptions.googleMerchantId;
        },

        /**
         * Google Merchant Display Name
         */
        getGoogleMerchantName: function () {
            return this.paymentMethodOptions.googleMerchantName ? this.paymentMethodOptions.googleMerchantName : '';
        },

        /**
         * Environment
         */
        getEnvironment: function () {
            return this.paymentMethodOptions.env;
        },

        /**
         * BTN Color
         */
        getBtnColor: function () {
            return this.paymentMethodOptions.btnColor;
        },

        getAllowedCardNetworks: function () {
            return this.paymentMethodOptions.ccTypes;
        },

        getAllowedCardAuthMethods: function () {
            return this.paymentMethodOptions.acaMethods;
        },

        getTokenizationSpecification: function () {
            return {
                type: 'PAYMENT_GATEWAY',
                parameters: {
                    'gateway': 'globalpayments',
                    'gatewayMerchantId': this.paymentMethodOptions.globalPaymentsMerchantId
                }
            }
        },

        getBaseCardPaymentMethod: function () {
            return {
                type: 'CARD',
                parameters: {
                    allowedAuthMethods: this.getAllowedCardAuthMethods(),
                    allowedCardNetworks: this.getAllowedCardNetworks(),
                    billingAddressRequired: true
                }
            }
        },

        getCardPaymentMethod: function () {
            return Object.assign(
                {},
                this.getBaseCardPaymentMethod(),
                {
                    tokenizationSpecification: this.getTokenizationSpecification()
                }
            );
        },

        getGoogleIsReadyToPayRequest: function () {
            return Object.assign(
                {},
                this.getBaseRequest(),
                {
                    allowedPaymentMethods: [this.getBaseCardPaymentMethod()]
                }
            );
        },

        getGooglePaymentDataRequest: function () {
            var paymentDataRequest = Object.assign({}, this.getBaseRequest());
            paymentDataRequest.allowedPaymentMethods = [this.getCardPaymentMethod()];
            paymentDataRequest.transactionInfo = this.getGoogleTransactionInfo();
            paymentDataRequest.merchantInfo = {
                merchantId: this.getGoogleMerchantId(),
                merchantName: this.getGoogleMerchantName()
            };
            return paymentDataRequest;
        },

        getGoogleTransactionInfo: function () {
            return {
                totalPriceStatus: 'FINAL',
                totalPrice: this.order.amount.toString(),
                currencyCode: this.order.currency
            };
        },

        /**
         * Init google pay client
         */
        setGooglePaymentsClient: function () {
            var self = this;
            if (null === this.paymentsClient) {
                this.paymentsClient = new google.payments.api.PaymentsClient({
                    environment: self.getEnvironment()
                });
            }
        },

        /**
         * Add the google pay button to the DOM
         */
        addGooglePayButton: function () {

            helper.createSubmitButtonTarget(this.id);

            var self = this;
            var button = this.paymentsClient.createButton(
                {
                    buttonColor: self.getBtnColor(),
                    onClick: function () { self.onGooglePaymentButtonClicked() }
                }
            );
            $(helper.getSubmitButtonTargetSelector(this.id)).append(button);
        },

        /**
         *
         * @returns
         */
        onGooglePaymentButtonClicked: function () {

            helper.blockOnSubmit();

            $('.globalpayments-checkout-error').remove();

            if (!helper.validateTermsAndConditions(this.id)) {
                return
            }

            var self = this;
            var paymentDataRequest = self.getGooglePaymentDataRequest();
            paymentDataRequest.transactionInfo = self.getGoogleTransactionInfo();

            this.paymentsClient.loadPaymentData(paymentDataRequest).then(function (paymentData) {
                helper.createInputElement(
                    self.id,
                    'dw_token',
                    JSON.stringify(JSON.parse(paymentData.paymentMethodData.tokenizationData.token))
                );

                var payerInfo = {};
                payerInfo.cardHolderName = paymentData.paymentMethodData.info.billingAddress.name;
                helper.createInputElement(
                    self.id,
                    'payer_info',
                    JSON.stringify(payerInfo)
                );

                return helper.placeOrder(self.id);
            }).catch(function (err) {
                // Handle errors
                console.error(err);
                helper.unblockOnError();
            });

            helper.unblockOnError();
        },
    };
    new GooglePayPrestaShop(globalpayments_googlepay_params);
}(
    (window).jQuery,
    (window).globalpayments_googlepay_params || {},
    (window).GlobalPaymentsHelper
));
