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

( function (
    $,
    globalpayments_admin_params
) {
    function GlobalPaymentsAdmin(options)
    {
        this.id = '';
        this.adminParams = options;
        this.messages = options.messages;
        this.attachEventHandlers();
    }
    GlobalPaymentsAdmin.prototype = {
        /**
         * Add important event handlers
         *
         * @returns
         */
        attachEventHandlers: function () {
            var self = this;
            $(document).ready(function () {
                self.attachEvents();
                $(document).on('click', self.getGatewayTabListSelector(), function () {
                    /**
                     * Once a new gateway has been selected, we remove the click event from the previous one
                     * and add it to the current one
                     */
                    $(document).off('click', self.getLiveModeSelector());
                    self.attachEvents();
                });
                self.setPayForOrderButton();
            });
        },

        /**
         * Set the id for the gateway, show/hide the credentials for it and set the event for the
         * 'Live Mode' switch
         */
        attachEvents: function () {
            this.setId();
            if (!this.id) {
                return;
            }
            this.toggleCredentialsSettings();
            this.toggleRequiredSettings();
            this.loadPaymentMethodTabsHash();
            $(document).on('click', this.getLiveModeSelector(), this.toggleCredentialsSettings.bind(this));
            $(document).on('click', this.getEnableSelector(), this.toggleRequiredSettings.bind(this));
            $(document).on('click', this.getCredentialsCheckButtonSelector(), this.checkApiCredentials.bind(this));
        },

        loadPaymentMethodTabsHash: function() {
            var hash = window.location.hash;
            // If there is a hash present in the URL, load that specific tab.
            if (hash) {
                $('ul.nav a[href="' + hash + '"]').tab('show');
                window.scrollTo({top: 0});
            }

            // When a tab is clicked, add the hash to the URL.
            $('.nav-tabs a').click(function (e) {
                $(this).tab('show');
                window.location.hash = this.hash;
                window.scrollTo({top: 0});
            });
        },

        checkApiCredentials: function (e) {
            e.preventDefault();

            var self = this;
            var button = $(this.getCredentialsCheckButtonSelector());
            var appId = this.getCredentialSetting('appId');
            var appKey = this.getCredentialSetting('appKey');

            var credentialsSuccess = $('.globalpayments-credentials-success');
            if (credentialsSuccess) {
                credentialsSuccess.remove();
            }

            var errors = [];

            if (!appId) {
                errors.push(self.messages.appId);
            }
            if (!appKey) {
                errors.push(self.messages.appKey);
            }

            if (errors.length > 0) {
                alert(errors.join('\n'));

                return;
            }

            button.text(self.messages.checkingCredentials).attr('disabled', true);


            $.ajax({
                type: 'POST',
                url: self.adminParams.credentialsCheckUrl,
                data: {
                    isLiveMode: self.getLiveModeInputValue(),
                    appId: appId,
                    appKey: appKey
                },
                showLoader: true,
            }).done(function (result) {
                if (result.error) {
                    alert(result.message);
                } else {
                    $('<div class=\'globalpayments-credentials-success\'>' + result.message + '</div>').insertAfter(button);
                }
            }).fail(function (error) {
                alert(error.responseJSON.message);
            }).always(function () {
                button.text(self.messages.credentialsCheck).attr('disabled', false);
            });
        },

        /**
         * Get the value of a credential setting.
         *
         * @param setting
         */
        getCredentialSetting: function (setting) {
            if (this.isLiveMode()) {
                return $('#' + this.id + '_' + setting).val().trim();
            }

            return $('#' + this.id + '_sandbox' + this.capitalizeFirstLetter(setting)).val().trim();
        },

        /**
         * Capitalize the first letter of a string.
         *
         * @param string
         */
        capitalizeFirstLetter: function (string) {
            return string.charAt(0).toUpperCase() + string.slice(1);
        },

        /**
         * Checks if "Live Mode" setting is enabled
         *
         * @returns {boolean}
         */
        isLiveMode: function () {
            return this.getLiveModeInputValue() === 1;
        },

        /**
         * Toggle gateway credentials settings
         */
        toggleCredentialsSettings: function () {
            var display = this.isLiveMode();

            $('.live-toggle').parents('.form-group').toggle(display);
            $('.sandbox-toggle').parents('.form-group').toggle(!display);

            this.toggleRequiredSettings();
        },

        toggleRequiredSettings: function () {
            var enabled = this.getEnableInputValue();
            var list = $('.required').closest('div').find('input');

            list.each(function() {
                var inputName = $(this).attr('name');
                // Skip the multi-checkbox inputs. These will be validated on backend.
                if (inputName && inputName.endsWith('[]')) {
                    return;
                }

                if ($(this).is(':visible')) {
                    $(this).prop('required', enabled);
                } else {
                    $(this).prop('required', false);
                }
            });
        },

        /**
         * Capitalize a string
         *
         * @returns {string}
         */
        capitalize: function (string) {
            return string.charAt(0).toUpperCase() + string.slice(1);
        },

        /**
         * Convenience function to get CSS selector for the "Live Mode" input
         *
         * @returns {string}
         */
        getLiveModeSelector: function () {
            return 'input[name=' + this.id + '_isProduction]';
        },

        /**
         * Convenience function to get the value of the "Live Mode" input
         *
         * @returns {number}
         */
        getLiveModeInputValue: function () {
            return Number.parseInt($('input[name=' + this.id + '_isProduction]:checked').attr('value'));
        },

        /**
         * Convenience function to get CSS selector for the "Enable" input
         *
         * @returns {string}
         */
        getEnableSelector: function () {
            return 'input[name=' + this.id + '_enabled]';
        },

        /**
         * Convenience function to get the value of the "Enable" input
         *
         * @returns {number}
         */
        getEnableInputValue: function () {
            return Number.parseInt($('input[name=' + this.id + '_enabled]:checked').attr('value'));
        },

        /**
         * Convenience function to get the value of the gateway tab list
         *
         * @returns {string}
         */
        getGatewayTabListSelector: function () {
            return '#globalPaymentsTab';
        },

        /**
         * Convenience function to get the value of the active form
         *
         * @returns {string}
         */
        getActiveFormSelector: function () {
            return '#globalPaymentsTabContent > .active';
        },

        /**
         * Convenience function to get Credentials Check button selector
         */
        getCredentialsCheckButtonSelector: function () {
            return '#' + this.id + '_credentialsCheck';
        },

        /**
         * Sets the id of the current gateway
         *
         */
        setId: function () {
            this.id = $(this.getActiveFormSelector()) ? $(this.getActiveFormSelector()).attr('id') : '';
        },

        setPayForOrderButton: function () {
            $('#globalpayments-form-show').fancybox({
                'hideOnContentClick': true,
            });
            $("#globalpayments-pay-form input[type='submit']").bind('click', function() {
                return false;
            });
            $(".payment-option").bind('click', function() {
                $('#globalpayments-form-show').fancybox({
                    'hideOnContentClick': true,
                }).resize();
            });
        }
    };
    new GlobalPaymentsAdmin(globalpayments_admin_params);
}(
    /**
     * Global `jQuery` reference
     *
     * @type {any}
     */
    (window).jQuery,
    (window).globalpayments_admin_params || {}
));
