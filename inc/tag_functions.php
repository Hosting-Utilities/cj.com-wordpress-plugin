<?php

require __DIR__ . '/tag_defaults.php';

global $CJ_Site_tag_objects;
$CJ_Site_tag_objects = [];

function cj_add_site_tag_obj(CJTagInterface $obj){
    global $CJ_Site_tag_objects;
    array_push($CJ_Site_tag_objects, $obj);
}

function cj_is_conversion_tracking_page(){
    global $CJ_Site_tag_objects;
    foreach($CJ_Site_tag_objects as $obj){
        if ($obj->isThankYouPage()){
            return true;
        }
    }
    return false;
}

/* This should be called after all of the integrations have had time to add a site tag */
function cj_add_default_integration(){
    static $already_added;
    if ($already_added)
        return;
    cj_add_site_tag_obj(new CJ_Site_Tag_Defaults());
    $already_added = true;
}

// data shared between site tag and conversion tag
function cj_get_shared_tag_data(){
    global $cj_site_tag_page_types, $cj_refferring_channels, $CJ_Site_tag_objects;

    $cj_acct_info = get_cj_settings();
    $email = trim(wp_get_current_user()->user_email ?: 'anonymous' );
    $email_hash = hash('sha256', strtolower($email));
    $page_type = false;
    $referring_channel = false;
    $items = array();

    foreach($CJ_Site_tag_objects as $obj){
        if (! $page_type)
            $page_type = $obj->getPageType();
        if (! $referring_channel)
            $referring_channel = $obj->getReferringChannel();
        $items = array_merge($items, $obj->getItems());
    }

    if (count($items) > 100){
        $items = array_slice($items, 0, 100);
        trigger_error('Truncated number of items sent in CJ tracking code. (' . count($items) . ' items in cart, CJ only supports 100)', E_USER_WARNING);
    }
    foreach($items as $item){
        $item['itemId'] = cj_sanitize_item_name($item['itemId']);
        $item['unitPrice'] = (float)$item['unitPrice'];
        $item['quantity'] = (int)$item['quantity'];
    }

    if ( ! in_array($page_type, $cj_site_tag_page_types)){
        throw new Exception('Invalid page type "' . $page_type . '"');
    }

    if ( ! in_array($referring_channel, $cj_refferring_channels)){
        throw new Exception('Invalid referring channel "' . $referring_channel . '"');
    }

    return array(
         //'enterpriseId' => get_cj_settings()['enterprise_id']??'', No PHP 7 yet :(
         'enterpriseId' => isset($cj_acct_info['enterprise_id']) ? $cj_acct_info['enterprise_id'] : '',
         'pageType' => $page_type,
         'userId' => get_current_user_id() ?: 'guest',
         'emailHash' => $email_hash,
         'referringChannel' => $referring_channel,
         'items' => $items
    );
}

global $cj_site_tag_page_types;
$cj_site_tag_page_types = array(
    'accountCenter', // All Verticals // Any pages within the account center after the user has logged in
    'accountSignup', // All Verticals
    'applicationStart', // Finance Only
    'branchLocator', // Finance Only
    'cart', // Retail, Network Services, and Travel Only
                     // Pass all items in the cart, if possible
                     // Follow the format outlines in the Site Tag code example
    'category', // Retail, Network Services, and Finance Only
    'conversionConfirmation', // All Verticals
    'department', // Retail, Network Services, and Finance Only
    'homepage', // All Verticals
    'information', // Travel Only
    'productDetail', // Retail, Network Services, and Finance Only
    'propertyDetail', // Travel Only
    'propertyResults', // Travel Only
    'searchResults', // All Verticals
    'storeLocator', // Retail and Network Services Only
    'subCategory', // Retail, Network Services, and Finance Only
);

global $cj_refferring_channels;
$cj_refferring_channels = array(
    'Affiliate',
    'Display',
    'Social',
    'Search',
    'Email',
    'Direct_Navigation'
);
