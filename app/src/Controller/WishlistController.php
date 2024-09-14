<?php

namespace Okhub\Controller;

use WP_REST_Controller;
use WP_REST_Request;
use WP_Error;
use Okhub\Service\WishlistService;
use Okhub\Service\AuthService;

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
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_wishlist'],
                'permission_callback' => array($this, 'bearerTokenAuth')
            ],
        ]);

        register_rest_route($this->namespace, '/' . $this->rest_base . '/add', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'add_to_wishlist'],
                'permission_callback' => array($this, 'bearerTokenAuth')
            ],
        ]);

        register_rest_route($this->namespace, '/' . $this->rest_base . '/remove', [
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

        return $this->wishlistService->getWishlistItems($request, $userId);
    }

    // Tạo wishlist mới
    public function create_wishlist(WP_REST_Request $request)
    {
        $userId = get_current_user_id();
        if (!$userId) {
            return new WP_Error('unauthorized', 'You are not logged in', ['status' => 401]);
        }

        return $this->wishlistService->createWishlist($request, $userId);
    }

    // Thêm sản phẩm vào wishlist
    public function add_to_wishlist(WP_REST_Request $request)
    {
        $userId = get_current_user_id();
        if (!$userId) {
            return new WP_Error('unauthorized', 'You are not logged in', ['status' => 401]);
        }

        return $this->wishlistService->addToWishlist($request, $userId);
    }

    // Xóa sản phẩm khỏi wishlist
    public function remove_from_wishlist(WP_REST_Request $request)
    {
        $userId = get_current_user_id();
        if (!$userId) {
            return new WP_Error('unauthorized', 'You are not logged in', ['status' => 401]);
        }

        return $this->wishlistService->removeFromWishlist($request, $userId);
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