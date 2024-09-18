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
        foreach ($termFields as $term_id) {
            $cat_thumb_id = get_term_meta($term_id, 'thumbnail_id', true);
            $cat_thumb_url = wp_get_attachment_thumb_url($cat_thumb_id);
            $theTerm =  get_term_by('term_id', $term_id, $this->tax, ARRAY_A);
            $theTerm['thumbnail'] = $cat_thumb_url;
            $response[] = $theTerm;
        }
        return $response;
    }
}
