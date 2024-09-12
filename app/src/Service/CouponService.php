<?php

namespace Okhub\Service;

use WP_Error;
use WC_Coupon;
use WC_Order;

class CouponService
{
    /**
     * Check if a product has a coupon and calculate the discount price.
     *
     * @param int $productId
     * @param int $variationId
     * @param int $quantity
     * @param string $couponCode
     * @return array|WP_Error
     */
    public function applyCouponToProduct($productId, $variationId, $quantity, $couponCode)
    {
        $product = wc_get_product($productId);
        if (!$product) {
            return new WP_Error('product_not_found', __('Product not found'), array('status' => 404));
        }

        // Retrieve the coupon
        $coupon = new WC_Coupon($couponCode);
        if (!$coupon->is_valid()) {
            return new WP_Error('invalid_coupon', __('Invalid or expired coupon'), array('status' => 400));
        }

         // Ensure $variationId is properly defined and the product is valid
        if (!$variationId || !$product || !is_a($product, 'WC_Product')) {
            return new WP_Error('invalid_data', __('Invalid product or variation ID'), array('status' => 400));
        }

        // Create the product variation object if needed
        $variation = new \WC_Product_Variation($variationId);

        // Check if the coupon applies to the product variation
        if (!$coupon->is_valid_for_product($variation, $product)) {
            return new WP_Error('coupon_not_applicable', __('Coupon not applicable to this product variation'), array('status' => 400));
        }
		
        // Calculate the discount
        $originalPrice = $product->get_price() * $quantity;
        $discount = $coupon->get_discount_amount($originalPrice, array($product));
        $discountedPrice = $originalPrice - $discount;

        return [
            'original_price' => $originalPrice,
            'discount' => $discount,
            'discounted_price' => $discountedPrice,
        ];
    }

    /**
     * Apply a coupon to an order and validate it.
     *
     * @param WC_Order $order
     * @param string $couponCode
     * @return bool|WP_Error
     */
    public function applyCouponToOrder(WC_Order $order, $couponCode)
    {
        $coupon = new WC_Coupon($couponCode);

        if (!$coupon->is_valid()) {
            return new WP_Error('invalid_coupon', __('Invalid or expired coupon'), array('status' => 400));
        }

        // Check if the coupon can be applied to the current order
//         if (!$coupon->validate_coupon()) {
//             return new WP_Error('coupon_not_valid', __('Coupon is not valid for this order'), array('status' => 400));
//         }

        // Apply the coupon to the order
        $order->apply_coupon($couponCode);

        return true;
    }
}
