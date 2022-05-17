<?php
function cj_conversion_tag_ajax_callback(){
    header('content-type: application/json; charset=utf-8');
    include 'get_order_data.php';
    echo json_encode(get_order_data());
    exit;
}
add_action('wp_ajax_cj_conversion_tag_data', 'cj_conversion_tag_ajax_callback');
add_action('wp_ajax_nopriv_cj_conversion_tag_data', 'cj_conversion_tag_ajax_callback');
