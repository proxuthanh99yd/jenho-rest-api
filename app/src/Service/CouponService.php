<?php

namespace Okhub\Service;

use Okhub\Utils\Exchange;
use WP_Error;
use WC_Coupon;
use WC_Order;


class CouponService
{
    /**
     * Check if a product has a coupon and calculate the discount price.
     *
     * @param array $items
     * @param string $couponCode
     * @param string $currency
     * @return array|WP_Error
     */
    public function applyCouponToProduct($items, $couponCode, $currency)
    {
        $response = [];
        foreach ($items as $key => $value) {
            $productId = $value['product_id'];
            $variationId = $value['variation_id'];
            $quantity = $value['quantity'];

            // Retrieve the product
            $product = wc_get_product($productId);
            if (!$product) {
                continue;
            }

            // Retrieve the coupon
            $coupon = new WC_Coupon($couponCode);
            if (!$coupon->is_valid()) {
                continue;
            }

            // Ensure $variationId is properly defined and the product is valid
            if (!$variationId || !$product || !is_a($product, 'WC_Product')) {
                continue;
            }

            // Create the product variation object if needed
            $variation = new \WC_Product_Variation($variationId);

            // Check if the coupon applies to the product variation
            if (!$coupon->is_valid_for_product($variation, $product)) {
                continue;
            }
            // Calculate the discount
            $originalPrice = $product->get_price() * $quantity;
            $discount = $coupon->get_discount_amount($originalPrice, array($product));
            $discountedPrice = $originalPrice - $discount;
            $discount =
                [
                    'original_price' => Exchange::price(
                        $currency,
                        $originalPrice
                    ),
                    'discount' => Exchange::price(
                        $currency,
                        $discount
                    ),
                    'discounted_price' => Exchange::price(
                        $currency,
                        $discountedPrice
                    ),
                    'currency' => $currency,
                    'coupon' => $couponCode
                ];
            $response[] = array_merge($value, $discount);
        }
        return $response;
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
        $order->apply_coupon($couponCode);

        return true;
    }
}
