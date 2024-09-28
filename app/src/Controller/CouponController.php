<?php

namespace Okhub\Controller;

use Okhub\Service\CouponService;
use Okhub\Service\AuthService;
use Okhub\Validator\ValidatorCouponController;
use Okhub\Utils\Validator;
use WP_REST_Request;
use WP_Error;

class CouponController
{
    private $couponService;
    private $authService;

    public function __construct(CouponService $couponService, AuthService $authService)
    {
        $this->couponService = $couponService;
        $this->authService = $authService;
        add_action('rest_api_init', array($this, 'registerRoutes'));
    }

    public function registerRoutes()
    {
        // Apply Coupon to Product
        register_rest_route('api/v1', 'coupons/apply', array(
            'methods' => 'POST',
            'callback' => array($this, 'applyCoupon'),
            'permission_callback' => '__return_true',
            'args' => [
                'items' => [
                    'validate_callback' => [ValidatorCouponController::class, 'validateCouponProductItems']
                ],
                'coupon_code' => [
                    'validate_callback' => [ValidatorCouponController::class, 'validateCouponCode']
                ],
                'currency' => [
                    'validate_callback' => [Validator::class, 'validate_currency']
                ],
            ]
        ));
    }

    /**
     * Apply a coupon to a product and calculate the discounted price.
     *
     * @param WP_REST_Request $request
     * @return array|WP_Error
     */
    public function applyCoupon(WP_REST_Request $request)
    {
        $items = $request->get_param('items');
        $couponCode = $request->get_param('coupon_code');
        $currency = $request->get_param('currency') ?? "MYR";

        $result = $this->couponService->applyCouponToProduct($items, $couponCode, $currency);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response($result);
    }
}
