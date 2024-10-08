<?php

namespace Okhub\Service;

use Okhub\Utils\TokenHandler;
use Google\Client as GoogleClient;
use WP_Error;
use WP_REST_Request;

class AuthService
{
    private $tokenHandler;
    private $secretKey;
    private $googleClientId;
    private $googleClientSecret;
    /**
     * Constructor initializes the TokenHandler with a secret key.
     */
    public function __construct()
    {
        $this->secretKey = defined('AUTH_SECRET_KEY') ? AUTH_SECRET_KEY : "";
        $this->googleClientId = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : "";
        $this->googleClientSecret = defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : "";
        $this->tokenHandler = new TokenHandler($this->secretKey);
    }

    /**
     * Registers a new user.
     *
     * @param string $emailOrUsername Email address or username for the new user
     * @param string $password Password for the new user
     * @param string $fullName Full name of the new user
     * @return array|WP_Error Array containing user ID, user meta, and JWT tokens; or a WP_Error object upon failure
     */
    public function registerUser($emailOrUsername, $password, $fullName)
    {
        // Check if the email or username is already registered
        if (username_exists($emailOrUsername) || email_exists($emailOrUsername)) {
            return new WP_Error('user_exists', __('Email or username already exists'), array('status' => 400));
        }

        // Determine whether the input is an email or username and create the user
        if (is_email($emailOrUsername)) {
            $user_id = wp_create_user($emailOrUsername, $password, $emailOrUsername);
        } else {
            $user_id = wp_create_user($emailOrUsername, $password);
        }

        // Check for errors during user creation
        if (is_wp_error($user_id)) {
            return $user_id;
        }

        // Add user meta
        update_user_meta($user_id, 'first_name', $fullName);

        // Fetch user meta and generate JWT tokens
        $usermeta = get_user_meta($user_id);
        $token = $this->tokenHandler->generateTokens($user_id);

        return array(
            'user_id' => $user_id,
            'usermeta' => $usermeta,
            'token' => $token
        );
    }


    /**
     * Authenticates a user with the given email, full name, and password.
     *
     * @param string $email User's email address
     * @param string $password User's password
     * @return array|WP_Error Returns JWT tokens or a WP_Error on authentication failure
     */
    public function login($email, $password)
    {
        // Attempt to authenticate the user
        $user = wp_authenticate($email, $password);

        // Check if authentication failed
        if (is_wp_error($user)) {
            return new WP_Error('invalid_login', __('Invalid login information'), array('status' => 403));
        }

        // Generate and return JWT tokens
        return $this->tokenHandler->generateTokens($user->ID);
    }

    /**
     * Initiates the password reset process by sending an email to the user.
     *
     * @param string $email User's email address
     * @return array|WP_Error
     */
    public function resetPassword($email)
    {
        // Validate the email
        if (empty($email) || !is_email($email)) {
            return new WP_Error('invalid_email', __('Invalid email address'), array('status' => 400));
        }

        // Get the user by email
        $user = get_user_by('email', $email);

        // Check if user exists
        if (!$user) {
            return new WP_Error('email_not_found', __('No user found with this email address'), array('status' => 404));
        }

        // Generate a password reset key
        $reset_key = get_password_reset_key($user);
        if (is_wp_error($reset_key)) {
            return new WP_Error('reset_key_error', __('Unable to generate password reset key'), array('status' => 500));
        }

        // Generate the password reset URL
        $reset_url = site_url("wp-login.php?action=rp&key=$reset_key&login=" . rawurlencode($user->user_login), 'login');

        // Define the path to the email template
        $dir = plugin_dir_path(__FILE__) . '../../template-parts/reset-password-mail-template.php';

        // Load and render the email template, if it exists
        if (file_exists($dir)) {
            ob_start();
            extract(array('reset_url' => $reset_url));
            include($dir);
            $html = ob_get_clean();
        } else {
            $html = '';
        }

        // Send the reset password email
        wp_mail($email, __('Reset your password'), $html);

        return array(
            'message' => __('Password reset email has been sent.'),
        );
    }

