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

namespace GlobalPayments\PaymentGatewayProvider\Platform\Helper;

if (!defined('_PS_VERSION_')) {
    exit;
}

class RequestHelper
{
    /**
     * @var array
     */
    private $headers;

    /**
     * @var array
     */
    private $params;

    /**
     * Get a header based on the key.
     *
     * @param $key
     *
     * @return mixed|null
     */
    public function getHeader($key)
    {
        return $this->headers[$key] ?? null;
    }

    /**
     * Get the request headers.
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Set the request headers.
     *
     * @param array $headers
     *
     * @return void
     */
    public function setHeaders($headers)
    {
        if (empty($this->headers)) {
            $this->headers = $headers;
        } else {
            $this->headers = array_merge($this->headers, $headers);
        }
    }

    /**
     * Get the request method.
     *
     * @return mixed
     */
    public function getMethod()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * Get a param based on the key.
     *
     * @param $key
     * @return mixed|null
     */
    public function getParam($key)
    {
        return $this->params[$key] ?? null;
    }

    /**
     * Get the request params.
     *
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * Set the request params.
     *
     * @param array $params
     *
     * @return void
     */
    public function setParams($params)
    {
        if (empty($this->params)) {
            $this->params = $params;
        } else {
            $this->params = array_merge($this->params, $params);
        }
    }

    /**
     * Get the request data.
     *
     * @return $this
     */
    public function getRequest()
    {
        $request = \Tools::getAllValues();
        $rawContent = \Tools::file_get_contents('php://input');
        $headers = \WebserviceRequest::getallheaders();
        $this->setHeaders($headers);

        if (isset($headers['Content-Encoding']) && strpos($headers['Content-Encoding'], 'gzip') !== false) {
            $rawContent = gzdecode($rawContent);
        }

        $this->setParams(['rawContent' => $rawContent]);

        if (isset($headers['Content-Type']) && $headers['Content-Type'] === 'application/json') {
            $rawContent = json_decode($rawContent);
        }

        $requestParams = array_merge($request, (array) $rawContent);
        $this->setParams($requestParams);

        return $this;
    }
}
