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
                
                // Auto-check credentials on page load
                self.autoCheckCredentialsOnLoad();
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
            this.syncAccountNameFields();
            this.attachCredentialChangeHandlers();
            $(document).on('click', this.getLiveModeSelector(), this.toggleCredentialsSettings.bind(this));
            $(document).on('click', this.getEnableSelector(), this.toggleRequiredSettings.bind(this));
            $(document).on('click', this.getCredentialsCheckButtonSelector(), this.checkApiCredentials.bind(this));
        },

        /**
         * Attach event handlers for App Id and App Key changes to clear Account Name
         */
        attachCredentialChangeHandlers: function () {
            var self = this;

            // Store original values to detect actual changes
            var originalValues = {};

            // Live mode credential change handlers
            $(document).on('focus', '#' + this.id + '_appId', function() {
                originalValues.appId = $(this).val();
            });

            $(document).on('blur', '#' + this.id + '_appId', function() {
                var currentValue = $(this).val();
                if (originalValues.appId !== currentValue) {
                    self.clearAccountName(true);
                }
            });

            $(document).on('focus', '#' + this.id + '_appKey', function() {
                originalValues.appKey = $(this).val();
            });

            $(document).on('blur', '#' + this.id + '_appKey', function() {
                var currentValue = $(this).val();
                if (originalValues.appKey !== currentValue) {
                    self.clearAccountName(true);
                }
            });

            // Sandbox mode credential change handlers
            $(document).on('focus', '#' + this.id + '_sandboxAppId', function() {
                originalValues.sandboxAppId = $(this).val();
            });

            $(document).on('blur', '#' + this.id + '_sandboxAppId', function() {
                var currentValue = $(this).val();
                if (originalValues.sandboxAppId !== currentValue) {
                    self.clearAccountName(false);
                }
            });

            $(document).on('focus', '#' + this.id + '_sandboxAppKey', function() {
                originalValues.sandboxAppKey = $(this).val();
            });

            $(document).on('blur', '#' + this.id + '_sandboxAppKey', function() {
                var currentValue = $(this).val();
                if (originalValues.sandboxAppKey !== currentValue) {
                    self.clearAccountName(false);
                }
            });
        },

        /**
         * Clear account name field and dropdown for the specified mode
         */
        clearAccountName: function (isLiveMode) {
            if (isLiveMode) {
                // Clear live mode account name
                $('#' + this.id + '_accountName').val('').trigger('change');
                var liveDropdown = $('#' + this.id + '_accountNameDropdown');
                if (liveDropdown.length > 0) {
                    // Clear all options
                    liveDropdown.empty();
                }
            } else {
                // Clear sandbox mode account name
                $('#' + this.id + '_sandboxAccountName').val('').trigger('change');
                var sandboxDropdown = $('#' + this.id + '_sandboxAccountNameDropdown');
                if (sandboxDropdown.length > 0) {
                    // Clear all options
                    sandboxDropdown.empty();
                }
            }
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

        autoCheckCredentialsOnLoad: function () {
            var self = this;
            
            // Wait a bit for the page to fully load and elements to be available
            setTimeout(function () {
                self.setId();
                if (!self.id) {
                    return;
                }
                
                // Check if we have the required credentials before auto-checking
                var appId = self.getCredentialSetting('appId');
                var appKey = self.getCredentialSetting('appKey');
                
                if (appId && appKey) {
                    // Simulate the credentials check without user interaction
                    self.performCredentialsCheck();
                }
            }, 500); // 500ms delay to ensure page is fully loaded
        },

        performCredentialsCheck: function () {
            var self = this;
            var appId = this.getCredentialSetting('appId');
            var appKey = this.getCredentialSetting('appKey');

            // Remove any existing success messages
            var credentialsSuccess = $('.globalpayments-credentials-success');
            if (credentialsSuccess) {
                credentialsSuccess.remove();
            }

            if (!appId || !appKey) {
                return; // Don't proceed if credentials are missing
            }

            $.ajax({
                type: 'POST',
                url: self.adminParams.credentialsCheckUrl,
                data: {
                    isLiveMode: self.getLiveModeInputValue(),
                    appId: appId,
                    appKey: appKey
                },
                showLoader: false, // Don't show loader for auto-check
            }).done(function (result) {
                if (!result.error && result.accountName && result.accountName.length > 0) {
                    self.populateAccountNameDropdown(result.accountName);
                }
            }).fail(function (error) {
                // Silently fail for auto-check - don't show error messages
                console.log('Auto credentials check failed:', error);
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
                    // Populate the appropriate account name dropdown based on production mode
                    if (result.accountName && result.accountName.length > 0) {
                        self.populateAccountNameDropdown(result.accountName);
                    }
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
            var element;
            if (this.isLiveMode()) {
                element = $('#' + this.id + '_' + setting);
            } else {
                // Handle special case for accountName in sandbox mode
                if (setting === 'accountName') {
                    element = $('#' + this.id + '_sandboxAccountName');
                } else {
                    element = $('#' + this.id + '_sandbox' + this.capitalizeFirstLetter(setting));
                }
            }

            // Check if element exists and has a value
            if (element.length > 0 && element.val() !== null) {
                return element.val().trim();
            }

            return ''; // Return empty string if element not found or value is null
        },

        /**
         * Get account name field selectors based on current mode
         */
        getAccountNameSelectors: function () {
            var prefix = this.isLiveMode() ? 'accountName' : 'sandboxAccountName';
            return {
                dropdown: '#' + this.id + '_' + prefix + 'Dropdown',
                hidden: '#' + this.id + '_' + prefix
            };
        },

        /**
         * Populate account name dropdown with API results
         */
        populateAccountNameDropdown: function (accountNames) {
            var selectors = this.getAccountNameSelectors();
            var dropdown = $(selectors.dropdown);
            var hiddenField = $(selectors.hidden);
            var savedValue = hiddenField.val();

            // Clear existing options except the first default option
            dropdown.find('option:not(:first)').remove();

            // Add each returned account name as an option
            for (var i = 0; i < accountNames.length; i++) {
                var accountName = accountNames[i].name;
                dropdown.append(new Option(accountName, accountName));
            }

            // Set the value: prioritize saved database value, then first available option
            if (savedValue && savedValue !== '' && savedValue !== 'Select Account' && 
                dropdown.find('option[value="' + savedValue + '"]').length > 0) {
                dropdown.val(savedValue);
            } else if (accountNames.length > 0) {
                dropdown.val(accountNames[0].name);
                hiddenField.val(accountNames[0].name);
            }
        },

        /**
         * Sync dropdown value with hidden field
         */
        syncAccountNameFields: function () {
            var self = this;

            // Initialize dropdown values from hidden fields on page load
            setTimeout(function() {
                self.initializeAccountNameDropdowns();
            }, 100);

            // Setup event handlers for dropdown changes
            this.setupAccountNameEventHandlers();
        },

        /**
         * Initialize dropdown values from hidden fields
         */
        initializeAccountNameDropdowns: function () {
            var modes = [
                { prefix: 'sandboxAccountName', isLive: false },
                { prefix: 'accountName', isLive: true }
            ];

            for (var i = 0; i < modes.length; i++) {
                var mode = modes[i];
                var hiddenValue = $('#' + this.id + '_' + mode.prefix).val();

                if (hiddenValue && hiddenValue !== '') {
                    var dropdown = $('#' + this.id + '_' + mode.prefix + 'Dropdown');
                    if (dropdown.find('option[value="' + hiddenValue + '"]').length > 0) {
                        dropdown.val(hiddenValue);
                    }
                }
            }
        },

        /**
         * Setup event handlers for account name dropdowns
         */
        setupAccountNameEventHandlers: function () {
            var self = this;

            // Sandbox account name dropdown
            $(document).on('change', '#' + this.id + '_sandboxAccountNameDropdown', function() {
                self.updateHiddenField($(this), '#' + self.id + '_sandboxAccountName');
            });

            // Live account name dropdown
            $(document).on('change', '#' + this.id + '_accountNameDropdown', function() {
                self.updateHiddenField($(this), '#' + self.id + '_accountName');
            });
        },

        /**
         * Update hidden field when dropdown changes
         */
        updateHiddenField: function (dropdown, hiddenFieldSelector) {
            var selectedValue = dropdown.val();
            if (selectedValue !== 'Select Account') {
                $(hiddenFieldSelector).val(selectedValue);
            }
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

            // Remove required attribute from hidden dropdown fields to prevent validation errors
            if (display) {
                // Live mode is ON - remove required from sandbox dropdown
                $('#' + this.id + '_sandboxAccountNameDropdown').prop('required', false);
            } else {
                // Live mode is OFF - remove required from live dropdown
                $('#' + this.id + '_accountNameDropdown').prop('required', false);
            }

            this.toggleRequiredSettings();
        },

        toggleRequiredSettings: function () {
            var enabled = this.getEnableInputValue();
            var list = $('.required').closest('div').find('input, select'); // Add select elements

            list.each(function() {
                var inputName = $(this).attr('name');
                // Skip the multi-checkbox inputs. These will be validated on backend.
                if (inputName && inputName.endsWith('[]')) {
                    return;
                }

                // Check if the element and its parent form group are visible
                var isVisible = $(this).is(':visible') && $(this).closest('.form-group').is(':visible');
                
                // Don't set required=true for hidden account name dropdowns
                if (inputName && inputName.includes('accountNameDropdown')) {
                    if (!isVisible) {
                        $(this).prop('required', false);
                        return;
                    }
                }

                if (isVisible && enabled) {
                    $(this).prop('required', true);
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
