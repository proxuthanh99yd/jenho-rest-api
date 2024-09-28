<?php

namespace Okhub\Validator;

use WP_Error;

class ValidatorCouponController
{
    public static function  validateCouponProductItems($param, $request, $key)
    {
        if (!is_array($param)) {
            return new WP_Error('invalid_param', 'Items must be an array', array('status' => 400));
        }

        foreach ($param as $item) {
            if (!is_array($item)) {
                return new WP_Error('invalid_param', 'Each item must be an array', array('status' => 400));
            }

            // Kiểm tra các khóa product_id, variation_id, và quantity
            if (!isset($item['product_id'])) {
                return new WP_Error('invalid_param', 'Each item must contain a product_id', array('status' => 400));
            }

            if (!isset($item['variation_id'])) {
                return new WP_Error('invalid_param', 'Each item must contain a variation_id', array('status' => 400));
            }

            if (!isset($item['quantity'])) {
                return new WP_Error('invalid_param', 'Each item must contain a quantity', array('status' => 400));
            }

            // Kiểm tra loại dữ liệu của các khóa
            if (!is_int($item['product_id'])) {
                return new WP_Error('invalid_param', 'product_id must be an integer', array('status' => 400));
            }

            if (!is_int($item['variation_id'])) {
                return new WP_Error('invalid_param', 'variation_id must be an integer', array('status' => 400));
            }

            if (!is_int($item['quantity']) || $item['quantity'] <= 0) {
                return new WP_Error('invalid_param', 'quantity must be a positive integer', array('status' => 400));
            }
        }

        return true;
    }

    public static function validateCouponCode($param, $request, $key)
    {
        if (!is_string($param)) {
            return new WP_Error('invalid_param', 'Coupon code must be a string', array('status' => 400));
        }
        return true;
    }
}