    /**
     * Changes the current user's password.
     *
     * @param string $currentPassword Current password
     * @param string $newPassword New password
     * @return array|WP_Error
     */
    public function changePassword($currentPassword, $newPassword)
    {
        // Get the current logged-in user
        $user = wp_get_current_user();

        // Check if the user is logged in
        if (!$user || !$user->ID) {
            return new WP_Error('not_logged_in', __('User is not logged in or token is invalid'), array('status' => 401));
        }

        // Verify the current password
        if (!wp_check_password($currentPassword, $user->user_pass, $user->ID)) {
            return new WP_Error('incorrect_password', __('Current password is incorrect'), array('status' => 400));
        }

        // Check if the new password meets minimum length requirement
        if (strlen($newPassword) < 6) {
            return new WP_Error('weak_password', __('New password must be at least 6 characters long'), array('status' => 400));
        }

        // Update the password
        wp_set_password($newPassword, $user->ID);

        return array('message' => __('Password has been successfully changed.'));
    }

    public function uploadAvatar(WP_REST_Request $request)
    {
        $user_id = get_current_user_id(); // Lấy ID người dùng hiện tại
        if (!$user_id) {
            return new WP_Error('no_user', 'Người dùng chưa đăng nhập.', array('status' => 401));
        }

        // Kiểm tra và xử lý file upload
        if (empty($_FILES['avatar'])) {
            return new WP_Error('no_file', 'Không có file nào được tải lên.', array('status' => 400));
        }

        $file = $_FILES['avatar'];

        // Kiểm tra tính hợp lệ của file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', 'Lỗi trong quá trình upload file.', array('status' => 500));
        }

        // Kiểm tra dung lượng file (2MB = 2 * 1024 * 1024 bytes)
        $max_file_size = 2 * 1024 * 1024;
        if ($file['size'] > $max_file_size) {
            return new WP_Error('file_too_large', 'Dung lượng file không được vượt quá 2MB.', array('status' => 400));
        }

        // Kiểm tra loại file (chỉ cho phép định dạng ảnh)
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif');
        $file_type = wp_check_filetype($file['name']);
        if (!in_array($file_type['type'], $allowed_types)) {
            return new WP_Error('invalid_file_type', 'Chỉ cho phép tải lên các định dạng ảnh (jpeg, png, gif).', array('status' => 400));
        }

        // Lấy thư mục upload của WordPress
        $upload_dir = wp_upload_dir();
        $avatar_dir = $upload_dir['basedir'] . '/avatars';

        // Tạo thư mục avatars nếu chưa có
        if (!file_exists($avatar_dir)) {
            wp_mkdir_p($avatar_dir);
        }

        // Tạo đường dẫn cho file upload
        $file_name = sanitize_file_name($file['name']);
        $file_path = $avatar_dir . '/' . $file_name;

        // Xóa avatar cũ (nếu có)
        $old_avatar_id = get_user_meta($user_id, 'wp_user_avatar', true);
        if (!empty($old_avatar_id)) {
            wp_delete_attachment($old_avatar_id, true); // Xóa attachment cũ và file liên quan
        }

