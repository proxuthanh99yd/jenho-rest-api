<?php

namespace Okhub\Controller;

use WP_REST_Controller;
use WP_REST_Request;
use WP_Error;
use Okhub\Service\WishlistService;
use Okhub\Service\AuthService;
use Okhub\Utils\Validator;

class WishlistController extends WP_REST_Controller
{
    protected $namespace = 'api/v1';
    protected $rest_base = 'wishlist';
    protected $wishlistService;
    private $authService;

    public function __construct(WishlistService $wishlistService, AuthService $authService)
    {
        $this->wishlistService = $wishlistService;
        $this->authService = $authService;
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    // Đăng ký các route
    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_wishlist'],
                'permission_callback' => array($this, 'bearerTokenAuth')
            ],
        ]);

        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'get_wishlist'],
                'permission_callback' => array($this, 'bearerTokenAuth')
            ],
        ]);

        register_rest_route($this->namespace, '/' . $this->rest_base . '/items', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'add_to_wishlist'],
                'permission_callback' => array($this, 'bearerTokenAuth'),
                'args' => $this->middleware()
            ],

        ]);
        register_rest_route($this->namespace, '/' . $this->rest_base . '/items/(?P<item_id>\d+)', [
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'remove_from_wishlist'],
                'permission_callback' => array($this, 'bearerTokenAuth')
            ],
        ]);
    }

    // Middleware
    public function middleware()
    {
        $args = [];
        $args['product_id'] = [
            'type'              => 'number',
            'required'          => true,
            'sanitize_callback' => [Validator::class, 'validate_number'],
        ];
        $args['variation_id'] = [
            'type'              => 'number',
            'required'          => true,
            'sanitize_callback' => [Validator::class, 'validate_number'],
        ];
        $args['quantity'] = [
            'type'              => 'number',
            'required'          => true,
            'sanitize_callback' => [Validator::class, 'validate_number'],
        ];
        return $args;
    }

    // Lấy danh sách sản phẩm trong wishlist
    public function get_wishlist(WP_REST_Request $request)
    {
        $userId = get_current_user_id();
        if (!$userId) {
            return new WP_Error('unauthorized', 'You are not logged in', ['status' => 401]);
        }
        return $this->wishlistService->get_user_wishlist($userId);
    }

    // // Tạo wishlist mới
    public function add_to_wishlist(WP_REST_Request $request)
    {
        $product_id = $request->get_param('product_id');
        $variation_id = $request->get_param('variation_id');
        $quantity = $request->get_param('quantity');

        if (!$product_id || !($variation_id >= 0)  || !$quantity) {
            return new WP_Error('missing_params', 'Missing required parameters', ['status' => 400]);
        }

        $userId = get_current_user_id();
        if (!$userId) {
            return new WP_Error('unauthorized', 'You are not logged in', ['status' => 401]);
        }

        return $this->wishlistService->add_to_wishlist($product_id, $variation_id, $quantity, $userId);
    }

    // Xóa wishlist
    public function remove_from_wishlist(WP_REST_Request $request)
    {
        $item_id = $request->get_param('item_id');
        if (!$item_id) {
            return new WP_Error('missing_params', 'Missing required parameters', ['status' => 400]);
        }

        $userId = get_current_user_id();
        if (!$userId) {
            return new WP_Error('unauthorized', 'You are not logged in', ['status' => 401]);
        }

        return $this->wishlistService->remove_from_wishlist($item_id, $userId);
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
