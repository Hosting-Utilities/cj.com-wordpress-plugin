<?php

global $cj_gf_current_entry;

class CJ_GF_Tag implements CJTagInterface{
    /* Functions should return a falsey value as a default.
        code that use this class should then proceed to the next registered integration upon receiving a falsey value */

    public function get_entry(){
        global $cj_gf_current_entry;
        return $cj_gf_current_entry;
    }

    public function turnOnManualOrderSending(){
        return true; // we will handle it in the gform_confirmation filter
    }

    /* Does this form have any products?
    only works on thank you pages */
    private function isPaymentForm(){
        global $cj_gf_current_entry;
        return $cj_gf_current_entry && ($data = gform_entry_data()) && isset($data['item']) && count($data['item']) > 0;
    }

    public function isThankYouPage(): bool{
        // return if CJ tracking is not enabled for this form
        $settings = get_cj_settings();
        if ( isset($settings['enabled_gravity_forms']) && $settings['enabled_gravity_forms']){
            $entry = $this->get_entry();
            if ($entry === null) // ie gform_confirmation filter hasn't fired
                return false;
            if ( ! isset($settings['enabled_gravity_forms'][$entry['form_id']]) ){
                return false;
            }
        }

        if (! (isset($_POST['gform_ajax']) || isset($_POST['gform_submit']) ) || ! $this->isPaymentForm() /* don't add CJ tracking code if this is not a payment form e.g. it's a contact form */ )
            return false;

        // $gform_ajax = array();
        // parse_str($_POST['gform_ajax'], $gform_ajax); // parses query params and stores them in $gform_ajax
        // if ( ! is_post_publicly_viewable() || ! is_post_publicly_viewable($gform_ajax['form_id']) )
        //     return false;

        return true;
    }
    public function getOrderId(): string{
        global $cj_gf_current_entry;

        if ( ! isset($cj_gf_current_entry['id'])){
            trigger_error('Failed to retreive the form ID for the CJ tracking code ', E_USER_WARNING);
            return '';
        }
        return $cj_gf_current_entry['id'];
    }
    public function getCurrency(): string{
        if ($this->isThankYouPage())
            return apply_filters('gform_currency', '');
        return '';
    }
    public function getDiscount(){
        if ($this->isThankYouPage()){
            if ( ($data = gform_entry_data()) && isset($data['discount']) )
                return (float)$data['discount'];
        }
        return 0.0;
    }
    public function getCoupon(): string{
        if ($this->isThankYouPage()){
            if ( ($data = gform_entry_data()) && isset($data['coupon']) )
                return $data['coupon'];
        }

        return '';
    }

    /* Forms can still be added in other places other than the post content and
    we're currently not checking for whitespace after left bracket in the shortcode
    or extra whitespace in the comment block editor comment
    */
    private function has_gform_shortcode_in_content(){
        if ( ! isset($_GET['post_id']))
            return false;

		$post = get_post(intval($_GET['post_id']));

        if ( ! isset( $post->post_content ))
            return false;

		return strpos( $post->post_content,'[gravityform' ) !== false || strpos( $post->post_content,'<!-- wp:gravityforms/form' ) !== false;
    }

    public function getPageType(): string{

        if ($this->isThankYouPage())
            return 'conversionConfirmation';

        if ($this->has_gform_shortcode_in_content()){
            return 'applicationStart';
        }

        return '';
    }
    public function getReferringChannel(): string{
        return '';
    }
    public function getCartSubtotal(){
        if ( $this->isThankYouPage() && ($data = gform_entry_data()) )
            return (float)$data['subtotal'];
        return 0.0;
    }
    public function getOrderSubtotal(){
        if ( $this->isThankYouPage() && ($data = gform_entry_data()) )
            return (float)$data['subtotal'];
        return 0.0;
    }
    public function getItems(){
        $ret = array();

        if ( $this->isThankYouPage() && ($data = gform_entry_data()) ){

            $qty_total = array_sum(array_map('intval', $data['qty']));

            for($i=0; $i<count($data['item']); $i++){

                $qty = (int)$data['qty'][$i];
                $product_discount = (isset($data['discount']) ? $data['discount'] : 0) * ($qty / $qty_total);

                $ret[] = array(
                    'unitPrice' => $data['amt'][$i] - $product_discount,
                    'itemId' => $data['item'][$i],
                    'quantity' => (int)$data['qty'][$i],
                    'discount' => $product_discount,
                );
            }

        }

        return $ret;
    }
}

