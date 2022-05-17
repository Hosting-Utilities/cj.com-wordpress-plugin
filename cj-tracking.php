<?php
/**
 *
 * Plugin Name:       CJ Tracking Code
 * Description:       Installs the tracking code for the CJ Affiliate network (cj.com)
 * Version:           3.2
 * Author:            Hosting Utilities & WP Overwatch
 * Author URI:        https://hostingutilities.com/
 * Text Domain:       cjtracking
 * Contributors:      hostingutilities
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

// Define our constants
define( 'CJ_TRACKING_PLUGIN_VERSION', "3.2.1" );
define( 'CJ_TRACKING_RUN_UNIT_TESTS', false );
define( 'CJ_TRACKING_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'CJ_TRACKING_USE_SERVICE_WORKER', false );
define( 'CJ_TRACKING_DEFAULT_IMPLEMENTATION', 'server_side_cookie' );

// version check
if ( version_compare( PHP_VERSION, '5.6.0' ) === -1 ){
    function cj_tracking_check_php_version() {
        if( is_admin() ){
            ?>
            <div class="error notice">
                <p><?php printf( __( 'The CJ Tracking Code plugin requires PHP version %1$s.'
                                   . ' You are currently running version: %2$s', 'cjtracking'),
                                   "5.6.0", PHP_VERSION );
                ?></p>
            </div>
            <?php
        }
    }
    add_action( 'admin_notices', 'cj_tracking_check_php_version' );
    return;
}

if ( defined('CJ_PLUGIN_VERSION') ){
    throw new Exception('Please deactivate the old CJ Tracking plugin before activating this one');
}

require CJ_TRACKING_PLUGIN_PATH . 'inc/in_prod.php';

/* For CJ's internal analytics */
add_filter('cj_data_layer', function($data, $tag_type){
        $data['pointOfSale'] = 'web';
        $data['trackingSource'] = 'woocommerce';
        return $data;
}, 5, 2);

if ( ! CJ_TRACKING_USE_SERVICE_WORKER){

    add_filter( 'query_vars', function($vars){
        $vars[] = 'cjproxy';
        return $vars;
    } );

    $use_rewrite_rules = false;

    if ($use_rewrite_rules){
        add_action('parse_query', function(){
            global $cj_proxy_path;
            if( $cj_proxy_path = get_query_var('cjproxy') ){
                require CJ_TRACKING_PLUGIN_PATH . 'inc/proxy.php';
                exit;
            }
        });

        function add_cj_proxy_rewrite_rule(){
            add_rewrite_rule(
                '^cj-proxy/(.+)',
                'index.php?cjproxy=$matches[1]',
                'top'
            );
            add_rewrite_endpoint( 'cj-proxy', EP_PERMALINK );
        }
        add_action('init',  'add_cj_proxy_rewrite_rule');
        register_activation_hook( __FILE__, function(){
            add_cj_proxy_rewrite_rule();
            flush_rewrite_rules(true);
        });
        register_deactivation_hook( __FILE__, function(){
            delete_option( 'rewrite_rules' );
            // Supposedly there is a way to remove a specific rule talked about somewhere on https://core.trac.wordpress.org/ticket/29118
            // But we're going to just remove all rules
            // And let the rules be naturally rebuilt on the next request
        });
    } else {
        add_action('parse_request', function( $wp ){
            $current_relative_url = add_query_arg( $_SERVER['QUERY_STRING'], '', $wp->request);
            if ( preg_match( '#^cj-proxy/(.+)#', $current_relative_url, $matches ) ) {
                global $cj_proxy_path;
                $cj_proxy_path = $matches[1];
                require CJ_TRACKING_PLUGIN_PATH . 'inc/proxy.php';

                exit;
            }
        });
    }

}

