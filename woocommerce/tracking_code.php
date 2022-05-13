<?php

//-------------------------------------------
// CJ CONVERSION TRACKING PIXEL
//-------------------------------------------

function cj_tracking( $order_id ) {

    //-----------------
    // Get order items
    //-----------------

    if ( is_object($order_id) ){
      // Accept a WooCommerce order object in place of the order ID.
      // I don't think we will ever encounter this situation under normal circumstances,
      // but it makes unit testing possible
      $order = $order_id;
      $order_id = $order->get_id();
    } else {
      $order = wc_get_order( $order_id );
    }
    $order_items = $order->get_items();

  //-----------------
  // Get account info
  //-----------------

  if ( CJ_TRACKING_RUN_UNIT_TESTS === true ){

    $tag_id = 'XXXXX';
    $cid = 'XXXXXXX';
    $type = 'XXXXXXX';
    $cj_tracking_note_urls = false;
    $other_params = '';
    $use_cookies = false;

  } else {

    // Get account specific info from DB
    $account_info = apply_filters('cj_settings', get_cj_settings(), 'woocommerce', $order_items, $order_id);

    // Don't add the tracking code if we got back false (While allowing an error to naturally happen if we get back null as null could mistakenly get returned if the return statement is forgotten)
    if ( $account_info === false )
        return false;

    $tag_id = $account_info['tag_id'];
    $cid = $account_info['cid'];
    $type = $account_info['type'];
    $cj_tracking_note_urls = $account_info['notate_order_data'];
    $other_params = $account_info['other_params'];
    $use_cookies = $account_info['storage_mechanism'] === 'cookies';

  }

  //---------------------
  // Create tracking code
  //---------------------

  // The documentation says to use container_tag_id, but containerTagId seems to be working so I'm leaving this as is for now
  $cj_url = "https://www.emjcd.com/tags/c?containerTagId=$tag_id&";

  // Skip products that are part of a product bundle
  $skip_these = array();
  //error_log( "\n\n\n\nCart Data:\n" . var_export($order_items, true), 3, ABSPATH . 'cj_debug_log_dkrjtwk.txt' );
  $order_items = array_filter($order_items);
  foreach ( $order_items as $item_id => $item_data ) {
      $product = $item_data->get_product();
      if ( $product->is_type('bundle') && class_exists('WC_PB_DB') && $bundled = WC_PB_DB::query_bundled_items( array('bundled_id' => $product->get_id()) )  ){
          foreach($bundled as $data){
              $skip_these[] = $data['product_id'];
          }
      } else if ( get_class($product) === 'WC_Product_Yith_Bundle' ){
          // TODO support yith product bundles
          $cj_url .= 'warning=yith_product_bundles_not_yet_supported_please_submit_a_ticket_using_the_form_on_the_cj_plugin_settings_page&';
      } else if ( strpos(get_class($product), 'Bundle') !== false ) {
          $cj_url .= 'warning=unsupported_bundling_plugin_detected_please_submit_a_ticket_using_the_form_on_the_cj_plugin_settings_page&';
      }
  }

  // Add info about each item in the order
  $i = 1;
  $total_price = 0;
  global $WooCommerce;
  foreach ( $order_items as $item_id => $item_data ) {

      $product = $item_data->get_product();

      if ( in_array($product->get_id(), $skip_these) ){
          unset( $skip_these[array_search($product->get_id(), $skip_these)] );
          continue;
      }

      $product_sku_or_name = $product->get_sku() ?: filter_var( str_replace(' ','-',$product->get_name()), FILTER_SANITIZE_URL );
      $product_sku_or_name = cj_sanitize_item_name($product_sku_or_name);
      $item_total = $product->get_price();
      $item_quantity = $item_data->get_quantity();
      $total_price += $item_total;

      $cj_url .= "ITEM$i=$product_sku_or_name&AMT$i=$item_total&QTY$i=$item_quantity&";

      $i++;

  }

  // Get discount info
  $coupon_codes = array();
  foreach ( $order->get_items('coupon') as $coupon_item ) {
    $coupon_codes[] = $coupon_item->get_code();
  }
  $coupon_codes = implode( ",", $coupon_codes ) ?: '';
  $discount_total = $order->get_discount_total();

  // Info stored in the affiliate link clicked on to get to our site
  if ($use_cookies){
      $publisherCID = htmlspecialchars( $_COOKIE['publisherCID'] ?? '' );
      $cjevent = htmlspecialchars( $_COOKIE['cje'] ?? '' );
  } else {
      $publisherCID = htmlspecialchars( WC()->session->get('publisherCID') );
      $cjevent = htmlspecialchars( WC()->session->get('cjevent') );
  }

  // format other query params
  $other_params = str_replace("\n", '&', $other_params);
  $other_params = str_replace('&&', '&', $other_params);
  // I would prefer to just urlencode everything between the ampersands and spaces
  // perhaps in the future I'll add a better solution that uses preg_split
  // and then after urlencode()ing, implodes everything back together
  $other_params = str_replace(" ", '+', $other_params);
  $other_params = filter_var($other_params, FILTER_SANITIZE_URL);
  if ($other_params && substr($other_params, 0, 1) !== '&')
    $other_params = '&' . $other_params;

  //Get Currency
  $currency = $order->get_currency();

  // Add info about the order
  $cj_url .= "CID=$cid&OID=$order_id&TYPE=$type&CJEVENT=$cjevent&CURRENCY=$currency";
  if ($coupon_codes)
    $cj_url .= "&COUPON=$coupon_codes";
  if ($discount_total)
    $cj_url .= "&DISCOUNT=$discount_total";
  $cj_url .= $other_params;



  // Echo out the tracking code
  if (CJ_IN_PROD)
      echo '<iframe height="1" width="1" frameborder="0" scrolling="no" src='.$cj_url.' name="cj_conversion"></iframe>';

  //---------------------
  // Maybe add debug info
  //---------------------

  // add an order note containing the url used for the tracking code
  if ( $cj_tracking_note_urls ){
    $order->add_order_note( sprintf( __('The following URL was used for the cj.com tracking code: %s', 'cjtracking'), $cj_url) );
  }

  if ($publisherCID && $cjevent){
    $order->add_order_note( sprintf( __("This order was from a CJ.com referral.<br/>---------<br/>DETAILS<p style='margin-left: 0px;'>Publisher ID: $publisherCID </p><p style='margin-left: 0px;'>CJ Event: $cjevent</p></span>", 'cjtracking'), $publisherCID, $cjevent) );
  } else if ($cjevent){
    $order->add_order_note( sprintf( __("This order was from a CJ.com referral.<br/>---------<br/>DETAILS<p style='margin-left: 0px;'>CJ Event: $cjevent</p></span>", 'cjtracking'), $cjevent) );
  }

  return $cj_url;
}
add_action( 'woocommerce_thankyou', 'cj_tracking' );
