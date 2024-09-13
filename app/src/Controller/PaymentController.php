<?php

namespace OKhub\Controller;

use Okhub\Service\PaymentService;
use Okhub\Utils\Validator;
use WP_REST_Request;
use WP_Error;

class PaymentController
{
    private $paymentService;
    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
        add_action('rest_api_init', array($this, 'registerRoutes'));
    }

    public function registerRoutes()
    {
        // Get a single product
        register_rest_route('api/v1', 'payments', array(
            'methods' => 'POST',
            'callback' => array($this, 'paymentGateway'),
            'permission_callback' => '__return_true',
            'args' => [
                'currency' => [
                    'validate_callback' => array(Validator::class, 'validate_currency'), // Validation for currency
                ],
            ]
        ));
    }

    /**
     * Handle payment gateway
     *
     * @param WP_REST_Request $request
     *
     * @return mixed
     */
    public function paymentGateway(WP_REST_Request $request)
    {
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        return $this->paymentService->getPaymentGateway($request, $ipAddress);
    }
}
