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

use GlobalPayments\Api\Entities\Enums\BNPLShippingMethod;
use GlobalPayments\Api\Entities\Product as GpProduct;
use GlobalPayments\PaymentGatewayProvider\Platform\Utils;
use PrestaShop\PrestaShop\Adapter\Image\ImageRetriever;
use PrestaShopBundle\Translation\TranslatorComponent as Translator;

if (!defined('_PS_VERSION_')) {
    exit;
}

class OrderHelper
{
    /**
     * Context
     */
    protected $context;

    /**
     * @var \Order
     */
    protected $order;

    /**
     * @var \GlobalPayments
     */
    protected $module;

    /**
     * @var Translator
     */
    protected $translator;

    /**
     * @var Utils
     */
    protected $utils;

    /**
     * CustomerHelper constructor.
     */
    public function __construct($orderId)
    {
        $this->order = new \Order($orderId);

        $this->context = \Context::getContext();
        $this->module = \GlobalPayments::getModuleInstance();
        $this->translator = $this->module->getTranslator();
        $this->utils = new Utils();
    }

    /**
     * Get all order items.
     *
     * @return array
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function getProductData()
    {
        $orderProducts = $this->order->getProducts();
        $product = new GpProduct();
        $product->productId = (string) $this->order->id;
        $product->productName = $this->translator->trans('Your order', [], 'Modules.Globalpayments.Shop');
        $product->description = $product->productName;
        $product->quantity = 1;
        $product->unitPrice = $this->utils->formatNumberToTwoDecimalPlaces($this->order->getTotalPaid());
        $product->netUnitPrice = $product->unitPrice;
        $product->url = $this->context->shop->getBaseURL(true);
        $product->imageUrl = $this->getProductImageUrl(reset($orderProducts));

        return [$product];
    }

    /**
     * Get the image url of a product.
     *
     * @param $product
     *
     * @return string
     *
     * @throws \PrestaShopDatabaseException
     */
    public function getProductImageUrl($product)
    {
        $orderProduct = new \Product($product['id_product']);
        $productCover = \Product::getCover($product['id_product']);
        $protocol = (\Tools::usingSecureMode() && \Configuration::get('PS_SSL_ENABLED')) ? 'https://' : 'http://';
        $link = new \Link($protocol, $protocol);

        if (empty($productCover)) {
            $imageRetriever = new ImageRetriever($this->context->link);
            $imagePath = $imageRetriever->getNoPictureImage(\Context::getContext()->language)['large']['url'];
        } else {
            $imagePath = $link->getImageLink(
                $orderProduct->link_rewrite[$this->context->language->id],
                $productCover['id_image'],
                \ImageType::getFormattedName('large')
            );
        }

        return $imagePath;
    }

    /**
     * Get the shipping method based on the cart products.
     *
     * @return BNPLShippingMethod
     */
    public function getShippingMethod()
    {
        $orderItems = $this->order->getProducts();
        $isVirtualProduct = false;
        $needsShipping = false;

        foreach ($orderItems as $orderItem) {
            if ($orderItem['is_virtual']) {
                $isVirtualProduct = true;
            } else {
                $needsShipping = true;
            }
        }

        if ($isVirtualProduct && $needsShipping) {
            return BNPLShippingMethod::COLLECTION;
        }
        if ($needsShipping) {
            return BNPLShippingMethod::DELIVERY;
        }

        return BNPLShippingMethod::EMAIL;
    }
}
