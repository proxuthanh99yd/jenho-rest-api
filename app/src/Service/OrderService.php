<?php

namespace Okhub\Service;

use WP_Error;
use WC_Order;
use WC_Product;
use WC_Order_Item_Fee;
use WC_Coupon;
use Okhub\Service\CartService;
use Okhub\Service\ProductService;
use Okhub\Service\CouponService; // Include the CouponService
use Okhub\Utils\Shipping;
use Okhub\Utils\StockLocation;
use Okhub\Utils\Exchange;

class OrderService
{
    private static $instance = null;
    private $cartService;  // Handles cart-related operations
    private $productService;  // Handles product-related operations
    private $couponService; // Handles coupon-related operations
    private $woocommerce;  // WooCommerce instance
    private $delay_minutes = 0.1; // Email delay time in minutes

    /**
     * Constructor to initialize dependencies.
     *
     * @param CartService $cartService The service handling cart operations.
     * @param CouponService $couponService The service handling coupon operations.
     * @param ProductService $productService The service handling product operations.
     * @param int $delay_minutes The delay in minutes before sending order emails.
     */
    public function __construct(CartService $cartService, CouponService $couponService, ProductService $productService)
    {
        $this->cartService = $cartService;
        $this->couponService = $couponService; // Initialize CouponService
        $this->productService = $productService;
        $this->woocommerce = WC(); // Initialize WooCommerce instance
    }

    // Singleton instance getter
    public static function getInstance(CartService $cartService, CouponService $couponService, ProductService $productService)
    {
        if (self::$instance === null) {
            self::$instance = new self($cartService, $couponService, $productService);
        }
        return self::$instance;
    }


