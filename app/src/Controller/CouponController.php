<?php

namespace Okhub\Controller;

use Okhub\Service\CouponService;
use Okhub\Service\AuthService;
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
            'permission_callback' => '__return_true'
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
        $productId = $request->get_param('product_id');
        $variationId = $request->get_param('variation_id');
        $quantity = $request->get_param('quantity') ?: 1;
        $couponCode = $request->get_param('coupon_code');

        if (!$productId || !$variationId || !$couponCode) {
            return new WP_Error('missing_params', __('Product ID, Variation ID, and Coupon Code are required'), array('status' => 400));
        }

        $result = $this->couponService->applyCouponToProduct($productId, $variationId, $quantity, $couponCode);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response($result);
    }
}
