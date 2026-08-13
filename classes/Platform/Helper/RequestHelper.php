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
        // Use native file_get_contents with hardcoded php://input stream
        // This is safe as php://input is a read-only stream for raw POST data
        $rawContent = self::getRequestBody();
        $headers = self::getAllHeaders();
        $this->setHeaders($headers);

        if (isset($headers['Content-Encoding']) && strpos($headers['Content-Encoding'], 'gzip') !== false) {
            $decodedContent = gzdecode($rawContent);
            // Only use decoded content if gzdecode was successful
            if ($decodedContent !== false) {
                $rawContent = $decodedContent;
            }
        }

        $this->setParams(['rawContent' => $rawContent]);

        if (isset($headers['Content-Type']) && $headers['Content-Type'] === 'application/json') {
            $decodedJson = json_decode($rawContent, false);
            // Only use decoded JSON if it's valid
            if (json_last_error() === JSON_ERROR_NONE && $decodedJson !== null) {
                $rawContent = $decodedJson;
            }
        }

        $requestParams = array_merge($request, (array) $rawContent);
        $this->setParams($requestParams);

        return $this;
    }

    /**
     * Get the raw request body from php://input stream.
     * This method uses a hardcoded stream path to prevent path traversal attacks.
     *
     * @return string
     */
    public static function getRequestBody(): string
    {
        // Hardcoded stream path - not user-controllable
        // php://input is a read-only stream that provides raw POST data
        $content = file_get_contents('php://input');
        
        return $content !== false ? $content : '';
    }

    /**
     * PHP-native replacement for getallheaders().
     *
     * Reconstructs incoming request headers from $_SERVER, making header
     * extraction work consistently across Apache, Nginx, FPM and IIS.
     *
     * @return array
     */
    public static function getAllHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headerName = str_replace(
                    ' ',
                    '-',
                    ucwords(str_replace('_', ' ', strtolower(substr($key, 5))))
                );
                $headers[$headerName] = $value;
            } elseif ($key === 'CONTENT_TYPE') {
                $headers['Content-Type'] = $value;
            } elseif ($key === 'CONTENT_LENGTH') {
                $headers['Content-Length'] = $value;
            } elseif ($key === 'CONTENT_MD5') {
                $headers['Content-MD5'] = $value;
            }
        }

        return $headers;
    }
}
