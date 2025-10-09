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

namespace GlobalPayments\PaymentGatewayProvider\Clients;

use GlobalPayments\Api\Builders\TransactionBuilder;
use GlobalPayments\Api\Builders\TransactionReportBuilder;
use GlobalPayments\Api\Entities\Address;
use GlobalPayments\Api\Entities\Enums\AddressType;
use GlobalPayments\Api\Entities\Enums\CardType;
use GlobalPayments\Api\Entities\Enums\GatewayProvider;
use GlobalPayments\Api\Entities\Enums\Secure3dStatus;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\Entities\GpApi\AccessTokenInfo;
use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\Api\Gateways\IPaymentGateway;
use GlobalPayments\Api\PaymentMethods\CreditCardData;
use GlobalPayments\Api\ServiceConfigs\AcceptorConfig;
use GlobalPayments\Api\ServiceConfigs\Gateways\GeniusConfig;
use GlobalPayments\Api\ServiceConfigs\Gateways\GpApiConfig;
use GlobalPayments\Api\ServiceConfigs\Gateways\PorticoConfig;
use GlobalPayments\Api\ServiceConfigs\Gateways\TransitConfig;
use GlobalPayments\Api\Services\ReportingService;
use GlobalPayments\Api\Services\Secure3dService;
use GlobalPayments\Api\ServicesContainer;
use GlobalPayments\Api\Utils\Logging\Logger;
use GlobalPayments\Api\Utils\Logging\SampleRequestLogger;
use GlobalPayments\PaymentGatewayProvider\Platform\Utils;
use GlobalPayments\PaymentGatewayProvider\Requests\RequestArg;
use GlobalPayments\PaymentGatewayProvider\Requests\RequestInterface;
use GlobalPayments\PaymentGatewayProvider\Requests\ThreeDSecure\AbstractAuthenticationsRequest;
use GlobalPayments\PaymentGatewayProvider\Requests\TransactionType;
use GlobalPayments\Api\Entities\StoredCredential;
use GlobalPayments\Api\Entities\Enums\StoredCredentialInitiator;
use GlobalPayments\PaymentGatewayProvider\Platform\Token;

if (!defined('_PS_VERSION_')) {
    exit;
}

class SdkClient implements ClientInterface
{
    /**
     * Current request arguments
     *
     * @var array<string,mixed>
     */
    protected $arguments = [];

    /**
     * Prepared builder arguments
     *
     * @var mixed[]
     */
    protected $builderArgs = [];

    /**
     * @var string[]
     */
    protected $authTransactions = [
        TransactionType::AUTHORIZE,
        TransactionType::SALE,
        TransactionType::VERIFY,
    ];

    /**
     * @var string[]
     */
    protected $clientTransactions = [
        TransactionType::CREATE_TRANSACTION_KEY,
        TransactionType::CREATE_MANIFEST,
        TransactionType::GET_ACCESS_TOKEN,
    ];

    /**
     * @var string[]
     */
    protected $refundTransactions = [
        TransactionType::REFUND,
        TransactionType::REVERSAL,
        TransactionType::VOID,
    ];

    protected $threeDSecureAuthStatus = [
        Secure3dStatus::NOT_ENROLLED,
        Secure3dStatus::SUCCESS_AUTHENTICATED,
        Secure3dStatus::SUCCESS_ATTEMPT_MADE,
    ];

    /**
     * Card data
     *
     * @var CreditCardData
     */
    protected $cardData;

    /**
     * Previous transaction
     *
     * @var Transaction
     */
    protected $previousTransaction;

    public function setRequest(RequestInterface $request)
    {
        $this->arguments = $request->getArguments();
        $this->prepareRequestObjects();

        return $this;
    }

    public function execute()
    {
        $this->configureSdk();
        $builder = $this->getTransactionBuilder();

        if ('transactionDetail' === $this->arguments['TXN_TYPE']) {
            return $builder->execute();
        }

        if (!$builder instanceof TransactionBuilder) {
            return $builder->{$this->getArgument(RequestArg::TXN_TYPE)}();
        }

        $this->prepareBuilder($builder);
        if ($this->threeDSecureEnabled()) {
            $this->setThreeDSecureData();
        }
        $response = $builder->execute();

        if ($response instanceof Transaction && $response->token) {
            $this->cardData->token = $response->token;
            $this->cardData->updateTokenExpiry();
        }

        return $response;
    }

    public function submitRequest(RequestInterface $request)
    {
        $this->arguments = $request->getArguments();
        $this->configureSdk();

        return $request->doRequest();
    }

    /**
     * @return void
     */
    protected function prepareBuilder(TransactionBuilder $builder)
    {
        foreach ($this->builderArgs as $name => $arguments) {
            $method = 'with' . \Tools::ucfirst($name);

            if (!method_exists($builder, $method)) {
                continue;
            }

            /**
             * @var callable
             */
            $callable = [$builder, $method];
            call_user_func_array($callable, $arguments);
        }
    }

