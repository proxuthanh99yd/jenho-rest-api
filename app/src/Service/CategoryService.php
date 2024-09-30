<?php

namespace Okhub\Service;

use WP_REST_Request;
use WP_Error;

class CategoryService
{
    private $page = 'danh-muc-san-pham';
    private $fieldname = 'danh_muc_san_pham_ui';
    private $tax = 'product_type_custom';
    public function getCategories()
    {

        $termFields =  get_field($this->fieldname, $this->page);
        $response = [];
        if (!$termFields) return $response;

        foreach ($termFields as $term_id) {
            if ($term_id) {
                $cat_thumb_id = get_field('product_type_thumbnail', $this->tax . '_' . $term_id);
                $cat_thumb_url = wp_get_attachment_image_url($cat_thumb_id);
                $theTerm =  get_term_by('term_id', $term_id, $this->tax, ARRAY_A);
                $theTerm['thumbnail'] = $cat_thumb_url;
                $response[] = $theTerm;
            }
        }
        return $response;
    }
}
