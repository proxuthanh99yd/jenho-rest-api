<?php

namespace Okhub\Service;

use ArrayAccess;
use WC_Product;
use WP_Error;

class ProductService
{

    private $currency = [
        'SGD' => 'jenho-malaysia',
        'USD' => 'jenho-malaysia',
        'MYR' => 'jenho-malaysia',
        'VND' => 'jenho-viet-nam',
    ];

    private $currency_return = [
        'MYR' => 'jenho-malaysia',
        'VND' => 'jenho-viet-nam',
    ];

    private $currency_return_reverse = [
        'USD' => 'exchange_to_usd',
        'SGD' => 'exchange_to_singapore',
    ];

    /**
     * Retrieves a list of products based on given arguments.
     *
     * @param array $args The arguments to filter products, such as 'limit', 'page', 'sizes', 'colors', and 'price_range'.
     * @return array Returns an array containing product data, pagination details, and total pages.
     */
    public function getProducts($args = [], $slug = false)
    {
        // Set default query parameters for fetching products
        $defaults = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => isset($args['limit']) ? intval($args['limit']) : 10, // Number of products per page
            'paged' => isset($args['page']) ? intval($args['page']) : 1, // Current page number
            'tax_query' => array(), // Taxonomy query array
            'orderby' => 'meta_value_num', // Order by a meta value (numeric)
            'order' => 'ASC', // Order direction (ascending)
        );

        if (isset($args['s'])) {
            $defaults['s'] = $args['s'];
        }

        // Add pagination offset if specified and valid
        if (isset($args['offset'])) {
            unset($defaults['paged']);
            $defaults['offset'] = $args['offset'];
        }

        // Handling size and color filters for products using taxonomies
        if (!empty($args['sizes']) || !empty($args['colors']) || !empty($args['currency']) || !empty($args['category_name'])) {
            $taxQuery = [];

            if (!empty($args['sizes'])) {
                $taxQuery[] = [
                    'taxonomy' => 'pa_size', // Product attribute for size
                    'field' => 'slug', // Field to match (slug in this case)
                    'terms' => $args['sizes'], // Terms to filter
                ];
            }

            if (!empty($args['colors'])) {
                $taxQuery[] = [
                    'taxonomy' => 'pa_colours', // Product attribute for color
                    'field' => 'slug',
                    'terms' => $args['colors'],
                ];
            }

            // Add currency filter if specified
            if (!empty($args['currency']) && array_key_exists($args['currency'], $this->currency)) {
                $taxQuery[] = [
                    'taxonomy' => 'product_cat',
                    'field' => 'slug',
                    'terms' => [$this->currency[$args['currency']]],
                ];
            }

            // Add category filter if specified
            if (!empty($args['category_name'])) {
                $taxQuery[] = [
                    'taxonomy' => 'product_cat',
                    'field' => 'slug',
                    'terms' => explode(',', $args['category_name']),
                ];
            }

            // Combine queries with 'AND' relation if multiple taxonomies are applied
            if (count($taxQuery) > 1) {
                array_unshift($taxQuery, ['relation' => 'AND']);
            }
            $defaults['tax_query'] = $taxQuery;
        }

        // Add price range filter if specified
        $defaults = $this->handle_price_range_query_var($defaults, $args);

        $products = [
            'data' => [], // Initialize data array to store products
        ];

        // Execute the query with WP_Query
        $query = new \WP_Query($defaults);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product = wc_get_product(get_the_ID());

                if ($slug) {
                    $products['data'][] = $product->get_slug();
                    continue;
                }