// If we're on the backend, register admin settings and return
if ( is_admin() ){
    require CJ_TRACKING_PLUGIN_PATH . 'inc/settings_page.php';
    if (wp_doing_ajax()){
        require CJ_TRACKING_PLUGIN_PATH . 'inc/inc.php';
        require CJ_TRACKING_PLUGIN_PATH . 'inc/ajax.php';
        require CJ_TRACKING_PLUGIN_PATH . 'inc/ajax_get_js_from_cj.php';
        require CJ_TRACKING_PLUGIN_PATH . 'inc/ajax_get_site_tag_data.php';
        require CJ_TRACKING_PLUGIN_PATH . 'inc/ajax_get_conversion_tag_data.php';
        require CJ_TRACKING_PLUGIN_PATH . 'inc/ajax_proxy.php';
        return;
    }
    include CJ_TRACKING_PLUGIN_PATH . 'inc/compatability_and_notices.php';

    // Add a settings link to the plugins page
    function cj_affiliate_settings_link( $links ) {
        $links[] = '<a href="options-general.php?page=cj-tracking-settings">' . __( 'Settings' ) . '</a>';
      	return $links;
    }
    add_filter( "plugin_action_links_".plugin_basename( __FILE__ ), 'cj_affiliate_settings_link' );

    return; // Return from this script

} else {
    $settings = get_option('ow_cj_tracking', $default=false);
    function cj_tracking_enqueue_js(){
        $duration = empty($settings['cookie_duration']) ? 120 : (int)$settings['cookie_duration'];
        echo "<script>cj_tracking_cookie_duration=$duration</script>";
        wp_enqueue_script('cj-tracking-store-referral-info', plugins_url('/assets/save_affiliate_referral_info.js', CJ_TRACKING_PLUGIN_PATH.'/placeholder'), array(), CJ_TRACKING_PLUGIN_VERSION, true);
    }
    add_filter('wp_enqueue_scripts', 'cj_tracking_enqueue_js');
}

require CJ_TRACKING_PLUGIN_PATH . 'inc/integrations.php';
require CJ_TRACKING_PLUGIN_PATH . 'inc/tag_functions.php';
require CJ_TRACKING_PLUGIN_PATH . 'inc/inc.php';
include CJ_TRACKING_PLUGIN_PATH . 'inc/compatability_and_notices.php';

$cj_integrations = cj_get_integrations();

$settings = get_cj_settings();
$use_deprecated = ! isset($settings['enterprise_id']) || ! $settings['enterprise_id'];

if (in_array('WooCommerce', $cj_integrations)){

    if ($use_deprecated){
        // Add the WooCommerce tracking code filter to the thank you page
        include_once CJ_TRACKING_PLUGIN_PATH . 'woocommerce/tracking_code.php';
    }

    // Maybe run unit tests
    if ( true === CJ_TRACKING_RUN_UNIT_TESTS ){
      require CJ_TRACKING_PLUGIN_PATH . 'woocommerce/unit_tests.php';
    }

    // mostly redundant since cookies are now being saved with JS,
    // although it still has the advantage that it may save the cjevent (if we get past the cache)
    // before the site tag is fired while the JS version happens later
    // which would cause false statistics about the first page people landed on
    include_once CJ_TRACKING_PLUGIN_PATH . 'woocommerce/legacy_save_affiliate_referral_info.php';
}

if ($use_deprecated){
    if (in_array('Gravity Forms', $cj_integrations)){
        // Send the tracking code data when a Gravity Form page that has a pricing field has been submitted
        include 'gravity-forms/gravity-forms-old.php';
    }
}

cj_register_integrations();

if (CJ_IN_PROD){
    add_filter('wp_enqueue_scripts', function() use ($settings){
         $tag_js_url = plugins_url('/assets/tag.js', CJ_TRACKING_PLUGIN_PATH.'/placeholder');
         if (strpos($tag_js_url, 'http://') === 0 )
             $tag_js_url = substr_replace($tag_js_url, 'https://', 0, strlen('http://'));
        wp_enqueue_script('cj-tracking-code', $tag_js_url, array(), CJ_TRACKING_PLUGIN_VERSION, true);

        wp_localize_script( 'cj-tracking-code', 'cj_from_php', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'proxy_sw' => plugin_dir_url(__FILE__) . 'proxy/proxy_to_ajax.js',
            'proxy_sw_dir' =>  plugin_dir_url(__FILE__) . 'proxy/',
            'tag_type' => cj_is_conversion_tracking_page() ? 'conversion_tag' : 'site_tag',
            'woo_order_id' => get_query_var('order-received', false),
            'post_id' => get_the_ID(),
            'action_tracker_id' => isset($settings['action_tracker_id']) ? $settings['action_tracker_id'] : '',
            'tag_id' => isset($settings['tag_id']) ? $settings['tag_id'] : '',
            'implementation' => isset($settings['implementation']) ? $settings['implementation'] : CJ_TRACKING_DEFAULT_IMPLEMENTATION,
        ));
    });
}
