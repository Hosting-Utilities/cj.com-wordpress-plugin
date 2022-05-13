<?php

/*
    To add a new integration
        - Add to CJ_ALL_POSSIBLE_INTEGRATIONS and CJ_INTEGRATION_DESCRIPTIONS
        - Add new check in cj_get_installed_integrations
        - Add new setting on settings page
        - Extend CJTagInterface, adding an object of your class with cj_add_site_tag_obj
        - Add to cj_register_integrations

*/

const CJ_ALL_POSSIBLE_INTEGRATIONS = array('WooCommerce', 'Gravity Forms');
const CJ_INTEGRATION_DESCRIPTIONS = array(
    'WooCommerce'=>'The tracking code will be added to the thank you page.',
    'Gravity Forms' => 'The tracking info will be sent to CJ when any forms containing a pricing field is submitted.'
);

interface CJTagInterface{
    public function getPageType(): string;
    public function getReferringChannel(): string;
    public function getCartSubtotal();
    public function getOrderSubtotal();
    public function getItems();
    public function isThankYouPage(): bool;
    // for the conversion tag
    public function getOrderId(): string;
    public function getCurrency(): string;
    public function getDiscount();
    public function getCoupon(): string;

    // optional
    // the following will only be used if they are present in your class
    // public function turnOnManualOrderSending(): bool // when true, you must add your own JS to send the order,
        // can be done with cjAPI.sendOrder(cj.order), otherwise it is automatically sent onLoad

}

function cj_register_integrations(){

    static $already_registered;
    if ($already_registered)
        return;
    $already_registered = true;

    // TODO remove this
    $settings = get_cj_settings();
    $use_deprecated = ! isset($settings['enterprise_id']) || ! $settings['enterprise_id'];
    if ($use_deprecated)
        return;

    $integrations = cj_get_integrations();

    if (in_array('WooCommerce', $integrations)){
        include_once CJ_TRACKING_PLUGIN_PATH . 'woocommerce/wc_tags.php';
        cj_add_site_tag_obj( new CJ_WC_Tag() );
    }
    if (in_array('Gravity Forms', $integrations)){
        include_once CJ_TRACKING_PLUGIN_PATH . 'gravity-forms/gravity-forms.php';
        cj_add_site_tag_obj( new CJ_GF_Tag() );
    }

    // This should always be last
    cj_add_site_tag_obj(new CJ_Site_Tag_Defaults());
}

function cj_make_url_friendly($input){
    $url_friendly = strtolower( str_replace(array(' ', '&'), '', $input) );
    return $url_friendly;
}

$cj_url_friendly_integration_mapping = array();
foreach (CJ_ALL_POSSIBLE_INTEGRATIONS as $integration){
    $cj_url_friendly_integration_mapping[cj_make_url_friendly($integration)] = $integration;
}

function cj_get_integrations(){
    return array_intersect( cj_get_installed_integrations(), cj_get_integrations_enabled_not_necessarily_installed() );
}

/* If the plugin is installed, but the setting is still enabled */
function cj_get_installed_integrations(){
    $active_plugins = apply_filters('active_plugins', get_option('active_plugins'));
    $gf_plugin_active = false;
    $wc_plugin_active = false;
    foreach($active_plugins as $p){
        // The folder name of the gravity forms plugin could have been modified by the user when the plugin was uploaded
        // But we can be fairly certain that there will continue to be a file called gravityforms.php
        if ( endsWith($p, '/gravityforms.php') || endsWith($p, '\\gravityforms.php') ){
            $gf_plugin_active = true;
        }
        if ( endsWith($p, '/woocommerce.php') || endsWith($p, '\\woocommerce.php') ){
            $wc_plugin_active = true;
        }
    }

    $ret = array();
    if ($wc_plugin_active)
        $ret[] = 'WooCommerce';
    if ($gf_plugin_active)
        $ret[] = 'Gravity Forms';
    return $ret;
}

/* Use get_integrations to find integrations that where both enabled in the settings menu and have an active plugin associated with them */
function cj_get_integrations_enabled_not_necessarily_installed(){
    global $cj_url_friendly_integration_mapping;
    $opts = get_option( 'ow_cj_tracking' );

    if ( ! isset($opts['integrations']) || ! is_array($opts['integrations'])){
        return array();
    }

    /* I don't know how this is possible since it's set earlier in this file, but somehow
    it's null on plugin activation
    which results in an error message being shown on the plugins page when the plugin is activated
    Problem seemed to start happening after I added the activation/deactivation hooks to cj-tracking.php
    This works around the problem, but TODO fix this better */
    if ( is_null($cj_url_friendly_integration_mapping) ){
        return array();
    }

    $ret = array();
    foreach($opts['integrations'] as $url_friendly => $enabled){
        if ($enabled)
            $ret[] = $cj_url_friendly_integration_mapping[$url_friendly];
    }

    return $ret;
}

function cj_get_uninstalled_and_installed_integrations(){
    return CJ_ALL_POSSIBLE_INTEGRATIONS;
}
