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
    globalpayments_bnpl_params,
    globalpayments_ob_params,
    globalpayments_apm_params,
    helper
) {
    function AsyncPaymentMethodPrestaShop(bnplOptions, obOptions, apmOptions) {
        /**
         * BNPL payment method options
         *
         * @type {object}
         */
        this.bnplPaymentMethodOptions = bnplOptions.paymentMethodOptions;

        /**
         * OB payment method options
         *
         * @type {object}
         */
        this.obPaymentMethodOptions = obOptions.paymentMethodOptions;

        /**
         * APM payment method options
         *
         * @type {object}
         */
        this.apmPaymentMethodOptions = apmOptions.paymentMethodOptions;

        this.providers = [];
        if (this.bnplPaymentMethodOptions && this.bnplPaymentMethodOptions.providers) {
            this.providers = this.providers.concat(this.bnplPaymentMethodOptions.providers);
        }
        if (this.obPaymentMethodOptions && this.obPaymentMethodOptions.providers) {
            this.providers = this.providers.concat(this.obPaymentMethodOptions.providers);
        }
        if (this.apmPaymentMethodOptions && this.apmPaymentMethodOptions.providers) {
            this.providers = this.providers.concat(this.apmPaymentMethodOptions.providers);
        }

        this.attachEventHandlers();
    };

    AsyncPaymentMethodPrestaShop.prototype = {
        attachEventHandlers: function() {
            // Checkout
            if ($(document.body).hasClass('page-order')) {
                $(document).ready(this.attachClickEvent.bind(this));
            }
        },

        attachClickEvent: function() {
            var self = this;

            $(helper.getPlaceOrderButtonSelector()).on('click', function (e) {
                for (var i = 0; i < self.providers.length; i++) {
                    var providerId = self.providers[i];
                    if (!helper.getPaymentOptionId(providerId)) {
                        continue;
                    }

                    e.preventDefault();
                    e.stopImmediatePropagation();
                    helper.placeOrder(providerId);

                    break;
                }
            });
        }
    };
    new AsyncPaymentMethodPrestaShop(globalpayments_bnpl_params, globalpayments_ob_params, globalpayments_apm_params);
}(
    (window).jQuery,
    (window).globalpayments_bnpl_params || {},
    (window).globalpayments_ob_params || {},
    (window).globalpayments_apm_params || {},
    (window).GlobalPaymentsHelper
));
