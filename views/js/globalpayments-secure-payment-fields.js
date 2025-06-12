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

// @ts-check

(function (
    $,
    GlobalPayments,
    GlobalPayments3DS,
    globalpayments_secure_payment_fields_params,
    globalpayments_secure_payment_threedsecure_params,
    helper
) {
    /**
     * Frontend code for Global Payments in PrestaShop
     *
     * @param {object} options
     */
    function GlobalPaymentsPrestaShop(options, threeDSecureOptions)
    {
        /**
         * Card form instance
         *
         * @type {any}
         */
        this.cardForm = {};

        /**
         * Payment gateway id
         *
         * @type {string}
         */
        this.id = options.id;

        /**
         * Payment field options
         *
         * @type {object}
         */
        this.fieldOptions = options.fieldOptions;

        /**
         * Payment field styles
         */
        this.fieldStyles = options.fieldStyles;

        /**
         * Payment gateway options
         *
         * @type {object}
         */
        this.gatewayOptions = options.gatewayOptions;

        /**
         * Payment gateway messages.
         */
        this.messages = options.gatewayOptions.messages;

        /**
         * 3DS endpoints
         */
        this.threedsecure = threeDSecureOptions.threedsecure;

        /**
         * Order info
         */
        this.order = helper.order;

        /**
         *
         * @type {null}
         */
        this.tokenResponse = null;

        this.attachEventHandlers();
    };

    GlobalPaymentsPrestaShop.prototype = {
        /**
         * Add important event handlers for controlling the payment experience during checkout
         *
         * @returns
         */
        attachEventHandlers: function () {
            var that = this;

            // General
            $(document).ready(function () {
                $(helper.getPlaceOrderButtonSelector()).on('click', function ($e) {
                    if (helper.getTokenId(that.id) && that.id === 'globalpayments_ucp') {
                        $e.preventDefault();
                        $e.stopImmediatePropagation();

                        if (!that.isThreeDSecureEnabled()) {
                            helper.placeOrder(that.id);
                            return;
                        }

                        that.threeDSecure();
                        return;
                    }

                    return true;
                });
            });

            // Checkout
            if ($(document.body).hasClass('page-order')) {
                $(document).ready(this.renderPaymentFields.bind(this));
                return;
            }
        },

        /**
         * Convenience function to get CSS selector for stored card radio inputs
         *
         * @returns {string}
         */
        getStoredPaymentMethodsRadioSelector: function () {
            return 'input[name="globalpayments-payment-method"]';
        },

        /**
         * Renders the payment fields using GlobalPayments.js. Each field is securely hosted on
         * Global Payments' production servers.
         *
         * @returns
         */
        renderPaymentFields: function () {
            if ($('#' + this.id + '-' + this.fieldOptions['card-number-field'].class).children().length > 0) {
                return;
            }
            if (!GlobalPayments.configure) {
                console.log('Warning! Payment fields cannot be loaded');
                return;
            }

            var gatewayConfig = this.gatewayOptions;
            if (gatewayConfig.error) {
                if (gatewayConfig.hide) {
                    console.error(gatewayConfig.message);
                    helper.hidePaymentMethod(this.id);
                    return;
                }
                helper.showPaymentError(this.id, gatewayConfig.message);
            }

            // ensure the submit button's parent is on the page as this is added
            // only after the initial page load
            if ($(helper.getSubmitButtonTargetSelector(this.id)).length === 0) {
                helper.createSubmitButtonTarget(this.id);
            }

            GlobalPayments.configure(gatewayConfig);
            this.cardForm = GlobalPayments.ui.form(
                {
                    fields: this.getFieldConfiguration(),
                    styles: this.getStyleConfiguration()
                }
            );
            this.cardForm.on('submit', 'click', helper.blockOnSubmit.bind(this));
            this.cardForm.on('token-success', this.handleResponse.bind(this));
            this.cardForm.on('token-error', this.handleErrors.bind(this));
            this.cardForm.on('error', this.handleErrors.bind(this));
            this.cardForm.on("card-form-validity", function (isValid) {
                if (!isValid) {
                    helper.unblockOnError();
                }
            });
            GlobalPayments.on('error', this.handleErrors.bind(this));

            // match the visibility of our payment form
            this.cardForm.ready(function () {
                helper.toggleSubmitButtons();
            });
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
            if (!helper.validateTermsAndConditions(this.id)) {
                this.resetValidationErrors();
                return;
            }
            if (!this.validateTokenResponse(response)) {
                return;
            }

            this.tokenResponse = JSON.stringify(response);

            var that = this;

            this.cardForm.frames['card-cvv'].getCvv().then(function (c) {

                /**
                 * CVV; needed for TransIT gateway processing only
                 *
                 * @type {string}
                 */
                var cvvVal = c;

                var tokenResponseElement =
                    /**
                     * Get hidden
                     *
                     * @type {HTMLInputElement}
                     */
                    (document.getElementById(that.id + '-token_response'));
                if (!tokenResponseElement) {
                    tokenResponseElement      = document.createElement('input');
                    tokenResponseElement.id   = that.id + '-token_response';
                    tokenResponseElement.name = that.id + '[token_response]';
                    tokenResponseElement.type = 'hidden';
                    helper.getForm(that.id).appendChild(tokenResponseElement);
                }

                response.details.cardSecurityCode = cvvVal;
                tokenResponseElement.value = JSON.stringify(response);

                if (!that.isThreeDSecureEnabled()) {
                    helper.placeOrder(that.id);
                    return;
                }
                if (that.id === 'globalpayments_ucp') {
                    that.threeDSecure();
                }
            });
        },

        /**
         * 3DS Process
         */
        threeDSecure: function () {
            helper.blockOnSubmit();

            var self    = this;
            var tokenId = helper.getTokenId(this.id);

            GlobalPayments.ThreeDSecure.checkVersion(this.threedsecure.checkEnrollmentUrl, {
                tokenResponse: this.tokenResponse,
                tokenId: tokenId,
                amount: self.order.amount,
                currency: self.order.currency,
                challengeWindow: {
                    windowSize: GlobalPayments.ThreeDSecure.ChallengeWindowSize.Windowed500x600,
                    displayMode: 'lightbox',
                },
            })
                .then(function ( versionCheckData ) {
                    if ( versionCheckData.error ) {
                        helper.showPaymentError(self.id, versionCheckData.message);
                        return false;
                    }
                    if ( "NOT_ENROLLED" === versionCheckData.status && "YES" !== versionCheckData.liabilityShift ) {
                        helper.showPaymentError(self.id, self.messages.noLiabilityShift);
                        return false;
                    }
                    if ( "NOT_ENROLLED" === versionCheckData.status && "YES" === versionCheckData.liabilityShift ) {
                        helper.placeOrder(self.id);
                        return true;
                    }

                    GlobalPayments.ThreeDSecure.initiateAuthentication(self.threedsecure.initiateAuthenticationUrl, {
                        tokenResponse: self.tokenResponse,
                        tokenId: tokenId,
                        versionCheckData: versionCheckData,
                        challengeWindow: {
                            windowSize: GlobalPayments.ThreeDSecure.ChallengeWindowSize.Windowed500x600,
                            displayMode: 'lightbox',
                        },
                        order: self.order,
                    })
                        .then(function ( authenticationData ) {
                            if ( authenticationData.error ) {
                                helper.showPaymentError(self.id, authenticationData.message);
                                return false;
                            }
                            helper.createInputElement(
                                self.id,
                                'serverTransId',
                                authenticationData.serverTransactionId || authenticationData.challenge.response.data.threeDSServerTransID || versionCheckData.serverTransactionId
                            );
                            helper.placeOrder(self.id);
                            return true;
                        })
                        .catch(function ( error ) {
                            console.error(error);
                            helper.showPaymentError(self.id, self.messages.threeDSFail);
                            return false;
                        });
                })
                .catch(function ( error ) {
                    console.error(error);
                    helper.showPaymentError(self.id, self.messages.threeDSFail);
                    return false;
                });

            $(document).on('click', 'img[id^="GlobalPayments-frame-close-"]', this.cancelTransaction.bind(this));

            return false;

        },

        /**
         * Assists with notifying the challenge status, when the user closes the challenge window
         */
        cancelTransaction: function () {
            window.parent.postMessage({ data: { "transStatus":"N" }, event: "challengeNotification" }, window.location.origin);
        },

        /**
         * States whether the 3D Secure authentication protocol should be processed.
         *
         * @returns {Boolean}
         */
        isThreeDSecureEnabled: function () {
            return this.gatewayOptions.enableThreeDSecure;
        },

        /**
         * Validates the tokenization response
         *
         * @param {object} response tokenization response
         *
         * @returns {boolean} status of validations
         */
        validateTokenResponse: function ( response ) {
            this.resetValidationErrors();

            var result = true;

            if (response.details) {
                var expirationDate = new Date(response.details.expiryYear, response.details.expiryMonth - 1);
                var now            = new Date();
                var thisMonth      = new Date(now.getFullYear(), now.getMonth());

                if ( ! response.details.expiryYear || ! response.details.expiryMonth || expirationDate < thisMonth ) {
                    this.showValidationError('card-expiration');
                    result = false;
                }
            }

            if ( response.details && ! response.details.cardSecurityCode ) {
                this.showValidationError('card-cvv');
                result = false;
            }

            return result;
        },

        /**
         * Hides all validation error messages
         *
         * @returns
         */
        resetValidationErrors: function () {
            $('.' + this.id + ' .globalpayments-validation-error').hide();
        },

        /**
         * Shows the validation error for a specific payment field
         *
         * @param {string} fieldType Field type to show its validation error
         *
         * @returns
         */
        showValidationError: function (fieldType) {
            $('.' + this.id + '.' + fieldType + ' .globalpayments-validation-error').show();

            helper.unblockOnError();
        },

        /**
         * Handles errors from the payment field iframes
         *
         * @param {object} error Details about the error
         *
         * @returns
         */
        handleErrors: function (error) {
            this.resetValidationErrors();
            helper.validateTermsAndConditions(this.id);

            if (!error.reasons) {
                return;
            }

            var numberOfReasons = error.reasons.length;
            for (var i=0; i < numberOfReasons; i++) {
                var reason = error.reasons[i];
                switch (reason.code) {
                    case 'NOT_AUTHENTICATED':
                        helper.showPaymentError(this.id, this.messages.notAuthenticated)
                        break;
                    case 'ERROR':
                        helper.showPaymentError(this.id, reason.message);
                        break;
                    default:
                        helper.showPaymentError(this.id, reason.message);
                }
            }
        },

        /**
         * Gets payment field config
         *
         * @returns {object}
         */
        getFieldConfiguration: function () {
            var fields = {
                'card-number': {
                    placeholder: this.fieldOptions['card-number-field'].placeholder,
                    target: '#' + this.id + '-' + this.fieldOptions['card-number-field'].class
                },
                'card-expiration': {
                    placeholder: this.fieldOptions['card-expiry-field'].placeholder,
                    target: '#' + this.id + '-' + this.fieldOptions['card-expiry-field'].class
                },
                'card-cvv': {
                    placeholder: this.fieldOptions['card-cvv-field'].placeholder,
                    target: '#' + this.id + '-' + this.fieldOptions['card-cvv-field'].class
                },
                'submit': {
                    text: this.getSubmitButtonText(),
                    target: helper.getSubmitButtonTargetSelector(this.id)
                }
            };
            if (this.fieldOptions.hasOwnProperty('card-holder-name-field')) {
                fields['card-holder-name'] = {
                    placeholder: this.fieldOptions['card-holder-name-field'].placeholder,
                    target: '#' + this.id + '-' + this.fieldOptions['card-holder-name-field'].class
                };
            }
            return fields;
        },

        /**
         * Gets payment field styles
         *
         * @returns {object}
         */
        getStyleConfiguration: function () {
            return JSON.parse(this.fieldStyles);
        },

        /**
         * Gets submit button text
         *
         * @returns {string}
         */
        getSubmitButtonText: function () {
            return $('#payment-confirmation button').text().replace(/\n/g,'').trim();
        },
    };

    new GlobalPaymentsPrestaShop(globalpayments_secure_payment_fields_params, globalpayments_secure_payment_threedsecure_params);
}(
    /**
     * Global `jQuery` reference
     *
     * @type {any}
     */
    (window).jQuery,
    /**
     * Global `GlobalPayments` reference
     *
     * @type {any}
     */
    (window).GlobalPayments,
    /**
     * Global `GlobalPayments` reference
     *
     * @type {any}
     */
    (window).GlobalPayments.ThreeDSecure,
    /**
     * Global `globalpayments_secure_payment_fields_params` reference
     *
     * @type {any}
     */
    (window).globalpayments_secure_payment_fields_params,
    /**
     * Global `globalpayments_secure_payment_threedsecure_params` reference
     *
     * @type {any}
     */
    (window).globalpayments_secure_payment_threedsecure_params || {},
    /**
     * Global `helper` reference
     *
     * @type {any}
     */
    (window).GlobalPaymentsHelper
));
