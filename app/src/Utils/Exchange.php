<?php

namespace Okhub\Utils;

class Exchange
{
    private static $currency_return_reverse = [
        'USD' => 'exchange_to_usd',
        'SGD' => 'exchange_to_singapore',
    ];

    /**
     * Exchange a given price in the default currency to the specified currency.
     *
     * @param string $currency The currency to exchange the price to.
     * @param float $price The price to exchange.
     *
     * @return float The exchanged price.
     */
    public static function price($currency, $price)
    {
        // Sử dụng self:: thay cho $this
        if (!array_key_exists($currency, self::$currency_return_reverse)) {
            return round($price, 2);
        }

        // Lấy tỷ lệ trao đổi từ trường ACF tương ứng
        $ratio = get_field(self::$currency_return_reverse[$currency], 'option');

        // Nếu tỷ lệ là null hoặc 0 (không hợp lệ), trả về giá ban đầu
        if (!$ratio || $ratio <= 0) {
            return round($price, 2);
        }

        // Tính giá sau khi đổi
        $after_exchange = $price * $ratio;
        return round($after_exchange, 2);
    }
    public static function price_reverse($currency, $price)
    {
        // Sử dụng self:: thay cho $this
        if (!array_key_exists($currency, self::$currency_return_reverse)) {
            return $price;
        }

        // Lấy tỷ lệ trao đổi từ trường ACF tương ứng
        $ratio = get_field(self::$currency_return_reverse[$currency], 'option');

        // Nếu tỷ lệ là null hoặc 0 (không hợp lệ), trả về giá ban đầu
        if (!$ratio || $ratio <= 0) {
            return $price;
        }

        // Tính giá sau khi đổi
        $after_exchange = $price / $ratio;
        return round($after_exchange, 2);
    }
}
