<?php

namespace Okhub\Utils;

class Shipping
{
    public static $currency_return_reverse = [
        'VND' => 'viet_nam_shipping_fee',
        'MYR' => 'malaysia_shipping_fee',
        'SGD' => 'singapore_shipping_fee',
    ];

    /**
     * Calculate the shipping fee based on currency and price.
     *
     * @param string $currency The currency code.
     * @param float $price The price to compare against the fee threshold.
     * @return float The shipping fee.
     */
    public static function fee($currency, $price)
    {
        // Xác định key để lấy dữ liệu ACF
        $field_key = array_key_exists($currency, self::$currency_return_reverse)
            ? self::$currency_return_reverse[$currency]
            : 'other_country_shipping_fee';

        // Lấy dữ liệu từ ACF
        $fee = get_field($field_key, 'option');

        // Kiểm tra dữ liệu ACF có tồn tại và hợp lệ
        if (!is_array($fee) || !isset($fee['under'], $fee['fee'])) {
            return 0; // Trả về 0 nếu dữ liệu không hợp lệ
        }

        // Tính toán phí vận chuyển dựa trên giá trị "under"
        return ($fee['under'] > $price) ? $fee['fee'] : 0;
    }
}
