<?php

// Store the publisher ID and cjevent, so we can connect it up with any orders placed on the site
function cj_tracking_store_publisher_info(){
    return;

    $GET_copy = array_change_key_case($_GET, CASE_LOWER);

    if ( ! (isset($GET_copy['publisherCID']) || isset($GET_copy['cje'])) )
        return;

    $settings = get_option( 'ow_cj_tracking', $default=false );
    $storage_mechanism = isset($settings['storage_mechanism']) ? $settings['storage_mechanism'] : 'cookies';

    if ($storage_mechanism === 'cookies'){

        $days_in_month = 31;
        $days = empty($settings['cookie_duration']) ? $days_in_month*13 : (int)$settings['cookie_duration'];
        $domain = parse_url(home_url())['host'];
        $domain = preg_replace('/^www\./', '', $domain); // remove www in front
        //$use_ssl = preg_match('/.local$/', $domain) ? true : is_ssl(); // only allow non-ssl cookies in dev environments
        $use_ssl = is_ssl(); // Don't try to set the secure flag if we are not on HTTPS as Chrome will refuse to set it over an HTTP connection (RFC 6265 section 5.3 #12)

        if (isset($GET_copy['publishercid']))
            setcookie('publisherCID', $GET_copy['publishercid'], time() + DAY_IN_SECONDS*$days, '/', $domain, $use_ssl, false );

        if (isset($GET_copy['cjevent']))
            setcookie('cje', $GET_copy['cjevent'], time() + DAY_IN_SECONDS*$days, '/', $domain, $use_ssl, $httponly=false );

    } else if ($storage_mechanism === 'woo_session') {

        // If we're not on a WooCommerce page, the session needs to be manually initiated
        if ( ! WC()->session->has_session() ) {
            WC()->session->set_customer_session_cookie( true );
        }

        if (isset($GET_copy['publishercid']))
            WC()->session->set('publisherCID', $GET_copy['publishercid']);

        if (isset($GET_copy['cjevent']))
            WC()->session->set('cjevent', $GET_copy['cjevent']);

        if (isset($GET_copy['publisherCID']) || isset($GET_copy['cje']) )
            WC()->session->save_data();

    }
}
add_action( 'woocommerce_init', 'cj_tracking_store_publisher_info' );
