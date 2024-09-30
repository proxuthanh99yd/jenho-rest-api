<?php

namespace Okhub\Controller;

use WP_REST_Request;
use WP_Error;
use WC_Customer;

class OtherController
{
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'registerRoutes'));
    }

    /**
     * Registers all the REST API routes for order operations.
     *
     * @return void
     */
    public function registerRoutes()
    {
        // Endpoint to get countries
        register_rest_route('api/v1', 'countries', array(
            'methods' => 'GET',
            'callback' => array($this, 'getCountries'),
            'permission_callback' => '__return_true',
        ));

        // Endpoint to get states based on country code
        register_rest_route('api/v1', 'countries/(?P<country>[A-Z]{2})/states', array(
            'methods' => 'GET',
            'callback' => array($this, 'getStatesByCountry'),
            'permission_callback' => '__return_true',
            'args' => array(
                'country' => array(
                    'validate_callback' => function ($param, $request, $key) {
                        return is_string($param) && strlen($param) === 2;
                    }
                )
            ),
        ));

        // Endpoint to get states based on country code
        register_rest_route('api/v1', 'shipping/price', array(
            'methods' => 'GET',
            'callback' => array($this, 'getShippingPrice'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Get all countries.
     *
     * @param WP_REST_Request $request
     * @return array
     */
    public function getCountries(WP_REST_Request $request)
    {
        $countries_obj = new \WC_Countries();
        $countries = $countries_obj->__get('countries');
        return rest_ensure_response($countries);
    }

    /**
     * Get states for a specific country.
     *
     * @param WP_REST_Request $request
     * @return array|WP_Error
     */
    public function getStatesByCountry(WP_REST_Request $request)
    {
        $country_code = $request->get_param('country');
        $countries_obj = new \WC_Countries();
        $states = $countries_obj->get_states($country_code);

        if (empty($states)) {
            return new WP_Error('no_states_found', 'No states found for this country', array('status' => 404));
        }

        return rest_ensure_response($states);
    }

    public function getShippingPrice(WP_REST_Request $request)
    {
        $viet_nam_shipping_fee = get_field('viet_nam_shipping_fee', 'option');
        $singapore_shipping_fee = get_field('singapore_shipping_fee', 'option');
        $malaysia_shipping_fee = get_field('malaysia_shipping_fee', 'option');
        $other_country_shipping_fee = get_field('other_country_shipping_fee', 'option');

        return [
            "VND" => [
                "under" => $viet_nam_shipping_fee["under"],
                "fee" => $viet_nam_shipping_fee["fee"],
            ],
            "SGD" => [
                "under" => $singapore_shipping_fee["under"],
                "fee" => $singapore_shipping_fee["fee"],
            ],
            "MYR" => [
                "under" => $malaysia_shipping_fee["under"],
                "fee" => $malaysia_shipping_fee["fee"],
            ],
            "other_country" => [
                "under" => $other_country_shipping_fee["under"],
                "fee" => $other_country_shipping_fee["fee"],
            ],
        ];
    }
}
