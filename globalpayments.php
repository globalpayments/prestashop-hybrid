<?php
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

use GlobalPayments\PaymentGatewayProvider\Gateways\{
    AbstractGateway,
    GatewayId,
    GpApiGateway,
    TransitGateway
};
use GlobalPayments\PaymentGatewayProvider\PaymentMethods\AbstractPaymentMethod;
use GlobalPayments\PaymentGatewayProvider\PaymentMethods\DigitalWallets\{
    ApplePay,
    ClickToPay,
    GooglePay
};
use GlobalPayments\PaymentGatewayProvider\Platform\{
    OrderAdditionalInfo,
    OrderStateInstaller,
    Token,
    TransactionHistory,
    TransactionManagement,
    Utils
};
use GlobalPayments\PaymentGatewayProvider\Platform\Admin\ConfigForm;
use GlobalPayments\PaymentGatewayProvider\Platform\Admin\Order\View\{
    DigitalWalletsAddress,
    TransactionManagementTab
};
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\{
    AddressHelper,
    CheckoutHelper
};
use GlobalPayments\PaymentGatewayProvider\Requests\IntegrationType;

if (!defined('_PS_VERSION_')) {
    exit;
}

$autoloader = dirname(__FILE__) . '/vendor/autoload.php';

if (is_readable($autoloader)) {
    include_once $autoloader;
}

class GlobalPayments extends PaymentModule
{
    public const MODULE_NAME = 'globalpayments';

    /**
     * @var AbstractGateway|null
     */
    private $activeGateway;

    /**
     * The active payment methods.
     *
     * @var array
     */
    private $activePaymentMethods = [];

    /**
     * @var AddressHelper
     */
    private $addressHelper;

    /**
     * @var ConfigForm
     */
    private $configForm;

    /**
     * @var OrderAdditionalInfo
     */
    private $orderAdditionalInfo;

    /**
     * @var OrderStateInstaller
     */
    private $orderStateInstaller;

    /**
     * List with all available payment methods.
     */
    private $paymentMethods;

    /**
     * @var TransactionHistory
     */
    private $transactionHistory;

    /**
     * @var TransactionManagement
     */
    private $transactionManagement;

    public function __construct()
    {
        $this->name = 'globalpayments';
        $this->tab = 'payments_gateways';
        $this->author = 'GlobalPayments';
        $this->controllers = ['customerCards'];
        $this->version = '2.1.4';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
        $this->module_key = 'f67f06328b552787831df138b42e1221';

        parent::__construct();

        $this->displayName = $this->trans('Global Payments', [], 'Modules.Globalpayments.Admin');
        $this->description = $this->trans(
            'Accept payments via Global Payments, while minimising your PCI Compliance requirements.',
            [],
            'Modules.Globalpayments.Admin'
        );
        $this->confirmUninstall = $this->trans(
            'Warning: Are you sure you want to uninstall the Global Payments payment module?',
            [],
            'Modules.Globalpayments.Admin'
        );

        $this->paymentMethods = [
            new GpApiGateway(),
            new TransitGateway(),
        ];

        $this->paymentMethods = array_merge($this->paymentMethods, GpApiGateway::getPaymentMethods());

        $this->addressHelper = new AddressHelper();
        $this->configForm = new ConfigForm($this);
        $this->orderAdditionalInfo = new OrderAdditionalInfo();
        $this->orderStateInstaller = new OrderStateInstaller();
        $this->transactionHistory = new TransactionHistory();
        $this->transactionManagement = new TransactionManagement($this);

        $this->setActivePaymentMethods();
        $this->setActiveGateway();
    }

    /**
     * {@inheritdoc}
     */
    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->_errors[] = $this->trans(
                'You have to enable the cURL extension on your server to install this module',
                [],
                'Modules.Globalpayments.Admin'
            );

