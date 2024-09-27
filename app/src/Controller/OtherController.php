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
}
