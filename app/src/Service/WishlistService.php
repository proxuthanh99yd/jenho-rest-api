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
            'wishlist_id' => $wishlist_id,
            'product_id' => $product_id,
            'variation_id' => $variation_id,
            'quantity' => $quantity
        ];

        $wishlist_item = WishlistItem::add_item_to_wishlist($data);
        if (!$wishlist_item) {
            return new WP_Error('error', 'Failed to add item to wishlist', ['status' => 500]);
        }
        $product = $this->productService->getProduct($product_id);
        $product['wishlist_id'] = $wishlist_id;
        return $product;
    }

    public function get_wishlist_by_user_id($user_id)
    {
        $wishlist = $this->find_wishlist_by_user_id($user_id);
        if (!$wishlist) {
            return new WP_Error('wishlist_not_found', 'Wishlist not found', ['status' => 404]);
        }
        $wishlist = $this->get_items_by_wishlist($wishlist['ID']);
    }

    private function find_wishlist_by_user_id($user_id)
    {
        $wishlist = Wishlist::get_all_wishlists_by_user($user_id);
        if ($wishlist) {
            return $wishlist['ID'];
        }
        $data = [
            'user_id' => $user_id,
            'wishlist_token' => bin2hex(random_bytes(16)),
        ];
        $wishlist = Wishlist::create_wishlist($data);
        return $wishlist;
    }

    private function get_items_by_wishlist($wishlist_id)
    {
        return WishlistItem::get_items_by_wishlist($wishlist_id);
    }
}
