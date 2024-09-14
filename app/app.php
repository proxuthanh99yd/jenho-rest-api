<?php
add_action('woocommerce_init', function () {

    // Khởi tạo các dịch vụ và controller
    $authService = new \Okhub\Service\AuthService();
    $authController = new \Okhub\Controller\AuthController($authService);


    $productService = new \Okhub\Service\ProductService();
    $productController = new \Okhub\Controller\ProductController($productService);

    $cartService = new \Okhub\Service\CartService($productService);
    $cartController = new \Okhub\Controller\CartController($cartService, $authService);

    $couponService = new \Okhub\Service\CouponService();
    $couponController = new \Okhub\Controller\CouponController($couponService, $authService);

    $orderService = new \Okhub\Service\OrderService($cartService, $couponService, $productService);
    $orderController = new \Okhub\Controller\OrderController($orderService, $authService, $cartService);

    $paymentService = new \Okhub\Service\PaymentService();
    $paymentController = new \Okhub\Controller\PaymentController($paymentService);

    $wishlistService = new \Okhub\Service\WishlistService();
    $wishlistController = new \Okhub\Controller\WishlistController($wishlistService, $authService);

    // Create a global instance of OrderService
    function okhub_order_service($arg)
    {
        return \Okhub\Service\OrderService::getInstance(new \Okhub\Service\CartService($arg['productService']), new \Okhub\Service\CouponService(), new \Okhub\Service\ProductService());
    }

    // Hook into WordPress's cron system using the global instance
    add_action('send_delayed_order_confirmation_email', [okhub_order_service(['productService' => $productService]), 'send_delayed_order_confirmation_email']);
}, 99);
