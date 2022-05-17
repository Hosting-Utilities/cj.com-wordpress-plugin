<?php

if (! function_exists('endsWith')){
    function endsWith($haystack, $needle){
        return strrpos($haystack, $needle, 0) === strlen($haystack) - strlen($needle);
    }
}

function get_cj_settings(){

    if ( CJ_TRACKING_RUN_UNIT_TESTS === true ){

      $tag_id = 'XXXXX';
      $cid = 'XXXXXXX';
      $type = 'XXXXXXX';
      $cj_tracking_note_urls = false;
      $other_params = '';

      $implementation = 'testing';
      $enterprise_id = 'XXX';
      $action_tracker_id = 'XXXX';
      $storage_mechanism = 'cookies';
      $limit_gravity_forms = false;
      $enabled_gravity_forms = array();
      $blank_field_handling = 'report_all_fields';

    } else {
      // Get account specific info from DB

      $account_info = get_option( 'ow_cj_tracking', $default=false );

      // Check if the account info was set
      if ( $account_info === false ){
        trigger_error("Unable to display the cj.com tracking code because the account info was never saved.", E_USER_WARNING);
        echo "<!-- Failed to retreive cj.com tracking code. See the error log for more info. -->";
        return false;
      }

      $implementation        = isset($account_info['implementation'])        ? $account_info['implementation']        : CJ_TRACKING_DEFAULT_IMPLEMENTATION;
      $enterprise_id         = isset($account_info['enterprise_id'])         ? $account_info['enterprise_id']         : '';
      $action_tracker_id     = isset($account_info['action_tracker_id'])     ? $account_info['action_tracker_id']     : '';
      $cj_tracking_note_urls = isset($account_info['order_notes'])           ? $account_info['order_notes']           : '';
      $other_params          = isset($account_info['other_params'])          ? $account_info['other_params']          : '';
      $storage_mechanism     = isset($account_info['storage_mechanism'])     ? $account_info['storage_mechanism']     : 'cookies';
      $limit_gravity_forms   = isset($account_info['limit_gravity_forms'])   ? $account_info['limit_gravity_forms']   : false;
      $enabled_gravity_forms = isset($account_info['enabled_gravity_forms']) ? $account_info['enabled_gravity_forms'] : array();
      $blank_field_handling  = isset($account_info['blank_field_handling'])  ? $account_info['blank_field_handling']  : 'report_all_fields';

      // no longer used, except to show warning message if the tag ID is present
      $tag_id                = isset($account_info['tag_id'])                ? $account_info['tag_id']                : '';
      $cid                   = isset($account_info['cid'])                   ? $account_info['cid']                   : '';
      $type                  = isset($account_info['type'])                  ? $account_info['type']                  : '';

      // Ensure we have all of the account info we need
      if ( $enterprise_id  === '' || $action_tracker_id === '' ){

        // get a comma seperated list of the missing account info
        $missing = implode( ", and ", array_filter( array(
          $enterprise_id ? false : "enterprise id",
          $action_tracker_id ? false : "action tracker id"
        ) ) );

        trigger_error("Incomplete cj.com account info. Did not receive the {$missing}.", E_USER_WARNING);
        if ( isset($_GET['action']) && $_GET['action']==='cj_site_tag_data' )
            echo "<!-- Failed to retreive cj.com tracking code. See the error log for more info. -->";
        return false;
      }
    }

    return array(
        'implementation' => $implementation,
        'enterprise_id' => $enterprise_id,
        'action_tracker_id' => $action_tracker_id,
        'tag_id' => $tag_id,
        'cid' => $cid,
        'type' => $type,
        'notate_urls' => false, /* No longer used */
        'notate_order_data' => $cj_tracking_note_urls,
        'other_params' => $other_params,
        'storage_mechanism' => $storage_mechanism,
        'limit_gravity_forms' => $limit_gravity_forms,
        'enabled_gravity_forms' => $enabled_gravity_forms,
        'blank_field_handling' => $blank_field_handling
    );

}

function cj_sanitize_item_name($item_name){
/* CJ has created the following rules for the item name (from https://developers.cj.com/docs/tracking-integration/advanced-integration):
- A maximum of 100 sets of item-based values is supported for each order
- The system supports an alphanumeric string with dashes or underscores only. Spaces and other characters are not permitted.
- Item ID values must be less than or equal to 100 characters.
*/

    // TODO would be better if I just didn't add : , and + to the item name to start with.
    // Would be even better if CJ were to ever remove this restriction. Perhaps I can talk to them about seeing if they can allow some of these characters.
    $item_name = str_replace(array(':', '+', ',', '|', ' '), array('___', '__', '_', '--', '_'), $item_name);
    $item_name = preg_replace("/[^A-Za-z0-9-_]+/", "", $item_name);
    return substr($item_name, 0, 100);
}
function cj_sanitize_order_id($item_name){
    return substr(cj_sanitize_item_name($item_name), 0, 96);
}
