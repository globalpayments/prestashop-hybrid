/**
 * GlobalPayments Refund Validation
 * Prevents partial refund form submission if amount exceeds refundable amount
 */

document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on an order page
    if (!window.location.href.includes('/admin') || !window.location.href.includes('orders')) {
        return;
    }

    // Prevent multiple validations and alerts
    let validationInProgress = false;
    let lastValidationTime = 0;
    const validationCooldown = 1000; // Reduced to 1 second cooldown between validations
    
    // Track processed elements to avoid duplicate event listeners
    const processedElements = new WeakSet();

    // Function to get order data from the page
    function getOrderDataFromPage() {
        // First try to use the data provided by PHP
        if (typeof globalpayments_order_data !== 'undefined' && globalpayments_order_data) {
            return {
                total: globalpayments_order_data.orderTotal,
                refunded: globalpayments_order_data.alreadyRefunded,
                currency: globalpayments_order_data.currency
            };
        }
        
        // Fallback: Try to find order total and refunded amount from the page
        let orderTotal = 0;
        let alreadyRefunded = 0;
        let currency = 'EUR';
        
        // Look for order total in price elements
        const priceElements = document.querySelectorAll('.badge, .text-success, .text-primary, .price, [class*="total"], [class*="amount"]');
        priceElements.forEach(function(el) {
            const text = el.textContent || el.innerText || '';
            // Look for currency symbols and amounts
            const matches = text.match(/([\d,]+\.?\d*)\s*(€|EUR|PLN|USD|\$)/);
            if (matches) {
                const amount = parseFloat(matches[1].replace(',', ''));
                if (amount > orderTotal) {
                    orderTotal = amount;
                    currency = matches[2] === '€' ? 'EUR' : matches[2];
                }
            }
        });
        
        // If we couldn't find it in badges, try other elements
        if (orderTotal === 0) {
            const allElements = document.querySelectorAll('*');
            allElements.forEach(function(el) {
                const text = el.textContent || el.innerText || '';
                if (text.includes('Total') || text.includes('total')) {
                    const matches = text.match(/([\d,]+\.?\d*)\s*(€|EUR|PLN|USD|\$)/);
                    if (matches) {
                        const amount = parseFloat(matches[1].replace(',', ''));
                        if (amount > orderTotal) {
                            orderTotal = amount;
                            currency = matches[2] === '€' ? 'EUR' : matches[2];
                        }
                    }
                }
            });
        }
        
        return {
            total: orderTotal,
            refunded: alreadyRefunded,
            currency: currency
        };
    }

    // Function to validate refund amounts with anti-spam protection
    function validateRefundAmounts() {
        const now = Date.now();
        
        // Check if validation is already in progress or too soon after last validation
        if (validationInProgress || (now - lastValidationTime) < validationCooldown) {
            // If we're in cooldown, allow the action to proceed (don't block valid refunds)
            return true;
        }

        // Set validation in progress
        validationInProgress = true;
        lastValidationTime = now;

        try {
            const orderData = getOrderDataFromPage();
            
            if (!orderData.total || orderData.total <= 0) {
                return true; // Allow if we can't validate
            }

            const remainingRefundable = orderData.total - orderData.refunded;
            let currentRefundAmount = 0;

            // Calculate total refund amount from all possible input fields
            const allInputs = document.querySelectorAll('input[type="number"], input[type="text"]');
            allInputs.forEach(function(input) {
                if (input.name && input.value) {
                    // Check for various refund field patterns
                    if (input.name.includes('cancel_product[amount_') ||
                        input.name.includes('partialRefundProduct') ||
                        input.name === 'partialRefundShippingCost' ||
                        input.name.includes('shipping_amount')) {
                        
                        const amount = parseFloat(input.value || 0);
                        if (amount > 0) {
                            currentRefundAmount += amount;
                        }
                    }
                }
            });

            // Only block if current refund amount is invalid
            if (currentRefundAmount > 0 && currentRefundAmount > remainingRefundable) {
                const errorMessage = 'GLOBALPAYMENTS ERROR: Cannot process refund!\n\n' +
                    'Refund amount: ' + currentRefundAmount.toFixed(2) + ' ' + orderData.currency + '\n' +
                    'Remaining refundable: ' + remainingRefundable.toFixed(2) + ' ' + orderData.currency + '\n\n' +
                    'The refund amount exceeds the remaining refundable amount for this order.';
                
                alert(errorMessage);
                return false;
            }

            // Allow valid refund amounts to proceed
            return true;
        } finally {
            // Reset validation flag after a short delay
            setTimeout(function() {
                validationInProgress = false;
            }, 500); // Reduced delay
        }
    }

    // Centralized event handler that prevents duplicate processing
    function handleRefundAction(e, element) {
        // Check if this is a refund-related action
        const isRefundAction = isRefundElement(element);
        
        if (isRefundAction) {
            // Only block if validation specifically fails (invalid amount)
            if (!validateRefundAmounts()) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
        }
        // Always return true for valid actions to allow them to proceed
        return true;
    }

    // Helper function to determine if an element is refund-related
    function isRefundElement(element) {
        const form = element.closest('form');
        if (!form) return false;

        // Check if this form contains refund-related fields
        const hasRefundFields = form.querySelector(
            'input[name*="cancel_product"], ' +
            'input[name*="partialRefund"], ' +
            'input[name*="refund"], ' +
            'button[name*="refund"], ' +
            'input[name*="shipping_amount"]'
        );

        // Also check the element itself
        const elementText = (element.textContent || element.value || element.name || '').toLowerCase();
        const isRefundButton = elementText.includes('refund') || 
                              elementText.includes('partial') || 
                              elementText.includes('cancel');

        return hasRefundFields || isRefundButton;
    }

    // Add event listeners only to unprocessed elements
    function addEventListeners() {
        // Handle form submissions
        const forms = document.querySelectorAll('form');
        forms.forEach(function(form) {
            if (!processedElements.has(form)) {
                processedElements.add(form);
                form.addEventListener('submit', function(e) {
                    return handleRefundAction(e, form);
                }, true); // Use capture phase
            }
        });

        // Handle button clicks
        const buttons = document.querySelectorAll('button, input[type="submit"]');
        buttons.forEach(function(button) {
            if (!processedElements.has(button)) {
                processedElements.add(button);
                button.addEventListener('click', function(e) {
                    return handleRefundAction(e, button);
                }, true); // Use capture phase
            }
        });
    }

    // Initialize validation system
    function initializeValidation() {
        addEventListeners();
    }

    // Initial setup with delay to ensure page is loaded
    setTimeout(initializeValidation, 1000);

    // Handle dynamic content (AJAX) with throttling
    let observerTimeout;
    const observer = new MutationObserver(function(mutations) {
        clearTimeout(observerTimeout);
        observerTimeout = setTimeout(function() {
            let hasNewElements = false;
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length > 0) {
                    hasNewElements = true;
                }
            });
            
            if (hasNewElements) {
                addEventListeners();
            }
        }, 1000); // Throttle to 1 second
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
});
