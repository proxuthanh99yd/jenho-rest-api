<?php

namespace Okhub\Service;

use Okhub\Service\ProductService;
use WP_Error;
use WP_REST_Response;
use WC;

class CartService
{
    private $cart;
    private $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
        add_action('wp_loaded', [$this, 'initializeCart']);
    }

    /**
     * Initializes the WooCommerce cart and session.
     */
    public function initializeCart($force_initialize = false)
    {
        // Include WooCommerce session and cart functions if necessary
        if (defined('WC_ABSPATH')) {
            include_once WC_ABSPATH . 'includes/class-woocommerce.php';
            include_once WC_ABSPATH . 'includes/wc-cart-functions.php';
            include_once WC_ABSPATH . 'includes/wc-notice-functions.php';
        }

        // Check if WooCommerce is active and its necessary objects are available
        if (!class_exists('WooCommerce') || !isset(WC()->session) || !isset(WC()->cart)) {
            error_log('WooCommerce is not fully initialized. Aborting cart initialization.');
            return;
        }

        // Initialize WooCommerce session if it hasn't started
        if (!WC()->session->has_session()) {
            WC()->session->init();
            error_log('WooCommerce session was not active, initializing now.');
        }

        // Initialize WooCommerce cart if not already initialized or forced
        if (!WC()->cart || $force_initialize) {
            WC()->cart = new \WC_Cart();
            WC()->cart->get_cart_from_session(); // This line should be guarded against nulls
            error_log('WooCommerce cart initialized.');
        } else {
            error_log('WooCommerce cart already initialized.');
        }

        // Double-check cart object is not null
        if (!WC()->cart) {
            error_log('Failed to initialize WooCommerce cart object.');
            return new WP_Error('cart_initialization_failed', __('Failed to initialize the cart.'), ['status' => 500]);
        }

        $this->cart = WC()->cart;
    }

    /**
     * Adds a product to the user's cart.
     */
    public function addToCart($productId, $quantity = 1, $variation_id = 0)
    {
        if (!WC()->cart && function_exists('wc_load_cart')) {
            wc_load_cart();
        }

        $userId = get_current_user_id();
        $product = wc_get_product($productId);

        if (!$product) {
            return new WP_Error('product_not_found', 'Product not found', ['status' => 404]);
        }

        if ($variation_id && !$this->inStock($product, $variation_id, $quantity)) {
            error_log('Product out of stock line 80');
            return new WP_Error('out_of_stock', 'Product out of stock', ['status' => 400]);
        }

        $cart_data = $this->findMyCart($userId) ?: ['cart' => []];

        foreach ($cart_data['cart'] as $key => $val) {
            if ($product->get_type() == 'variable' && $val['product_id'] == $productId && $val['variation_id'] == $variation_id) {

                if ($variation_id && !$this->inStock($product, $variation_id, $quantity + $cart_data['cart'][$key]['quantity'])) {
                    error_log('Product out of stock line 90');
                    return new WP_Error('out_of_stock', 'Product out of stock', ['status' => 400]);
                }

                $cart_data['cart'][$key]['quantity'] += $quantity;
                update_user_meta($userId, '_woocommerce_persistent_cart_1', $cart_data);
                return $this->formatData($cart_data['cart'][$key], $product, $variation_id)[$product->get_type()]();
                // return $this->cartFormatData($cart_data['cart'][$key], $product, $variation_id);
            } else {

                if (!$this->inStock($product, 0, $quantity + $cart_data['cart'][$key]['quantity'])) {
                    error_log('Product out of stock line 100');
                    return new WP_Error('out_of_stock', 'Product out of stock', ['status' => 400]);
                }

                $cart_data['cart'][$key]['quantity'] += $quantity;
                update_user_meta($userId, '_woocommerce_persistent_cart_1', $cart_data);
                return $this->formatData($cart_data['cart'][$key], $product)[$product->get_type()]();
            }
        }

        $cart_item_key = WC()->cart->generate_cart_id($productId, $variation_id);
        $cart_data['cart'][$cart_item_key] = [
            'key' => $cart_item_key,
            'product_id' => $product->get_id(),
            'variation_id' => $variation_id,
            'quantity' => $quantity,
            'created_at' => current_time('mysql'),
        ];

        update_user_meta($userId, '_woocommerce_persistent_cart_1', $cart_data);
        return $this->formatData($cart_data['cart'][$cart_item_key], $product, $variation_id)[$product->get_type()]();
        // return $this->cartFormatData($cart_data['cart'][$cart_item_key], $product, $variation_id);
    }


    /**
     * Updates the quantity of a specific item in the cart.
     */
    public function updateToCart($cart_item_key, $quantity, $variation_id = null)
    {
        if (!WC()->cart && function_exists('wc_load_cart')) {
            wc_load_cart();
        }

        $userId = get_current_user_id();
        $cart_data = $this->findMyCart($userId);

        // Check if cart item exists
        if (!isset($cart_data['cart'][$cart_item_key])) {
            return new WP_Error('no_item', 'No item found with the specified cart key', ['status' => 404]);
        }

        $cart_item = $cart_data['cart'][$cart_item_key];
        $product = wc_get_product($cart_item['product_id']);

        // Validate product
        if (!$product) {
            return new WP_Error('product_not_found', 'Product not found', ['status' => 404]);
        }

        // Check if the quantity is valid
        if ($quantity <= 0) {
            return new WP_Error('invalid_quantity', 'Invalid quantity', ['status' => 400]);
        }

        // Update the quantity
        $cart_item['quantity'] = $quantity;

        // Update variation data if applicable
        if ($variation_id) {
            if (!$this->inStock($product, $variation_id, $quantity)) {
                return new WP_Error('out_of_stock', 'Product variation out of stock', ['status' => 400]);
            }
            $cart_item['variation_id'] = $variation_id;
        } else {
            // Check stock for the main product if no variation
            if (!$this->inStock($product, 0, $quantity)) {
                return new WP_Error('out_of_stock', 'Product out of stock', ['status' => 400]);
            }
        }

        // Update the cart data in the user's meta
        $cart_data['cart'][$cart_item_key] = $cart_item;
        update_user_meta($userId, '_woocommerce_persistent_cart_1', $cart_data);

        // Return the updated cart item data
        // return $this->cartFormatData($cart_item, $product, $cart_item['variation_id']);
        return $this->formatData($cart_item, $product, $variation_id)[$product->get_type()]();
    }


    /**
     * Adds a customized product to the cart.
     */
    public function addToCartCustom($productId, $quantity = 1, $customize)
    {
        if (!WC()->cart && function_exists('wc_load_cart')) {
            wc_load_cart();
        }

        $userId = get_current_user_id();
        $product = wc_get_product($productId);

        if (!$product) {
            return new WP_Error('product_not_found', 'Product not found', ['status' => 404]);
        }

        $cart_data = $this->findMyCart($userId) ?: ['cart' => []];

        foreach ($cart_data['cart'] as $key => $val) {
            if ($val['product_id'] == $productId && $val['customize']['color'] == $customize['color']) {
                $cart_data['cart'][$key]['quantity'] += $quantity;
                update_user_meta($userId, '_woocommerce_persistent_cart_1', $cart_data);
                return $this->formatData($cart_data['cart'][$key], $product)[$product->get_type()]();
                // return $this->cartFormatData($cart_data['cart'][$key], $product);
            }
        }

        $cart_item_key = WC()->cart->generate_cart_id($productId);
        $cart_data['cart'][$cart_item_key] = [
            'key' => $cart_item_key,
            'product_id' => $product->get_id(),
            'variation_id' => 0,
            'quantity' => $quantity,
            'customize' => $customize,
            'created_at' => current_time('mysql')
        ];

        update_user_meta($userId, '_woocommerce_persistent_cart_1', $cart_data);

        // return $this->cartFormatData($cart_data['cart'][$cart_item_key], $product);
        return $this->formatData($cart_data['cart'][$cart_item_key], $product)[$product->get_type()]();
    }

    /**
     * Retrieves a specific cart item by its key.
     */
    public function getCartItemByKey($cartItemKey)
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('no_user_id', 'Invalid or missing user ID', ['status' => 400]);
        }

        $session_data = get_user_meta($user_id, '_woocommerce_persistent_cart_' . get_current_blog_id(), true);

        if (empty($session_data['cart']) || !isset($session_data['cart'][$cartItemKey])) {
            return new WP_Error('no_item', 'No item found with the specified cart key', ['status' => 404]);
        }

        $cart_item = $session_data['cart'][$cartItemKey];
        $product = wc_get_product($cart_item['product_id']);

        if (!$product) {
            return new WP_Error('product_not_found', 'Product not found', ['status' => 404]);
        }

        $variation_id = $cart_item['variation_id'] ?? null;

        return $this->formatData($cart_item, $product, $variation_id)[$product->get_type()]();
    }

    /**
     * Removes a product from the cart by its cart key.
     */
    public function removeFromCart($cartItemKey)
    {
        $cartData = $this->findMyCart(get_current_user_id());
        unset($cartData['cart'][$cartItemKey]);
        update_user_meta(get_current_user_id(), '_woocommerce_persistent_cart_1', $cartData);
        return ['message' => __('Product removed from cart successfully.')];
    }

    /**
     * Retrieves all cart items for the current user.
     */
    public function getCartItems()
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('no_user_id', 'Invalid or missing user ID', ['status' => 400]);
        }

        $session_data = get_user_meta($user_id, '_woocommerce_persistent_cart_' . get_current_blog_id(), true);

        if (empty($session_data['cart'])) {
            return new WP_Error('no_cart', 'No cart found for the user', ['status' => 404]);
        }

        $cart_items = array_filter(array_map(function ($cart_item) use ($session_data) {
            $product = wc_get_product($cart_item['product_id']);
            if (!$product) {
                return null;
            }

            $variation_id = $cart_item['variation_id'] ?? null;
            $formatted_item = $this->formatData($cart_item, $product, $variation_id)[$product->get_type()]();
            // $formatted_item = $this->cartFormatData($cart_item, $product, $variation_id);
            // Assuming created_at is stored in $cart_item or needs to be fetched in some way
            $formatted_item['created_at'] = $cart_item['created_at'] ?? null;

            return $formatted_item;
        }, $session_data['cart']));

        // Sort the cart items by created_at
        usort($cart_items, function ($a, $b) {
            return strtotime($a['created_at']) <=> strtotime($b['created_at']);
        });

        return array_values($cart_items);
    }


    /**
     * Clears the current user's cart.
     */
    public function clearCart()
    {
        global $wpdb;
        // Additional step to clear WooCommerce persistent cart data from the database
        $user_id = get_current_user_id();
        if ($user_id) {
            // Define the meta key for the persistent cart
            $persistent_cart_meta_key = '_woocommerce_persistent_cart_1'; // Adjust suffix based on the specific context or WP install
            $wpdb->delete(
                $wpdb->usermeta,
                [
                    'user_id' => $user_id,
                    'meta_key' => $persistent_cart_meta_key
                ]
            );
            error_log('Persistent cart data cleared for user ID: ' . $user_id);
        }

        return ['message' => __('Cart cleared successfully.')];
    }

    /**
     * Finds and retrieves the cart data for a specific user.
     */
    private function findMyCart($userId)
    {
        $cart_data = get_user_meta($userId, '_woocommerce_persistent_cart_1', true);
        return $cart_data ? maybe_unserialize($cart_data) : [];
    }

    /**
     * Retrieves the variation data for a specific product.
     */
    private function getVariation($product, $variation_id)
    {
        if (!$product->is_type('variable')) return [];

        foreach ($product->get_available_variations() as $variation) {
            if ($variation['variation_id'] === $variation_id) {
                $attributes = [];
                foreach ($variation['attributes'] as $key => $value) {
                    $term = get_term_by('slug', $value, str_replace('attribute_', '', $key), 'ARRAY_A');
                    if ($term) {
                        if ($term['taxonomy'] === 'pa_color') {
                            $term['hex_color'] = get_field('color_hex_color_codes', $term['taxonomy'] . '_' . $term['term_id']);
                        }
                        unset($term["term_group"], $term["description"], $term["parent"], $term["count"], $term["filter"]);
                        $attributes[str_replace('attribute_', '', $key)] = $term;
                    }
                }
                return [
                    'attributes' => $attributes,
                    'price' => $variation['display_price'],
                    'regular_price' => $variation['display_regular_price'],
                    'is_in_stock' => $variation['is_in_stock'],
                    'stock' => $variation['max_qty'],
                ];
            }
        }

        return [];
    }

    // /**
    //  * Formats the cart item data for response.
    //  */
    // private function cartFormatData($data, $product, $variation_id = null)
    // {
    //     unset($data['line_tax_data']);
    //     unset($data['line_subtotal']);
    //     unset($data['line_subtotal_tax']);
    //     unset($data['line_total']);
    //     unset($data['line_tax']);

    //     if ($variation_id) {
    //         $variations = $this->getVariation($product, $variation_id);
    //         return array_merge($data, $variations, array(
    //             'product_name' => $product->get_name(),
    //             'product_slug' => $product->get_slug(),
    //             'product_image' => wp_get_attachment_url($product->get_image_id()),
    //         ));
    //     } else {
    //         return array_merge($data, array(
    //             'product_name' => $product->get_name(),
    //             'product_slug' => $product->get_slug(),
    //             'product_image' => wp_get_attachment_url($product->get_image_id()),
    //             'variation' => "customize",
    //             'price' => $product->get_price(),
    //             'regular_price' => $product->get_regular_price(),
    //             'is_in_stock' => $product->is_in_stock(),
    //         ));
    //     }
    // }

    /**
     * Checks if a product or variation is in stock.
     */
    private function inStock($product, $variation_id = 0, $quantity = 1)
    {
        if ($variation_id) {
            $variation = new \WC_Product_Variation($variation_id);
            if ($variation && $variation->is_in_stock()) {
                // Kiểm tra số lượng tồn kho nếu đang quản lý tồn kho
                if ($variation->managing_stock()) {
                    return $variation->get_stock_quantity() >= $quantity;
                }
                return true; // Nếu không quản lý tồn kho, giả định là đủ hàng
            }
            return false;
        }
        // Kiểm tra sản phẩm gốc
        if ($product->is_in_stock()) {
            if ($product->managing_stock()) {
                return $product->get_stock_quantity() >= $quantity;
            }
            return true;
        }
        return false;
    }


    public function addToCartTest($productId, $quantity, $variation_id)
    {
        $product = wc_get_product($productId);
        return $this->formatData([], $product, $variation_id)[$product->get_type()]();
    }

    private function formatData($data, $product, $variation_id = null)
    {
        unset($data['line_tax_data']);
        unset($data['line_subtotal']);
        unset($data['line_subtotal_tax']);
        unset($data['line_total']);
        unset($data['line_tax']);

        return [
            'simple' => function () use ($data, $product) {
                return  $this->simpleProductFormat($data, $product);
            },
            'variable' =>
            function () use ($data, $product, $variation_id) {
                return  $this->variableProductFormat($data, $product, $variation_id);
            },
        ];
    }

    private function simpleProductFormat($data, $product)
    {

        return array_merge($data, array(
            'product' => $this->productService->getProduct($product->get_id()),
        ));
    }

    private function variableProductFormat($data, $product, $variation_id = null)
    {
        if ($variation_id) {
            return array_merge($data, array(
                'product' => $this->productService->getProduct($product->get_id()),
                'variation' => $this->productService->getVariationById($product->get_id(), $variation_id),
            ));
        } else {
            return array_merge($data, array(
                'product' => $this->productService->getProduct($product->get_id()),
            ));
        }
    }
}