add_filter( 'gform_confirmation', function($confirmation, $form, $entry, $is_ajax){
    global $cj_gf_current_entry;
    $cj_gf_current_entry = $entry;

    $e = wp_json_encode($entry);
    $f = wp_json_encode($form);
    include CJ_TRACKING_PLUGIN_PATH . 'inc/get_order_data.php';

    $order = get_order_data();
    global $cj_gf_current_entry;
    //$order['temp'] = gform_entry_data($cj_gf_current_entry);
    $order = wp_json_encode($order);

    //$url = plugins_url('/assets/tag.js', CJ_TRACKING_PLUGIN_PATH.'/placeholder') . '?ver=' . CJ_TRACKING_PLUGIN_VERSION;

    // Since Gravity Forms loads the confirmation message in an iframe, we need to load the JavaScript again
    $url = admin_url('admin-ajax.php') . '?action=cj_com_js';

    if ( is_array($confirmation) && isset($confirmation['redirect']) ){

        if ( isset($settings['gf_confirmation_workaround']) && $settings['gf_confirmation_workaround']){
            // By returning a string instead of the confirmation object, 
            // the current page will load the javascript in our confirmation message instead of redirecting to a different page
            $confirmation = ''; 
        } else {
            // Without the workaround we will redirect, and won't be able to give it any JavaScript to load
            // So let's attempt to just send the data to CJ right now without using their tag.js API
            // This is probably not being done correctly, and we will beed feedback from CJ on how to do send them the CJ data server-side
            $settings = get_cj_settings();
            $url2 = 'https://www.mczbf.com/tags/' . (isset($settings['tag_id']) ? $settings['tag_id'] : '') . '/tag.js';
            $response = wp_remote_post( $url2, array('body' => $order) );

            if ( is_wp_error( $response ) ) {
                $error_message = $response->get_error_message();
                return "Something went wrong sending data to CJ: $error_message";
            }

            return;
        }
    }

    $confirmation .=
<<<"JS_SCRIPT"
<script src='$url' id='cjapitag'></script>
<script>
    if (!window.cj) window.cj = {};
    cj.order = $order;
    window.cjApi.sendOrder(cj.order)
</script>
JS_SCRIPT;

    return $confirmation;

}, 10, 4 );









// TODO would be better to use https://www.php.net/manual/en/book.bc.php for floating point operations (This would require using string data types for all pricing variables)

// Hack to fix my use of the list() function in a way that was not supported in PHP 5.x
//extract(cj_extract_prep( array('name','price'), $arr )); is the same as list($name, $price) = $arr; but it's more compatable with older PHP
function cj_extract_prep($ToExtract, $arr){
    if (count($ToExtract) > count($arr))
        trigger_error('not enough items in $arr' . '(' . count($ToExtract) . ' > ' . count($arr) . ')', E_USER_NOTICE);
    $ret = array();
    $a = array_values($ToExtract);
    $b = array_values($arr);
    for ($i=0; $i < count($a); $i++) {
        $ret[$a[$i]] = $b[$i];
    }
    return $ret;
}

/* returns if an item should be recorded
 the global variable $cj_blank_field_handling should be set to either: 'report_all_fields', 'ignore_blank_fields', or 'ignore_0_dollar_items'
*/
function cj_should_report_item($price, $field_filled_in){
    global $cj_blank_field_handling;

    // make sure that no matter what we alway report items that have a price attached
    if ($price > 0)
        return true;

    $blank_field_handling = $cj_blank_field_handling ? $cj_blank_field_handling : 'report_all_fields';

    switch ($blank_field_handling){
        case 'ignore_blank_fields':
            // This won't be able to detect user defined fields because they look the same when they are blank vs not blank
            return $field_filled_in;
        case 'ignore_0_dollar_items':
            $left_blank = ! $field_filled_in;
            return ! ($price == 0 || $left_blank);
    }

    return true;
}