    /**
     * Gets required builder for the transaction
     *
     * @return IPaymentGateway|TransactionReportBuilder
     *
     * @throws ApiException
     */
    protected function getTransactionBuilder()
    {
        $result = null;

        if (in_array($this->getArgument(RequestArg::TXN_TYPE), $this->clientTransactions, true)) {
            $result = ServicesContainer::instance()->getClient('default'); // this value should always be safe here
        } elseif ($this->getArgument(RequestArg::TXN_TYPE) === 'transactionDetail') {
            $result = ReportingService::transactionDetail($this->getArgument('GATEWAY_ID'));
        } elseif (in_array($this->getArgument(RequestArg::TXN_TYPE), $this->refundTransactions, true)) {
            $subject = Transaction::fromId($this->getArgument('GATEWAY_ID'));
            $result = $subject->{$this->getArgument(RequestArg::TXN_TYPE)}();
        } elseif ($this->getArgument(RequestArg::TXN_TYPE) === TransactionType::CAPTURE) {
            $subject = Transaction::fromId($this->getArgument('GATEWAY_ID'));
            $result = $subject->{$this->getArgument(RequestArg::TXN_TYPE)}();
        } else {
            $subject =
                in_array($this->getArgument(RequestArg::TXN_TYPE), $this->authTransactions, true)
                ? $this->cardData : $this->previousTransaction;
            $result = $subject->{$this->getArgument(RequestArg::TXN_TYPE)}();
        }

        return $result;
    }

    /**
     * @return void
     */
    protected function prepareRequestObjects()
    {
        if ($this->hasArgument(RequestArg::AMOUNT)) {
            $this->builderArgs['amount'] = [$this->getArgument(RequestArg::AMOUNT)];
        }

        if ($this->hasArgument(RequestArg::CURRENCY)) {
            $this->builderArgs['currency'] = [$this->getArgument(RequestArg::CURRENCY)];
        }

        // Pay For Order
        if ($this->hasArgument(RequestArg::ORDER_ID)) {
            $orderId = 'ORDER#' . $this->getArgument(RequestArg::ORDER_ID);
            $this->builderArgs['orderId'] = [$orderId];
        }

        // Checkout
        if ($this->hasArgument(RequestArg::CART_ID)) {
            $cartId = 'CART#' . $this->getArgument(RequestArg::CART_ID);
            $this->builderArgs['orderId'] = [$cartId];
        }

        if ($this->hasArgument(RequestArg::CARD_DATA)) {
            $token = $this->getArgument(RequestArg::CARD_DATA);
            $this->prepareCardData($token);

            if (null !== $token && $this->hasArgument(RequestArg::CARD_HOLDER_NAME)) {
                $this->cardData->cardHolderName = $this->getArgument(RequestArg::CARD_HOLDER_NAME);
            }

          if ($token !== null) {
                $is_first = empty($token->id_globalpayments_token);

                $storedCredential = new StoredCredential();
                $storedCredential->initiator = StoredCredentialInitiator::PAYER;
                $storedCredential->type = 'UNSCHEDULED';
                $storedCredential->sequence = $is_first ? 'FIRST' : 'SUBSEQUENT';

                $this->builderArgs['storedCredential'] = [$storedCredential];
            }
        }

        if ($this->hasArgument(RequestArg::BILLING_ADDRESS)) {
            $this->prepareAddress(AddressType::BILLING, $this->getArgument(RequestArg::BILLING_ADDRESS));
        }

        if ($this->hasArgument(RequestArg::SHIPPING_ADDRESS)) {
            $this->prepareAddress(AddressType::SHIPPING, $this->getArgument(RequestArg::SHIPPING_ADDRESS));
        }

        if ($this->hasArgument(RequestArg::DESCRIPTION)) {
            $this->builderArgs['description'] = [$this->getArgument(RequestArg::DESCRIPTION)];
        }

        if ($this->hasArgument(RequestArg::AUTH_AMOUNT)) {
            $this->builderArgs['authAmount'] = [$this->getArgument(RequestArg::AUTH_AMOUNT)];
        }

        if ($this->hasArgument(RequestArg::DW_TOKEN)) {
            $this->cardData = new CreditCardData();
            $this->cardData->token = $this->getArgument(RequestArg::DW_TOKEN);
            $this->cardData->mobileType = $this->getArgument(RequestArg::MOBILE_TYPE);
        }

        if ($this->hasArgument(RequestArg::DYNAMIC_DESCRIPTOR)) {
            $this->builderArgs['dynamicDescriptor'] = [$this->getArgument(RequestArg::DYNAMIC_DESCRIPTOR)];
        }

        if ($this->hasArgument(RequestArg::TXN_MODIFIER)) {
            $this->builderArgs['modifier'] = [$this->getArgument(RequestArg::TXN_MODIFIER)];
        }
    }

