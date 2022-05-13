<?php

use Automattic\Jetpack\Constants;

/* Because the conditional tags provided don't accept a post ID,
and since this is an Ajax request that's a problem */
class CJWooConditionalTags{
    public function __construct($post_id){
        if ( ! $post_id){
            $this->pid = false;
            $this->post = false;
        }
        $this->pid = (int)$post_id;
        $this->post = get_post($this->pid);
    }
    public function post_content_has_shortcode($tag){
        return $this->post->post_content && has_shortcode( $this->post->post_content, $tag );
    }
    public function is_cart(){
        // https://woocommerce.wp-a2z.org/oik_api/is_cart/
        if ($page_id = $this->pid)
            return ( $page_id && is_page( $page_id ) ) || Constants::is_defined( 'WOOCOMMERCE_CART' ) || $this->post_content_has_shortcode( 'woocommerce_cart' );
        return false;
    }
    function is_checkout() {
        if ($page_id = $this->pid)
            return ( $page_id && is_page( $page_id ) ) || $this->post_content_has_shortcode( 'woocommerce_checkout' ) || apply_filters( 'woocommerce_is_checkout', false ) || Constants::is_defined( 'WOOCOMMERCE_CHECKOUT' );
        return false;
    }
    function is_order_received_page() {
		global $wp;

		if ($page_id = $this->pid)
            return apply_filters( 'woocommerce_is_order_received_page', ( $page_id && is_page( $page_id ) && isset( $wp->query_vars['order-received'] ) ) );
        return false;
	}
    function is_account_page() {
		if ($page_id = $this->pid)
            return ( $page_id && is_page( $page_id ) ) || $this->post_content_has_shortcode( 'woocommerce_my_account' ) || apply_filters( 'woocommerce_is_account_page', false );
        return false;
	}
    function is_product() {
        if ($this->pid)
            return is_single($this->pid) && get_post_type($this->pid) === 'product';
        return false;
	}
}

class CJ_WC_Tag implements CJTagInterface{
    /* Functions should return a falsey value as a default.
        code that use this class should then proceed to the next registered integration upon receiving a falsey value */

    public function __construct(){
        global $wp;
        $this->order = false;

        if ( isset($_GET['action']) && $_GET['action']==='cj_site_tag_data' ){
            return;
        }

        if (defined('DOING_AJAX')){
            $order_id = $this->getOrderId();
            $this->order = wc_get_order( absint($order_id) );
        } else {
            // I think wp might be the earliest we can use get_query_var
            add_action('wp', function(){
                global $wp;
                if ($order_id = get_query_var('order-received', false))
                    $this->order = wc_get_order( absint($order_id) );
            });
        }
    }

    public function isThankYouPage(): bool{
        static $res;
        if ($res === null)
            $res = (function_exists('is_order_received_page') && is_order_received_page())
                || (defined('DOING_AJAX') && $_GET['action']==='cj_conversion_tag_data');
        return $res;
    }
    public function getOrderId(): string{
        if (! isset($_GET['order-received']))
            throw new Exception('please pass in the order-received parameter when getting the conversion code data');

        return isset($_GET['order-received']) ? (string)absint($_GET['order-received']) : '';
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
        if (! isset($_GET['post_id']) || ! is_post_publicly_viewable($_GET['post_id']))
            return '';
        $page_is = new CJWooConditionalTags($_GET['post_id']);

        if ($this->isThankYouPage()) return 'conversionConfirmation'; // must be before is_cart/is_checkout
        if ($page_is->is_cart() || $page_is->is_checkout()) return 'cart';
        if ($page_is->is_account_page()) return 'accountCenter';
        if ($page_is->is_product()) return 'productDetail';

        return '';
    }
    public function getReferringChannel(): string{
        return '';
    }
    public function getCartSubtotal(){
        $subtotal = WC()->cart->get_subtotal() - WC()->cart->get_discount_total();
        return max((float)$subtotal, 0.0);
        // if ( ! $this->isThankYouPage()){
        //     return WC()->cart->get_cart_subtotal();
        //     // also `get_cart_contents_total` does not include discounts/fees
        //     // https://woocommerce.wp-a2z.org/oik_api/wc_cartget_cart_contents_total/
        //     // https://woocommerce.wp-a2z.org/oik_api/wc_cartget_cart_subtotal/
        //     // and get_total includes shipping
        //     // looking at source clearly shows what is and is not included in the total
        // }
        // return 0;
    }
    public function getOrderSubtotal(){
        if ($this->order)
            return (float)($this->order->get_subtotal() - $this->order->get_discount_total());
            // See: https://stackoverflow.com/questions/40711160/woocommerce-getting-the-order-item-price-and-quantity
        return 0.0;
    }
    public function getItems(){
        if ( ! $this->isThankYouPage()){
            $ret = [];
            $cart = WC()->cart;
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
            );
            if ($per_product_discount != 0)
                $add_me['discount'] = (float)$per_product_discount;

            array_push($ret, $add_me);

        }
        return $ret;
    }
    function notateOrder($data, $debug_mode){
        $order_id = $this->getOrderId();
        $order = wc_get_order($order_id);
        if ($debug_mode){
            $order->add_order_note(
                sprintf( __('Data prepared for sending to CJ.com: %s', 'cjtracking'),
                json_encode($data))
            );
        }
        $order->add_order_note( sprintf( __("This order was from a CJ.com referral.<br/>---------<br/>DETAILS<p style='margin-left: 0px;'>Action Tracker ID: %s </p><p style='margin-left: 0px;'>CJ Event: %s</p></span>", 'cjtracking'), $data['actionTrackerId'], $data['cjeventOrder']) );
    }
}
