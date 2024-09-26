<?php

namespace Okhub\Model;

class WishlistItem
{
    public $ID;
    public $wishlist_id;
    public $product_id;
    public $quantity;
    public $date_added;

    public static function get_items_by_wishlist($wishlist_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wishlist_items';
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE wishlist_id = %d", $wishlist_id), ARRAY_A);
    }

    public static function add_item_to_wishlist($data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wishlist_items';
        $wpdb->insert($table, $data);
        return $wpdb->insert_id;
    }

    public static function item_exists($wishlist_id, $product_id, $variation_id = 0)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wishlist_items';

        if ($variation_id > 0) {
            // Nếu có variation_id, truy vấn cả product_id và variation_id
            $sql = $wpdb->prepare(
                "SELECT ID FROM $table WHERE wishlist_id = %d AND product_id = %d AND variation_id = %d",
                $wishlist_id,
                $product_id,
                $variation_id
            );
        } else {
            // Nếu không có variation_id, chỉ truy vấn theo product_id
            $sql = $wpdb->prepare(
                "SELECT ID FROM $table WHERE wishlist_id = %d AND product_id = %d",
                $wishlist_id,
                $product_id
            );
        }

        return $wpdb->get_var($sql);
    }

    public static function delete_item_by_id($wishlist_item_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wishlist_items';
        return $wpdb->delete($table, ['ID' => $wishlist_item_id]);
    }
}
