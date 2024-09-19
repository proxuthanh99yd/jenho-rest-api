<?php

namespace OKhub\Controller;

use Okhub\Service\ProductService;
use Okhub\Utils\Validator;
use WP_REST_Request;
use WP_Error;

class ProductController
{
    private $productService;

    /**
     * Constructor to initialize the ProductService and register API routes.
     *
     * @param ProductService $productService
     */
    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
        add_action('rest_api_init', array($this, 'registerRoutes'));
    }

    /**
     * Registers all the REST API routes for product operations.
     *
     * @return void
     */
    public function registerRoutes()
    {
        // Route to get a single product by ID
        register_rest_route('api/v1', 'products/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'getProduct'),
            'permission_callback' => '__return_true', // No authentication required
        ));

        // Route to get a list of products with optional filters
        register_rest_route('api/v1', 'products', array(
            'methods' => 'GET',
            'callback' => array($this, 'getProducts'),
            'permission_callback' => '__return_true', // No authentication required
            'args' => [
                'currency' => [
                    'validate_callback' => array(Validator::class, 'validate_currency'), // Validation for currency
                ],
            ]
        ));

        register_rest_route('api/v1', 'slug-products', array(
            'methods' => 'GET',
            'callback' => array($this, 'getSlugProducts'),
            'permission_callback' => '__return_true', // No authentication required
            'args' => [
                'currency' => [
                    'validate_callback' => array(Validator::class, 'validate_currency'), // Validation for currency
                ],
            ]
        ));

        // Route to get a single product by slug
        register_rest_route('api/v1', 'products/(?P<slug>[\w-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'getProductBySlug'),
            'permission_callback' => '__return_true' // No authentication required
        ));
    }

    /**
     * Retrieves a single product by its ID.
     *
     * @param WP_REST_Request $request
     * @return array|WP_Error
     */
    public function getProduct(WP_REST_Request $request)
    {
        // Retrieve the product ID from the request
        $productId = $request->get_param('id');

        // Fetch the product using the ProductService
        $product = $this->productService->getProduct($productId);

        // Check if the product fetch resulted in an error
        if (is_wp_error($product)) {
            return $product;
        }

        // Return the product details
        return $product;
    }

    /**
     * Retrieves a list of products with optional filters for size, color, and price range.
     *
     * @param WP_REST_Request $request
     * @return array|WP_Error
     */
    public function getProducts(WP_REST_Request $request)
    {
        // Set up arguments for the product query with default values
        $args = array(
            'currency' => $request->get_param('currency') ?: null, // Currency to display prices in
            'limit' => $request->get_param('limit') ?: 10,  // Number of products per page
            'page' => $request->get_param('page') ?: 1, // Current page number
            'offset' => $request->get_param('offset') ?: null, // Offset for pagination
        );
        // Optional filters from request parameters
        $sizes = $request->get_param('sizes');
        $colors = $request->get_param('colors');
        $price_range = $request->get_param('price_range');
        $category = $request->get_param('category');
        $s = $request->get_param('s');

        if ($s) {
            $args['s'] = $s;
        }

        if ($category) {
            $args['category_name'] = $category;
        }

        // If sizes filter is provided, split it into an array
        if ($sizes) {
            $args['sizes'] = explode(',', $sizes);
        }

        // If colors filter is provided, split it into an array
        if ($colors) {
            $args['colors'] = explode(',', $colors);
        }

        // If price range filter is provided, split it into an array
        if ($price_range) {
            $args['price_range'] = explode(',', $price_range);
        }
        // Fetch the products based on the arguments using ProductService
        return $this->productService->getProducts($args);
    }

    public function getSlugProducts(WP_REST_Request $request)
    {
        $slug = $request->get_param('slug');
        $args = array(
            'currency' => $request->get_param('currency') ?: null, // Currency to display prices in
            'limit' => $request->get_param('limit') ?: 10,  // Number of products per page
            'page' => $request->get_param('page') ?: 1, // Current page number
            'offset' => $request->get_param('offset') ?: null, // Offset for pagination
            'slug' => $slug
        );
        return $this->productService->getProducts($args, true);
    }

    /**
     * Retrieves a single product by its slug.
     *
     * @param WP_REST_Request $request
     * @return array|WP_Error
     */
    public function getProductBySlug(WP_REST_Request $request)
    {
        // Retrieve the product slug from the request
        $slug = $request->get_param('slug');

        // Fetch the product by slug using ProductService
        $product = $this->productService->getProductBySlug($slug);

        // Check if the product fetch resulted in an error
        if (is_wp_error($product)) {
            return $product;
        }

        // Return the product details
        return $product;
    }
}
