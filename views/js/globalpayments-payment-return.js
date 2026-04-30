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
    $
) {
    function PaymentReturn() {
        this.attachEventHandlers();
    }

    PaymentReturn.prototype = {

        attachEventHandlers: function () {
            $(document).ready(function () {
                setTimeout(function() {
                    const modal = document.getElementById('gp-installments-modal');

                    if (modal) {
                        modal.classList.add('gp-modal-show');

                        const installmentsTrigger = document.querySelector('.gp-installments-trigger');

                        if (installmentsTrigger) {
                            installmentsTrigger.classList.add('show-trigger');

                            installmentsTrigger.querySelector('#gp-installments-btn').addEventListener('click', function () {
                                modal.classList.add('gp-modal-show');
                            });
                        }

                        const closeButtons = modal.querySelectorAll('[id*=gp-modal-close]');

                        if (closeButtons && closeButtons.length > 0) {
                            closeButtons.forEach(function (button) {
                                button.addEventListener('click', function () {
                                    modal.classList.remove('gp-modal-show');
                                });
                            });
                        }
                    }
                }, 2000);
            });
        },

    };

    if (!window.GlobalPaymentsPaymentReturn) {
        window.GlobalPaymentsPaymentReturn = new PaymentReturn();
    }
} (
    (window).jQuery
));