    /**
     * Creates a new WooCommerce order.
     *
     * @param int $customerId The ID of the customer placing the order.
     * @param string $paymentMethod The payment method to be used for the order.
     * @param array $shippingAddress The shipping address details.
     * @param array $billingAddress The billing address details.
     * @param array $items The items to be included in the order.
     * @param string|null $couponCode An optional coupon code to apply to the order.
     * @return array|WP_Error Returns formatted order data or a WP_Error on failure.
     */
    public function createOrder($customerId, $paymentMethod, $shippingAddress, $billingAddress, $items, $couponCode = null, $currency)
    {
        try {
            $order = wc_create_order(); // Create a new WooCommerce order object

            // Set customer ID and addresses
            $order->set_customer_id($customerId);
            $order->set_billing_address($billingAddress);
            $order->set_shipping_address($shippingAddress);
            $order->set_payment_method($paymentMethod);
            $total_fee = 0;
            // Loop through items to add them to the order

            foreach ($items as $item) {
                $product_id = $item['product_id'];
                $quantity = $item['quantity'];
                $variation_id = $item['variation_id'] && $item['variation_id'] !== 0 ? $item['variation_id'] : $product_id; // Check for variation ID if applicable
                $customizeFields = $item['customize'] ?? [];
                $product = wc_get_product($product_id); // Retrieve the product object

                if (!$product) {
                    // Return error if product is invalid
                    return new WP_Error('invalid_product', __('Invalid product ID: ' . $product_id), array('status' => 400));
                }

                // Check if product is valid
                $product_currency = $this->productService->getProduct($product_id, $currency);

                if (!$currency) {
                    $currency = $product_currency['currency'] ? $product_currency['currency'] : "";
                } else {
                    if (!$product_currency['currency'] || $currency != $product_currency['currency']) {
                        return new WP_Error('invalid_currency', __('Invalid currency: ' . $currency), array('status' => 400));
                    }
                }

                // Add product or variation to the order
                if ($variation_id) {
                    $productVariations = wc_get_product($variation_id);
                    // Check if the product is in stock and has sufficient stock quantity
                    if (!$productVariations->is_in_stock() || !$productVariations->has_enough_stock($quantity)) {
                        return new WP_Error('out_of_stock', __('The product "' . $product->get_name() . '" is out of stock or does not have enough quantity.'), array('status' => 400));
                    }
                    $item_id = $order->add_product($productVariations, $quantity); // Add variation

                    // Add custom fields
                    if (($variation_id === $product_id) && count($customizeFields) > 0) {
                        // Add custom fields
                        foreach ($customizeFields as $key => $value) {
                            wc_add_order_item_meta($item_id, '_custom_line_item_field_' . $key, sanitize_text_field($value), true);
                        }

                        // Add custom fields to the order
                        $fee_percent = get_field('customize_size_fee', 'option');
                        $item_fee = $quantity * $productVariations->get_price() * ($fee_percent / 100);
                        $total_fee += $item_fee;
                        wc_add_order_item_meta($item_id, '_custom_line_item_field_fee', sanitize_text_field($item_fee), true);
                    }
                } else {
                    $order->add_product(
                        $product,
                        $quantity
                    ); // Add simple product
                }
            }

            // Apply a coupon to the order if provided
            if ($couponCode) {
                $couponApplicationResult = $this->couponService->applyCouponToOrder($order, $couponCode);

                // Return error if coupon application fails
                if (is_wp_error($couponApplicationResult)) {
                    return $couponApplicationResult;
                }
            }
            if ($total_fee > 0) {
                $fee = new WC_Order_Item_Fee();
                $fee->set_name('Customize Size Fee');
                $fee->set_amount($total_fee);
                $fee->set_total($total_fee);
                $order->add_item($fee);
            }

            // Calculate order totals and save the order
            $dateString = $billingAddress['dob'] ?? $shippingAddress['dob'] ?? '';
            $date = new \DateTime($dateString);
            $formattedDate = $date->format('Y-m-d');
            $order->update_meta_data('date_of_birth', wc_clean($formattedDate));
            $order->calculate_totals();

            $order->update_meta_data('order_currency', $currency);

            $order_total = $order->get_total(); // Lấy tổng giá trị đơn hàng

            $shipping_fee =  Shipping::fee($currency, Exchange::price($currency, $order_total));
            if ($shipping_fee != 0) {
                $shipping_fee_exchange = Exchange::price_reverse(
                    $currency,
                    $shipping_fee
                );
                $fee = new \WC_Order_Item_Fee();
                $fee->set_name('Shipping Fee');
                $fee->set_amount($shipping_fee_exchange);
                $fee->set_total($shipping_fee_exchange);
                $order->add_item($fee);
                $order->calculate_totals(); // Cập nhật lại tổng đơn hàng sau khi thêm phí}
            }

            $order_id = $order->save(); // Save order and get order ID

            // Schedule an email to be sent after a delay (e.g., 1 minute)
            if ($this->delay_minutes > 0) {
                wp_schedule_single_event(time() + ($this->delay_minutes * 60), 'send_delayed_order_confirmation_email', array($order_id));
            }

            // Return formatted order data or an error if creation fails
            return $order_id ? $this->formatOrderData($order, $currency) : new WP_Error('order_creation_failed', __('Order creation failed'), array('status' => 500));
        } catch (\Exception $e) {
            // Return error if an exception occurs during order creation
            return new WP_Error('order_creation_exception', $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * Retrieves an order by its ID.
     *
     * @param int $orderId The ID of the order to retrieve.
     * @return array|WP_Error Returns formatted order data or a WP_Error if the order is not found.
     */
    public function getOrderById($orderId, $currency)
    {
        $order = wc_get_order($orderId); // Retrieve the order object by ID

        if (!$order) {
            // Return an error if the order is not found
            return new WP_Error('order_not_found', __('Order not found with ID: ' . $orderId), array('status' => 404));
        }

        // Format and return the order data
        return $this->formatOrderData($order, $currency);
    }

    /**
     * Retrieves all orders with optional filters and pagination.
     *
     * @param int $userId The ID of the user whose orders are to be retrieved.
     * @param array $args Optional arguments to filter and paginate the orders.
     * @return array Returns a list of formatted orders.
     */
    public function getAllOrders($userId, $args = [], $currency)
    {
        // Set default arguments for retrieving orders
        $defaultArgs = [
            'limit' => 10, // Default number of orders per page
            'page' => 1,   // Default page number
            'status' => 'any', // Include orders with any status
            'customer_id' => $userId, // Filter orders by user ID
        ];

        // Merge provided arguments with defaults
        $args = wp_parse_args($args, $defaultArgs);

        // Calculate the offset based on the page number and limit
        $args['offset'] = ($args['page'] - 1) * $args['limit'];

        // Query WooCommerce orders using the provided arguments
        $orders = wc_get_orders($args);

        // Format each order using the formatOrderData method
        $formattedOrders = [];
        foreach ($orders as $order) {
            $orderItem = $this->formatOrderData($order, $currency);
            if (is_array($orderItem) && count($orderItem) > 0) {
                $formattedOrders[] = $orderItem;
            }
        }

        // Return the list of formatted orders
        return $formattedOrders;
    }

    /**
     * Retrieves all orders by a customer's email with optional pagination.
     *
     * @param string $email The customer's email address.
     * @param array $args Optional arguments for pagination (limit and page).
     * @return array Returns a list of formatted orders for the specified email.
     */
    public function getAllOrdersByEmail($email, $args = [], $currency)
    {
        // Set default arguments for retrieving orders
        $defaultArgs = [
            'limit' => 10, // Default number of orders per page
            'page' => 1,   // Default page number
            'status' => 'any', // Include orders with any status
            'customer' => $email, // Filter orders by customer email
        ];

        // Merge provided arguments with defaults
        $args = wp_parse_args($args, $defaultArgs);

        // Calculate the offset based on the page number and limit
        $args['offset'] = ($args['page'] - 1) * $args['limit'];

        // Query WooCommerce orders using the provided arguments
        $orders = wc_get_orders($args);

        // Format each order using the formatOrderData method
        $formattedOrders = [];
        foreach ($orders as $order) {
            $formattedOrders[] = $this->formatOrderData($order, $currency);
        }

        // Return the list of formatted orders
        return $formattedOrders;
    }

    /**
     * Cancels an order by its ID if the provided email and phone match the order details.
     *
     * @param int $order_id
     * @param string $email
     * @param string $phone
     * @return array|WP_Error
     */
    public function cancelOrder($order_id, $email, $phone, $currency)
    {
        // Validate input parameters
        if (!$order_id || !$email || !$phone) {
            return new WP_Error('missing_params', __('Order ID, email, and phone are required'), array('status' => 400));
        }

        // Fetch the order
        $order = wc_get_order($order_id);

        // Check if the order exists
        if (!$order) {
            return new WP_Error('invalid_order', __('Order not found'), array('status' => 404));
        }

        // Get the order's billing email and phone
        $order_email = $order->get_billing_email();
        $order_phone = $order->get_billing_phone();

        // Validate email and phone number
        if ($email !== $order_email || $phone !== $order_phone) {
            return new WP_Error('invalid_auth', __('Email or phone number does not match the order'), array('status' => 401));
        }

        // Check if the order is already cancelled or completed
        if ($order->get_status() === 'cancelled' || $order->get_status() === 'completed') {
            return new WP_Error('invalid_order_status', __('Order cannot be cancelled'), array('status' => 400));
        }

        // Attempt to cancel the order
        $order->update_status('cancelled', __('Order cancelled via API', 'your-text-domain'));

        // Check if there was an error updating the order status
        if (is_wp_error($order)) {
            return $order;
        }

        // Return the updated order data
        return $this->formatOrderData($order, $currency);
    }


    /**
     * Format and return order data.
     *
     * @param WC_Order $order The order object to format.
     * @param string $currency The currency to convert prices to.
     * @return array The formatted order data.
     */
    private function formatOrderData($order, $currency)
    {
        $items = [];
        foreach ($order->get_items() as $item_id => $item) {
            if (!StockLocation::check($currency, $item->get_product_id())) {
                continue;
            }

            $customize_fee = wc_get_order_item_meta($item_id, '_custom_line_item_field_fee', true);
            if (!empty($customize_fee)) {
                $item->set_total($item->get_total() + $customize_fee);
            }
            // Calculate discount
            $line_total = $item->get_total();
            $line_subtotal = $item->get_subtotal();
            $item_discount = $line_subtotal - $line_total;

            $items[] = array(
                'product' => $this->productService->getProduct($item->get_product_id(), $currency),
                'variation' => $this->productService->getVariationById($item->get_product_id(), $item->get_variation_id(), $currency),
                'quantity' => $item->get_quantity(),
                'subtotal' => Exchange::price($currency, $item->get_subtotal()),
                'total' => Exchange::price($currency, $item->get_total()),
                'discount' => Exchange::price($currency, $item_discount), // Add item-level discount
                'customize' => $this->get_custom_fields($item_id),
            );
        }

        // Lấy mã giảm giá đã được áp dụng
        $coupons = [];
        foreach ($order->get_items('coupon') as $coupon_item_id => $coupon_item) {
            $coupon_code = $coupon_item->get_code(); // Lấy mã coupon
            $discount_amount = $coupon_item->get_discount(); // Lấy số tiền giảm giá của coupon
            $coupons[] = array(
                'code' => $coupon_code,
                'discount_amount' => Exchange::price($currency, $discount_amount),
            );
        }
        $fees = $order->get_items('fee'); // Lấy tất cả mục phí từ đơn hàng
        $shipping_total = 0;
        foreach ($fees as $fee) {
            $fee_name = $fee->get_name();
            if ($fee_name === 'Shipping Fee') {
                $fee_total = $fee->get_total();
                $shipping_total = Exchange::price($currency, $fee_total);
            }
        }

        // Get shipping details

        $order_info = [
            'customer_id' => $order->get_customer_id(),
            'user_id' => $order->get_user_id(),
            'user' => $order->get_user(),
            'customer_ip_address' => $order->get_customer_ip_address(),
            'customer_user_agent' => $order->get_customer_user_agent(),
            'created_via' => $order->get_created_via(),
            'customer_note' => $order->get_customer_note(),
            'billing' => [
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'company' => $order->get_billing_company(),
                'address_1' => $order->get_billing_address_1(),
                'address_2' => $order->get_billing_address_2(),
                'city' => $order->get_billing_city(),
                'state' => $order->get_billing_state(),
                'postcode' => $order->get_billing_postcode(),
                'country' => $order->get_billing_country(),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
                'formatted_full_name' => $order->get_formatted_billing_full_name(),
                'formatted_address' => $order->get_formatted_billing_address(),
            ],
            'shipping' => [
                'first_name' => $order->get_shipping_first_name(),
                'last_name' => $order->get_shipping_last_name(),
                'company' => $order->get_shipping_company(),
                'address_1' => $order->get_shipping_address_1(),
                'address_2' => $order->get_shipping_address_2(),
                'city' => $order->get_shipping_city(),
                'state' => $order->get_shipping_state(),
                'postcode' => $order->get_shipping_postcode(),
                'country' => $order->get_shipping_country(),
                'address_map_url' => $order->get_shipping_address_map_url(),
                'formatted_full_name' => $order->get_formatted_shipping_full_name(),
                'formatted_address' => $order->get_formatted_shipping_address(),
            ],
            'payment' => [
                'method' => $order->get_payment_method(),
                'method_title' => $order->get_payment_method_title(),
                'transaction_id' => $order->get_transaction_id(),
            ]
        ];

        if (count($items) === 0) {
            return [];
        }

        return array(
            'id' => $order->get_id(),
            'status' => $order->get_status(),
            'total' => Exchange::price($currency, $order->get_total()),
            'subtotal' => Exchange::price($currency, $order->get_subtotal()),
            'shipping_total' => $shipping_total,
            'coupons' => $coupons, // Mã giảm giá đã áp dụng
            'date_created' => $order->get_date_created()->date('Y-m-d H:i:s'),
            'items' => $items,
            'order_info' => $order_info
        );
    }

    /**
     * Send a confirmation email after the order is created.
     *
     * @param int $order_id The ID of the created order.
     */
    public function send_order_confirmation_email($order_id)
    {
        // Retrieve the order object
        $order = wc_get_order($order_id);

        if (!$order) {
            // Log error if order retrieval fails
            // error_log('Failed to retrieve order with ID: ' . $order_id);
            return;
        }

        $wc_emails = WC()->mailer()->get_emails(); // Get all WC_emails objects instances
        $customer_email = $order->get_billing_email(); // Get the customer email

        // Change the recipient of the instance
        $wc_emails['WC_Email_Customer_Invoice']->recipient = $customer_email;
        // Sending the email from this instance
        $wc_emails['WC_Email_Customer_Invoice']->trigger($order_id);
    }

    /**
     * Sends a delayed order confirmation email.
     *
     * @param int $order_id The ID of the order to send the email for.
     */
    public function send_delayed_order_confirmation_email($order_id)
    {
        // Trigger the delayed email function
        $this->send_order_confirmation_email($order_id);
    }

    private function get_custom_fields($item_id)
    {
        $custom_fields = [
            'height',
            'weight',
            'shoulder_to_shoulder',
            'bust',
            'under_bust',
            'shoulder_to_hand',
            'to_fit',
            'waist',
            'hip',
            'shoulder_to_waist',
            'shoulder_to_toe',
            'color',
            'fee',
        ];
        $data = [];
        foreach ($custom_fields as $field) {
            $meta_value = wc_get_order_item_meta($item_id, '_custom_line_item_field_' . $field, true);
            if (!empty($meta_value)) {
                $data[$field] = $meta_value;
            }
        }
        if (!empty($data)) {
            return $data;
        }
        return null;
    }
}
