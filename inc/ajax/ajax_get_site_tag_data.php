<?php
function cj_site_tag_ajax_callback(){
    global $CJ_Site_tag_objects;
    header('content-type: application/json; charset=utf-8');

    include_once __DIR__ . '/../tag_functions.php';
    cj_register_integrations();

    $ret = cj_get_shared_tag_data();

    $subtotal = 0.0;
    foreach($CJ_Site_tag_objects as $obj){
        $subtotal += (float)$obj->getCartSubtotal();
    }

    $ret['cartSubtotal'] = $subtotal;

    $ret = apply_filters('cj_data_layer', $ret, 'sitePage');

    echo json_encode($ret);
    exit;
}
add_action('wp_ajax_cj_site_tag_data', 'cj_site_tag_ajax_callback');
add_action('wp_ajax_nopriv_cj_site_tag_data', 'cj_site_tag_ajax_callback');
