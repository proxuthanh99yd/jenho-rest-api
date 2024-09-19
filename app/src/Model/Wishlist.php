<?php

namespace Okhub\Model;

class Wishlist
{
    public $ID;
    public $user_id;
    public $wishlist_token;
    public $wishlist_name;
    public $wishlist_description;
    public $wishlist_visibility;
    public $date_created;
    public $date_modified;

    public static function get_all_wishlists_by_user($user_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wishlist';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d", $user_id), ARRAY_A);
        if (!$row) {
            return false;
        }
        return $row;
    }

    public static function create_wishlist($data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wishlist';
        $wpdb->insert($table, $data);
        return $wpdb->insert_id;
    }
}
