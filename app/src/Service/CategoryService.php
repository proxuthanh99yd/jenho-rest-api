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
            $response[] = get_term_by('term_id', $term, $this->tax);
        }
        return $response;
    }
}
