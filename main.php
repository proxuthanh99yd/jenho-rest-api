<?php


/**
 * Plugin Name: Custom API woocommerce
 * Version: 1.0.0
 * Author: Mr.lee + ChatGPT
 * Author URI: https://www.facebook.com/kokorolee/
 */

// Include Composer's autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}
function mytheme_add_woocommerce_support()
{
    add_theme_support('woocommerce');
}
add_action('after_setup_theme', 'mytheme_add_woocommerce_support');
// Load the app
require_once plugin_dir_path(__FILE__) . '/app/app.php';
require_once plugin_dir_path(__FILE__) . '/app/src/Database/create_wishlist_tables.php';

function when_plugin_activate()
{
    // Gọi hàm để tạo bảng wishlist
    \Okhub\Database\CreateWishlistTables::create();
}

// Đăng ký hàm kích hoạt cho plugin
register_activation_hook(__FILE__, 'when_plugin_activate');

function when_plugin_deactivate()
{
    // Gọi hàm xoá bảng wishlist
    \Okhub\Database\CreateWishlistTables::drop();
}
register_deactivation_hook(__FILE__, 'when_plugin_deactivate');