function cj_get_field_data($field, $entry){
    global $product_quantities;
    $item_quantity = 1;
    $multi_choice_field = false;

    if ( $field->inputType === 'singleshipping' ){
        $item_name = '';
        $item_total = (float)str_replace('$', '', $entry[(string)$field->id]) ?: 0;
        $item_quantity = 1;

    } else if ( in_array($field->inputType, array('singleproduct', 'calculation', 'hiddenproduct')) ){ /* other single fields */
        $item_name = $entry[(string)$field->id . '.1'] ?: '';
        $item_total = (float)str_replace('$', '', $entry[(string)$field->id . '.2']) ?: 0;
        $item_quantity = (int)$entry[(string)$field->id . '.3'] ?: 0;

    } else if ($field->inputs){ /* checkbox */
        $item_name = '';
        $item_total = 0;
        $multi_choice_field = true;

        foreach($field->inputs as $input){
            $name_and_price = $entry[$input['id']];

            if ($name_and_price){
                //list($name, $price) = explode('|', $name_and_price, 2); // PHP 7 only :(
                extract(cj_extract_prep( array('name','price'), explode('|', $name_and_price, 2) ));
                if ($item_name)
                    $item_name .= '+';
                $item_name .= $name;
                $item_total += $price;
            }
        }

    } else if ( isset($field->choices) ){ /* drop downs & radio */
        $multi_choice_field = true;
        $name_and_price = $entry[(string)$field->id];
        //list($name, $price) = explode('|', $name_and_price, 2); // PHP 7 only :(
        if ( ! empty($name_and_price) ){
            extract(cj_extract_prep( array('name','price'), explode('|', $name_and_price, 2) ));
            $item_name = $name;
            $item_total = $price;
        } else {
            $item_name = '';
            $item_total = 0;
        }

    } else if ( $field->inputType === 'price' ){ /* user defined price */
        $item_name = $field->label;
        $item_total = (float)str_replace('$', '', $entry[(string)$field->id]) ?: 0;
        $item_quantity = 1;

    } else {
        $item_name = 'unknown';
        $item_total = 0;
        $item_quantity = 1;
        GFCommon::log_debug( 'Unknown Input Type: ' . $field->inputType );
        wp_die('Form submitted, but... The CJ Affiliates plugin failed because of an Unknown Gravity Forms field. Please contact us.');
    }

    $item_quantity = (isset($product_quantities[$field->id]) && $product_quantities[$field->id] !== '') ? $product_quantities[$field->id] : $item_quantity;
    $blank_name = $item_name === '';

    if ( $multi_choice_field && ! $blank_name ){
            $item_name = $field->label . '|' . $item_name;
    } else {
        /* The item name appears to give the same result as the field label, but for the user defined field the item name is always
        just "User defined field" which is gross */
        $item_name = isset($field->label) && $field->label !== '' ? $field->label : $field->id;
    }

    return array(
        'name' => $item_name,
        'price' => $item_total,
        'quantity' => $item_quantity,
        'id' => $field->id,
        'filled_in' => !$blank_name
    );
}


