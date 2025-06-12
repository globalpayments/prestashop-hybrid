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

( function(
    $,
    helper
) {
    $( function() {
        var placeOrderButton = document.querySelector(helper.getPlaceOrderButtonSelector());
        if (placeOrderButton === null) {
            return;
        }
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === "attributes") {
                    helper.toggleSubmitButtons();
                }
            });
        });

        observer.observe(placeOrderButton, {
            attributes: true //configure it to listen to attribute changes
        });
    });
})(
    jQuery,
    (window).GlobalPaymentsHelper
);
