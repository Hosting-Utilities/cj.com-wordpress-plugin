<?php

class CJ_WC_Tag implements CJTagInterface{

    public function __construct(){
        global $wp;
        $this->order = false;

        add_filter('init', function(){
            if ( isset( $_GET['order-received'] ) )
                $this->order = wc_get_order( (int)$this->getOrderId() );
        });
    }

    public function isThankYouPage(): bool{
        return is_order_received_page();
    }
    public function getOrderId(): string{
        global $wp;
        $order_id  = absint( $_GET['order-received'] );

        if ( empty($order_id) || $order_id == 0 )
            return '';
        return (string)$order_id;
    }
    public function getCurrency(): string{
        if ($order = $this->order){
            return $order->get_currency();
        }
        return '';
    }
    public function getDiscount(){
        if ($order = $this->order){
            return $order->get_discount_total();
        }
        return 0;
    }
    public function getCoupon(): string{
        if ($order = $this->order){
            $coupon_codes = array();
            foreach ( $order->get_items('coupon') as $coupon_item ) {
              $coupon_codes[] = $coupon_item->get_code();
            }
            $coupon_codes = implode( ",", $coupon_codes ) ?: '';
            return $coupon_codes;
        }
        return '';
    }

    public function getPageType(): string{
        if (is_cart() || is_checkout()) return 'cart';
        if ($this->isThankYouPage()) return 'conversionConfirmation';
        if (is_account_page()) return 'accountCenter';
        if (is_product()) return 'productDetail';
        return '';
    }
    public function getReferringChannel(): string{
        return '';
    }
    public function getCartSubtotal(){
        if ($this->order)
            return $this->order->get_subtotal();
            // does not include discounts, for that we would use get_total() https://stackoverflow.com/questions/40711160/woocommerce-getting-the-order-item-price-and-quantity
        return 0;
    }
    public function getItems(){
        if ( ! $this->order)
            return array();
        $ret = [];
        $order_items = $this->order->get_items();
        $qty_total = array_reduce($order_items, function($accumulator, $order_item){ return $accumulator + $order_item->get_quantity(); }, 0);
        $qty_total = $qty_total ?: 1; // make sure we never end up with division by zero errors
        $discount_total = (float)$this->order->get_discount_total(); // - $cart->get_fee_total();
        $per_product_discount = $discount_total / $qty_total;

        foreach($order_items as $item) {
            $product = $item->get_product();
            $product_id = $product->get_id();
            $qty = $item->get_quantity();

            $add_me = array(
                'unitPrice' => (float)($item->get_subtotal()/$qty - $per_product_discount),
                'itemId' => (string)($product->get_sku() ?: $product_id),
                'quantity' => (int)$qty,
            )
            if ($per_product_discount != 0)
                $add_me['discount'] = (float)$per_product_discount;

            array_push($ret, $add_me);

        }
        return $ret;
    }
}