/*
    This is a rehack of the old filter that would put together a URL for the CJ tracking code
    and send a request to it. Now instead of doing that we use parse_str at the very end to
    change a URL into an array, and return this info to be used elsewhere when gathering data
    to stick in the browser in window.cj.order
*/
function gform_entry_data($entry=null){
    global $itemCount, $product_options, $product_quantities, $total_before_discounts, $cj_blank_field_handling, $cj_gf_current_entry;

    static $ret;
    if ($ret !== null){
        return $ret;
    }

    if ($entry === null)
        $entry = $cj_gf_current_entry;

    $form = GFAPI::get_form( (int)$entry['form_id'] );
    //var_dump($form); exit;
    //var_dump($entry); exit;

    $settings = apply_filters('cj_settings', get_cj_settings(), 'gravity_forms', $form, (int)$entry['form_id']);

    // Don't add the tracking code if we got back false (While allowing an error to naturally happen if we get back null as null could mistakenly get returned if the return statement is forgotten)
    if ( $settings === false )
        return false;

    // $tag_id = $settings['tag_id'];
    // $cid = $settings['cid'];
    // $type = $settings['type'];
    $currency = 'usd';
    $order_id = $entry['id'];
    $cjevent = isset($_COOKIE['cje']) ? $_COOKIE['cje'] : '';
    $gf_id = $form['id'];
    $gf_title = str_replace( array('?', '&'), array('', ''), $form['title'] );
    $cj_blank_field_handling = isset($settings['blank_field_handling']) ? $settings['blank_field_handling'] : 'report_all_fields';

    $product_options = array();
    $product_quantities = array();
    $itemCount = 0;

    function storeQuantityField($url, $field, $entry){
        global $product_quantities;
        $product_quantities[$field->productField] = $entry[$field->id];
    }
    function storeOptionField($url, $field, $entry){
        global $product_options;
        $prodID = (int)$field->productField;
        if (array_key_exists($prodID, $product_options)){
            // PHP 7 only :(
            //list('name'=>$old_opt_name, 'price'=>$old_opt_total, 'quantity'=>$old_opt_quantity) = $product_options[$prodID];
            //list('name'=>$opt_name, 'price'=>$opt_total, 'quantity'=>$opt_quantity) = cj_get_field_data($field, $entry);
            extract(cj_extract_prep( array('old_opt_name','old_opt_total', 'old_opt_quantity', 'old_id', 'old_filled_in'), $product_options[$prodID] ));

            extract(cj_extract_prep( array('opt_name', 'opt_total', 'opt_quantity', 'opt_id', 'opt_filled_in'), cj_get_field_data($field, $entry) ));

            // There isn't a way to preserve price/quantity when we are merging two different price/quantity combos.
            // We could take the average, but that could result in some long decimal numbers.
            // Instead I'm combing the prices and setting the quantity to one.
            // I think only products don't actaully have quantities, so this seems like a reasonable way of doing things.
            if ($opt_total || cj_should_report_item($opt_total, $opt_filled_in || $old_filled_in)){ // $opt_filled_in || $old_filled_in should be the same
                $product_options[$prodID] = array(
                    'name' => $old_opt_name . '+' . $opt_name,
                    'price' => $old_opt_total*$old_opt_quantity + $opt_total*$opt_quantity,
                    'quantity' => $opt_quantity,
                    'id' => $opt_id,
                    'opt_filled_in' => $opt_filled_in
                );
            }

        } else {
            $product_options[$prodID] = cj_get_field_data($field, $entry);
        }
    }
    function handleProductField($url, $field, $entry){
        global $itemCount, $product_quantities, $product_options, $total_before_discounts;
        $itemCount += 1;

        //list('name'=>$item_name, 'price'=>$item_total, 'quantity'=>$item_quantity) = cj_get_field_data($field, $entry); // PHP 7 only :(
        extract(cj_extract_prep( array('item_name','item_total', 'item_quantity', 'id', 'filled_in'), cj_get_field_data($field, $entry) ));

        if ( isset($product_options[(int)$field->id]) ){
            //list('name'=>$opt_name, 'price'=>$opt_price, 'quantity'=>$opt_quantity) = $product_options[(int)$field->id]; // PPH 7 only :(
            extract(cj_extract_prep( array('opt_name','opt_price', 'opt_quantity', 'id', 'opt_filled_in'), $product_options[(int)$field->id] ));
            if (isset($opt_name[1]) && $opt_name[0] === '+')
                $opt_name = substr($opt_name, 1);
            if ($opt_name && cj_should_report_item($opt_price, $opt_filled_in) )
                $item_name .= ':'.$opt_name;
            if ($opt_price)
                $item_total += (float)$opt_price * ((float)$opt_quantity ?: 1);
        }

        // var_dump($item_name);
        // var_dump($item_quantity);
        // var_dump($item_total);
        if ( cj_should_report_item($item_total, $filled_in) ){
            $total_before_discounts += $item_total * $item_quantity;
            $item_name = cj_sanitize_item_name($item_name);
            $url .= "ITEM[]=$item_name&AMT[]=$item_total&QTY[]=$item_quantity&";
        } else {
            $itemCount -= 1;
        }
        return $url;
    }

    function handleShippingField($url, $field, $entry){

        return $url; // Skip shipping fields, CJ does not want these included

        global $itemCount, $total_before_discounts;
        $itemCount += 1;

        //list('name'=>$item_name, 'price'=>$item_total) = cj_get_field_data($field, $entry); // PHP 7 only :(
        extract(cj_extract_prep( array('item_name','item_total', '_', '_', 'filled_in'), cj_get_field_data($field, $entry) ));
        $item_name = $item_name ? 'Shipping:'.$item_name : 'Shipping';
        $item_quantity = 1;

        if ( cj_should_report_item($item_total, $filled_in) ){
            $total_before_discounts += $item_total * $item_quantity;
            $item_name = cj_sanitize_item_name($item_total, $filled_in);
            $url .= "ITEM$itemCount=$item_name&AMT$itemCount=$item_total&QTY$itemCount=$item_quantity&";
        } else {
            $itemCount -= 1;
        }
        return $url;
    }

    function handleCouponField($url, $field, $entry, $total){
        // TODO this doesn't work with multiple coupons
        global $total_before_discounts;
        $discount_total = $total_before_discounts - $total;
        // $discount_total = 0;
        // $json = json_decode($field->get_value_details($entry[$field->id]));
        // foreach($json as $coupon){
        //     if ($coupon->type === 'flat')
        //         $discount_total += $coupon->amount;
        //     if ($coupon->type === 'percentage')
        //         $discount_total += ($coupon->amount/100) * ($current_total-$discount_total); // TODO check if we need to update current_total after each coupon or not
        // }
        $coupon_names = $entry[$field->id];

        if ($discount_total >= .01 /* attempt to ignore floating point errors */ || $coupon_names){
            $url .= "COUPON=$coupon_names&";
            $url .= "DISCOUNT=$discount_total&";
        }
        return $url;
    }

    $url = "?OID=$order_id&CJEVENT=$cjevent&CURRENCY=$currency&form_name=$gf_title&gravity_form_id=$gf_id&";
    $form_total = 0;
    $total_before_discounts = 0;

    foreach ($form['fields'] as $field){
        if ($field->type === 'quantity'){
            storeQuantityField($url, $field, $entry);
        }
    }
    foreach ($form['fields'] as $field){
        if ($field->type === 'option'){
            storeOptionField($url, $field, $entry);
        } else if ($field->type === 'total'){
            $total_price = $entry[$field->id];
        }
    }

    foreach ($form['fields'] as $field){
        switch ($field->type){
            case 'product':
                $url = handleProductField($url, $field, $entry);
                break;
            case 'shipping':
                $url = handleShippingField($url, $field, $entry);
                break;
        }
    }

    foreach ($form['fields'] as $field){
        if ($field->type === 'coupon'){
            $url = handleCouponField($url, $field, $entry, $total_price);
        }
    }

    // echo str_replace('&', '&<br/>', $url);
    // exit;

    if ( substr($url, -1) === '&' )
        $url = substr($url, 0, -1);

    if ($itemCount){
        $ret = array();

        parse_str(ltrim($url, '?'), $ret); // Fills $ret with data from the URL

        $ret = array_change_key_case($ret, CASE_LOWER);
        $ret['total'] = $form_total;
        $ret['subtotal'] = $total_before_discounts;

        GFCommon::log_debug( 'cj.com data: ' . var_export($ret, true) );
        //GFFormsModel::add_note($entry['id'], get_current_user_id(), wp_get_current_user(get_current_user_id())->user_login, "The following order data will be sent to CJ:\n" . esc_url_raw($url));
        GFFormsModel::add_note(
                        $entry['id'],
                        get_current_user_id(),
                        wp_get_current_user(get_current_user_id())->user_login,
                        "The following order data will be sent to CJ:\n" . json_encode($ret)
                    );

        return $ret;

    } else {
        $ret = false;
    }
    return $ret;
}

