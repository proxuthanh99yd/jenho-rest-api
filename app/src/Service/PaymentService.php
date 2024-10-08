<?php

namespace Okhub\Service;

use Okhub\Utils\Exchange;
use WP_REST_Request;
use WP_Error;

class PaymentService
{
    private $password;
    private $serviceId;
    private $action;
    private $transactionType = "SALE";
    private $pymtMethod = "ANY";
    private $merchantReturnUrl;
    private $currencyCode = "MYR";

    private $currency_return = [
        'MYR' => 'jenho-malaysia',
        'VND' => 'jenho-viet-nam',
    ];

    private $currency_return_reverse = [
        'USD' => 'exchange_to_usd',
        'SGD' => 'exchange_to_singapore',
    ];

    public function __construct()
    {
        $this->password = defined('PMT_PASSWORD') ? PMT_PASSWORD : "";
        $this->serviceId = defined('PMT_SERVICEID') ? PMT_SERVICEID : "";
        $this->action = defined('PMT_GATE') ? PMT_GATE : "";
        $this->merchantReturnUrl = site_url('/return');
    }

    public function getPaymentGateway(WP_REST_Request $request, $custIp)
    {
        if ($request->get_param('currency')) {
            $this->currencyCode = $request->get_param('currency');
        }
        $timestamp = time() * 1000;
        $orderId = $request->get_param('OrderNumber');
        $order = wc_get_order($orderId);

        if (!$order)   return new WP_Error('order_not_found', 'Order not found', ['status' => 404]);


        $data = $order->get_data();
        $paymentID = "JENHO" . substr($timestamp, -6) . $orderId;

        return [
            'payment_url' => $this->action . '?' . http_build_query([
                'TransactionType' => $this->transactionType,
                'PymtMethod' => $this->pymtMethod,
                'ServiceID' => $this->serviceId,
                'PaymentID' => $paymentID,
                'OrderNumber' => $orderId,
                'PaymentDesc' => "JENHO Payment for #" . $orderId,
                'MerchantReturnURL' => $this->merchantReturnUrl,
                'Amount' => $this->formatAmount(Exchange::price($this->currencyCode, $order->get_total())),
                'CurrencyCode' => $this->currencyCode,
                'CustName' => $data['billing']['first_name'] . " " . $data['billing']['last_name'],
                'CustEmail' => $data['billing']['email'],
                'CustPhone' => $data['billing']['phone'],
                'CustIP' => $custIp,
                'HashValue' => $this->generateHashValue(
                    $this->password,
                    $this->serviceId,
                    $paymentID,
                    $this->merchantReturnUrl,
                    Exchange::price(
                        $this->currencyCode,
                        $order->get_total()
                    ),
                    $this->currencyCode,
                    $custIp,
                    600
                ),
                'PageTimeout' => 600,
                'Param6' => $request->get_param('MerchantCallBackURL'),
            ])
        ];
    }

    private function formatAmount($amount)
    {
        // Format the number to 2 decimal places without commas
        return number_format($amount, 2, '.', '');
    }

    private function generateHashValue(
        $Password,
        $ServiceID,
        $PaymentID,
        $MerchantReturnURL,
        $Amount,
        $CurrencyCode,
        $CustIP,
        $PageTimeout
    ) {
        return hash('sha256', $Password . $ServiceID . $PaymentID . $MerchantReturnURL  . $this->formatAmount($Amount) . $CurrencyCode . $CustIP . $PageTimeout);
    }

    /**
     * Exchanges a given price in the default currency to the specified currency.
     *
     * @param string $currency The currency to exchange the price to.
     * @param float $price The price to exchange.
     *
     * @return float The exchanged price.
     */
    public function exchangePrice($currency, $price)
    {
        if (!array_key_exists($currency, $this->currency_return_reverse) && !isset($this->currency_return_reverse[$currency])) {
            return $price;
        }
        $ratio = get_field($this->currency_return_reverse[$currency], 'option');
        $after_exchange = $price * $ratio;
        return round($after_exchange, 2);
    }
}
