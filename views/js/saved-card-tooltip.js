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
 * Move installment tooltip inline with saved card label
 */
(function() {
    'use strict';

    const TOOLTIP_MESSAGE = 'For card-based installment payment options, please enter your card information below.';

    function addTooltipsToSavedCards() {
        // Find all additional information divs with tooltip
        const tooltipContainers = document.querySelectorAll('[id$="-additional-information"] .globalpayments-installment-tooltip-wrapper');
        
        if (tooltipContainers.length === 0) {
            return false;
        }

        tooltipContainers.forEach(function(tooltip) {
            // Find the corresponding payment option label
            const additionalInfoDiv = tooltip.closest('[id$="-additional-information"]');
            if (!additionalInfoDiv) return;
            
            const additionalInfoId = additionalInfoDiv.id;
            const optionNumber = additionalInfoId.replace('-additional-information', '');
            const label = document.querySelector('label[for="' + optionNumber + '"]');
            
            if (label && !label.querySelector('.globalpayments-installment-tooltip-wrapper')) {
                // Create tooltip wrapper
                const tooltipWrapper = document.createElement('span');
                tooltipWrapper.className = 'globalpayments-installment-tooltip-wrapper';
                tooltipWrapper.style.cssText = 'margin-left: 8px; position: relative; display: inline-block; vertical-align: middle;';

                // Create info icon
                const icon = document.createElement('span');
                icon.className = 'globalpayments-tooltip-icon';
                icon.textContent = '?';
                icon.style.cssText = 'display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; border-radius: 50%; background-color: #fff; color: #333; border: 1.5px solid #333; font-size: 12px; font-weight: bold; cursor: help; line-height: 18px; text-align: center;';
                icon.setAttribute('aria-label', TOOLTIP_MESSAGE);

                // Create tooltip bubble
                const bubble = document.createElement('span');
                bubble.className = 'globalpayments-tooltip-text';
                bubble.textContent = TOOLTIP_MESSAGE;
                bubble.style.cssText = 'visibility: hidden; opacity: 0; position: absolute; left: calc(100% + 10px); top: 50%; transform: translateY(-50%); width: 280px; background-color: #fff; color: #333; padding: 12px 14px; border-radius: 6px; font-size: 13px; line-height: 1.5; box-shadow: 0 3px 12px rgba(0, 0, 0, 0.15); border: 1px solid #ddd; z-index: 10000; transition: opacity 0.3s ease, visibility 0.3s ease; pointer-events: none; white-space: normal; text-align: left;';

                // Create arrow (border)
                const arrowBorder = document.createElement('span');
                arrowBorder.style.cssText = 'content: ""; position: absolute; right: 100%; top: 50%; transform: translateY(-50%); border: 7px solid transparent; border-right-color: #ddd;';
                bubble.appendChild(arrowBorder);

                // Create arrow (fill)
                const arrow = document.createElement('span');
                arrow.style.cssText = 'content: ""; position: absolute; right: 100%; top: 50%; transform: translateY(-50%); border: 6px solid transparent; border-right-color: #fff; margin-right: -1px;';
                bubble.appendChild(arrow);

                // Add hover events
                tooltipWrapper.addEventListener('mouseenter', function() {
                    bubble.style.visibility = 'visible';
                    bubble.style.opacity = '1';
                });

                tooltipWrapper.addEventListener('mouseleave', function() {
                    bubble.style.visibility = 'hidden';
                    bubble.style.opacity = '0';
                });

                tooltipWrapper.appendChild(icon);
                tooltipWrapper.appendChild(bubble);
                label.appendChild(tooltipWrapper);
                
                // Hide original tooltip in additional info
                tooltip.style.display = 'none';
            }
        });

        return true;
    }

    function init() {
        if (!addTooltipsToSavedCards()) {
            // Retry if elements not found yet
            setTimeout(addTooltipsToSavedCards, 100);
        }

        // Watch for dynamic changes
        const observer = new MutationObserver(addTooltipsToSavedCards);
        const targetNode = document.querySelector('.payment-options') || document.body;
        observer.observe(targetNode, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
