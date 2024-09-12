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

// function testApiAction () {
// 	add_menu_page (
// 		"Test Custom Woo",
// 		"Test Custom Woo",
// 		'manage_options',
// 		'testApiAction',
// 		'fnc_testApiAction',
// 		'',
// 		'100'
// 	);
// }
// add_action('admin_menu', 'testApiAction');
// function fnc_testApiAction () {
// 	$WC_Cart = new WC_Cart();
// 	$WC_Cart->add_to_cart(272, 1);
	
// 	echo "<pre>";
// 	var_dump($WC_Cart);
// 	echo "</pre>";
// }