    /**
     * @return void
     */
    protected function prepareCardData(?\stdClass $token = null)
    {
        if (null === $token) {
            return;
        }

        $this->cardData = new CreditCardData();
        $this->cardData->token = $token->paymentReference;

        if (isset($token->details->expiryYear)) {
            $this->cardData->expYear = $token->details->expiryYear;
        }

        if (isset($token->details->expiryMonth)) {
            $this->cardData->expMonth = $token->details->expiryMonth;
        }

        if (isset($token->details->cardSecurityCode)) {
            $this->cardData->cvn = $token->details->cardSecurityCode;
        }

        if (isset($token->details->cardType)) {
            switch ($token->details->cardType) {
                case 'visa':
                    $this->cardData->cardType = CardType::VISA;

                    break;
                case 'mastercard':
                    $this->cardData->cardType = CardType::MASTERCARD;

                    break;
                case 'amex':
                    $this->cardData->cardType = CardType::AMEX;

                    break;
                case 'diners':
                case 'discover':
                case 'jcb':
                    $this->cardData->cardType = CardType::DISCOVER;

                    break;
                default:
                    break;
            }
        }

        if ($this->hasArgument(RequestArg::ENTRY_MODE)) {
            $this->cardData->entryMethod = $this->getArgument(RequestArg::ENTRY_MODE);
        }

        $userId = \Context::getContext()->customer->id;
        $already_saved = false;

        $gatewayId = $this->getArgument(RequestArg::GATEWAY_PROVIDER_ID);
        $existing_tokens = Token::getCustomerTokens($userId, $gatewayId);

        foreach ($existing_tokens as $existing_token) {
            if (
                isset($existing_token->details->last4, $existing_token->details->expiryMonth, $existing_token->details->expiryYear) &&
                isset($token->details->cardLast4, $token->details->expiryMonth, $token->details->expiryYear) &&
                $existing_token->details->last4 === $token->details->cardLast4 &&
                $existing_token->details->expiryMonth === $token->details->expiryMonth &&
                $existing_token->details->expiryYear === $token->details->expiryYear
            ) {
                $already_saved = true;
                break;
            }
        }

        if (!$already_saved) {
            $this->builderArgs['requestMultiUseToken'] = [true];
        }
    }

    protected function threeDSecureEnabled()
    {
        return $this->hasArgument(RequestArg::SERVER_TRANS_ID);
    }

    protected function setThreeDSecureData()
    {
        $translator = (new Utils())->getTranslator();
        $errorMessage = $translator->trans(
            '3DS Authentication failed. Please try again.',
            [],
            'Modules.Globalpayments.Shop'
        );

        try {
            $threeDSecureData = Secure3dService::getAuthenticationData()
                ->withServerTransactionId($this->getArgument(RequestArg::SERVER_TRANS_ID))
                ->execute();
        } catch (\Exception $e) {
            throw new ApiException($errorMessage);
        }
        if (AbstractAuthenticationsRequest::YES !== $threeDSecureData->liabilityShift
            || !in_array($threeDSecureData->status, $this->threeDSecureAuthStatus)
        ) {
            throw new ApiException($errorMessage);
        }
        $this->cardData->threeDSecure = $threeDSecureData;
    }

    /**
     * @param string $addressType
     * @param array<string,string> $data
     *
     * @return void
     */
    protected function prepareAddress($addressType, array $data)
    {
        $address = new Address();
        $address->type = $addressType;
        $address = $this->setObjectData($address, $data);

        $this->builderArgs['address'] = [$address, $addressType];
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    protected function hasArgument($name)
    {
        return isset($this->arguments[$name]);
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    protected function getArgument($name)
    {
        return $this->arguments[$name];
    }

    /**
     * @return void
     */
    protected function configureSdk()
    {
        $gatewayConfig = null;

        switch ($this->arguments['SERVICES_CONFIG']['gatewayProvider']) {
            case GatewayProvider::PORTICO:
                $gatewayConfig = new PorticoConfig();

                break;
            case GatewayProvider::TRANSIT:
                $gatewayConfig = new TransitConfig();
                // @phpstan-ignore-next-line
                $gatewayConfig->acceptorConfig = new AcceptorConfig(); // defaults should work here

                break;
            case GatewayProvider::GENIUS:
                $gatewayConfig = new GeniusConfig();

                break;
            case GatewayProvider::GP_API:
                $gatewayConfig = new GpApiConfig();

                $accountName = $this->getArgument(RequestArg::SERVICES_CONFIG)['accountName'] ?? null;
                if (!empty($accountName)) {
                    $accessTokenInfo = new AccessTokenInfo();
                    $accessTokenInfo->transactionProcessingAccountName = $accountName;
                    $gatewayConfig->accessTokenInfo = $accessTokenInfo;
                }

                break;
            default:
                break;
        }

        if (null === $gatewayConfig) {
            return;
        }

        $config = $this->setObjectData(
            $gatewayConfig,
            $this->arguments[RequestArg::SERVICES_CONFIG]
        );

        if ($this->arguments[RequestArg::SERVICES_CONFIG]['debug']) {
            $gatewayConfig->requestLogger = new SampleRequestLogger(new Logger(_PS_ROOT_DIR_ . '/var/logs/'));
        }

        ServicesContainer::configureService($config);
    }

    /**
     * @param object $obj
     * @param array<string,mixed> $data
     *
     * @return object
     */
    protected function setObjectData($obj, array $data)
    {
        foreach ($data as $key => $value) {
            if (property_exists($obj, $key)) {
                $obj->{$key} = $value;
            }
        }

        return $obj;
    }
}