            return false;
        }
        if (!$this->addDefaultValues()) {
            return false;
        }
        /*
         * Install the custom order states
         */
        if (!$this->installOrderState()) {
            return false;
        }
        /*
         * Update the custom order states.
         */
        if (!$this->orderStateInstaller->update()) {
            return false;
        }
        /*
         * Create the tables specific to tokenization
         */
        if (!Token::installTokenDb()) {
            return false;
        }
        if (!$this->transactionHistory->installTable()) {
            return false;
        }
        if (!$this->orderAdditionalInfo->installTable()) {
            return false;
        }

        /*
         * Update the table for the existing users;
         */
        try {
            $this->transactionHistory->updateTable();
            $this->orderStateInstaller->removeVerifyState();
        } catch (Exception $e) {
        }

        /*
         * Clear Smarty and Symphony cache.
         */
        Tools::clearAllCache();

        return parent::install()
            && $this->registerHook('actionAdminControllerSetMedia')
            && $this->registerHook('actionGpHostedFieldsStyling')
            && $this->registerHook('actionFrontControllerSetMedia')
            && $this->registerHook('actionOrderSlipAdd')
            && $this->registerHook('actionProductCancel')
            && $this->registerHook('displayAdminOrderLeft')
            && $this->registerHook('displayAdminOrderMain')
            && $this->registerHook('displayCustomerAccount')
            && $this->registerHook('displayPaymentReturn')
            && $this->registerHook('paymentOptions')
            && $this->registerHook('actionEmailAddAfterContent')
            && $this->registerHook('actionGetExtraMailTemplateVars');
    }

    /**
     * {@inheritdoc}
     */
    public function uninstall()
    {
        /*
         * Loop through all the payment methods and remove their fields from the PrestaShop database
         */
        foreach ($this->paymentMethods as $paymentMethod) {
            foreach (array_keys($paymentMethod->formFields) as $key) {
                if (!Configuration::deleteByName($key)) {
                    return false;
                }
            }
        }

        return parent::uninstall();
    }

    /**
     * {@inheritdoc}
     */
    public function getContent()
    {
        return $this->configForm->getContent();
    }

    /**
     * {@inheritdoc}
     */
    public function isUsingNewTranslationSystem()
    {
        return true;
    }

    /**
     * Get an instance of the module.
     *
     * @return Module
     */
    public static function getModuleInstance()
    {
        return parent::getInstanceByName(self::MODULE_NAME);
    }

    /**
     * Add the default values of the payment methods to the Configuration table,
     * only if the values are not already present.
     *
     * @return bool
     */
    public function addDefaultValues()
    {
        foreach ($this->paymentMethods as $paymentMethod) {
            foreach ($paymentMethod->formFields as $key => $value) {
                if (!empty($value['skipConfigSave']) || Configuration::hasKey($key)) {
                    continue;
                }
                if (is_array($value['default'])) {
                    $value['default'] = implode(',', $value['default']);
                }
                if (!Configuration::updateValue($key, $value['default'])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get the context of the current module.
     *
     * @return Context|null
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * Get the available payment methods.
     *
     * @return array
     */
    public function getPaymentMethods()
    {
        return $this->paymentMethods;
    }

    /**
     * Get the path of the current module.
     *
     * @return string|null
     */
    public function getPath()
    {
        return $this->_path;
    }

    /**
     * Get the path used for enqueueing the front end scripts.
     *
     * @return string
     */
    public function getFrontendScriptsPath()
    {
        return 'modules/' . $this->name;
    }

    /**
     * Returns an array with the active payment methods.
     *
     * @return array
     */
    public function getActivePaymentMethods()
    {
        return $this->activePaymentMethods;
    }

    /**
     * Loop through all the payment methods and if it finds the active one, it sets it.
     */
    public function setActivePaymentMethods()
    {
        foreach ($this->paymentMethods as $paymentMethod) {
            if ($paymentMethod->enabled) {
                $this->activePaymentMethods[$paymentMethod->id] = $paymentMethod;
            }
        }
    }

    /**
     * Get the active gateway.
     *
     * @return AbstractGateway
     */
    public function getActiveGateway()
    {
        return $this->activeGateway;
    }

    /**
     * Set the active gateway.
     *
     * @return void
     */
    public function setActiveGateway()
    {
        if (isset($this->getActivePaymentMethods()[GatewayId::GP_UCP])) {
            $this->activeGateway = $this->getActivePaymentMethods()[GatewayId::GP_UCP];
        } elseif (isset($this->getActivePaymentMethods()[GatewayId::TRANSIT])) {
            $this->activeGateway = $this->getActivePaymentMethods()[GatewayId::TRANSIT];
        }

        uasort($this->activePaymentMethods, [$this, 'sortPaymentMethods']);
    }

    /**
     * Sort the payment methods based on the 'sortOrder' param.
     *
     * @param AbstractGateway|AbstractPaymentMethod $a
     * @param AbstractGateway|AbstractPaymentMethod $b
     *
     * @return int
     */
    public function sortPaymentMethods($a, $b)
    {
        return $a->sortOrder <=> $b->sortOrder;
    }

    /**
     * Create order state
     *
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function installOrderState()
    {
        return $this->orderStateInstaller->install();
    }

    public function registerCustomHooks()
    {
        $hook = new Hook();
        $hook->name = 'actionGpHostedFieldsStyling';
        $hook->title = 'actionGpHostedFieldsStyling';
        $hook->description = 'Style GP Hosted fields';
        $hook->position = 1;
        $hook->add();
        $this->registerHook('actionGpHostedFieldsStyling');

        return true;
    }

    public function getGpUcpOrder()
    {
        $order = new stdClass();

        $cart = $this->context->cart;

        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0) {
            return;
        }

        $amount = (float) $cart->getOrderTotal(true, Cart::BOTH);
        $currency = $this->context->currency->iso_code;
        $sameAddress = ((int) $cart->id_address_invoice === (int) $cart->id_address_delivery);
        $billingAddress = $this->addressHelper->getAddress($cart->id_address_invoice);
        $customerEmail = Context::getContext()->customer->email ?? '';

        if ($sameAddress) {
            $shippingAddress = $billingAddress;
        } else {
            $shippingAddress = $this->addressHelper->getAddress($cart->id_address_delivery);
        }

        $order->amount = $amount;
        $order->currency = $currency;
        $order->billingAddress = $billingAddress;
        $order->shippingAddress = $shippingAddress;
        $order->customerEmail = $customerEmail;

        return $order;
    }

    public function isCheckoutPage()
    {
        $checkoutHelper = new CheckoutHelper($this);

        return $checkoutHelper->isCheckoutPage();
    }

    /**
     * Retrieve Card Holder Name either from Hosted Fields or Billing Address.
     *
     * @param $customerName
     * @param $cardData
     *
     * @return string
     */
    public function getCardHolderName($customerName, $cardData)
    {
        return $cardData->details->cardholderName ?? $customerName;
    }

    private function displayAdminOrderData($psOrder)
    {
        $html = '';
        $tabs = [
            TransactionManagementTab::class,
            DigitalWalletsAddress::class,
        ];

        foreach ($tabs as $tab) {
            $template = new $tab($this, $psOrder);
            if (method_exists($template, 'getTemplate')) {
                $html .= $template->getTemplate();
            }
        }

        return $html;
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     */
    public function hookActionAdminControllerSetMedia()
    {
        $this->context->controller->addCSS(
            $this->_path . 'views/css/globalpayments-secure-payment-fields-bo.css'
        );

        $this->context->controller->addJS(
            $this->_path . 'views/js/globalpayments-admin.js'
        );

        $this->context->controller->addJS(
            $this->_path . 'views/js/globalpayments-enforce-single-gateway.js'
        );

        $this->context->controller->addJS(
            'https://cdnjs.cloudflare.com/ajax/libs/jquery.blockUI/2.70/jquery.blockUI.min.js'
        );

        $this->context->controller->addJS(
            $this->_path . '/views/js/admin/globalpayments-helper.js'
        );

        $this->context->controller->addJS(
            $this->_path . '/views/js/admin/globalpayments-observe-place-order-button.js'
        );

        $this->context->controller->addJS(
            $this->_path . '/views/js/admin/globalpayments-get-transaction-details.js'
        );

        $this->context->controller->addJS(
            $this->_path . '/views/js/admin/globalpayments-refund-validation.js'
        );

        Media::addJsDef([
            'globalpayments_admin_params' => [
                'credentialsCheckUrl' => $this->configForm->getCredentialsCheckUrl(),
                'defaultCurrency' => $this->context->currency->iso_code ?? 'USD',
                'messages' => [
                    'appId' => $this->getTranslator()->trans(
                        'Please enter an App ID',
                        [],
                        'Modules.Globalpayments.Admin'
                    ),
                    'appKey' => $this->getTranslator()->trans(
                        'Please enter an App Key',
                        [],
                        'Modules.Globalpayments.Admin'
                    ),
                    'checkingCredentials' => $this->getTranslator()->trans(
                        'We\'re checking your credentials...',
                        [],
                        'Modules.Globalpayments.Admin'
                    ),
                    'credentialsCheck' => $this->getTranslator()->trans(
                        'Credentials Check',
                        [],
                        'Modules.Globalpayments.Admin'
                    ),
                    'enforceOneGateway' => $this->getTranslator()->trans(
                        'You can enable only one GlobalPayments gateway at a time. Please disable %gateway% first',
                        [],
                        'Modules.Globalpayments.Admin'
                    ),
                    'saveChanges' => $this->getTranslator()->trans(
                        'Please save the changes in the %tabname% tab before continue.',
                        [],
                        'Modules.Globalpayments.Admin'
                    ),
                ],
            ],
        ]);

        // Add order data for refund validation if we're on an order page
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $legacyController = Tools::getValue('controller') ?: Tools::getValue('tab');
        $isLegacyOrderPage = $legacyController === 'AdminOrders';
        $orderId = (int) Tools::getValue('id_order');

        if ($orderId <= 0 && (int) Tools::getValue('orderId') > 0) {
            $orderId = (int) Tools::getValue('orderId');
        }

        if ($orderId <= 0 && preg_match('~(?:^|/)(?:index\.php/)?sell/orders/(\d+)/view~', $requestUri, $matches)) {
            $orderId = (int) $matches[1];
        }

        if ($orderId > 0 && ($isLegacyOrderPage || strpos($requestUri, '/sell/orders/') !== false)) {
            $order = new Order($orderId);

            if ($order && $order->module === $this->name) {
                $orderAdditionalInfo = new OrderAdditionalInfo();
                $installmentHistory = $orderAdditionalInfo->getAdditionalInfo($orderId, 'installment_history');
                // Get already refunded amount
                $alreadyRefunded = 0.00;
                $orderSlips = OrderSlip::getOrdersSlip($order->id_customer, $order->id);
                foreach ($orderSlips as $slip) {
                    $alreadyRefunded +=
                        (float) $slip['total_products_tax_incl'] + (float) $slip['total_shipping_tax_incl'];
                }

                // Check if payment is accepted (state 2)
                $paymentAcceptedStateId = (int) Configuration::get('PS_OS_PAYMENT') ?: 2;
                $currentState = (int) $order->getCurrentState();
                $isPaymentAccepted = ($currentState === $paymentAcceptedStateId);
                
                // Check if payment method is Open Banking
                $isOpenBankingPayment = (
                    stripos($order->payment, 'Open Banking') !== false 
                    || stripos($order->payment, 'Bank Payment') !== false
                );

                // Translated message for Open Banking partial refund not supported
                $partialRefundNotSupportedMessage = $this->getTranslator()->trans(
                    'Payment confirmation for this method may take several days. Refunds are only available after a final payment status is received. Please wait for confirmation or contact support if the delay continues.',
                    [],
                    'Modules.Globalpayments.Admin'
                );

                Media::addJsDef([
                    'globalpayments_order_data' => [
                        'orderId' => $orderId,
                        'orderModule' => $order->module,
                        'orderTotal' => (float) $order->total_paid,
                        'alreadyRefunded' => $alreadyRefunded,
                        'remainingRefundable' => (float) $order->total_paid - $alreadyRefunded,
                        'currency' => (new Currency($order->id_currency))->iso_code,
                        'installmentHistory' => $installmentHistory,
                        'currentState' => $currentState,
                        'paymentMethod' => $order->payment,
                        'isPaymentAccepted' => $isPaymentAccepted,
                        'isOpenBankingPayment' => $isOpenBankingPayment,
                        'isPartialRefundDisabled' => ($isPaymentAccepted && $isOpenBankingPayment),
                        'partialRefundDisabledMessage' => $partialRefundNotSupportedMessage,
                    ],
                ]);
            }
        }

        if (isset($this->activePaymentMethods[GatewayId::GP_UCP])) {
            $this->context->controller->addCSS(
                $this->_path . 'views/css/globalpayments-secure-payment-fields.css'
            );

            $this->context->controller->addJS(
                'https://js.globalpay.com/' . Utils::getJsLibVersion() . '/globalpayments'
                . (defined('_PS_MODE_DEV_') && _PS_MODE_DEV_ ? '' : '.min') . '.js'
            );

            $this->context->controller->addJS(
                $this->_path . '/views/js/admin/globalpayments-secure-payment-fields.js'
            );

            Media::addJsDef(
                [
                    'globalpayments_secure_payment_fields_params' => $this->activePaymentMethods[GatewayId::GP_UCP]->getPaymentFieldsParams(),
                ]
            );
        }
    }

    /**
     * Load Javascripts and CSS related to the GlobalPayments's module
     * during the checkout process only.
     */
    public function hookActionFrontControllerSetMedia()
    {
        if (empty($this->activePaymentMethods)) {
            return;
        }

        if (empty($this->context->controller->page_name) || !$this->isCheckoutPage()) {
            return;
        }

        $path = $this->getFrontendScriptsPath();

        /*
         * jQuery BlockUI
         */
        $this->context->controller->registerJavascript(
            'jquery-blockui',
            'https://cdnjs.cloudflare.com/ajax/libs/jquery.blockUI/2.70/jquery.blockUI.min.js',
            ['server' => 'remote']
        );

        $this->context->controller->registerJavascript(
            'helper',
            $path . '/views/js/globalpayments-helper.js'
        );

        $this->context->controller->registerJavascript(
            'observer',
            $path . '/views/js/globalpayments-observe-place-order-button.js'
        );

        $this->context->controller->registerJavascript(
            'saved-card-tooltip',
            $path . '/views/js/saved-card-tooltip.js'
        );

        foreach ($this->activePaymentMethods as $paymentMethod) {
            $paymentMethod->enqueuePaymentScripts($this);
        }

        $gpUcpOrder = $this->getGpUcpOrder();
        if (!$gpUcpOrder) {
            return;
        }

        Media::addJsDef(
            [
                'globalpayments_helper_params' => [
                    'order' => [
                        'amount' => $gpUcpOrder->amount,
                        'amountDecimal' => number_format((float) $gpUcpOrder->amount, 2, '.', ''),
                        'currency' => $gpUcpOrder->currency,
                        'billingAddress' => $gpUcpOrder->billingAddress,
                        'shippingAddress' => $gpUcpOrder->shippingAddress,
                        'customerEmail' => $gpUcpOrder->customerEmail,
                    ],
                    'toggle' => [
                        GatewayId::GP_UCP,
                        GatewayId::TRANSIT,
                        ApplePay::PAYMENT_METHOD_ID,
                        GooglePay::PAYMENT_METHOD_ID,
                    ],
                    'hide' => [
                        ClickToPay::PAYMENT_METHOD_ID,
                    ],
                    'messages' => [
                        'termsOfService' => $this->getTranslator()->trans(
                            'Please accept the terms of service',
                            [],
                            'Modules.Globalpayments.Shop'
                        ),
                    ],
                    'urls' => [
                        'asyncPaymentMethodValidation' => $this->context->link->getModuleLink(
                            $this->name,
                            'asyncPaymentMethodValidation',
                            [],
                            true
                        ),
                    ],
                ],
            ]
        );
    }

    public function hookActionOrderSlipAdd($params)
    {
        if (version_compare(_PS_VERSION_, '1.7.7.0', '>')) {
            return;
        }

        $psOrder = $params['order'];

        if ($psOrder->module !== $this->name) {
            return;
        }

        $products = $params['productList'];
        $totalToBeRefunded = 0.00;
        $shippingAmount = Tools::getIsset('partialRefundShippingCost') ?
            Tools::getValue('partialRefundShippingCost') : 0.00;

        foreach ($products as $product) {
            $quantity = (int) $product['quantity'];
            $price = (float) $product['unit_price'];
            if ($quantity > 0 && $price > 0.00) {
                $totalToBeRefunded += $price * $quantity;
            }
        }

        $totalToBeRefunded += $shippingAmount;

        // Early validation - if this fails, we should prevent the entire refund process
        try {
            $this->transactionManagement->validateRefundAmountOnly($psOrder, $totalToBeRefunded);
        } catch (\Exception $e) {
            // Log the validation error
            PrestaShopLogger::addLog(
                'GlobalPayments: Pre-validation failed - ' . $e->getMessage(),
                PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR
            );

            // This won't stop PrestaShop's order slip creation, but will prevent our refund processing
            return;
        }

        try {
            $this->transactionManagement->processRefund($psOrder, $totalToBeRefunded);
        } catch (\Exception $e) {
            // Ensure error is displayed on admin page
            if (isset($this->context->controller)) {
                $this->context->controller->errors[] = Tools::displayError($e->getMessage());
            }
            // Log the error for debugging
            PrestaShopLogger::addLog(
                'GlobalPayments Refund Hook Error (OrderSlipAdd): ' . $e->getMessage(),
                PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR
            );
        }
    }

    /**
     * Hook called when a product is refunded
     *
     * @param $params
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookActionProductCancel($params)
    {
        if (version_compare(_PS_VERSION_, '1.7.7.0', '<=')) {
            return;
        }

        $psOrder = $params['order'];

        if ($psOrder->module !== $this->name) {
            return;
        }

        $orderId = (int) $psOrder->id;
        $orderDetail = new OrderDetail((int) $params['id_order_detail']);
        $orderProducts = $orderDetail->getList($orderId);
        $totalToBeRefunded = 0.00;

        if (Tools::getIsset('cancel_product')) {
            $refundedProducts = Tools::getValue('cancel_product');
        } else {
            $refundedProducts = [];
        }

        foreach ($orderProducts as $product) {
            $productId = $product['id_order_detail'];
            $productQuantity = (int) $refundedProducts['quantity_' . $productId];
            $productValue = (float) $refundedProducts['amount_' . $productId];
            /*
             * If the product has a quantity and a price, it means that it was refunded
             */
            if ($productQuantity > 0 && $productValue > 0.00) {
                $totalToBeRefunded += $productValue;
            }
        }

        if ((float) $refundedProducts['shipping_amount'] > 0.00) {
            $totalToBeRefunded += (float) $refundedProducts['shipping_amount'];
        }

        // Early validation - if this fails, we should prevent the entire refund process
        try {
            $this->transactionManagement->validateRefundAmountOnly($psOrder, $totalToBeRefunded);
        } catch (\Exception $e) {
            // Log the validation error
            PrestaShopLogger::addLog(
                'GlobalPayments: Pre-validation failed - ' . $e->getMessage(),
                PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR
            );

            // This won't stop PrestaShop's order slip creation, but will prevent our refund processing
            return;
        }

        try {
            $this->transactionManagement->processRefund($psOrder, $totalToBeRefunded);
        } catch (\Exception $e) {
            // Ensure error is displayed on admin page
            if (isset($this->context->controller)) {
                $this->context->controller->errors[] = Tools::displayError($e->getMessage());
            }
            // Log the error for debugging
            PrestaShopLogger::addLog(
                'GlobalPayments Refund Hook Error (ProductCancel): ' . $e->getMessage(),
                PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR
            );
        }
    }

    /**
     * Hook for displaying the transaction history panel
     * This works until PrestaShop 1.7.7
     *
     * @param $params
     *
     * @return bool|string|void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookDisplayAdminOrderLeft($params)
    {
        $psOrder = new Order($params['id_order']);

        if ($psOrder->module !== $this->name) {
            return;
        }

        $this->transactionManagement->doTransaction($psOrder);

        return $this->displayAdminOrderData($psOrder);
    }

    /**
     * Hook for displaying the transaction history panel
     * This works for PrestaShop >= 1.7.7
     *
     * @param $params
     *
     * @return bool|string|void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookDisplayAdminOrderMain($params)
    {
        $psOrder = new Order($params['id_order']);

        if ($psOrder->module !== $this->name) {
            return;
        }

        $this->transactionManagement->doTransaction($psOrder);

        return $this->displayAdminOrderData($psOrder);
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active || empty($this->activePaymentMethods)) {
            return;
        }

        $paymentOptions = [];

        foreach ($this->activePaymentMethods as $paymentMethod) {
            $paymentOptions = array_merge(
                $paymentOptions,
                $paymentMethod->getPaymentOptions($this, $params, $this->isCheckoutPage())
            );
        }

        return $paymentOptions;
    }

    public function hookDisplayCustomerAccount()
    {
        $activeGateway = $this->getActiveGateway();

        // Only show stored cards link if allowCardSaving is enabled
        if (!$activeGateway || !$activeGateway->allowCardSaving) {
            return;
        }

        $this->context->smarty->assign([
            'activeGateway' => $activeGateway,
            'title' => (new Utils())->getCardStorageText(),
        ]);

        return $this->display(__FILE__, 'my_account_stored_cards.tpl');
    }

    /**
     * This hook is used to display the order confirmation page.
     */
    public function hookDisplayPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        $templateVars = [];

        $activeGateway = $this->getActiveGateway();

        $path = $this->getFrontendScriptsPath();

        $this->context->controller->registerJavascript(
            'PaymentReturn',
            $path . '/views/js/globalpayments-payment-return.js'
        );

        $this->context->controller->addCSS(
            $this->_path . 'views/css/globalpayments-installments-modal.css'
        );

        $order = $params['order'];
        $cookie = $params['cookie'] ?? $this->context->cookie;
        $paymentError = '';

        if ($activeGateway && $activeGateway->integrationType === IntegrationType::HOSTED_PAYMENT_PAGE) {
            $order = new Order((int) $params['order']->id);
            $orderAdditionalInfo = new OrderAdditionalInfo();
            $installmentHistory = $orderAdditionalInfo->getAdditionalInfo($order->id, 'installment_history');

            if (!empty($installmentHistory)) {
                $installmentHistory['order_amount'] = number_format((float) $order->total_paid, 2, '.', '');
                $templateVars['installmentsData'] = $installmentHistory;
            }
        }


        if ($cookie->__isset('globalpayments_payment_error')) {
            $paymentError = $cookie->globalpayments_payment_error;
            unset($cookie->globalpayments_payment_error);
        }

        if ($order->getCurrentOrderState()->id !== (int) Configuration::get('PS_OS_ERROR')) {
            $templateVars['error'] = $paymentError;
            $templateVars['status'] = 'ok';
        }

        $this->context->smarty->assign($templateVars);

        return $this->display(__FILE__, 'payment_return.tpl');
    }

    public function hookActionGpHostedFieldsStyling($params)
    {
        $isAdmin = (
            is_object(Context::getContext()->controller)
            && (
                Context::getContext()->controller->controller_type == 'admin'
                || Context::getContext()->controller->controller_type == 'moduleadmin'
            )
        );

        if (!$isAdmin) {
            return $params['styles'];
        }

        $securePaymentFieldsStyles = json_decode($params['styles'], true);

        $securePaymentFieldsStyles['button#secure-payment-field.submit'] = [
            'border' => '0',
            'background' => 'none',
            'background-color' => '#24b9d7',
            'border-color' => 'rgba(0,0,0,0)',
            'border-radius' => '4px',
            'color' => '#fff',
            'cursor' => 'pointer',
            'padding' => '0.65rem 1.25rem',
            'text-decoration' => 'none',
            'text-transform' => 'none',
            'text-align' => 'center',
            'text-shadow' => 'none',
            'display' => 'inline-block',
            '-webkit-appearance' => 'none',
            'height' => 'initial',
            'width' => 'auto',
            'flex' => 'initial',
            'position' => 'static',
            'margin' => '0',
            'white-space' => 'pre-wrap',
            'margin-bottom' => '0',
            'float' => 'none',
            'font' => '600 .875rem/1.5 "Open Sans",helvetica,arial,sans-serif !important',
        ];
        $securePaymentFieldsStyles['#secure-payment-field[type=button]:focus'] = [
            'color' => '#fff',
            'background-color' => '#7cd5e7',
            'border-color' => '#7cd5e7',
        ];
        $securePaymentFieldsStyles['#secure-payment-field[type=button]:hover'] = [
            'color' => '#fff',
            'background-color' => '#7cd5e7',
            'border-color' => '#7cd5e7',
        ];

        return json_encode($securePaymentFieldsStyles);
    }

    /**
     * ActionEmailAddAfterContent Hook
     *
     * Used to inject {installment_section} placeholder into the payment confirmation email HTML template.
     * The placeholder is populated by hookActionGetExtraMailTemplateVars.
     */
    public function hookActionEmailAddAfterContent(array $params): void
    {
        if ($params['template'] !== 'payment') {
            return;
        }

        $placeholder = '{installment_section}';

        if (strpos($params['template_html'], '<!-- SHOP NAME BEGINING -->') !== false) {
            $params['template_html'] = str_replace(
                '<!-- SHOP NAME BEGINING -->',
                $placeholder . '<!-- SHOP NAME BEGINING -->',
                $params['template_html']
            );
        } else {
            $params['template_html'] = str_replace(
                '</body>',
                $placeholder . '</body>',
                $params['template_html']
            );
        }
    }

    /**
     * ActionGetExtraMailTemplateVars Hook
     *
     * inject installment details into payment confirmation email
     */
    public function hookActionGetExtraMailTemplateVars(array $params): void
    {
        if ($params['template'] !== 'payment') {
            return;
        }

        $params['extra_template_vars']['{installment_section}'] = '';

        $orderId = (int)($params['template_vars']['{id_order}'] ?? 0);

        if ($orderId <= 0) {
            return;
        }

        $orderAdditionalInfo = new OrderAdditionalInfo();
        $installmentData = $orderAdditionalInfo->getAdditionalInfo($orderId, 'installment_history');

        if (empty($installmentData)) {
            return;
        }

        $params['extra_template_vars']['{installment_section}'] = $this->getInstallmentsHtml($installmentData);
    }

    /**
     * Build installments display HTML
     *
     * @param array<string, mixed> $installmentData Installment data
     * @return string HTML for installment details
     */
    private function getInstallmentsHtml(array $installmentData): string
    {
        $installments = (string)($installmentData['installments'] ?? 'N/A');
        $financedAmount = (string)($installmentData['financed_amount'] ?? 'N/A');
        $financeFee = (string)($installmentData['finance_fee'] ?? 'N/A');
        $monthlyAmount = (string)($installmentData['monthly_amount'] ?? 'N/A');
        $currency = (string)($installmentData['currency'] ?? '');
        $currencyDisplay = $currency ? ' ' . $currency : '';

        $this->context->smarty->assign([
            'installments' => $installments,
            'financedAmount' => $financedAmount,
            'financeFee' => $financeFee,
            'monthlyAmount' => $monthlyAmount,
            'currencyDisplay' => $currencyDisplay,
        ]);

        return $this->display(__FILE__, 'installment_email_section.tpl');
    }
}
