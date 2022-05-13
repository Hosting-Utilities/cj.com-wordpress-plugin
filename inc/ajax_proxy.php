<?php
function cj_proxy_ajax_callback(){
    global $cj_proxy_path;
    $cj_proxy_path = $_GET['path'];
    require 'proxy.php';
    exit;
}
add_action('wp_ajax_proxy', 'cj_proxy_ajax_callback');
add_action('wp_ajax_nopriv_proxy', 'cj_proxy_ajax_callback');
