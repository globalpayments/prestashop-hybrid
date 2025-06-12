<a href="https://github.com/globalpayments" target="_blank">
    <img src="https://avatars.githubusercontent.com/u/25797248?s=200&v=4" alt="Global Payments logo" title="Global Payments" align="right" width="225" />
</a>

# Changelog

## Latest Version - ## v1.6.6 (04/24/25)
- Added support for Spanish Translations
- Added local js file for 9.0.0 js caching

## v1.6.5 (10/29/24)
- Add the possibility to configure the Transaction Processing Account Name

## v1.6.4 (04/18/24)
- Added French translations

## v1.6.3 (03/19/24)
- Upgraded the Hosted Fields library to the latest version
- Improved the validation messages

## v1.6.2 (02/29/24)
- Unified Payments - added PayPal

## v1.6.1 (02/15/24)
- Merged Sepa and Faster Payments into Bank Payment
- Added sort order for the payment methods

## v1.6.0 (12/05/23)
### Enhancements:
- Unified Payments - added Open Banking (Sepa and Faster Payments)

## v1.5.4 (10/31/23)
### Enhancements:
- Google Pay - made the Allowed Card Auth Methods configurable

## v1.5.3 (09/12/23)
### Enhancements:
- Added the option to enable/disable the 3ds flow

## v1.5.2 (08/08/23)
### Enhancements:
- Added the Card Holder Name in the Google Pay and Apple Pay requests
- Added the Card ID / Order ID in the authorize/charge requests

## v1.5.1 (06/29/23)
### Enhancements:
- Unified Payments - Added Credential Check button

## v1.5.0 (06/15/23)
### Enhancements:
- Unified Payments - Added Buy Now Pay Later

### Bug Fixes:
- Unified Payments - Fixed a bug where the Card Number iframe would not be 100% expanded on Mozilla Firefox

## v1.4.0 (05/23/23)
### Enhancements:
- Unified Payments - Added Click To Pay

## v1.3.1 (04/11/23)
### Enhancements:
- Added PrestaShop 8 compatibility

## v1.3.0 (10/27/22)
### Enhancements:
- Removed 3DS1
- Checkout flow now uses AJAX requests instead of submitting the form

## v1.2.1 (09/29/22)
### Enhancements:
- Unified Payments - added custom hook for Hosted Fields styling
- Unified Payments - increased Merchant Contact Url length to 256
- Unified Payments - send Order Transaction Descriptor in Transaction request
- Updated Settings saving and validation
- Updated Logger

### Bug Fixes:
- Fixed payment method title is displayed instead of id
- Fixed Admin Pay for Order window size

## v1.2.0 (08/09/22)
### Enhancements:
- Added Pay for Order functionality
- Unified Payments - added Card Holder Name for Hosted Fields
- Added Merchant Name option for the Google Pay gateway
- Renamed 'Global Payments Merchant ID' to 'Global Payments Client ID' for the Google Pay gateway

### Bug Fixes:
- Fixed a bug where the 'Save for later use' checkbox would show for guest users
- Fixed a bug where the Hosted Fields would not load when Terms and Conditions would not be enabled

## v1.1.1 (06/07/22)
### Enhancements:
- Add Admin option for Apple Pay button color
- Rename "Unified Commerce Platform" to "Unified Payments"
- Update PHP-SDK to 3.0.5

### Bug Fixes:
- Typo on Apple Pay JS message
- Fix 3DS address state
- Fix error on 3DS js library

## v1.1.0 (04/07/22)
### Enhancements:
- Added support for digital wallets(Apple Pay and Google Pay).
- Added support for multiple gateways
- Added toggle for live/sandbox credentials
- Added author for admin transactions
- Added payment details for orders

## v1.0.0 (08/03/21)
### Enhancements:
- Initial Release.

---
