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

        register_rest_route($this->namespace, '/' . $this->rest_base . '/items', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'add_to_wishlist'],
                'permission_callback' => array($this, 'bearerTokenAuth'),
                'args' => [
                    'product_id' => [
                        'validate_callback' => array(Validator::class, 'validate_number'),
                    ],
                    'variation_id' => [
                        'validate_callback' => array(Validator::class, 'validate_number'),
                    ],
                    'quantity' => [
                        'validate_callback' => array(Validator::class, 'validate_number'),
                    ]
                ]
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

    // Lấy danh sách sản phẩm trong wishlist
    public function get_wishlist(WP_REST_Request $request)
    {
        $userId = get_current_user_id();
        if (!$userId) {
            return new WP_Error('unauthorized', 'You are not logged in', ['status' => 401]);
        }
        return $this->wishlistService->get_wishlist_by_user_id($userId);
    }

    // // Tạo wishlist mới
    public function add_to_wishlist(WP_REST_Request $request)
    {
        $product_id = $request->get_param('product_id'); // product_id
        $variation_id = $request->get_param('variation_id'); // variation_id,
        $quantity = $request->get_param('quantity'); // quantity

        $userId = get_current_user_id();
        if (!$userId) {
            return new WP_Error('unauthorized', 'You are not logged in', ['status' => 401]);
        }

        return $this->wishlistService->add_to_wishlist($product_id, $variation_id, $quantity, $userId);
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
