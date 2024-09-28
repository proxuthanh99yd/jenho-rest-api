<?php

namespace Okhub\Utils;

class StockLocation
{
    private static $currency = [
        'MYR' => 'jenho-malaysia',
        'VND' => 'jenho-viet-nam',
    ];

    /**
     * Checks the stock quantity of a product in a given location.
     *
     * @param string $currency The currency to use when retrieving the product.
     * @param int    $product_id The ID of the product to check.
     *
     * @return string|false Returns "LOCAL", "GLOBAL", or false if not found.
     */
    public static function check($currency, $product_id)
    {
        // Lấy termSlug từ currency hoặc gán mặc định là 'jenho-malaysia'
        $termSlug = self::$currency[$currency] ?? self::$currency['MYR'];

        // Lấy các terms liên quan đến sản phẩm
        $theTerms = get_the_terms($product_id, 'product_cat');

        // Kiểm tra xem kết quả có hợp lệ không
        if (is_wp_error($theTerms) || empty($theTerms)) {
            return false; // Trả về false nếu không có terms hoặc gặp lỗi
        }

        // Duyệt qua các terms để kiểm tra termSlug
        foreach ($theTerms as $term) {
            if ($term->slug === $termSlug) {
                return true;
            }
        }

        return false; // Không tìm thấy termSlug
    }
}
