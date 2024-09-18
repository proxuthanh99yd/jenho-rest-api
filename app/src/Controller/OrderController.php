<?php

namespace Okhub\Controller;

use Okhub\Service\OrderService;
use Okhub\Service\CartService;
use Okhub\Service\AuthService;
use Okhub\Utils\Validator;

use WP_REST_Request;
use WP_Error;
use WC_Customer;

class OrderController
{
    private $orderService;
    private $authService;
    private $cartService;

    /**
     * Constructor to initialize the OrderService, AuthService, and CartService, and register API routes.
     *
     * @param OrderService $orderService
     * @param AuthService $authService
     * @param CartService $cartService
     */
    public function __construct(OrderService $orderService, AuthService $authService, CartService $cartService)
    {
        $this->orderService = $orderService;
        $this->authService = $authService;
        $this->cartService = $cartService;
        add_action('rest_api_init', array($this, 'registerRoutes'));
    }

    /**
     * Registers all the REST API routes for order operations.
     *
     * @return void
     */
    public function registerRoutes()
    {
        // Create Order
        register_rest_route('api/v1', 'orders', array(
            'methods' => 'POST',
            'callback' => array($this, 'createOrder'),
            'permission_callback' => '__return_true',
        ));

        // Get Order by ID
        register_rest_route('api/v1', 'orders/(?P<order_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'getOrderById'),
            'permission_callback' => '__return_true',
        ));

        // Get All Orders
        register_rest_route('api/v1', 'orders', array(
            'methods' => 'GET',
            'callback' => array($this, 'getAllOrders'),
            'permission_callback' => array($this, 'bearerTokenAuth')
        ));

