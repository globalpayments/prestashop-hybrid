=== GlobalPayments PrestaShop ===
Contributors: globalpayments
Tags: prestashop, unified, commerce, global, payments, payment, systems, 3DS, gateway, token, tokenize, save cards
License: MIT
License URI: LICENSE

== Description ==
This extension allows PrestaShop to use the available Global Payments payment gateways. All card data is tokenized using the respective gateway's tokenization service.

= Features =
- Unified Payments gateway
- Credit Cards
- Integrates with PrestaShop
- Sale transactions (automatic capture or separate capture action later)
- Refund transactions from a previous Sale
- Stored payment methods
- 3D Secure 2 & SCA
- Payments over the phone
- Digital Wallets - Google Pay
- Digital Wallets - Apple Pay
- Digital Wallets - Click To Pay
- Buy Now Pay Later - Affirm
- Buy Now Pay Later - Clearpay
- Buy Now Pay Later - Klarna
- Open Banking
- PayPal

= Support =
For more information or questions, please email <a href="mailto:developers@globalpay.com">developers@globalpay.com </a>.

= Developer Docs =
Discover our developer portal for companies located outside the US (https://developer.globalpay.com/).

== Installation ==
After you have installed and configured the PrestaShop site use the following steps to install the GlobalPayments PrestaShop:
1. Installing the module from PrestaShop addons store:
    A. Go to the PrestaShop addons store and search for 'GlobalPayments'
    B. Click Install, and they will give you a zip archive, download it
    C. Go to PrestaShop Admin -> Modules -> Module Manager -> Upload a module, and upload the zip
    D. Configure and Enable gateways in PrestaShop by adding your public and secret Api Keys
2. Installing the module from the Github repo:
    A. Clone the module inside a directory called 'globalpayments'
    B. Inside that directory, run 'composer install --no-dev' and 'composer dump-autoload'
    C. From here you have 2 options:
        a. Option 1:
            - Zip the directory
            - Go to PrestaShop Admin -> Modules -> Module Manager -> Upload a module, and upload the zip
        b. Option 2:
            - Copy-Paste the directory inside <prestashop_location>/modules/
            - Go to PrestaShop Admin -> Modules -> Module Catalog -> Search for 'Global Payments' and install it
    D. Configure and Enable gateways in PrestaShop by adding your public and secret Api Keys

== Unified Payments Sandbox credentials ==
Access to our Unified Payments requires sandbox credentials which you can retrieve yourself via our <a href="https://developer.globalpay.com/" target="_blank">Developer Portal</a>:

1. First go to the Developer Portal.
2. Click on the person icon in the top-right corner and select Log In or Register.
3. Once registered, click on the person icon again and select Unified Payments Apps.
4. Click  ‘Create a New App’. An app is a set of credentials used to access the API and generate access tokens.
