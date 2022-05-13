<?php

class CJ_WC_Tag implements CJTagInterface{
    public function getPageType(): string{
        if (is_cart() || is_checkout()) return 'cart';
        if (is_wc_endpoint_url('order-received')) return 'conversionConfirmation';
        if (is_account_page()) return 'accountCenter';
        if (is_product()) return 'productDetail';
        return '';
    }
    public function getReferringChannel(): string{
        return '';
    }
    public function getCartSubtotal(){
        return WC()->cart->get_cart_subtotal();
        // also `get_cart_contents_total` does not include discounts/fees
        // https://woocommerce.wp-a2z.org/oik_api/wc_cartget_cart_contents_total/
        // https://woocommerce.wp-a2z.org/oik_api/wc_cartget_cart_subtotal/
        // and get_total includes shipping
        // looking at source clearly shows what is and is not included in the total
    }
    public function getItems(){
        $ret = [];
        $cart = WC()->cart
        $cart_items = $cart->get_cart();
        $quantities = $cart->get_cart_item_quantities();
        $qty_total = array_sum($quantities);
        $discount_total = $cart->get_discount_total() - $cart->get_fee_total();

            foreach($cart_items as $line_item) {
                $product_id = $line_item['product_id'];
                $product =  wc_get_product( $product_id );
                $qty = $quantities[$product->get_stock_managed_by_id()] ?? $line_item['quantity'];
                $product_discount = $discount_total * ($qty / $qty_total);

                array_push($ret, array(
                    'unitPrice' => $product->get_price() - $product_discount,
                    'itemId' => $product->get_sku() ?: $product_id,
                    'quantity' => $qty,
                    //'discount' => $product->is_on_sale() ? $product->get_regular_price() - $product->get_sale_price() : 0.0,
                    'discount' => $product_discount,
                ));
            }
        return $ret;
    }
}
