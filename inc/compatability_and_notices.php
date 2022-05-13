<?php

if ( ! defined('WPFC_CACHE_QUERYSTRING') ){
    define( 'WPFC_CACHE_QUERYSTRING', false );
} else if ( WPFC_CACHE_QUERYSTRING === true ){
	add_action( 'admin_notices', function(){
        $settings = get_option( 'ow_cj_tracking', $default=false );
        $using_woo_sessions = empty($settings['storage_mechanism']) ? false : $settings['storage_mechanism'] === 'woo_session';
        if ( $using_woo_sessions ){
    		?>
    		<div class="notice notice-error">
    		    <p><b>CJ tracking code conflict:</b> WP Fastest Cache is ignoring query strings (The WPFC_CACHE_QUERYSTRING constant was true). <b>This will cause the website to frequently fail at recording the CJ Event.</b></p>
    		</div>
    		<?php
        }
	} );
}

if (is_admin()){

    if ( ! is_ssl() && CJ_IN_PROD ){
    	add_action( 'admin_notices', function(){
    		?>
    		<div class="notice notice-warning">
    		    <p><b>CJ tracking code SSL Warning:</b> We detected that you are logged in over HTTP!
                    The CJ Tracking code plugin will not work properly if you do not have a valid TLS certificate.
                    If HTTPS works fine, and you just happen to be loading this page over HTTP instead (why?!), then you are fine.
                    If not, you will need to setup HTTPS.</p>
    		</div>
    		<?php
    	} );
    }

    /* The built in getallheaders doesn't work good with NGINX on I believe anything less than PHP 7.3 */
    /* https://stackoverflow.com/q/13224615/786593 */
    function cj_tracking_getallheaders(){
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if ( ! empty($headers))
                return $headers;
        }
        if (!is_array($_SERVER)) {
            return array();
        }

        $headers = array();
        foreach ($_SERVER as $name => $val) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $header_name = str_replace(array('_', ' '), '-', substr($name, 5));
                $headers[$header_name] = $val;
            }
        }
        return $headers;
    }
    /* case insensitive check if request header exists */
    function cj_tracking_has_header_i($val){
        static $headers;
        // in PHP 7 could do
        //$headers = $headers ?? array_change_key_case(cj_tracking_getallheaders(), CASE_LOWER);
        $headers = isset($headers) ? $headers : array_change_key_case(cj_tracking_getallheaders(), CASE_LOWER);
        return isset($headers[strtolower($val)]);
    }

    if (cj_tracking_has_header_i('Content-Security-Policy')){
        add_action( 'admin_notices', function(){
    		?>
    		<div class="notice notice-error">
    		    <p><b>CJ tracking code compatability issue</b>: Detected that you are using a CSP (Content Security Policy).
                    Please use the form on the plugin's settings page to contact us so we can help you get the plugin working with CSP.</p>
    		</div>
    		<?php
    	} );
    }

    add_action( 'admin_notices', function(){
        // Purposefully bypassing the get_cj_settings function and grabbing the option directly
        $cj_tracking_option = get_option( 'ow_cj_tracking', $default=false );
        if ( ! isset($cj_tracking_option['enterprise_id'])){
            ?>
    		<div class="notice notice-error">
                <p><b>CJ Tracking, your attention is required</b>: The CJ plugin has received a major upgrade that requires you to go to the <a href='<?= admin_url('options-general.php?page=cj-tracking-settings') ?>'>settings page</a> and fill in some new settings
                to be able to start using CJ's new tracking code.
                <br>CJ is no longer supported the old tracking code, so this change is required. You can get the new information needed to fill in the settings page from your client integration engineer at CJ.
                Thank you and sorry for the extra trouble.
                <br>
                    <br>If you are no longer using CJ's services, we recommend using the <i>"Remove Plugin Data"</i> button on the settings page and then deactivate this plugin.</p>
                <!-- for when we actually remove the old code
    		    <p><b>CJ TRACKING CODE NOT CURRENTLY OPERATING</b>: The CJ plugin has received a major upgrade that requires you to go to the <a href='<?= admin_url('cj-tracking-settings') ?>'>settings page</a> and re-save the settings.</p>
                <p>Until this is done the tracking code will not work. There are some new settings on that page that you will have to get from your client integration engineer at CJ.
                    <br/>This change is required because of changes CJ has made to their how their systems operate. Sorry for the extra trouble.
                    <br>If you are no longer using CJ's services, we recommend using the "Remove Plugin Data" button on the settings page and then deactivating the plugin.</p>
                -->
    		</div>
    		<?php
        }

    });

    if ( ! CJ_IN_PROD){
        add_action( 'admin_notices', function(){
    		?>
    		<div class="notice notice-warning">
    		    <p><b>Dev/Staging site detected:</b> No data will be sent to CJ. The plugin will still add notes to orders/form submissions as if the data was sent to make debugging problems with the plugin possible.</p>
    		</div>
    		<?php
    	} );
    }
}
