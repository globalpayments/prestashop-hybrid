{*
* NOTICE OF LICENSE
*
* This file is licenced under the Software License Agreement.
* With the purchase or the installation of the software in your application
* you accept the licence agreement.
*
* DISCLAIMER
*
* @author    GlobalPayments
* @copyright Since 2021 GlobalPayments
* @license   LICENSE
*}

<script src="{$jsPath|escape:'htmlall':'UTF-8'}"></script>
{literal}
    <script>
        GlobalPayments.ThreeDSecure.handleMethodNotification({/literal} {$response|cleanHtml nofilter} {literal});
    </script>
{/literal}
