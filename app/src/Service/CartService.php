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
            // error_log('WooCommerce is not fully initialized. Aborting cart initialization.');
            return;
        }

        // Initialize WooCommerce session if it hasn't started
        if (!WC()->session->has_session()) {
            WC()->session->init();
            // error_log('WooCommerce session was not active, initializing now.');
        }

        // Initialize WooCommerce cart if not already initialized or forced
        if (!WC()->cart || $force_initialize) {
            WC()->cart = new \WC_Cart();
            WC()->cart->get_cart_from_session(); // This line should be guarded against nulls
            // error_log('WooCommerce cart initialized.');
        } else {
            // error_log('WooCommerce cart already initialized.');
        }

        // Double-check cart object is not null
        if (!WC()->cart) {
            // error_log('Failed to initialize WooCommerce cart object.');
            return new WP_Error('cart_initialization_failed', __('Failed to initialize the cart.'), ['status' => 500]);
        }

        $this->cart = WC()->cart;
    }

    /**
     * Adds a product to the user's cart.
     */
    public function addToCart($productId, $quantity = 1, $variation_id = 0)
    {
        // Kiểm tra xem WooCommerce giỏ hàng đã khởi tạo hay chưa
        if (!WC()->cart) {
            if (function_exists('wc_load_cart')) {
                wc_load_cart();
            } else {
                return new WP_Error('cart_not_initialized', 'Cart could not be initialized', ['status' => 500]);
            }
        }

        $userId = get_current_user_id();
        $product = wc_get_product($productId);

        // Kiểm tra xem sản phẩm có tồn tại không
        if (!$product) {
            return new WP_Error('product_not_found', 'Product not found', ['status' => 404]);
        }

        // Kiểm tra hàng tồn kho cho sản phẩm biến thể
        if ($variation_id && !$this->inStock($product, $variation_id, $quantity)) {
            error_log('Product out of stock line 80');
            return new WP_Error('out_of_stock', 'Product out of stock', ['status' => 400]);
        }

        $cart_data = $this->findMyCart($userId) ?: ['cart' => []];

        // Kiểm tra xem sản phẩm đã có trong giỏ hàng hay chưa
        foreach ($cart_data['cart'] as $key => $val) {
            error_log('add cart: ' . $product->get_type() . ' - ' . $productId . ' - ' . $variation_id);
            error_log('in cart: ' . $product->get_type() . ' - ' . $val['product_id'] . ' - ' . $val['variation_id']);

            if ($product->get_type() == 'variable') {
                if ($val['product_id'] != $productId || $val['variation_id'] != $variation_id) continue;

                // Kiểm tra tồn kho khi tăng số lượng sản phẩm
                if ($variation_id && !$this->inStock($product, $variation_id, $quantity + $cart_data['cart'][$key]['quantity'])) {
                    error_log('Product out of stock line 90');
                    return new WP_Error('out_of_stock', 'Product out of stock', ['status' => 400]);
                }

                // Tăng số lượng sản phẩm trong giỏ hàng
                $cart_data['cart'][$key]['quantity'] += $quantity;
                update_user_meta($userId, '_woocommerce_persistent_cart_1', $cart_data);
                return $this->formatData($cart_data['cart'][$key], $product, $variation_id)[$product->get_type()]();
            } else {
                if ($val['product_id'] != $productId) continue;

                // Kiểm tra tồn kho cho sản phẩm đơn giản
                if (!$this->inStock($product, 0, $quantity + $cart_data['cart'][$key]['quantity'])) {
                    error_log('Product out of stock line 100');
                    return new WP_Error('out_of_stock', 'Product out of stock', ['status' => 400]);
                }

                // Tăng số lượng sản phẩm trong giỏ hàng
                $cart_data['cart'][$key]['quantity'] += $quantity;
                update_user_meta($userId, '_woocommerce_persistent_cart_1', $cart_data);
                return $this->formatData($cart_data['cart'][$key], $product)[$product->get_type()]();
            }
        }

        // Nếu sản phẩm chưa có trong giỏ hàng, thêm mới vào giỏ
        $cart_item_key = WC()->cart->generate_cart_id($productId, $variation_id);
        $cart_data['cart'][$cart_item_key] = [
            'key' => $cart_item_key,
            'product_id' => $product->get_id(),
            'variation_id' => $variation_id,
            'quantity' => $quantity,
            'created_at' => current_time('mysql'),
        ];

        // Cập nhật giỏ hàng trong cơ sở dữ liệu
        update_user_meta($userId, '_woocommerce_persistent_cart_1', $cart_data);
        return $this->formatData($cart_data['cart'][$cart_item_key], $product, $variation_id)[$product->get_type()]();
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
            if ($val['product_id'] == $productId && array_key_exists('customize', $val) && $val['customize']['color'] == $customize['color']) {
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
            // error_log('Persistent cart data cleared for user ID: ' . $user_id);
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