// this is mostly redundant since cookies are now being saved with JS
// but doing this allows the correct cookies to be set on the initial page load
add_filter('init', function(){
    $GET_copy = array_change_key_case($_GET, CASE_LOWER);

    if ( isset($_GET['cjevent']) || isset($_GET['publishercid']) ){

          $days_in_month = 31;
          $days = empty($settings['cookie_duration']) ? 13*$days_in_month : (int)$settings['cookie_duration'];
          $domain = parse_url(home_url())['host'];
          $domain = preg_replace('/^www\./', '', $domain); // remove www in front
          $use_ssl = preg_match('/.local$/', $domain) ? true : is_ssl(); // only allow non-ssl cookies in dev environments

          if (isset($GET_copy['publishercid'])){
              setcookie("publisherCID", $GET_copy['publishercid'], time() + DAY_IN_SECONDS*$days, '/', $domain, $use_ssl, false);  /* expire in 120 days */
              GFCommon::log_debug( 'Got publisherID ' . $GET_copy['publishercid'] );
          }
          if (isset($GET_copy['cjevent'])){
              setcookie("cjevent", $GET_copy['cjevent'], time() + DAY_IN_SECONDS*$days, '/', $domain, $use_ssl, false );
              GFCommon::log_debug( 'Got cjevent ' . $GET_copy['cjevent'] );
          }
    }

});
