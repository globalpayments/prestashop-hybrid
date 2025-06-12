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

( function( $, globalpayments_admin_params ) {
    $( function() {
        var gatewayNames = [];
        var isUnsavedTab = false;
        var selectedTab = '';
        var gateways = ['globalpayments_ucp_enabled'];
        var messages = globalpayments_admin_params.messages;
        var isGateway = function (self) {
            return gateways.includes($( self ).attr('name'));
        }

        $( '#globalPaymentsTab a' ).each( function () {
            gatewayNames.push( $( this ).text().trim() );
        });

        // Toggle GlobalPayments gateway on/off.
        $( '#globalPaymentsTabContent' ).on( 'click', 'input[name$="_enabled"]', function () {

            if ( isUnsavedTab &&  $( this ).attr('name') !== selectedTab ) {
                var tabName = $( '#' + selectedTab.replace('_enabled','-tab' )).text().trim();
                window.alert( messages.saveChanges.replace('%tabname%', tabName) );
                return false;
            }

            var toggle = true;
            var self = this;
            if( !isGateway(this) ) {
                selectedTab = $( self ).attr('name');
                isUnsavedTab = true;
                return;
            }

            $( '#globalPaymentsTabContent input[name$="_enabled"]:checked' ).each( function (i) {
                if ( $( self ).get( 0 ) === $( this ).get( 0 ) ) {
                    return;
                }

                if ( isGateway(this) && Number.parseInt($( this ).attr('value')) === 1 ) {
                    window.alert( messages.enforceOneGateway.replace('%gateway%', gatewayNames[i]) )
                    toggle = false;
                    return;
                }
            });

            isUnsavedTab = toggle;
            selectedTab = $( self ).attr('name');
            return toggle;
        });
    });
})(
    (window).jQuery,
    (window).globalpayments_admin_params || {}
);
