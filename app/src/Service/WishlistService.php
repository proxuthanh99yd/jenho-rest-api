<?php

namespace Okhub\Service;

use Okhub\Model\Wishlist;
use Okhub\Model\WishlistItem;
use Okhub\Service\ProductService;
use WP_Error;

class WishlistService
{
    private $productService;
    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }
    public function get_user_wishlist($user_id)
    {
        return Wishlist::get_all_wishlists_by_user($user_id);
    }

    public function add_to_wishlist($product_id, $variation_id, $quantity, $userId)
    {
        $wishlist_id = $this->find_wishlist_by_user_id($userId);
        $data = [
            'wishlist_id' => intval($wishlist_id),
            'product_id' => $product_id,
            'variation_id' => $variation_id,
            'quantity' => $quantity
        ];

        // Kiểm tra sản phẩm có tồn tại không
        $product = $this->productService->getProduct($product_id);
        if (!$product) {
            return new WP_Error('product_not_found', 'Product not found', ['status' => 404]);
        }

        // Nếu có variation_id, kiểm tra sự tồn tại của biến thể
        if ($variation_id > 0) {
            $product_variation = $this->productService->getVariationById($product['id'], $variation_id);
            if (!$product_variation) {
                return new WP_Error('variation_not_found', 'Variation not found', ['status' => 404]);
            }
        }

        // Thêm sản phẩm vào wishlist
        $wishlist_item = WishlistItem::add_item_to_wishlist($data);
        if (!$wishlist_item) {
            return new WP_Error('error', 'Failed to add item to wishlist', ['status' => 500]);
        }

        // Trả về sản phẩm kèm thông tin wishlist_id
        $product['wishlist_id'] = $wishlist_id;
        $product['wishlist_item_id'] = $wishlist_item['ID'];
        return $product;
    }


    public function get_wishlist_by_user_id($user_id)
    {
        $wishlist = $this->find_wishlist_by_user_id($user_id);
        return $wishlist;
        if (!$wishlist) {
            return new WP_Error('wishlist_not_found', 'Wishlist not found', ['status' => 404]);
        }
        $wishlist = $this->get_items_by_wishlist($wishlist['ID']);
    }

    private function find_wishlist_by_user_id($user_id)
    {
        $wishlist = Wishlist::get_single_wishlist_by_user($user_id); // Lấy một wishlist duy nhất
        if ($wishlist) {
            return $wishlist['ID'];
        }
        // Nếu không tìm thấy wishlist thì tạo mới
        $data = [
            'user_id' => $user_id,
            'wishlist_token' => bin2hex(random_bytes(16)),
        ];
        $wishlist = Wishlist::create_wishlist($data);
        return $wishlist['ID']; // Trả về ID của wishlist mới tạo
    }


    private function get_items_by_wishlist($wishlist_id)
    {
        return WishlistItem::get_items_by_wishlist($wishlist_id);
    }
}