        // Di chuyển file vào thư mục avatars
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            return new WP_Error('upload_failed', 'Không thể di chuyển file.', array('status' => 500));
        }

        // Tạo attachment cho file mới
        $attachment = array(
            'guid'           => $upload_dir['baseurl'] . '/avatars/' . $file_name,
            'post_mime_type' => $file_type['type'],
            'post_title'     => sanitize_file_name($file_name),
            'post_content'   => '',
            'post_status'    => 'private', // Đặt post_status là private để ẩn ảnh trong thư viện Media
        );

        // Insert attachment vào thư viện media
        $attach_id = wp_insert_attachment($attachment, $file_path, 0);

        // Tạo metadata cho attachment
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
        wp_update_attachment_metadata($attach_id, $attach_data);

        // Set avatar cho người dùng (lưu ID của attachment vào user meta)
        update_user_meta($user_id, 'wp_user_avatar', $attach_id);

        return array(
            'status'  => 'success',
            'message' => 'Avatar đã được tải lên thành công.',
            'avatar_url' => wp_get_attachment_url($attach_id)
        );
    }

    /**
     * Handles the Google OAuth callback and authenticates the user.
     *
     * @param string $googleCode Authorization code from Google
     * @return array|WP_Error
     */
    public function handleGoogleCallback($googleCode)
    {
        // Initialize the Google Client
        $client = new GoogleClient();
        $client->setClientId($this->googleClientId);
        $client->setClientSecret($this->googleClientSecret);
        $client->setRedirectUri(site_url('/wp-json/api/v1/google-callback/'));

        // Attempt to fetch the access token using the provided authorization code
        if ($googleCode) {
            $token = $client->fetchAccessTokenWithAuthCode($googleCode);
            $client->setAccessToken($token);

            // Fetch user info from Google
            $oauth = new \Google_Service_Oauth2($client);
            $googleUserInfo = $oauth->userinfo->get();

            // Check if the user exists in the system
            $email = $googleUserInfo->email;
            $user = get_user_by('email', $email);

            // If the user does not exist, create a new user
            if (!$user) {
                $user_id = wp_create_user($email, wp_generate_password(), $email);
                wp_update_user(array(
                    'ID' => $user_id,
                    'display_name' => $googleUserInfo->name,
                ));
            } else {
                $user_id = $user->ID;
            }

            // Generate JWT tokens for the authenticated user
            $tokens = $this->tokenHandler->generateTokens($user_id);
            return $tokens;
        } else {
            return new WP_Error('invalid_google_login', __('Unable to authenticate with Google'), array('status' => 401));
        }
    }

    /**
     * Refreshes a JWT token using a refresh token.
     *
     * @param string $refreshToken The refresh token
     * @return array|WP_Error
     */
    public function refreshToken($refreshToken)
    {
        // Attempt to refresh the bearer token
        $newTokens = $this->tokenHandler->refreshBearerToken($refreshToken);

        // Check if the token refresh was successful
        if ($newTokens) {
            return $newTokens;
        } else {
            return new WP_Error('invalid_token', __('Refresh token is invalid or expired'), array('status' => 403));
        }
    }

    /**
     * Retrieves the current user's information.
     *
     * @return array|WP_Error
     */
    public function getUserInfo()
    {
        // Get the current logged-in user
        $user = wp_get_current_user();

        // Check if the user exists
        if (!$user) {
            return new WP_Error('user_not_found', __('User not found'), array('status' => 404));
        }

        // Return the user's information
        return array(
            'id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'roles' => $user->roles,
            'fullName' => get_user_meta($user->ID, 'first_name', true) . ' ' . get_user_meta($user->ID, 'last_name', true),
            'nationality' => get_user_meta($user->ID, 'wp_user_nationality', true),
            'day' => wp_date('d', get_user_meta($user->ID, 'wp_user_birthday', true)),
            'month' =>   wp_date('m', get_user_meta($user->ID, 'wp_user_birthday', true)),
            'year' => wp_date('Y', get_user_meta($user->ID, 'wp_user_birthday', true)),
            'gender' => get_user_meta($user->ID, 'wp_user_gender', true),
            'phone' => get_user_meta($user->ID, 'wp_user_phone', true),
            'email' => get_user_meta($user->ID, 'wp_user_email', true),
            'avatar' => get_user_meta($user->ID, 'wp_user_avatar', true)
        );
    }

    /**
     * Authenticates a user using a Bearer token from the request header.
     *
     * @param WP_REST_Request $request The REST API request object
     * @return array|null
     */
    public function bearerTokenAuth(WP_REST_Request $request)
    {
        // Get the Authorization header
        $auth_header = $request->get_header('authorization');
        if ($auth_header) {
            list($token) = sscanf($auth_header, 'Bearer %s');

            // Validate the token
            if ($token) {
                $decoded_token = $this->tokenHandler->validateBearerToken($token);

                // Check if the token is valid and not expired
                if ($decoded_token && $decoded_token['exp'] > time()) {
                    $user_id = $decoded_token['user_id']; // Extract the user ID from the token
                    wp_set_current_user($user_id); // Set the current user in WordPress
                    return $decoded_token;
                }
            }
        }
        return null; // Return null if authentication fails
    }
}
