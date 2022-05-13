<?php

function _cj_in_prod(){

    if (! function_exists('str_ends_with')){
        function str_ends_with($haystack, $needle){
            return strrpos($haystack, $needle, 0) === strlen($haystack) - strlen($needle);
        }
    }

    $curent_domain = parse_url(home_url())['host'];
    return (
        ( ! function_exists('wp_get_environment_type') || wp_get_environment_type() === 'production' )
        && ! str_ends_with($curent_domain, '.local')
        && ! in_array($curent_domain, array('localhost', '127.0.0.1'))
    ) || (
        $curent_domain === 'laboratory.local'
    );
}

if (defined('CJ_IN_PROD')){
    if (CJ_IN_PROD)
        throw new Exception('To turn off CJ_IN_PROD please set WP_ENVIRONMENT_TYPE to one of the eligible non-production values (https://developer.wordpress.org/reference/functions/wp_get_environment_type/)');
    _doing_it_wrong( 'CJ_IN_PROD', '3.0', 'Set WP_ENVIRONMENT_TYPE to change the value of CJ_IN_PROD (https://developer.wordpress.org/reference/functions/wp_get_environment_type/)' );
} else {
    define('CJ_IN_PROD', _cj_in_prod());
}