                $products['data'][] = [
                    'id' => $product->get_id(),
                    'name' => $product->get_name(),
                    'slug' => $product->get_slug(),
                    'price' => $this->getPrice($product->get_id(), $args['currency']),
                    'currency' => $args['currency'],
                    'regular_price' => $this->getRegularPrice($product->get_id(), $args['currency']),
                    'image' => wp_get_attachment_url($product->get_image_id()),
                    'video' => $this->getVideo($product->get_id()),
                    'variations' => $this->getVariations($product, $args['currency']),
                ];
            }
        }

        // Reset post data after query execution
        wp_reset_postdata();

        // Set pagination details
        $products['page'] = isset($args['offset']) ? null : intval($query->query_vars['paged']);
        $products['offset'] = isset($args['offset']) ? intval($args['offset']) : null;
        $products['totalPages'] = intval($query->max_num_pages);
        $products['limit'] = intval($query->query_vars['posts_per_page']);
        $products['currency'] = $args['currency'] ?? null;

        return $products;
    }


    /**
     * Adds a price range filter to the query if specified.
     *
     * @param array $query The existing query parameters.
     * @param array $query_vars The input arguments, which may include 'price_range'.
     * @return array The modified query parameters with a price range filter if applicable.
     */
    function handle_price_range_query_var($query, $query_vars)
    {
        // Check if price range is specified and correctly formatted
        if (!empty($query_vars['price_range'])) {
            $price_range = $query_vars['price_range'];
            if (is_array($price_range) && count($price_range) == 2) {
                $query['meta_query'][] = array(
                    'key' => '_price', // Meta key for product price
                    'value' => array(reset($price_range), end($price_range)), // Price range values
                    'compare' => 'BETWEEN', // Compare prices within the specified range
                    'type' => 'NUMERIC' // Treat values as numeric
                );
            }
        }
        return $query;
    }

    /**
     * Retrieves a single product by its ID.
     *
     * @param int $productId The ID of the product to retrieve.
     * @return array|WP_Error Returns formatted product data or an error if not found.
     */
    public function getProduct($productId, $currency)
    {
        $product = wc_get_product($productId);
        if (!$product) {
            return false;
        }
        return $this->formatSingleProduct($product, $currency);
    }

    /**
     * Retrieves a single product by its slug or SKU.
     *
     * @param string $slug The slug or SKU of the product.
     * @return array|WP_Error Returns formatted product data or an error if not found.
     */
    public function getProductBySlug($slug, $currency)
    {
        // Fetch product ID by SKU first
        $product_id = wc_get_product_id_by_sku($slug);
        if (!$product_id) {
            // If not found by SKU, try fetching by slug
            $product = get_page_by_path($slug, OBJECT, 'product');
            if ($product) {
                $product_id = $product->ID;
            }
        }

        if (!$product_id) {
            return new WP_Error('product_not_found', __('Product not found'), array('status' => 404));
        }

        $product = wc_get_product($product_id);

        if (!$product) {
            return new WP_Error('product_not_found', __('Product not found'), array('status' => 404));
        }

        return $this->formatSingleProduct($product, $currency);
    }

    /**
     * Formats a single product object into a detailed array.
     *
     * @param WC_Product $product The product object to format.
     * @return array The formatted product data.
     */
    private function formatSingleProduct(WC_Product $product, $currency)
    {
        $response = array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'slug' => $product->get_slug(),
            'price' => $this->getPrice($product->get_id(), $currency),
            'currency' => $currency,
            'regular_price' =>  $this->getRegularPrice($product->get_id(), $currency),
            'descriptions' => get_field('product_details', $product->get_id()),
            'sku' => $product->get_sku(),
            'stock' => $product->get_stock_quantity(),
            'stock_status' => $product->get_stock_status(),
            'image' => wp_get_attachment_url($product->get_image_id()),
            'video' => $this->getVideo($product->get_id()),
            'categories' => wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names')),
            'variations' => $this->getVariations($product, $currency),
            'customize_fee' => get_field('customize_size_fee', 'option')
        );

        return $response;
    }

    /**
     * Retrieves video data related to a product based on custom meta fields.
     *
     * @param int $productId The ID of the product.
     * @return array The formatted video data.
     */
    private function getVideo($productId)
    {
        // Fetch various meta fields related to product videos
        $afpv_enable_featured_video = get_post_meta(intval($productId), 'afpv_enable_featured_video', true);
        $afpv_enable_featured_video_shop_page = get_post_meta(intval($productId), 'afpv_enable_featured_video_shop_page', true);
        $afpv_enable_featured_video_product_page = get_post_meta(intval($productId), 'afpv_enable_featured_video_product_page', true);
        $afpv_enable_featured_image_as_first_img = get_post_meta(intval($productId), 'afpv_enable_featured_image_as_first_img', true);
        $afpv_featured_video_type = get_post_meta(intval($productId), 'afpv_featured_video_type', true);
        $afpv_yt_featured_video_id = get_post_meta(intval($productId), 'afpv_yt_featured_video_id', true);
        $afpv_fb_featured_video_id = get_post_meta(intval($productId), 'afpv_fb_featured_video_id', true);
        $afpv_dm_featured_video_id = get_post_meta(intval($productId), 'afpv_dm_featured_video_id', true);
        $afpv_vm_featured_video_id = get_post_meta(intval($productId), 'afpv_vm_featured_video_id', true);
        $afpv_mc_featured_video_id = get_post_meta(intval($productId), 'afpv_mc_featured_video_id', true);
        $afpv_cus_featured_video_id = get_post_meta(intval($productId), 'afpv_cus_featured_video_id', true);
        $afpv_video_thumb = get_post_meta(intval($productId), 'afpv_video_thumb', true);

        // Format and return the video data
        $formattedVideo = [
            'is_featured_video' => $afpv_enable_featured_video,
            'is_featured_video_product_page' => $afpv_enable_featured_video_product_page,
            'is_featured_video_shop_page' => $afpv_enable_featured_video_shop_page,
            'video_type' => $afpv_featured_video_type,
            'youtube' => $afpv_yt_featured_video_id,
            'facebook' => $afpv_fb_featured_video_id,
            'dailymotion' => $afpv_dm_featured_video_id,
            'vimeo' => $afpv_vm_featured_video_id,
            'metacafe' => $afpv_mc_featured_video_id,
            'custom' => $afpv_cus_featured_video_id,
            'videoThumb' => $afpv_video_thumb
        ];
        return $formattedVideo;
    }

    /**
     * Retrieves variations of a variable product and formats them into an array.
     *
     * @param WC_Product $product The variable product object.
     * @return array An array of formatted product variations.
     */
    private function getVariations($product, $currency)
    {
        // Check if the product is of type 'variable'
        if (!$product->is_type('variable')) return [];

        $formattedVariations = [];
        $variations = $product->get_available_variations();
        // Loop through each variation and format its data
        foreach ($variations as $variation) {
            // Add the formatted variation to the list
            $formattedVariations[] = $this->formatVariation($variation, $currency);
        }

        return $formattedVariations;
    }

    /**
     * Retrieves additional images for a product variation based on meta data.
     *
     * @param int $variation_id The ID of the variation.
     * @return array An array of URLs for additional images.
     */
    private function getVariationImages($variation_id)
    {
        $formattedVariationImage = [];
        // Fetch additional variation images from post meta
        $strMediaIds = get_post_meta($variation_id, '_wc_additional_variation_images', true);
        $arrMediaIds = explode(',', $strMediaIds);

        // Loop through each image ID and get the attachment URL
        foreach ($arrMediaIds as $mediaId) {
            $image = wp_get_attachment_url($mediaId);
            if ($image) $formattedVariationImage[] = $image;
        }
        return $formattedVariationImage;
    }

    private function getPrice($product_id, $currency)
    {
        $product = wc_get_product($product_id);
        if ($product->is_type('variable')) {
            $variations = $product->get_available_variations();

            // Loop through each variation and format its data
            foreach ($variations as $variation) {
                if ($variation['display_price']) {
                    return $this->exchangePrice($currency, ($variation['display_price']));
                }
                // return $variation;
            }
        }
        return $product->get_price();
    }

    private function getRegularPrice($product_id, $currency)
    {
        $product = wc_get_product($product_id);
        if ($product->is_type('variable')) {
            $variations = $product->get_available_variations();
            // Loop through each variation and format its data
            foreach ($variations as $variation) {
                if ($variation['display_regular_price']) {
                    return $this->exchangePrice($currency, $variation['display_regular_price']);
                }
                // return $variation;
            }
        }
        return $this->exchangePrice($currency, $product->get_regular_price());
    }

    /**
     * Retrieves a single product variation by its ID.
     *
     * @param int $product The ID of the product that owns the variation.
     * @param int $variation_id The ID of the variation to retrieve.
     * @param string $currency The currency to display prices in.
     * @return array|null The formatted variation data or null if not found.
     */


    public function getVariationById(int $product, int $variation_id, $currency)
    {
        if (is_int($product)) $product = wc_get_product($product);

        // Check if the product is of type 'variable'
        if (!$product->is_type('variable')) return null;
        $variations = $product->get_available_variations();
        // Loop through each variation and format its data
        foreach ($variations as $variation) {
            if ($variation['variation_id'] == $variation_id) {
                return $this->formatVariation($variation, $currency);
            }
        }
        return null;
    }

    private function formatVariation($variation, $currency)
    {
        $attributes = [];
        foreach ($variation['attributes'] as $key => $value) {
            $term = get_term_by('slug', $value, str_replace('attribute_', '', $key), 'ARRAY_A');
            if ($term) {
                if ($term['taxonomy'] === 'pa_colours') {
                    $term['hex_color'] = get_field('color_hex_color_codes', $term['taxonomy'] . '_' . $term['term_id']);
                    $term['taxonomy'] = 'pa_color';
                }
                unset($term["term_group"], $term["description"], $term["parent"], $term["count"], $term["filter"]);
                $attributes[str_replace('attribute_', '', $key)] = $term;
            }
        }
        // Format the variation data
        return [
            'id' => $variation['variation_id'],
            'name' => $variation['display_regular_price'],
            'price' => $this->exchangePrice($currency, $variation['display_price']),
            'regular_price' => $this->exchangePrice(
                $currency,
                $variation['display_regular_price']
            ),
            'stock' => $variation['max_qty'],
            'in_stock' => $variation['is_in_stock'],
            'attributes' => array_values($attributes),
            'image' => [
                'title' => $variation['image']['title'],
                'caption' => $variation['image']['caption'],
                'alt' => $variation['image']['alt'],
                'src' => $variation['image']['url'],
            ],
            'additional_images' => $this->getVariationImages($variation['variation_id']),
        ];
    }

    public function getCurrencyByProductId($product_id)
    {
        // Ensure the product ID is valid
        if (empty($product_id) || !is_numeric($product_id)) {
            return false; // Return false if the product ID is invalid
        }

        // Define the taxonomy for product categories
        $taxonomy = 'product_cat';

        // Retrieve the product categories (terms) associated with the product
        $terms = wp_get_post_terms($product_id, $taxonomy);

        // Check if terms are found and there are no errors
        if (!is_wp_error($terms) && !empty($terms)) {
            foreach ($terms as $term) {
                $key = array_search($term->slug, $this->currency_return);
                if ($key) {
                    return $key;
                }
            }
        }

        // Return false if no categories are found or an error occurs
        return false;
    }

    /**
     * Exchanges a given price in the default currency to the specified currency.
     *
     * @param string $currency The currency to exchange the price to.
     * @param float $price The price to exchange.
     *
     * @return float The exchanged price.
     */
    public function exchangePrice($currency, $price)
    {
        if (!array_key_exists($currency, $this->currency_return_reverse) && !isset($this->currency_return_reverse[$currency])) {
            return $price;
        }
        $ratio = get_field($this->currency_return_reverse[$currency], 'option');
        $after_exchange = $price * $ratio;
        return round($after_exchange, 2);
    }
}
