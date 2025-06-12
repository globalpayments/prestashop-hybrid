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

namespace GlobalPayments\PaymentGatewayProvider\Controllers\Admin;

use GlobalPayments\Api\Entities\Enums\Environment;
use GlobalPayments\PaymentGatewayProvider\Gateways\GpApiGateway;
use GlobalPayments\PaymentGatewayProvider\Requests\TransactionType;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ConfigurationController extends FrameworkBundleAdminController
{
    /**
     * Get transaction details.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function credentialsCheckAction(Request $request)
    {
        $response = new JsonResponse();
        $environment = (int) $request->get('isLiveMode') === 0 ? Environment::TEST : Environment::PRODUCTION;
        $appId = $request->get('appId');
        $appKey = $request->get('appKey');
        $gateway = new GpApiGateway();

        $configData = [
            'environment' => $environment,
            'appId' => $appId,
            'appKey' => $appKey,
        ];

        try {
            $request = $gateway->prepareRequest(TransactionType::GET_ACCESS_TOKEN, null, $configData);
            $gatewayResponse = $gateway->submitRequest($request);

            if (!empty($gatewayResponse->token)) {
                $response->setData([
                    'error' => false,
                    'message' => $this->trans(
                        'Your credentials were successfully confirmed!',
                        'Modules.Globalpayments.Admin'
                    ),
                ]);
            } else {
                $response->setData([
                    'error' => true,
                    'message' => $this->trans(
                        'Unable to perform request. Invalid data.',
                        'Modules.Globalpayments.Admin'
                    ),
                ]);
            }
        } catch (\Exception $e) {
            $message = $this->trans(
                'Unable to perform request. Invalid data. %message%',
                'Modules.Globalpayments.Admin',
                ['%message%' => $e->getMessage()]
            );
            $response->setStatusCode(400);
            $response->setData([
                'error' => true,
                'message' => $message,
            ]);
        }

        return $response;
    }
}
