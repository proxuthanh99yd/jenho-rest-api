<?php

namespace Okhub\Service;

use WP_REST_Request;
use WP_Error;

class CategoryService
{
    private $page = 'danh-muc-san-pham';
    private $fieldname = 'danh_muc_san_pham_ui';
    private $tax = 'product_cat';
    public function getCategories()
    {

        $termFields =  get_field($this->fieldname, $this->page);
        $response = [];
        foreach ($termFields as $term) {
            $cat_thumb_id = get_woocommerce_term_meta($term->term_id, 'thumbnail_id', true);
            $cat_thumb_url = wp_get_attachment_thumb_url($cat_thumb_id);
            $theTerm =  get_term_by('term_id', $term, $this->tax);
            $response[] = array_merge($theTerm, array('thumbnail' => $cat_thumb_url));
        }
        return $response;
    }
}
