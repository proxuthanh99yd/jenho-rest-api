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

    public static function get_single_wishlist_by_user($user_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wishlist';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d", $user_id), ARRAY_A);
        if (!$row) {
            return false;
        }
        return $row;
    }

    public static function get_all_wishlist_by_user($user_id)
    {
        global $wpdb;
        $user_id = get_current_user_id();

        $sql = "
            SELECT wi.*, w.user_id
            FROM {$wpdb->prefix}wishlist_items AS wi
            INNER JOIN {$wpdb->prefix}wishlist AS w
            ON wi.wishlist_id = w.ID
            WHERE w.user_id = %d
        ";

        $results = $wpdb->get_results($wpdb->prepare($sql, $user_id), ARRAY_A);
        $data = [];
        if (!empty($results)) {
            foreach ($results as $row) {
                $data[] = $row;
            }
        } else {
            return false;
        }
        return $data;
    }

    public static function create_wishlist($data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wishlist';
        $wpdb->insert($table, $data);
        return $wpdb->insert_id;
    }

    public static function delete_all_items_by_wishlist_id($wishlist_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wishlist_items';
        return $wpdb->delete($table, ['wishlist_id' => $wishlist_id]);
    }
}
