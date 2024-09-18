<?php

namespace Okhub\Controller;

use Okhub\Service\CartService;
use Okhub\Service\AuthService;
use WP_REST_Request;
use WP_Error;

class CartController
{
    private $cartService;
    private $authService;

    /**
     * Constructor to initialize the CartService and AuthService, and register API routes.
     *
     * @param CartService $cartService
     * @param AuthService $authService
     */
    public function __construct(CartService $cartService, AuthService $authService)
    {
        $this->authService = $authService;
        $this->cartService = $cartService;
        add_action('rest_api_init', array($this, 'registerRoutes'));
    }

    /**
     * Registers all the REST API routes for cart operations.
     *
     * @return void
     */
    public function registerRoutes()
    {
        // Add to Cart
        register_rest_route('api/v1', 'carts', array(
            'methods' => 'POST',
            'callback' => array($this, 'addToCart'),
            'permission_callback' => array($this, 'bearerTokenAuth')
        ));

        // Add to Cart
        register_rest_route('api/v1', 'carts/(?P<cart_item_key>[\w]+)', array(
            'methods' => 'PUT',
            'callback' => array($this, 'updateToCart'),
            'permission_callback' => array($this, 'bearerTokenAuth')
        ));

        // Add to Cart Custom Variation
        register_rest_route('api/v1', 'carts-custom', array(
            'methods' => 'POST',
            'callback' => array($this, 'addToCartCustom'),
            'permission_callback' => array($this, 'bearerTokenAuth')
        ));

        // Remove from Cart
        register_rest_route('api/v1', 'carts/(?P<cart_item_key>[\w]+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'removeFromCart'),
            'permission_callback' => array($this, 'bearerTokenAuth')
        ));

        // Get Cart Items
        register_rest_route('api/v1', 'carts', array(
            'methods' => 'GET',
            'callback' => array($this, 'getCartItems'),
            'permission_callback' => array($this, 'bearerTokenAuth')
        ));

        // Get Cart Item by Key
        register_rest_route('api/v1', 'carts/(?P<cart_item_key>[\w]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'getCartItemByKey'),
            'permission_callback' => array($this, 'bearerTokenAuth')
        ));

        // Clear Cart
        register_rest_route('api/v1', 'carts', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'clearCart'),
            'permission_callback' => array($this, 'bearerTokenAuth')
        ));

        // Clear Cart
        register_rest_route('api/v1', 'carts-multiple', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'removeFromCartMultiple'),
            'permission_callback' => array($this, 'bearerTokenAuth')
        ));
    }

    /**
     * Adds a product to the cart.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function addToCart(WP_REST_Request $request)
    {
        $productId = $request->get_param('product_id');
        $variation_id = $request->get_param('variation_id');
        $quantity = $request->get_param('quantity') || $request->get_param('quantity') <= 0 ? $request->get_param('quantity') : 1;

        if (!$variation_id && $variation_id !== 0) {
            return new WP_Error('no_variation_id', __('Variation ID is required'), array('status' => 400));
        }
        if (!$productId) {
            return new WP_Error('no_product_id', __('Product ID is required'), array('status' => 400));
        }

        return $this->cartService->addToCart($productId, $quantity, $variation_id);
    }

    /**
     * Updates a product to the cart.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function updateToCart(WP_REST_Request $request)
    {
        $variation_id = $request->get_param('variation_id');
        $quantity = $request->get_param('quantity') || $request->get_param('quantity') <= 0 ? $request->get_param('quantity') : 1;
        $cartItemKey = $request->get_param('cart_item_key');

        if (!$cartItemKey) {
            return new WP_Error('no_cart_item_key', __('Cart item key is required'), array('status' => 400));
        }

        return $this->cartService->updateToCart($cartItemKey, $quantity, $variation_id);
    }

    /**
     * Adds a product to the cart with custom variation.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function addToCartCustom(WP_REST_Request $request)
    {
        $productId = $request->get_param('product_id');
        $quantity = $request->get_param('quantity') || $request->get_param('quantity') <= 0 ? $request->get_param('quantity') : 1;
        $customize = $request->get_param('customize');

        if (!$customize) {
            return new WP_Error('no_customize', __('Customize is required'), array('status' => 400));
        }

        if (!$productId) {
            return new WP_Error('no_product_id', __('Product ID is required'), array('status' => 400));
        }

        return $this->cartService->addToCartCustom($productId, $quantity, $customize);
    }

    /**
     * Removes an item from the cart by its cart item key.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function removeFromCart(WP_REST_Request $request)
    {
        $cartItemKey = $request->get_param('cart_item_key');

        if (!$cartItemKey) {
            return new WP_Error('no_cart_item_key', __('Cart item key is required'), array('status' => 400));
        }

        return $this->cartService->removeFromCart($cartItemKey);
    }

    /**
     * Retrieves all items in the cart.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function getCartItems(WP_REST_Request $request)
    {
        return $this->cartService->getCartItems();
    }

    /**
     * Retrieves a specific cart item by its cart item key.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function getCartItemByKey(WP_REST_Request $request)
    {
        $cartItemKey = $request->get_param('cart_item_key');

        if (!$cartItemKey) {
            return new WP_Error('no_cart_item_key', __('Cart item key is required'), array('status' => 400));
        }

        return $this->cartService->getCartItemByKey($cartItemKey);
    }

    /**
     * Clears all items from the cart.
     *
     * @param WP_REST_Request $request
     * @return array
     */
    public function clearCart(WP_REST_Request $request)
    {
        return  $this->cartService->clearCart();
    }


    /**
     * Removes multiple items from the cart by their cart item keys.
     *
     * @param WP_REST_Request $request
     * @return array
     */
    public function removeFromCartMultiple(WP_REST_Request $request)
    {
        error_log(print_r($request->get_param('cart_item_keys'), true));
        return $this->cartService->removeFromCartMultiple($request->get_param('cart_item_keys'));
    }

    /**
     * Authenticates API requests using a bearer token.
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function bearerTokenAuth(WP_REST_Request $request)
    {
        return $this->authService->bearerTokenAuth($request);
    }
}
