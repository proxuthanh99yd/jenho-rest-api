<?php

namespace Okhub\Service;

use WP_REST_Request;
use WP_Error;

class PaymentService
{
    private $password = PMT_PASSWORD;
    private $serviceId = PMT_SERVICEID;
    private $action = PMT_GATE;
    private $transactionType = "SALE";
    private $pymtMethod = "CC";
    private $merchantReturnUrl = "https://jenho.cms.okhub-tech.com/return";
    private $currencyCode = "MYR";

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
                'Amount' => $this->formatAmount($order->get_total()),
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
                    $order->get_total(),
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
}
