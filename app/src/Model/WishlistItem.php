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
}
