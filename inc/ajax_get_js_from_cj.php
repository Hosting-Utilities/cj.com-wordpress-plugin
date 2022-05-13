<?php

if (! function_exists('cj_get_server_ip_addr')){
    function cj_get_server_ip_addr(){
        // According to https://stackoverflow.com/a/29733041/786593 both methods of finding the
        // IP address have their shortcomings, so we'll try using $_SERVER first and then a DNS lookup of the hostname
        $ip = $_SERVER['SERVER_NAME'];
        if (
            ! $ip
            || in_array($ip, array('127.0.0.1', '::1', 'localhost', ''))
            || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE |  FILTER_FLAG_NO_RES_RANGE)
        ){
            $host= gethostname();
            $ip = gethostbyname($host);
        }
        return $ip;
    }
}

/* To workaround third party cookies being blocked, we use this endpoint as an intermediary */
function cj_js_ajax_callback(){
    $tag_id = get_cj_settings()['action_tracker_id'];

    if (! $tag_id)
        exit('console.error("No cj.com tag ID")');

    if (get_cj_settings()['implementation'] === 'proxy'){
        $args = array('http' =>
            array(
                'X-Forwarded-For'  => cj_get_server_ip_addr(),
                'X-Forwarded-Host'  => parse_url(home_url())['host'],
                'X-Forwarded-Server'  => gethostname() . ' utilizing a WordPress plugin',
                'X-Forwarded-Request-Host'  => parse_url(home_url())['host'],
                'X-Forwarded-Request-Path'  => $_SERVER['PHP_SELF'],
            )
        );
    } else {
        $args = array('http' =>
            array(
                
            )
        );
    }

    $res = file_get_contents("https://www.mczbf.com/tags/$tag_id/tag.js", false, stream_context_create($args));

    $headers = array_slice($http_response_header, 1);
    foreach($headers as $header){
    	header($header);
    }

    if ($res){
        echo $res;
    } else {
        // Currently happens if the tag ID contains non-numeric characters
        throw new Exception('Did not receive any JS when trying to fetch it from cj.com. Check if your tag ID is valid. If you need to you may contact me through the plugin\'s setting page.');
    }

    exit;
}

add_action('wp_ajax_cj_com_js', 'cj_js_ajax_callback');
add_action('wp_ajax_nopriv_cj_com_js', 'cj_js_ajax_callback');
