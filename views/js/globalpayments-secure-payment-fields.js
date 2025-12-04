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
            $(document).ready(function (e) {
                const isHppEnabled = that.isHppEnabled();
                $(helper.getPlaceOrderButtonSelector()).on('click', function ($e) {
                    // For HPP mode, use AJAX to prevent raw JSON error display
                    if (isHppEnabled) {
                        $e.preventDefault();
                        $e.stopImmediatePropagation();
                        
                        // Validate terms and conditions before processing
                        if (!helper.validateTermsAndConditions(that.id)) {
                            return false;
                        }
                        
                        that.processHppPayment();
                        return false;
                    }
                    
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
                //Make Phone number required if 3DS is enabled and HPP mode is used. 
                if(isHppEnabled && that.isThreeDSecureEnabled()){
                    helper.enforcePhoneNumber();
                }
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
        renderPaymentFields: function (e) {
            // For HPP mode, skip rendering payment fields entirely
            if (this.isHppEnabled()) {
                helper.toggleSubmitButtons();
                return;
            }

            if (!this.gatewayOptions.accessToken) {
                if (
                    $('#' + this.id + '-' + this.fieldOptions['card-number-field'].class).children().length > 0
                ) {
                    return;
                }
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

            // Add Blik configuration if enabled
            let acceptBlik = (this.isBlikPaymentEnabled() === true) ? true : false;
             let acceptOpenBanking = (this.isOpenBankingEnabled() === true) ? true : false;

             let apmsEnabled = (acceptBlik || acceptOpenBanking) ? true : false;

             if (apmsEnabled) {
                // Use baseCurrency/baseCountry from gatewayOptions, fallback to PLN/PL
                var baseCurrency = this.gatewayOptions.baseCurrency || 'PLN';
                var baseCountry = this.gatewayOptions.baseCountry || 'PL';
                gatewayConfig.apms = {
                    currencyCode: baseCurrency,
                    countryCode: baseCountry,
                    allowedCardNetworks: [
                        GlobalPayments.enums.CardNetwork.Visa,
                        GlobalPayments.enums.CardNetwork.Mastercard,
                        GlobalPayments.enums.CardNetwork.Amex,
                        GlobalPayments.enums.CardNetwork.Discover
                    ],
                    nonCardPayments: {
                    allowedPaymentMethods: [
                        {
                        provider: GlobalPayments.enums.ApmProviders.Blik,
                        enabled: acceptBlik,
                        },
                    ]
                    }
                };

                // using push because Open Banking doesn't respect the 'enabled' property currently
                if (acceptOpenBanking) {
                    gatewayConfig.apms.nonCardPayments.allowedPaymentMethods.push(
                        {
                        provider: GlobalPayments.enums.ApmProviders.OpenBanking,
                        enabled: acceptOpenBanking,
                        category: "TBD"
                        }
                    )
                }
            }

            // ensure the submit button's parent is on the page as this is added
            // only after the initial page load
            if ($(helper.getSubmitButtonTargetSelector(this.id)).length === 0) {
                helper.createSubmitButtonTarget(this.id);
            }

            GlobalPayments.configure(gatewayConfig);

            // Utilize Drop-in UI for GP API
            if (gatewayConfig.accessToken) {
                var formConfig = {
                    style: "gp-default"
                };

                // Only add amount and apms when BLIK is enabled
                if (apmsEnabled) {
                    formConfig.amount = this.order.amount ? this.order.amount : '0';
                    formConfig.apms = [];
                }

                this.cardForm = GlobalPayments.creditCard.form(
                    '#' + this.id + '-' + this.fieldOptions['payment-form'].class,
                    formConfig
                )
            } else {
                this.cardForm = GlobalPayments.ui.form(
                    {
                        fields: this.getFieldConfiguration(),
                        styles: this.getStyleConfiguration()
                    }
                );
            }

            // To initiate blik transaction process
            this.cardForm.on(GlobalPayments.enums.ApmEvents.PaymentMethodSelection, (paymentProviderData, event) => {
                const {
                    provider,
                    countryCode,
                    currencyCode,
                    bankName,
                    acquirer
                } = paymentProviderData;
                console.log('Selected provider: ' + provider);

                // Prevent any default form submission behavior for BLIK
                if (provider === GlobalPayments.enums.ApmProviders.Blik) {
                    // Prevent default form submission if event is available
                    if (event) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    // Also prevent any form submission on the page
                    $('form').off('submit.blik').on('submit.blik', function(e) {
                        console.log('Form submission prevented for BLIK payment');
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        return false;
                    });
                }

                if (provider === GlobalPayments.enums.ApmProviders.OpenBanking) {
                    // Prevent default form submission if event is available
                    if (event) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    // Also prevent any form submission on the page
                    $('form').off('submit.open-banking').on('submit.open-banking', function(e) {
                        console.log('Form submission prevented for Open Banking payment');
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        return false;
                    });
                }

                let detail = {};

                switch (provider) {
                    case GlobalPayments.enums.ApmProviders.Blik:
                        console.log('BLIK payment method selected');

                        // Block UI during processing
                        helper.blockOnSubmit();

                        // IMPORTANT: Do NOT call helper.placeOrder() here as it causes form submission
                        // Make AJAX call to process Blik transaction
                        var ajaxUrl = helper.getAjaxUrl('asyncPaymentMethodValidation');
                        var ajaxData = {
                            payment_method: 'blik',
                            gateway_id: this.id,
                            action: 'initiate_sale'
                        };

                        // Add order data if available
                        if (this.order) {
                            if (this.order.amount) ajaxData.amount = this.order.amount;
                            if (this.order.currency) ajaxData.currency = this.order.currency;
                        }
                        console.log('BLIK AJAX Data:', ajaxData);
                        $.ajax({
                            url: ajaxUrl,
                            type: 'POST',
                            dataType: 'json',
                            data: ajaxData,
                            success: function(response) {
                                helper.unblockOnError();
                                // Remove form submission prevention
                                $('form').off('submit.blik');
                                if (response.success && response.redirect_url) {
                                    // Create detail object with actual redirect URL from response
                                    const detail = {
                                        provider,
                                        redirect_url: response.redirect_url,
                                    };
                                    // Dispatch the custom event with actual redirect URL
                                    const merchantCustomEventProvideDetails = new CustomEvent(GlobalPayments.enums.ApmEvents.PaymentMethodActionDetail, {
                                        detail: detail
                                    });
                                    window.dispatchEvent(merchantCustomEventProvideDetails);

                                    console.log('Redirecting to:', response.redirect_url);

                                } else {
                                    helper.showPaymentError(this.id, response.message || 'Payment processing failed');
                                }
                            }.bind(this),
                            error: function(xhr, error) {
                                helper.unblockOnError();

                                // Remove form submission prevention on error
                                $('form').off('submit.blik');

                                helper.showPaymentError(this.id, 'Payment processing failed. Please try again.');
                                console.error('Blik payment error:', error);
                                console.error('XHR Response:', xhr.responseText);
                            }.bind(this)
                        });

                        // Return early to prevent further processing
                        return;
                    case GlobalPayments.enums.ApmProviders.OpenBanking:
                        console.log('bankName',bankName);
                        if(!bankName){
                            detail = {
                                provider,
                                redirect_url: "https://fluentlenium.com/",
                                countryCode,
                                currencyCode,
                            }
                        } else {
                                helper.blockOnSubmit();
                                // IMPORTANT: Do NOT call helper.placeOrder() here as it causes form submission
                                // Make AJAX call to process Open Banking transaction
                                var ajaxUrl = helper.getAjaxUrl('asyncPaymentMethodValidation');
                                var ajaxData = {
                                    payment_method: 'open_banking',
                                    gateway_id: this.id,
                                    action: 'initiate_sale'
                                };

                                // Add order data if available
                                if (this.order) {
                                    if (this.order.amount) ajaxData.amount = this.order.amount;
                                    if (this.order.currency) ajaxData.currency = this.order.currency;
                                }
                                ajaxData.bank = bankName;
                                console.log('Open Banking AJAX Data:', ajaxData);
                                $.ajax({
                                    url: ajaxUrl,
                                    type: 'POST',
                                    dataType: 'json',
                                    data: ajaxData,
                                    success: function(response) {
                                        helper.unblockOnError();
                                        // Remove form submission prevention
                                        $('form').off('submit.open-banking');
                                        if (response.success && response.redirect_url) {
                                            // Create detail object with actual redirect URL from response
                                            const detail = {
                                                provider,
                                                redirect_url: response.redirect_url,
                                            };
                                            // Dispatch the custom event with actual redirect URL
                                            const merchantCustomEventProvideDetails = new CustomEvent(GlobalPayments.enums.ApmEvents.PaymentMethodActionDetail, {
                                                detail: detail
                                            });
                                            window.dispatchEvent(merchantCustomEventProvideDetails);

                                            console.log('Redirecting to:', response.redirect_url);

                                        } else {
                                            helper.showPaymentError(this.id, response.message || 'Payment processing failed');
                                        }
                                    }.bind(this),
                                    error: function(xhr, error) {
                                        helper.unblockOnError();

                                        // Remove form submission prevention on error
                                        $('form').off('submit.open-banking');

                                        helper.showPaymentError(this.id, 'Payment processing failed. Please try again.');
                                        console.error('Open Banking payment error:', error);
                                        console.error('XHR Response:', xhr.responseText);
                                    }.bind(this)
                                });
                        }
                        break;
                    default:
                        detail = {
                            "seconds_to_expire": "900",
                            "next_action": "REDIRECT_IN_FRAME",
                            "redirect_url": 'https://google.com/',
                            provider,
                        };
                        break;
                }
                const merchantCustomEventProvideDetails = new CustomEvent(GlobalPayments.enums.ApmEvents.PaymentMethodActionDetail, {
                    detail: detail
                });
                if (!bankName) window.dispatchEvent(merchantCustomEventProvideDetails);
                return 0;
            });

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
         * Process HPP (Hosted Payment Page) payment via AJAX
         * 
         * This prevents raw JSON error responses from being displayed to the user
         * by using AJAX to call validation.php and handling the response properly.
         * 
         * @returns {void}
         */
        processHppPayment: function () {
            
            // Block UI during processing
            helper.blockOnSubmit();
            
            var self = this;
            var form = $(helper.getForm(this.id));
            
            const defaultHPPFailedTxt = 'Payment processing failed. Please Refresh the page and try again.';

            // Make AJAX call to validation.php
            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                dataType: 'json',
                data: form.serialize(),
                success: function(response) {
                    
                    // Check if there's a redirect URL (successful HPP initiation)
                    if (response.redirect) {
                        window.location.href = response.redirect;
                        return;
                    }
                    
                    // If we get here, something went wrong
                    helper.unblockOnError();
                    
                    if (response.error && response.errorMessage) {
                        helper.showPaymentError(self.id, response.errorMessage);

                    } else {
                        helper.showPaymentError(self.id, defaultHPPFailedTxt);
                    }
                },
                error: function(xhr, status, error) {
                    helper.unblockOnError();
                    
                    console.error('HPP payment error:', error);
                    console.error('XHR Response:', xhr.responseText);
                    
                    // Try to parse error response
                    var errorMessage = defaultHPPFailedTxt;
                    
                    try {
                        var errorResponse = JSON.parse(xhr.responseText);
                        if (errorResponse.errorMessage) {
                            errorMessage = errorResponse.errorMessage;
                        }
                    } catch (e) {
                        // If we can't parse the response, use the default message
                        console.error('Could not parse error response:', e);
                    }
                    
                    helper.showPaymentError(self.id, errorMessage);
                }
            });
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
         * States whether the blik is enabled.
         *
         * @returns {Boolean}
         */
        isBlikPaymentEnabled: function () {
            return this.gatewayOptions.enableBlikPayment;
        },

        /**
         * States whether the open banking is enabled.
         *
         * @returns {Boolean}
         */
        isOpenBankingEnabled: function () {
            return this.gatewayOptions.enableOpenBanking;
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

        /*
        * Determines if the intergration type is HPP
        *
        * @returns {Boolean}
        */ 
       isHppEnabled: function(){
            return this.gatewayOptions?.integrationMethod === 'hosted payment page'
       }
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
