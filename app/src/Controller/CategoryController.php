<?php

namespace OKhub\Controller;

use Okhub\Service\CategoryService;
use Okhub\Utils\Validator;
use WP_REST_Request;
use WP_Error;

class CategoryController
{
    private $categoryService;
    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
        add_action('rest_api_init', array($this, 'registerRoutes'));
    }

    public function registerRoutes()
    {
        // Get a single product
        register_rest_route('api/v1', 'categories', array(
            'methods' => 'GET',
            'callback' => array($this, 'getCategories'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Handle payment gateway
     *
     * @param WP_REST_Request $request
     *
     * @return mixed
     */
    public function getCategories(WP_REST_Request $request)
    {
        return $this->categoryService->getCategories();
    }
}