        // Get Order by phone
        register_rest_route('api/v1', 'orders/(?P<email>[^\/]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'getAllOrdersByEmail'),
            'permission_callback' => '__return_true',
        ));

        // Cancel Order with email and phone authentication
        register_rest_route('api/v1', 'orders/(?P<order_id>\d+)/cancel', array(
            'methods' => 'POST',
            'callback' => array($this, 'cancelOrder'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Creates a new order based on the cart items and customer details.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function createOrder(WP_REST_Request $request)
    {
        try {
            // Authenticate request
            $this->bearerTokenAuth($request);

            // Retrieve current customer ID
            $customerId = get_current_user_id();

            // Retrieve required parameters from request
            $paymentMethod = $request->get_param('payment_method');
            $billingAddress = $request->get_param('billing_address');
            $shippingAddress = $request->get_param('shipping_address');
            $items = $request->get_param('items');
            $cartItemKeys = $request->get_param('cart_item_keys');
            $couponCode = $request->get_param('coupon_code');
            $send_news_offers = $request->get_param('send_news_offers');
            $email = $billingAddress['email'] ?? $shippingAddress['email'];
            if ($send_news_offers && $email) {
                $this->send_news_offers($email);
            }

            // Validate customer ID and cart item keys
            if ($customerId && $cartItemKeys) {
                $customer = new WC_Customer($customerId);

                // Retrieve billing address components from customer profile if not provided
                if (!$billingAddress) {
                    $billingAddress = array(
                        'first_name' => $customer->get_billing_first_name(),
                        'last_name'  => $customer->get_billing_last_name(),
                        'company'    => $customer->get_billing_company(),
                        'address_1'  => $customer->get_billing_address_1(),
                        'address_2'  => $customer->get_billing_address_2(),
                        'city'       => $customer->get_billing_city(),
                        'state'      => $customer->get_billing_state(),
                        'postcode'   => $customer->get_billing_postcode(),
                        'country'    => $customer->get_billing_country(),
                        'phone'      => $customer->get_billing_phone(),
                        'email'      => $customer->get_billing_email(),
                    );
                }

                // Retrieve shipping address components from customer profile if not provided
                if (!$shippingAddress) {
                    $shippingAddress = array(
                        'first_name' => $customer->get_shipping_first_name(),
                        'last_name'  => $customer->get_shipping_last_name(),
                        'company'    => $customer->get_shipping_company(),
                        'address_1'  => $customer->get_shipping_address_1(),
                        'address_2'  => $customer->get_shipping_address_2(),
                        'city'       => $customer->get_shipping_city(),
                        'state'      => $customer->get_shipping_state(),
                        'postcode'   => $customer->get_shipping_postcode(),
                        'country'    => $customer->get_shipping_country(),
                        'phone'      => $customer->get_billing_phone(),
                        'email'      => $customer->get_billing_email(),
                    );
                }

                // Build the items array from cart items
                $items = [];
                foreach ($cartItemKeys as $item) {
                    $cart = $this->cartService->getCartItemByKey($item);
                    if (is_array($cart)) {
                        $items[] = [
                            "product_id" => $cart['product_id'],
                            "quantity" => $cart['quantity'],
                            "variation_id" => $cart['variation_id'],
                            "customize" => $cart['customize'],
                        ];
                    } else {
                        return new WP_Error('invalid_item', __('Invalid item ID: ' . $item), array('status' => 400));
                    }
                }
            }
            // Validate required parameters
            if ($cartItemKeys && (!$shippingAddress || !$billingAddress || !$items)) {
                return new WP_Error('rest_forbidden', __('Sorry, you are not allowed to do that.'), array('status' => 401));
            }
            if (!$paymentMethod || !$shippingAddress || !$billingAddress || !$items) {
                return new WP_Error('missing_params', __('Required parameters are missing'), array('status' => 400));
            }

            // Create the order
            $order = $this->orderService->createOrder($customerId, $paymentMethod, $shippingAddress, $billingAddress, $items, $couponCode);

            // Check for errors in order creation
            if (is_wp_error($order)) {
                return $order;
            }

            // If order is pending and customer ID and cart items are valid, clear cart
            if ($order["status"] === "pending" && $customerId && $cartItemKeys) {
                foreach ($cartItemKeys as $item) {
                    $this->cartService->removeFromCart($item);
                }
            }

            return rest_ensure_response($order);
        } catch (\Exception $e) {
            return new WP_Error('error', $e->getMessage(), array('status' => 400));
        }
    }

    /**
     * Retrieves all orders with optional limit and offset parameters.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function getAllOrders(WP_REST_Request $request)
    {
        $limit = $request->get_param('limit') ?: 10;
        $page = $request->get_param('page') ?: 1;
        $userId = get_current_user_id();
        $orders = $this->orderService->getAllOrders($userId, ['limit' => $limit, 'page' => $page]);

        if (is_wp_error($orders)) {
            return $orders;
        }

        return rest_ensure_response($orders);
    }

    /**
     * Retrieves all orders with optional limit and offset parameters.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function getAllOrdersByEmail(WP_REST_Request $request)
    {
        $limit = $request->get_param('limit') ?: 10;
        $page = $request->get_param('page') ?: 1;
        $email = $request->get_param('email');
        $orders = $this->orderService->getAllOrdersByEmail($email, ['limit' => $limit, 'page' => $page]);

        if (is_wp_error($orders)) {
            return $orders;
        }

        return rest_ensure_response($orders);
    }

    /**
     * Retrieves a specific order by its ID.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function getOrderById(WP_REST_Request $request)
    {
        $order_id = $request->get_param('order_id');

        if (!$order_id) {
            return new WP_Error('no_order_id', __('Order ID is required'), array('status' => 400));
        }

        $order = $this->orderService->getOrderById($order_id);

        if (is_wp_error($order)) {
            return $order;
        }

        return rest_ensure_response($order);
    }

    /**
     * Cancels an order if the provided email and phone match the order details.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function cancelOrder(WP_REST_Request $request)
    {
        $order_id = $request->get_param('order_id');
        $email = $request->get_param('email');
        $phone = $request->get_param('phone');

        // Call the cancelOrder method in OrderService with email and phone validation
        $result = $this->orderService->cancelOrder($order_id, $email, $phone);

        // Check for errors
        if (is_wp_error($result)) {
            return $result;
        }

        // Return the response
        return rest_ensure_response($result);
    }

    private function send_news_offers($email)
    {
        // Kiểm tra xem Contact Form 7 có tồn tại hay không
        if (!class_exists('WPCF7_ContactForm')) {
            error_log('WPCF7_ContactForm: 500');
            return false;
        }

        // Lấy đối tượng form Contact Form 7 với ID 11
        $contact_form = \WPCF7_ContactForm::get_instance(11);

        if (!$contact_form) {
            error_log('Invalid form ID or form not found.');
            return false;
        }

        // Giả lập dữ liệu form mà Contact Form 7 sẽ xử lý
        $submission_data = array(
            'email' => $email // Giả định trường trong form là 'your-email'
        );

        // Giả lập $_POST dữ liệu form để truyền vào submission
        $_POST = array_merge($_POST, $submission_data);

        // Gửi form và kiểm tra kết quả
        $result = $contact_form->submit();

        if ($result) {
            // error_log('Sending email success: ' . json_encode($result));
            return true;
        } else {
            // error_log('Sending email failed: ' . json_encode($result));
            return false;
        }
    }



    private function news_offers_message() {}

    private function save_customer_info() {}



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
