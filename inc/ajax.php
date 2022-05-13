<?php

/* callback for the feedback form */
function cj_tracking_contact_us_ajax_callback() {
    $current_user = wp_get_current_user();
    $_POST = array_map( 'stripslashes_deep', $_POST ); // necessary because of https://wpartisan.me/tutorials/wordpress-auto-adds-slashes-post-get-request-cookie
    if ( check_ajax_referer( 'cj-tracking-feedback', 'security', false ) ) {

        $urlparts = parse_url(home_url());
        $current_domain = $urlparts['host'];

        $from_name = str_replace(array('"', '\\'), '', $current_user->display_name);
        $from_email = filter_var($current_user->user_email, FILTER_SANITIZE_EMAIL);
        $headers[] = "Content-Type: text/html; charset=UTF-8";
        $headers[] = "From: \"CJ Contact Form\" <cjquestion@$current_domain>";
        $headers[] = "Reply-To: \"$from_name\" <$from_email>";

        /* remove all tags except newlines */
        $body = $_POST['message'];
        // $body = str_replace("<br/>", "\n", $body);
        // $body = strip_tags($body);
        $body = str_replace("\n", "<br/>", $body);

        $res = wp_mail( 'russell@wp-overwatch.com', 'CJ Event ticket from ' . get_bloginfo(), $body, $headers );

        if ($res)
            exit('success');

    } else {
        wp_die('Message could not be sent: security check failed');
    }
}
add_action( 'wp_ajax_cj_tracking_contact_us', 'cj_tracking_contact_us_ajax_callback' );

/* callback for uninstall button */
function cj_tracking_uninstall_ajax_callback() {
    if ( check_ajax_referer( 'cj-tracking-uninstall', 'nonce', false) ) {

        if (! get_option('ow_cj_tracking'))
            exit("                                            Success      \n".
                 "     Easy peasy, there wasn't anything that needed deleting");

       $res = delete_option('ow_cj_tracking');

       if ($res)
          exit('success');
       exit('failed to delete plugin data');

 } else {
   wp_die('Could not process uninstall: security check failed');
 }
}
add_action( 'wp_ajax_cj_tracking_uninstall', 'cj_tracking_uninstall_ajax_callback' );
