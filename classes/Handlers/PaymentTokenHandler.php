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

namespace GlobalPayments\PaymentGatewayProvider\Handlers;

use GlobalPayments\Api\Entities\Enums\GatewayProvider;
use GlobalPayments\PaymentGatewayProvider\Data\PaymentTokenData;
use GlobalPayments\PaymentGatewayProvider\Requests\RequestArg;

if (!defined('_PS_VERSION_')) {
    exit;
}

class PaymentTokenHandler extends AbstractHandler
{
    public function handle()
    {
        if (!$this->request->getArgument(RequestArg::REQUEST_MULTI_USE_TOKEN)) {
            return;
        }

        // Get the multi-use token from response
        $multiUseToken = $this->response->token;
        
        // Get gateway provider to handle gateway-specific token logic
        $config = $this->request->getArgument(RequestArg::SERVICES_CONFIG);
        $gatewayProvider = $config['gatewayProvider'] ?? null;
        
        // If response doesn't have token, get it from the payment method ID in request
        // BUT NOT for Genius gateway - OTT (One-Time Tokens) cannot be reused
        if (empty($multiUseToken) && $this->request->getArgument(RequestArg::CARD_DATA)) {
            $cardData = $this->request->getArgument(RequestArg::CARD_DATA);
            
            if (isset($cardData->paymentReference)) {
                $paymentRef = $cardData->paymentReference;
                
                // For Genius gateway, OTT_ tokens are one-time tokens that cannot be saved
                // They must first be converted to VaultTokens via BoardCard/Verify operation
                if ($gatewayProvider === GatewayProvider::GENIUS && $this->isOneTimeToken($paymentRef)) {
                    // Skip saving OTT for Genius - it won't work for future transactions
                    return;
                }
                
                $multiUseToken = $paymentRef;
            }
        }

        if (empty($multiUseToken)) {
            return;
        }

        (new PaymentTokenData($this->request->getArguments()))
            ->saveNewToken($multiUseToken, $this->response->cardBrandTransactionId);
    }
    
    /**
     * Check if the token is a one-time token (OTT) that cannot be reused
     *
     * @param string $token
     * @return bool
     */
    private function isOneTimeToken(string $token): bool
    {
        // Genius/MerchantWare one-time tokens start with 'OTT_'
        return strpos($token, 'OTT_') === 0;
    }
}
