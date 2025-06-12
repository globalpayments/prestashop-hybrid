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
    globalpayments_clicktopay_params,
    helper
) {
    function ClickToPayPrestaShop(options) {
        /**
         * Click To Pay form instance
         *
         * @type {any}
         */
        this.ctpForm = {};

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
        this.order = {};

        /**
         * Payment method options
         *
         * @type {object}
         */
        this.paymentMethodOptions = options.paymentMethodOptions;

        this.attachEventHandlers();
    }

    ClickToPayPrestaShop.prototype = {
        attachEventHandlers: function() {
            // Checkout
            if ($(document.body).hasClass('page-order')) {
                $(document).ready(this.renderClickToPay.bind(this));
                return;
            }
        },

        /**
         * Renders Click To Pay using GlobalPayments.js.
         *
         * @returns
         */
        renderClickToPay: function() {
            this.preventEvents();
            this.clearContent();

            if (!GlobalPayments.configure) {
                console.log('Warning! Payment fields cannot be loaded');
                return;
            }

            var gatewayConfig = this.paymentMethodOptions;
            if (gatewayConfig.error) {
                console.error(gatewayConfig.message);
                helper.hidePaymentMethod(this.id);
                return;
            }

            this.order = helper.order;
            gatewayConfig.apms.currencyCode = this.order.currency;

            GlobalPayments.configure(gatewayConfig);
            GlobalPayments.on('error', this.handleErrors.bind(this));

            this.ctpForm = GlobalPayments.apm.form('#' + this.id, {
                amount: this.order.amount.toString(),
                style: "gp-default",
                apms: [GlobalPayments.enums.Apm.ClickToPay]
            });

            this.ctpForm.on('token-success', this.handleResponse.bind(this));

            this.ctpForm.on('token-error', this.handleErrors.bind(this));
            this.ctpForm.on('error', this.handleErrors.bind(this));
        },

        preventEvents: function() {
            var ctpElement = document.querySelector('#' + this.id);

            if (!ctpElement) {
                return;
            }

            ctpElement.addEventListener('click', function(e) {
                var element = e.target.tagName;
                if (element === 'BUTTON' || element === 'LABEL') {
                    e.preventDefault();
                    e.stopPropagation();
                }
            });
            ctpElement.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    e.stopPropagation();
                }
            });
        },

        /**
         * If the CTP element already has some previous content, clear it.
         */
        clearContent: function() {
            var ctpElement = document.querySelector('#' + this.id);

            if (ctpElement.children) {
                ctpElement.innerHTML = '';
            }
        },

        /**
         * Handles the tokenization response
         *
         * On valid payment fields, the tokenization response is added to the current
         * state, and the order is placed.
         *
         * @param {object} response tokenization response
         *
         * @returns
         */
        handleResponse: function (response) {
            var self = this;

            if (!helper.validateTermsAndConditions(self.id)) {
                self.renderClickToPay();
                return;
            }

            helper.createInputElement(
                self.id,
                'dw_token',
                response.paymentReference
            )

            return helper.placeOrder(self.id, self.renderClickToPay.bind(self));
        },

        /**
         * Handles errors from the payment field
         *
         * @param {object} error Details about the error
         *
         * @returns
         */
        handleErrors: function (error) {
            console.error(error);
        },
    }

    new ClickToPayPrestaShop(globalpayments_clicktopay_params);
})(
    (window).jQuery,
    (window).globalpayments_clicktopay_params || {},
    (window).GlobalPaymentsHelper
)
