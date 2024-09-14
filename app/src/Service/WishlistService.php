<?php

namespace Okhub\Service;

use WP_REST_Request;
use WP_Error;
use WC_Wishlists_Wishlist;
use WC_Wishlists_Wishlist_Item;

class WishlistService
{
    // Lấy danh sách sản phẩm trong wishlist
    public function getWishlistItems(WP_REST_Request $request, $userId)
    {
        $wishlist_id = $request->get_param('wishlist_id');

        // Kiểm tra xem wishlist có tồn tại không
        $wishlist = WC_Wishlists_Wishlist::get_by_id($wishlist_id);

        if (!$wishlist || $wishlist->get_wishlist_owner() != $userId) {
            return new WP_Error('wishlist_not_found', 'Wishlist not found or access denied', ['status' => 404]);
        }

        // Lấy tất cả sản phẩm trong wishlist
        $items = WC_Wishlists_Wishlist_Item::get_items($wishlist_id);

        $products = [];
        foreach ($items as $item) {
            $product = wc_get_product($item->get_product_id());
            $products[] = [
                'product_id' => $product->get_id(),
                'product_name' => $product->get_name(),
                'quantity' => $item->get_quantity(),
            ];
        }

        return $products;
    }

    // Thêm sản phẩm vào wishlist
    public function addToWishlist(WP_REST_Request $request, $userId)
    {
        $wishlist_id = $request->get_param('wishlist_id');
        $product_id = $request->get_param('product_id');
        $quantity = $request->get_param('quantity') ? $request->get_param('quantity') : 1;

        // Kiểm tra xem wishlist có tồn tại không
        $wishlist = WC_Wishlists_Wishlist::get_by_id($wishlist_id);

        if (!$wishlist || $wishlist->get_wishlist_owner() != $userId) {
            return new WP_Error('wishlist_not_found', 'Wishlist not found or access denied', ['status' => 404]);
        }

        // Kiểm tra xem sản phẩm có tồn tại không
        $product = wc_get_product($product_id);
        if (!$product) {
            return new WP_Error('product_not_found', 'Product not found', ['status' => 404]);
        }

        // Thêm sản phẩm vào wishlist
        WC_Wishlists_Wishlist_Item::add_item($wishlist_id, $product_id, $quantity);

        return [
            'message' => 'Product added to wishlist',
            'product_id' => $product_id,
            'wishlist_id' => $wishlist_id,
        ];
    }

    // Xóa sản phẩm khỏi wishlist
    public function removeFromWishlist(WP_REST_Request $request, $userId)
    {
        $wishlist_id = $request->get_param('wishlist_id');
        $product_id = $request->get_param('product_id');

        // Kiểm tra xem wishlist có tồn tại không
        $wishlist = WC_Wishlists_Wishlist::get_by_id($wishlist_id);

        if (!$wishlist || $wishlist->get_wishlist_owner() != $userId) {
            return new WP_Error('wishlist_not_found', 'Wishlist not found or access denied', ['status' => 404]);
        }

        // Xóa sản phẩm khỏi wishlist
        WC_Wishlists_Wishlist_Item::remove_item($wishlist_id, $product_id);

        return [
            'message' => 'Product removed from wishlist',
            'product_id' => $product_id,
            'wishlist_id' => $wishlist_id,
        ];
    }

    // Tạo wishlist mới
    public function createWishlist(WP_REST_Request $request, $userId)
    {
        $wishlist_name = $request->get_param('wishlist_name');

        // Tạo wishlist mới
        $wishlist = new WC_Wishlists_Wishlist();
        $wishlist->set_wishlist_owner($userId);
        $wishlist->set_wishlist_name($wishlist_name);
        $wishlist->save();

        return [
            'message' => 'Wishlist created',
            'wishlist_id' => $wishlist->get_id(),
            'wishlist_name' => $wishlist_name,
        ];
    }
}
