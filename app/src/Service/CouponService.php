<?php

namespace Okhub\Service;

use Okhub\Utils\Exchange;
use WP_Error;
use WC_Coupon;
use WC_Order;

class CouponService
{

    /**
     * Retrieve the product (or variation) and validate coupon for the product.
     *
     * @param int $productId
     * @param int $variationId
     * @param string $couponCode
     *
     * @return array|WP_Error Array with product and coupon if valid, or WP_Error.
     */
    public function getProductAndValidateCoupon($productId, $variationId, $couponCode)
    {
        // Retrieve the product
        $product = wc_get_product($productId);
        if (!$product) {
            return new WP_Error('invalid_product', 'Product not found.');
        }

        // Retrieve the coupon
        $coupon = new WC_Coupon($couponCode);
        if (!$coupon->is_valid()) {
            return new WP_Error('invalid_coupon', 'Coupon is not valid.');
        }

        // If variation ID is provided, validate it
        if ($variationId) {
            $variation = wc_get_product($variationId);
            if (!$variation || !is_a($variation, 'WC_Product_Variation')) {
                return new WP_Error('invalid_variation', 'Invalid product variation.');
            }
        } else {
            $variation = $product; // fallback to the main product if no variation
        }

        // Check if the coupon applies to the product (or variation)
        if (!$coupon->is_valid_for_product($variation, $product)) {
            return new WP_Error('coupon_not_applicable', 'Coupon does not apply to this product or variation.');
        }

        return ['product' => $product, 'variation' => $variation, 'coupon' => $coupon];
    }

    /**
     * Apply a coupon to a product and calculate the discounted price.
     *
     * @param int $productId
     * @param int $variationId
     * @param int $quantity
     * @param string $couponCode
     * @param string $currency
     *
     * @return array|WP_Error
     */
    public function applyCouponToProduct($productId, $variationId, $quantity, $couponCode, $currency)
    {
        // Validate product and coupon
        $result = $this->getProductAndValidateCoupon($productId, $variationId, $couponCode);
        if (is_wp_error($result)) {
            return $result;
        }

        $product = $result['product'];
        $coupon = $result['coupon'];

        // Calculate the discount
        $originalPrice = $product->get_price() * $quantity;
        $discountAmount = $coupon->get_discount_amount($originalPrice, [$product]);
        $discountedPrice = $originalPrice - $discountAmount;

        return [
            'original_price' => Exchange::price($currency, $originalPrice),
            'discount' => Exchange::price($currency, $discountAmount),
            'discounted_price' => Exchange::price($currency, $discountedPrice),
            'currency' => $currency,
            'coupon' => $couponCode,
        ];
    }

    /**
     * Apply a coupon to multiple products.
     *
     * @param array $items
     * @param string $couponCode
     * @param string $currency
     *
     * @return array
     */
    public function applyCouponToProducts($items, $couponCode, $currency)
    {
        $response = [];

        foreach ($items as $item) {
            $productId = $item['product_id'];
            $variationId = $item['variation_id'];
            $quantity = $item['quantity'];

            $discount = $this->applyCouponToProduct($productId, $variationId, $quantity, $couponCode, $currency);

            if (is_wp_error($discount)) {
                // Log or handle errors per product here
                continue;
            }

            $response[] = array_merge($item, $discount);
        }

        return $response;
    }

    /**
     * Apply a coupon to an order and validate it.
     *
     * @param WC_Order $order
     * @param string $couponCode
     *
     * @return bool|WP_Error
     */
    public function applyCouponToOrder(WC_Order $order, $couponCode)
    {
        $coupon = new WC_Coupon($couponCode);

        if (!$coupon->is_valid()) {
            return new WP_Error('invalid_coupon', 'Coupon is not valid.');
        }

        $order->apply_coupon($couponCode);
        return true;
    }
}
