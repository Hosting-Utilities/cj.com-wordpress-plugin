<?php

/* Used in both ajax requests and appended to Gravity Form confirmation messages to
    get data to eventually add in JavaScript into window.cj.order */
function get_order_data(){
    global $CJ_Site_tag_objects;

    include_once __DIR__ . '/tag_functions.php';

    cj_register_integrations();

    $ret = cj_get_shared_tag_data();

    $cj_acct_info = get_cj_settings();

    $order_ids = array();
    $currencies = array();
    $coupons = array();
    $discount = 0;
    $subtotal = 0;
    $manuallySendingOrderRequested = false;
    foreach($CJ_Site_tag_objects as $obj){
        if ($obj->isThankYouPage()){

            $order_id = $obj->getOrderId();
            if ($order_id){
                array_push($order_ids, $order_id);
            }

            $currency = $obj->getCurrency();
            if ($currency){
                array_push($currencies, $currency);
            }

            $coupon = $obj->getCoupon();
            if ($coupon){
                array_push($coupons, $coupon);
            }

            $discount += (float)$obj->getDiscount();
            $subtotal += (float)$obj->getOrderSubtotal();

            if ( ! $manuallySendingOrderRequested && method_exists($obj, 'turnOnManualOrderSending') ){
                $manuallySendingOrderRequested = $obj->turnOnManualOrderSending();
            }
        }
    }
    $order_ids = array_unique(array_filter($order_ids));
    $order_id = empty($order_ids) ? '' : implode(', ', $order_ids);
    $order_id = cj_sanitize_order_id($order_id);
    $coupon = empty($coupons) ? '' : implode(', ', array_unique(array_filter($coupons)));

    $currencies = array_unique(array_filter($currencies));
    if (count($currencies) > 1){
        trigger_error('Received multiple conflicting currencies for a CJ Tracking code: ' . implode(', ', $currencies), E_USER_WARNING);
    }
    $currency = empty($currencies) ? 'USD' : $currencies[0];

    $use_cookies = $cj_acct_info['storage_mechanism'] === 'cookies';
    if ($use_cookies){
        $cjevent = htmlspecialchars( isset($_COOKIE['cje']) ? $_COOKIE['cje'] : '' );
    } else {
        $cjevent = htmlspecialchars( WC()->session->get('cjevent') );
    }

    if ($ret['pageType'] !== 'conversionConfirmation'){
        throw new Exception('Expected a page type of \'conversionConfirmation\' when using the conversion tag ajax endpoint.');
    }

    $ret = array_merge($ret, array(
         'orderId' => $order_id,
         'actionTrackerId' => $cj_acct_info['action_tracker_id'],
         'currency' => $currency,
         'discount' => (float)$discount,
         'cjeventOrder' => $cjevent,
         'sendOrderOnLoad' => ! $manuallySendingOrderRequested,
    ));
    if ($coupon){
        $ret['coupon'] = $coupon;
    }

    $ret = apply_filters('cj_data_layer', $ret, 'order');

    foreach($CJ_Site_tag_objects as $obj){
        if ($obj->isThankYouPage() && method_exists($obj, 'notateOrder')){
            $obj->notateOrder($ret, $cj_acct_info['notate_order_data'] );
        }
    }

    return $ret;
}
