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

use GlobalPayments\Api\Entities\Enums\ManualEntryMethod;
use GlobalPayments\PaymentGatewayProvider\Data\Order as OrderModel;
use GlobalPayments\PaymentGatewayProvider\Data\PaymentTokenData;
use GlobalPayments\PaymentGatewayProvider\Platform\Helper\AddressHelper;
use GlobalPayments\PaymentGatewayProvider\Platform\OrderStateInstaller;
use GlobalPayments\PaymentGatewayProvider\Platform\TransactionHistory;
use GlobalPayments\PaymentGatewayProvider\Requests\TransactionType;

if (!defined('_PS_VERSION_')) {
    exit;
}

class GlobalPaymentsPayForOrderModuleFrontController extends ModuleFrontController
{
    /**
     * @var AddressHelper
     */
    private $addressHelper;

    /**
     * @var OrderModel
     */
    private $order;

    /**
     * @var TransactionHistory
     */
    private $transactionHistory;

    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        parent::__construct();

        $this->order = new OrderModel();
        $this->transactionHistory = new TransactionHistory();
    }

    public function postProcess()
    {
        $globalpayments = $this->module;
        $currency = $this->context->currency;
        $activeGateway = array_values(
            array_intersect_key($globalpayments->getActivePaymentMethods(), Tools::getAllValues())
        )[0] ?? $globalpayments->getActiveGateway();
        $orderId = Tools::getValue('orderId');
        $psOrder = new Order((int) $orderId);
        $amount = $psOrder->getTotalPaid();
        $sameAddress = ($psOrder->id_address_invoice === $psOrder->id_address_delivery);
        $this->addressHelper = new AddressHelper();
        $billingAddress = $this->addressHelper->getAddress($psOrder->id_address_invoice);
        $orderState = Configuration::get('PS_OS_PAYMENT');
        $customer = new Customer($psOrder->id_customer);
        $customerName = $customer->firstname . ' ' . $customer->lastname;

        $transactionType = TransactionType::SALE;
        $transactionAction = 'charged';

        if ($activeGateway->paymentAction === TransactionType::AUTHORIZE) {
            $orderState = Configuration::get(OrderStateInstaller::CAPTURE_WAITING);
            $transactionAction = 'authorized';
            $transactionType = TransactionType::AUTHORIZE;
        }
        if ($sameAddress) {
            $shippingAddress = $billingAddress;
        } else {
            $shippingAddress = $this->addressHelper->getAddress($psOrder->id_address_delivery);
        }

        $cardDataRaw = Tools::getIsset($activeGateway->id) ?
            Tools::getValue($activeGateway->id) : null;
        $cardData = $cardDataRaw && array_key_exists('token_response', $cardDataRaw) ?
            json_decode($cardDataRaw['token_response']) : null;

        $muToken = Tools::getIsset($activeGateway->id . '-enable-vault')
            && Tools::getValue($activeGateway->id . '-enable-vault') === 'on';
        $muTokenId = Tools::getIsset('globalpayments-payment-method') ?
            (int) Tools::getValue('globalpayments-payment-method') : null;

        $order = $this->order->generateOrder(
            [
                'amount' => $amount,
                'billingAddress' => $billingAddress,
                'cardData' => $cardData,
                'cardHolderName' => $globalpayments->getCardHolderName($customerName, $cardData),
                'currency' => $currency->iso_code,
                'entryMode' => ManualEntryMethod::MOTO,
                'gatewayProviderId' => $activeGateway->id,
                'multiUseTokenId' => $muTokenId,
                'orderId' => $orderId,
                'requestMultiUseToken' => $muToken,
                'shippingAddress' => $shippingAddress,
            ]
        );

        if (null !== $muTokenId) {
            $token = (new PaymentTokenData($order->asArray()))->getMultiUseToken();
            $order->cardData = $token;
        }

        try {
            $transaction = $activeGateway->processPayment($order);
            $transactionId = $transaction->transactionReference->transactionId;

            $psOrder->setCurrentState($orderState);
            $psOrder->payment = $activeGateway->title;
            $psOrder->save();

            if ($order->cardData) {
                $currentOrder = $psOrder;
                $orderPayment = OrderPayment::getByOrderReference($currentOrder->reference)[0];
                $cardDetails = $order->cardData->details;

                $orderPayment->payment_method = $activeGateway->title;
                $orderPayment->card_number = $cardDetails->cardLast4;
                $orderPayment->card_brand = $cardDetails->cardType;
                $orderPayment->card_expiration = $cardDetails->expiryMonth . '/' . $cardDetails->expiryYear;
                $orderPayment->card_holder = $order->cardHolderName;
                $orderPayment->transaction_id = $transactionId;
                $orderPayment->save();
            }

            $transactionMessage = $this->transactionHistory->generateTransactionMessage(
                $amount,
                $currency->iso_code,
                $transactionAction,
                $transactionId
            );

            $this->transactionHistory->saveResult(
                $orderId,
                $transactionType,
                $amount,
                $currency->iso_code,
                $transactionId,
                1
            );

            $response = [
                'error' => false,
            ];
        } catch (Exception $e) {
            $response = [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }

        echo json_encode($response);
        exit;
    }
}
