<?php

namespace Okhub\Database;

class CreateWishlistTables
{
    public static function create()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Bảng lưu thông tin wishlist
        $sql1 = "CREATE TABLE {$wpdb->prefix}wishlist (
        ID bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, 
        user_id bigint(20) UNSIGNED NOT NULL, 
        wishlist_token varchar(255) NOT NULL UNIQUE, 
        PRIMARY KEY (ID),
        KEY user_id (user_id)
    ) $charset_collate;";

        // Bảng lưu thông tin sản phẩm trong wishlist
        $sql2 = "CREATE TABLE {$wpdb->prefix}wishlist_items (
        ID bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, 
        wishlist_id bigint(20) UNSIGNED NOT NULL, 
        product_id bigint(20) UNSIGNED NOT NULL, 
        variation_id bigint(20) UNSIGNED NOT NULL, 
        quantity int(11) NOT NULL DEFAULT 1, 
        PRIMARY KEY (ID),
        KEY wishlist_id (wishlist_id),
        KEY product_id (product_id),
        FOREIGN KEY (wishlist_id) REFERENCES {$wpdb->prefix}wishlist(ID) ON DELETE CASCADE
    ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);
    }

    public static function drop()
    {
        global $wpdb;

        // Tên bảng
        $wishlist_items_table = $wpdb->prefix . 'wishlist_items';
        $wishlist_table = $wpdb->prefix . 'wishlist';

        // Xóa bảng wishlist_items trước
        $result1 = $wpdb->query("DROP TABLE IF EXISTS {$wishlist_items_table}");
        // Sau đó xóa bảng wishlist
        $result2 = $wpdb->query("DROP TABLE IF EXISTS {$wishlist_table}");

        // Kiểm tra kết quả
        if ($result1 === false || $result2 === false) {
            // Ghi log nếu có lỗi
            error_log('Có lỗi khi xóa bảng wishlist hoặc wishlist_items.');
        }
    }
}