<?php

namespace Okhub\Controller;

use Okhub\Service\AuthService;
use WP_REST_Request;
use WP_REST_Response;

class AuthController
{
    private $authService;

    /**
     * Constructor to initialize the AuthService and register API routes.
     *
     * @param AuthService $authService
     */
    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
        add_action('rest_api_init', array($this, 'registerRoutes'));
    }

    /**
     * Registers all the REST API routes for authentication.
     *
     * @return void
     */
    public function registerRoutes()
    {
        register_rest_route('api/v1', 'login', array(
            'methods' => 'POST',
            'callback' => array($this, 'login'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route('api/v1', 'register', array(
            'methods'  => 'POST',
            'callback' => array($this, 'registerUser'),
            'permission_callback' => '__return_true',
            'args'     => array(
                'emailOrUsername' => array(
                    'required' => true,
                    'validate_callback' => function ($param, $request, $key) {
                        return is_string($param) && !empty($param);
                    },
                ),
                'fullName' => array(
                    'required' => true,
                    'validate_callback' => function ($param, $request, $key) {
                        return is_string($param) && !empty($param);
                    },
                ),
                'password' => array(
                    'required' => true,
                    'validate_callback' => function ($param, $request, $key) {
                        return is_string($param) && !empty($param);
                    },
                ),
            ),
        ));

        register_rest_route('api/v1', 'refresh-token', array(
            'methods' => 'POST',
            'callback' => array($this, 'refreshToken'),
            'permission_callback' => '__return_true'
        ));
        register_rest_route('api/v1', 'google-callback', array(
            'methods' => 'POST',
            'callback' => array($this, 'googleCallback'),
            'permission_callback' => '__return_true'
        ));
        register_rest_route('api/v1', 'change-password', array(
            'methods' => 'POST',
            'callback' => array($this, 'changePassword'),
            'permission_callback' => '__return_true'
        ));
        register_rest_route('api/v1', 'reset-password', array(
            'methods' => 'POST',
            'callback' => array($this, 'resetPassword'),
            'permission_callback' => '__return_true'
        ));
        register_rest_route('api/v1', 'user-info', array(
            'methods' => 'GET',
            'callback' => array($this, 'getUserInfo'),
            'permission_callback' => array($this, 'bearerTokenAuth')
        ));
    }

    /**
     * Handles user login via the API.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function login(WP_REST_Request $request)
    {
        $email = $request->get_param('email');
        $password = $request->get_param('password');

        return $this->authService->login($email, $fullName, $password);
    }

    /**
     * Registers a new user via the API.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function registerUser(WP_REST_Request $request)
    {
        $emailOrUsername = $request->get_param('emailOrUsername');
        $fullName = $request->get_param('fullName');
        $password = $request->get_param('password');

        $result = $this->authService->registerUser($emailOrUsername, $password, $fullName);

        if (is_wp_error($result)) {
            return new WP_REST_Response($result, 400);
        }

        return new WP_REST_Response($result, 200);
    }

    /**
     * Handles password reset requests via the API.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function resetPassword(WP_REST_Request $request)
    {
        $email = $request->get_param('email');
        return $this->authService->resetPassword($email);
    }

    /**
     * Changes the user's password via the API.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function changePassword(WP_REST_Request $request)
    {
        $currentPassword = $request->get_param('currentPassword');
        $newPassword = $request->get_param('newPassword');
        return $this->authService->changePassword($currentPassword, $newPassword);
    }

    /**
     * Refreshes the authentication token via the API.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function refreshToken(WP_REST_Request $request)
    {
        $body = $request->get_json_params();
        if (isset($body['refreshToken'])) {
            $refreshToken = $body['refreshToken'];
        }
        return $this->authService->refreshToken($refreshToken);
    }

    /**
     * Handles Google OAuth callback for user login.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function googleCallback(WP_REST_Request $request)
    {
        $googleCode = $request->get_param('googleCode');
        return $this->authService->handleGoogleCallback($googleCode);
    }

    /**
     * Retrieves the current authenticated user's information.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function getUserInfo()
    {
        return $this->authService->getUserInfo();
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
