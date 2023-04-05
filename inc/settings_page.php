<?php
include_once __DIR__ . '/integrations.php';

const SHOW_EMAIL_FORM = false;

if (! function_exists('endsWith')){
	function endsWith($haystack,$needle){ // case insensitive version
	    $expectedPosition = strlen($haystack) - strlen($needle);
	    return strripos($haystack, $needle, 0) === $expectedPosition;
	}
}

function cj_enqueue_css(){
    if ( isset($_GET['page']) && $_GET['page'] === 'cj-tracking-settings' ){
        wp_enqueue_style('cj_admin', plugins_url('/assets/admin_css.css', CJ_TRACKING_PLUGIN_PATH.'/placeholder'), array(), CJ_TRACKING_PLUGIN_VERSION);
    }
}
add_action('admin_head', 'cj_enqueue_css');

function cj_commit_rewrite_rules_for_proxy(){
    /*$proxy_path = wp_enqueue_style('cj_admin', plugins_url('/assets/admin_css.css', CJ_TRACKING_PLUGIN_PATH.'/placeholder'), array(), CJ_TRACKING_PLUGIN_VERSION);
    add_rewrite_rule('/cj-proxy/(.+)', 'wp-content/plugins/my-plugin/index.php?leaf=$1', 'top');
    flush_rewrite_rules();*/
}

function cj_create_toggle($setting, $isChecked, $yesno=false){
    ?>
    <!-- Provide a fallback that will be used when the toggle is unchecked (because unchecked checkboxes are not sent to the server) -->
    <!--<input type=hidden name="<?php echo $setting ?>" value='0' />-->

    <?php //echo var_export( filter_var($isChecked, FILTER_VALIDATE_BOOLEAN) ); ?>
    <input type="checkbox" value='1' class="ow-toggle <?php echo $yesno ? 'ow-toggle-yes-no' : 'ow-toggle-on-off' ?> square-toggle" id="ow-toggle-<?php echo $setting ?>" name="<?php echo $setting ?>" <?php echo (filter_var($isChecked, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '') ?>>
    <label for="ow-toggle-<?php echo $setting ?>" class="ow-toggle-label"></label>
    <?php
}

class CJTrackingSettingsPage
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Start up
     */
    public function __construct()
    {
        $this->integrations_installed = cj_get_installed_integrations();
        $this->integrations_installed_and_enabled = cj_get_integrations();
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
    }


    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {

        // if a checkbox is suppose to be checked by default, we do:
        // $new_input['opt'] = wp_validate_boolean( isset( $input['opt'] ) && $input['opt'] );
        // otherwise we do:
        // if ( isset( $input['opt'] ) ){
        //     $new_input['opt'] = wp_validate_boolean( $input['opt'] );
        // }

        $new_input = array();

        if ( isset( $input['implementation'] ) ){
            if ( in_array($input['implementation'], array('server_side_cookie', 'proxy', 'pixel'))){
                $new_input['implementation'] = $input['implementation'];
            } else {
                $new_input['implementation'] = CJ_TRACKING_DEFAULT_IMPLEMENTATION;
            }
        }

        if ( isset( $input['action_tracker_id'] ) )
            $new_input['action_tracker_id'] = sanitize_text_field( $input['action_tracker_id'] );

        if ( isset( $input['tag_id'] ) )
            $new_input['tag_id'] = sanitize_text_field( $input['tag_id'] );

        if ( isset( $input['cid'] ) )
            $new_input['cid'] = sanitize_text_field( $input['cid'] );

        if ( isset( $input['type'] ) )
            $new_input['type'] = sanitize_text_field( $input['type'] );

        if ( isset( $input['enterprise_id'] ) )
            $new_input['enterprise_id'] = sanitize_text_field( $input['enterprise_id'] );

        if ( isset( $input['order_notes'] ) )
            $new_input['order_notes'] = wp_validate_boolean( $input['order_notes'] );

        if ( isset( $input['other_params'] ) )
            $new_input['other_params'] = sanitize_textarea_field( $input['other_params'] );

		if ( isset( $input['storage_mechanism'] ) ){
			if ( in_array( $input['storage_mechanism'], array('woo_session') ) ){
				$new_input['storage_mechanism'] = $input['storage_mechanism'];
			} else {
				$new_input['storage_mechanism'] = 'cookies';
			}
		}

        if ( isset( $input['cookie_duration'] ) )
            $new_input['cookie_duration'] = empty($input['cookie_duration']) ? $input['cookie_duration'] : (int)$input['cookie_duration'];

        if ( isset( $input['limit_gravity_forms'] ) ){
            $new_input['limit_gravity_forms'] = wp_validate_boolean( $input['limit_gravity_forms'] );

            if ( isset($input['enabled_gravity_forms']) && is_array($input['enabled_gravity_forms']) ){
                foreach ($input['enabled_gravity_forms'] as $form => $val){
                    $url_friendly = (int)$form;
                    $new_input['enabled_gravity_forms'][$url_friendly] = $val;
                }
            }
        }

        $possible_options = array('report_all_fields', 'ignore_blank_fields', 'ignore_0_dollar_items');
        $new_input['blank_field_handling'] = isset($input['blank_field_handling']) && in_array($input['blank_field_handling'], $possible_options) ? $input['blank_field_handling'] : 'report_all_fields';

        $new_input['auto_detect_integrations'] = isset($input['auto_detect_integrations'])
                                                ? wp_validate_boolean($input['auto_detect_integrations'])
                                                /* default to true when we save the form and multiple integrations haven't been introduced yet,
                                                    so that as soon as a plugin that adds another integration is added we automatically start using it */
                                                : true;

        /* When there is only one integration available, then the ability to enable/disable integrations goes away.
            No consider the scenario where one plugin is enabled and the other is disabled, or they could both be disabled.
            The corresponding plugin is then deactivated leaving behind one inactive integration with no options to enable/disable the integration
            since the option goes away when there is only one available integration. We want to make sure that this one integration is always going to be enabled, and ideally we would
            hook into the activation hook to make this happen, but for now, I'm just going to re-enable the plugin when the form is submitted.
        */
        if ( count($this->integrations_installed) === 1 ){
            foreach ($this->integrations_installed as $integration){
                $url_friendly = $this->make_url_friendly($integration);
                $new_input['integrations'][$url_friendly] = true;
            }
        }
        else if ( isset($input['integrations']) && is_array($input['integrations']) ){
            foreach ($this->integrations_installed as $integration){
                $url_friendly = $this->make_url_friendly($integration);
                $new_input['integrations'][$url_friendly] = wp_validate_boolean( isset( $input['integrations'][$url_friendly] ) && $input['integrations'][$url_friendly] );
            }
        } else {
            foreach ($this->integrations_installed as $integration){
                $url_friendly = $this->make_url_friendly($integration);
                $new_input['integrations'][$url_friendly] = false;
            }
        }

        return $new_input;
    }

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        add_options_page(
            'CJ Tracking Settings',
            'CJ Tracking Code',
            'manage_options',
            'cj-tracking-settings',
            array( $this, 'create_admin_page' )
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {

        cj_commit_rewrite_rules_for_proxy();

        // Set class property
        $this->options = get_option( 'ow_cj_tracking' );
        ?>
        <div class="wrap">
            <h1><?php echo _e( 'CJ Account Info', 'cjtracking' ); ?></h1>
            <form method="post" action="options.php">
            <style>
                .implementation-specific{
                    display: none;
                }
                .implementation-specific.active{
                    display: table-row;
                }
            </style>

            <?php
                // This prints out all hidden setting fields
                settings_fields( 'ow_cj_tracking_group' );
                do_settings_sections( 'ow-cj-tracking-settings-page' );
                submit_button();
            ?>
            </form>

            <script>
                function cj_toggle_implementation_fields(){
                    let selected = document.getElementById('implementation').value
                    if (selected === 'server_side_cookie')
                        selected = 'ssc'
                    for (row of document.querySelectorAll('.implementation-specific')){
                        if (row.classList.contains('impl-'+selected)){
                            row.classList.add('active')
                        } else if (row.classList.contains('active')) {
                            row.classList.remove('active')
                        }
                    }
                }
                cj_toggle_implementation_fields()
                document.getElementById('implementation').addEventListener('change', cj_toggle_implementation_fields)
            </script>

            <?php
            $enabled_integrations = cj_get_integrations();

            $gf_plugin_enabled = in_array('Gravity Forms', $enabled_integrations);
            $wc_plugin_enabled = in_array('WooCommerce', $enabled_integrations);

            /* If none of the conditions listed within are true, don't display the info box */
            if ( $wc_plugin_enabled || $gf_plugin_enabled || empty(cj_get_installed_integrations()) || cj_get_integrations_enabled_not_necessarily_installed() !== array() ){
                ?><fieldset style="margin-top: 44px;padding: .75em 2em;border: 1px solid #e4e4e4; box-shadow: 0 0 1px #d2d2d2; background: #f1f1f1;"><?php
                    echo '<span class="implementation-specific impl-pixel">';
                        if ($wc_plugin_enabled && $gf_plugin_enabled){
                            echo '<legend style="background: #f1f1f1;">Enabled When</legend>';
                        }
                        if ( $wc_plugin_enabled ){
                            $pre = ($gf_plugin_enabled || count(cj_get_installed_integrations()) > 1) ? '<p>After a WooCommerce ' : '<p>After an ';
                            echo $pre . 'order has been placed, the tracking code (or pixel or whatever you\'re suppose to call the iframe) it will be added to the thank you page.</p>';
                        }
                        if ( $gf_plugin_enabled ){
                            $pre = $wc_plugin_enabled ? '<p>Tracking info will also be ' : '<p>Tracking will be ';
                            $enabled = 'one of the Gravity Forms';
                            if (! isset($this->options['enabled_gravity_forms']) ){
                                $enabled = 'one of the enabled Gravity Forms';
                            }
                            echo $pre . 'sent when ' . $enabled . ' is submitted, if that form contains pricing fields.</p>';
                        }
                    echo '</span>';

                    $implementation = isset($this->options['implementation']) ? $this->options['implementation'] : 'server side cookies';

                    $is_ssc_active = $implementation === 'server_side_cookie';
                    echo '<span class="implementation-specific impl-ssc ' . ($is_ssc_active ? 'active' : '') . '">';
                        $lookup = ($wc_plugin_enabled ? 'wc' : '_') . ($gf_plugin_enabled ? ',gf' : ',_');
                        $tracking = array(
                            '_,_' => 'No',
                            'wc,_' => 'WooCommerce cart and order data',
                            '_,gf' => 'Gravity Form submissions',
                            'wc,gf' => 'WooCommerce & Gravity Forms data',
                        )[$lookup];
                        echo '<p>' . $tracking . ' will be tracked using the server side cookie implemetation.</p>';
                    echo '</span>';

                    // can't be completely dry here because a dry implementation wouldn't change the text added server side for $implementation when the dropdown is changed
                    // although we could be a little bit better
                    $is_proxy_active = $implementation === 'proxy';
                    echo '<span class="implementation-specific impl-proxy ' . ($is_proxy_active ? 'active' : '') . '">';
                        $lookup = ($wc_plugin_enabled ? 'wc' : '_') . ($gf_plugin_enabled ? ',gf' : ',_');
                        $tracking = array(
                            '_,_' => 'No',
                            'wc,_' => 'WooCommerce cart and order data',
                            '_,gf' => 'Gravity Form submissions',
                            'wc,gf' => 'WooCommerce & Gravity Forms data',
                        )[$lookup];
                        echo '<p>' . $tracking . ' will be tracked using a proxy to communicate with CJ.</p>';
                    echo '</span>';

                    if (!$wc_plugin_enabled && !$gf_plugin_enabled){
                        if ( empty(cj_get_installed_integrations()) ){
                            echo '<p>Gravity Forms or WooCommerce must be installed to use this plugin. If you are trying to add tracking codes for some other plugin, <a href="https://tickets.wp-overwatch.com">send us a ticket</a>, and we will look into the feasability of adding a new integration for you.</p>';
                        } else if (cj_get_integrations_enabled_not_necessarily_installed() === array()){
                            /* In this case the fieldset is not getting outputted, so we don't need to do anything */
                            /* This should only happen when the plugin is first activated, and the settings form has never been saved */
                            //echo '<p>Thank you for installing the CJ tracking code plugin</p>';
                        } else {
                            /* The setting won't show if they uninstalled plugins until they where left with one integration. There is a safety mechanism that gets triggered when the form is submitted that corrects this */
                            echo '<p><span style="color:#f15353;font-weight:bold;">NOTHING IS ENABLED</span>. You must enable an integration by expanding out the <i>Advanced</i> options and slecting one of the <i>Available Integrations</i>. <br/><br/>If you don\'t see this setting, then because of a plugin deactivation there is only one integration available when there used to be two. If you simply save the form, the system will automatically take care of re-enabling the remaining integration, and fixing this error.</p>';
                        }
                    }
                ?></fieldset>
            <?php } ?>


            <?php if ( SHOW_EMAIL_FORM ){ ?>
                <div style="
                margin-top: 43px;
                padding: 19px 36px;
                background: #fafaf7;
                border-radius:2px;
                ">
                    <h2 style="font-size:23px;letter-spacing:.3px;margin-bottom: 12px;">Having a problem?</h2>
                    <h3 style="font-size:14px;margin-bottom: 19px;">Fill out the form below to contact the plugin developer</h3>
                    <form id="feedback-form" class='email-form' data-msg="Your email has been sent. We will get back to you within the next few days.">
                      <label>From: </label><input value="<?= wp_get_current_user()->user_email ?>" style="border:none;background:transparent;vertical-align:middle;width:calc(100% - 100px);padding-left:2px;" />
                      <br/>
                      <label>Message: </label><br/>
                      <textarea name='body' rows=8 cols=80 required style="margin-top:5px;background:#fbfbf8"></textarea>
                      <br />
                      <details>
                          <summary id=debug-info-summary><small>(some debug info will automatically be appended to your email)</small></summary>
                          <label for=debug-info style="cursor:default">The following will be added</label>
                          <?php $debug_info =
                              "CJ Event Settings: "            . strip_tags( var_export($this->options, true ))
                            . " <br/><br/>Active Plugins: "    . $this->get_active_plugins_as_html()
                            . " <br/><br/>Theme: "   . $this->get_theme_as_html()
                            . " <br/><br/>Is a Multi-Site webSite: " . strip_tags( var_export(is_multisite(), true ))
                            . " <br/><br/>WordPress Version: " . strip_tags( get_bloginfo('version') );
                            ?>
                          <input id='debug-info' value='<?= esc_attr($debug_info) ?>' readonly style="width:calc(100% - 100px);border:1px solid #ddd;">
                      </details>
                      <input type=submit class='ow-btn' value='Send Email' style="margin-top:15px;">
                    </form>
                    <style>
                        .email-form summary::-webkit-details-marker{
                            color: #aaa;
                        }
                    </style>
                </div>

                <?php $ajax_nonce = wp_create_nonce( 'cj-tracking-feedback' ); ?>

                <script type="text/javascript" >
                jQuery('.email-form').submit(function() {
                  event.preventDefault();
                  var $form = jQuery(this)
                  var data = {
                    'security': '<?php echo $ajax_nonce ?>',
                    'action': 'cj_tracking_contact_us',
                    'message': $form.find('textarea').val() + "\n<br/><pre>" + $form.find('#debug-info').val() + '</pre>',
                    'useremail': '<?php echo wp_get_current_user()->user_email ?>'
                  };

                  // ajaxurl is always defined in the admin header and points to admin-ajax.php
                  jQuery.post(ajaxurl, data, function(response) {
                    if (response === 'success'){
                        $form.html('<p>'+$form.data('msg')+'</p>')
                    } else {
                        console.log(response)
                        alert('There was an error 😥 Please send me an email directly at russell@wp-overwatch.com.');
                        throw new Exception('Sending message was not successful')
                    }

                }).fail(function(xhr, status, err){
                    console.log(status + ' ' + err);
                    console.log(xhr);
                    alert('There was an error 😥 Please send me an email directly at russell@wp-overwatch.com.');
                });

              });
              </script>

          <?php } else { ?>

              <div style="
              margin-top: 43px;
              padding: 19px 36px;
              background: #fafaf7;
              border-radius:2px;
              ">
                  <h2 style="font-size:23px;letter-spacing:.3px;margin-bottom: 12px;">Having a problem?</h2>
                  <h3 style="font-size:14px;margin-bottom: 19px;">Changes to this plugin are now up to you & the rest of the community to make.</h3>
                  <p>If something isn't working right, you or your developer will need to submit a code fix to
                      <a href="https://github.com/Hosting-Utilities/cj.com-wordpress-plugin/issues">this github repo.</a>
                  </p>
                  <p>If you do not know what implementation to use or what your tag/action tracker/enterprise IDs are,
                      please reach out to a CJ representative. </p>
                  <p>For any other questions, you may shoot me an email at <a href="mailto:russell@wordpressoverwatch.com">russell@wordpressoverwatch.com</a>, or use the WordPress support forums.</p>
              </div>


          <?php } ?>

        </div>
        <?php
    }

	private function get_theme_as_html(){
		$theme = wp_get_theme();
		$theme_info = array(
			'name' => $theme->get('Name'),
			'url' => $theme->get('ThemeURI'),
			'version' => $theme->get('Version')
		);

		$ret = '<table border="1"><tr><td>Name</td><td>URL</td><td>Version</td></tr><tr>';
		foreach($theme_info as $cell){
			$ret .= '<td>' . strip_tags($cell) . '</td>';
		}
		$ret .= '</tr></table>';

        return $ret;
    }

    private function get_active_plugins_as_html(){
        $ret = '<table border="1"><tr><td>Name</td><td>Version</td><td>PluginURI</td><th>NetworkActive</td></tr>';
        $active = get_option('active_plugins');
        foreach($active as $plugin){
            $data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
            $row = array(
                'Name' => $data['Name'],
                'Version' => $data['Version'],
                'PluginURI' => $data['PluginURI'],
                'NetworkActive' => is_plugin_active_for_network($plugin)
            );

			$ret .= '<tr>';
			foreach($row as $cell){
				$ret .= '<td>' . strip_tags($cell) . '</td>';
			}
			$ret .= '</tr>';

        }
		$ret .= "</table>";

        return $ret;
    }


    /**
     * Register and add settings
     */
    public function page_init()
    {
        register_setting(
            'ow_cj_tracking_group', // Option group
            'ow_cj_tracking', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'account_info_section', // ID
            '', // Title
            array( $this, 'print_section_info' ), // Callback
            'ow-cj-tracking-settings-page' // Page
        );

        add_settings_field(
            'implementation', // ID
            'Implementation', // Title
            array( $this, 'implementation_callback' ), // Callback
            'ow-cj-tracking-settings-page', // Page
            'account_info_section', // Section
            array('label_for'=>'implementation')
        );

        add_settings_field(
            'tag_id', // ID
            'Tag ID', // Title
            array( $this, 'tag_id_callback' ), // Callback
            'ow-cj-tracking-settings-page', // Page
            'account_info_section', // Section
            array('label_for'=>'tag_id',
                'class'=>'implementation-specific impl-ssc impl-proxy impl-pixel',
            )
        );

        add_settings_field(
            'action_tracker_id', // ID
            'Action Tracker ID', // Title
            array( $this, 'action_tracker_id_callback' ), // Callback
            'ow-cj-tracking-settings-page', // Page
            'account_info_section', // Section
            array('label_for'=>'action_tracker_id',
                'class' => 'implementation-specific impl-ssc impl-proxy')
        );

        add_settings_field(
            'cid', // ID
            'CID', // Title
            array( $this, 'cid_callback' ), // Callback
            'ow-cj-tracking-settings-page', // Page
            'account_info_section', // Section
            array('label_for'=>'cid',
                'class' => 'implementation-specific impl-pixel')
        );

        add_settings_field(
            'type', // ID
            'Type', // Title
            array( $this, 'type_callback' ), // Callback
            'ow-cj-tracking-settings-page', // Page
            'account_info_section', // Section
            array('label_for'=>'type',
                'class' => 'implementation-specific impl-pixel')
        );

        add_settings_field(
            'enterprise-id', // ID
            'Enterprise ID', // Title
            array( $this, 'enterprise_id_callback' ), // Callback
            'ow-cj-tracking-settings-page', // Page
            'account_info_section', // Section
            array('label_for'=>'enterprise-id',
                'class' => 'implementation-specific impl-ssc impl-proxy')
        );

        // deprecated feb 19 2022
        if ( isset($this->options['cookie_duration'] )){
    		add_settings_field(
                'cookie_duration', // ID
                'Cookie Duration', // Title
                array( $this, 'cookie_duration_callback' ), // Callback
                'ow-cj-tracking-settings-page', // Page
                'account_info_section' // Section
            );
        }

        add_settings_field(
            'uninstall', // ID
            'Uninstall', // Title
            array( $this, 'uninstall_callback' ), // Callback
            'ow-cj-tracking-settings-page', // Page
            'account_info_section' // Section
        );

        add_settings_section(
            'advanced_section', // ID
            '', // Title
            function(){ // Callback
              echo '<br/>';
              echo '<details>';
              echo '<summary style="display:revert;">'. _x('Advanced', 'The label for expanding the advanced settings', 'cj-tracking') . '</summary>';
            },
            'ow-cj-tracking-settings-page' // Page
        );

        add_settings_field(
            'order_notes', // ID
            'Add debug info to order notes', // Title
            array( $this, 'order_notes_callback' ), // Callback
            'ow-cj-tracking-settings-page', // Page
            'advanced_section', // Section
            array('label_for'=>'order_notes')
        );

		if (count($this->integrations_installed) > 1 ){
            add_settings_field(
                'auto_detect_integrations', // ID
                'Turn on all available integrations', // Title
                array( $this, 'auto_detect_integrations_callback' ), // Callback
                'ow-cj-tracking-settings-page', // Page
                'advanced_section', // Section
                array('label_for'=>'auto_detect_integration')
            );
            add_settings_field(
                'enabled_integrations', // ID
                'Available integrations', // Title
                array( $this, 'enable_integrations_callback' ), // Callback
                'ow-cj-tracking-settings-page', // Page
                'advanced_section' // Section
            );
        }

        if ( in_array('Gravity Forms', $this->integrations_installed)
            && (empty($this->options) || in_array('Gravity Forms', $this->integrations_installed_and_enabled) )
            && count(GFAPI::get_forms()) > 1
        ){
            add_settings_field(
                'limit_gravity_forms', // ID
                'Limit to specific Gravity Forms', // Title
                array( $this, 'choose_gravity_forms_callback' ), // Callback
                'ow-cj-tracking-settings-page', // Page
                'advanced_section', // Section
                array('label_for'=>'cj-limit-gravity-forms-enabled')
            );

        }

        if ( in_array('WooCommerce', $this->integrations_installed)
            && ( empty($this->options) || in_array('WooCommerce', $this->integrations_installed_and_enabled) )
         ){
            add_settings_field(
                'storage_mechanism', // ID
                'Storage Mechanism', // Title
                array( $this, 'storage_mechanism_callback' ), // Callback
                'ow-cj-tracking-settings-page', // Page
                'advanced_section', // Section
                array('label_for'=>'storage_mechanism',
                'class' => 'implementation-specific impl-pixel')
            );
        }

        if ( in_array('Gravity Forms', $this->integrations_installed)
            && (empty($this->options) || in_array('Gravity Forms', $this->integrations_installed_and_enabled) )
            && count(GFAPI::get_forms()) > 1
        ){
            add_settings_field(
                'blank_field_handling', // ID
                'Gravity Forms: Reporting $0 dollar fields', // Title
                array( $this, 'blank_field_handling_callback' ), // Callback
                'ow-cj-tracking-settings-page', // Page
                'advanced_section', // Section
                array('label_for'=>'cj-blank-field-handling')
            );
        }

        if ( in_array('Gravity Forms', $this->integrations_installed)
            && (empty($this->options) || in_array('Gravity Forms', $this->integrations_installed_and_enabled) )
        ){
            add_settings_field(
                'confirmation_message_workaround', // ID
                'Gravity Forms: Confirmation workaround when redirecting', // Title
                array( $this, 'confirmation_message_workaround_callback' ), // Callback
                'ow-cj-tracking-settings-page', // Page
                'advanced_section', // Section
                array('label_for'=>'gf_confirmation_workaround')
            );
        }

        // add_settings_field(
        //     'other_params', // ID
        //     'Include additional params', // Title
        //     array( $this, 'other_params_callback' ), // Callback
        //     'ow-cj-tracking-settings-page', // Page
        //     'advanced_section' // Section
        // );

        add_settings_field(
            'extending_for_devs', // ID
            'Hooking into this plugin', // Title
            array( $this, 'extending_callback' ), // Callback
            'ow-cj-tracking-settings-page', // Page
            'advanced_section' // Section
        );

        add_settings_section(
            'end_advanced_section', // ID
            '', // Title
            function(){ // Callback
              echo '</details>';
            },
            'ow-cj-tracking-settings-page' // Page
        );

    }

    /**
     * Display the intro text
     */
    public function print_section_info()
    {
        echo _e('Enter your account info below.'
               . ' This info can be obtained from a cj.com client integration engineer.', 'cjtracking');
    }

    public function implementation_callback()
    {
        /* TODO combine overlapping styles with the action tracker ID's tooltip
        (the only difference is using a span tag for #cj-ati-hover-target), a different tooltip text */
        $tooltip = '<span id="cj-impl-hover-target">';
        $tooltip .= '<span class="dashicons dashicons-info-outline" id=cj-impl-info-bubble></span>';
        $tooltip .= '<p id="cj-impl-tooptip"><span><b>Server side cookie:</b>Recommended as it will work reliably regardless of who your webhost is.<br>
                    <b>Proxy:</b>This option is best as it is more resistant against adblockers, however, many webhosts, including WP Engine and Kinsta, are not compatable with this option.</span></p><br>';
        $tooltip .= '</span><style>';
        /* The tooltip is 1.5em + 22px high inclusing the line height. Line height is 1.5. So we add a paddin-top of 22px here
            ( plus 3px more extra for stylistic reasons)
            with accomanying negative margin to allow the cursor to enter the bubble without the bubble disappearing */
        $tooltip .= '#cj-impl-info-bubble{font-size:17px;color:#808080;padding-top:.4px;padding:5px;}
        #cj-impl-hover-target{position:relative;padding-bottom:6px;padding-top:23px;margin-top:-22px;}
        #cj-impl-hover-target:hover #cj-impl-tooptip{display:block;}
        #cj-impl-hover-target #cj-impl-tooptip:hover{display:block;}
        #cj-impl-tooptip > span > b{margin-right:5px;}
        #cj-impl-tooptip{display:none;position:absolute;top:4px;left:55px;background:#fdfdfd;padding:15px;width:650px;
            border:1px solid #ddd;border-radius:3px;border-bottom-width:2px;border-right-width:2px;margin-top:0;}
        #cj-impl-tooptip::before{
            content: "";
            position: absolute;
            top: 7px;
            left: -20px;
            width: 0;
            border-top: 20px solid transparent;
            border-bottom: 20px solid transparent;
            border-right: 20px solid #ddd;
        }
        #cj-impl-tooptip::after{
            content: "";
            position: absolute;
            top: 9px;
            left: -18px;
            width: 0;
            border-top: 18px solid transparent;
            border-bottom: 18px solid transparent;
            border-right: 18px solid #fdfdfd;
        }';
        $tooltip .= '</style>';

        printf(
            '<select id="implementation" name="ow_cj_tracking[implementation]">' .
            '<option value=server_side_cookie %s>Server side cookie</option>' .
            '<option value=proxy %s>Proxy</option>' .
			'<option value=pixel %s>Pixel (deprecated)</option>' .
            '</select>' . $tooltip, // <-- comma instead of a period
            // TODO make this code use CJ_TRACKING_DEFAULT_IMPLEMENTATION to decide what is selected by default
            ( ! isset( $this->options['implementation'] ) || $this->options['implementation'] === 'server_side_cookie' ) ? 'selected' : '',
            ( isset( $this->options['implementation'] ) && $this->options['implementation'] === 'proxy' ) ? 'selected' : '',
            ( isset( $this->options['implementation'] ) && $this->options['implementation'] === 'pixel' ) ? 'selected' : ''
        );
    }

    public function tag_id_callback(){
        printf(
            '<input type="text" id="tag_id" name="ow_cj_tracking[tag_id]" value="%s" />',
            isset( $this->options['tag_id'] ) ? esc_attr( $this->options['tag_id'] ) : ''
        );
    }

    /**
     * Display the tag ID field
     */
    public function action_tracker_id_callback()
    {
        /* ati stands for action tracker id */
        $tooltip = '<div id="cj-ati-hover-target">Have multiple Action Tracker IDs? ';
        $tooltip .= '<span class="dashicons dashicons-info-outline" id=cj-ati-info-bubble></span>';
        $tooltip .= '<p id="cj-ati-tooptip">If you have multiple Action Tracker IDs, special configuration will be necessary. Please shoot me an email at russell@wordpressoverwatch.com, and I will help you through that.</p><br>';
        $tooltip .= '</div><style>';
        /* The tooltip is 1.5em + 22px high inclusing the line height. Line height is 1.5. So we add a paddin-top of 22px here
            ( plus 3px more extra for stylistic reasons)
            with accomanying negative margin to allow the cursor to enter the bubble without the bubble disappearing */
        $tooltip .= '#cj-ati-info-bubble{font-size:17px;color:#808080;padding-top:.4px;}
        #cj-ati-hover-target{position:relative;padding-bottom:6px;padding-top:23px;margin-top:-22px;max-width:300px;}
        #cj-ati-hover-target:hover #cj-ati-tooptip{display:block;}
        #cj-ati-hover-target #cj-ati-tooptip:hover{display:block;}
        #cj-ati-tooptip{display:none;position:absolute;bottom:calc(1.5em + 22px);left:0;background:#fdfdfd;padding:15px;width:650px;
            border:1px solid #ddd;border-radius:3px;border-bottom-width:2px;border-right-width:2px;}
        #cj-ati-tooptip::before{
          content: "";
          position: absolute;
          top: 100%%;
          left: 210px;
          width: 0;
          border-top: 20px solid #ddd;
          border-left: 20px solid transparent;
          border-right: 20px solid transparent;
        }
        #cj-ati-tooptip::after{
          content: "";
          position: absolute;
          top: 100%%;
          left: 211px;
          width: 0;
          border-top: 18px solid #fdfdfd;
          border-left: 18px solid transparent;
          border-right: 18px solid transparent;
        }';
        $tooltip .= '</style>';
        printf(
            $tooltip .
            '<input type="text" id="action_tracker_id" name="ow_cj_tracking[action_tracker_id]" value="%s" />',
            isset( $this->options['action_tracker_id'] ) ? esc_attr( $this->options['action_tracker_id'] ) : ''
        );
    }

    /**
     * Display the tag CID field
     */
    public function cid_callback()
    {
        printf(
            '<input type="text" id="cid" name="ow_cj_tracking[cid]" value="%s" />',
            isset( $this->options['cid'] ) ? esc_attr( $this->options['cid'] ) : ''
        );
    }

    /**
     * Display the tag type field
     */
    public function type_callback()
    {
        printf(
            '<input type="text" id="type" name="ow_cj_tracking[type]" value="%s" />',
            isset( $this->options['type'] ) ? esc_attr( $this->options['type'] ) : ''
        );
    }

    /**
     * Display the type field
     */
    public function enterprise_id_callback()
    {
        printf(
            '<input type="text" id="enterprise_id" name="ow_cj_tracking[enterprise_id]" value="%s" />',
            isset( $this->options['enterprise_id'] ) ? esc_attr( $this->options['enterprise_id'] ) : ''
        );
    }

    /**
     * Display the debug checkbox
     */
    public function order_notes_callback()
    {
        printf(
            '<input type="checkbox" id="order_notes" name="ow_cj_tracking[order_notes]" value="true" %s />',
            isset( $this->options['order_notes'] ) ? 'checked="checked"' : ''
        );
    }

    public function storage_mechanism_callback()
    {
        printf(
            '<p>How the CJ event should be stored</p><br/>' .
            '<select id="storage_mechanism" name="ow_cj_tracking[storage_mechanism]" >' .
			'<option value=cookies %s>Cookies (default)</option>' .
			'<option value=woo_session %s>WooCommerce Session Data</option>' .
            '</select>', // <-- the last option needs a comman instead of a period
			//'<option value=cookies %s>Legacy Cookie Storage</option>',
			( ! isset( $this->options['storage_mechanism'] ) || $this->options['storage_mechanism'] === 'cookies' ) ? 'selected' : '',
            ( isset( $this->options['storage_mechanism'] ) && $this->options['storage_mechanism'] === 'woo_session' ) ? 'selected' : ''
            //( isset( $this->options['storage_mechanism'] ) && $this->options['storage_mechanism'] === 'cookies' ) ? 'selected' : ''
        );
    }

    public function cookie_duration_callback()
    {
        echo '<span id=cj-cookie-duration>';
        echo '<label>How long until the cjevent cookies expires (in days) </label><br/>';
		printf(
            '<input type="range" id="cookie_duration_slider" value="%s" step=1 min=1 max=730 />',
            isset( $this->options['cookie_duration'] ) ? $this->options['cookie_duration'] : '120'
        );
        printf(
            '<input type="number" id="cookie_duration" name="ow_cj_tracking[cookie_duration]" value="%s" />',
            isset( $this->options['cookie_duration'] ) ? $this->options['cookie_duration'] : '120'
        );
        echo '</span>';
        if (has_filter('cj_cookie_duration')){
            // TODO color is a random guess, change if necessary, if this callback is ever used again
            echo '<p style="color:#055">Detected that the cookie duration is being manipulated by a filter. The value inputted below will most likely get overwritten by this filter.</p>';
        }
		echo '<span id=cj-cookie-duration-unavailable><p>Use the "<code>wc_session_expiring</code>" and "<code>wc_session_expiration</code>" PHP filters to change how long the cjevent is stored</p></span>'

        ?>
        <script>
			document.getElementById('cj-cookie-duration-unavailable').style.display = 'none';
			document.getElementById('cj-cookie-duration').style.display = 'none';

			document.getElementById('cookie_duration_slider').addEventListener('input', function(ev){
				document.getElementById('cookie_duration').value = ev.target.value
				//this.style.background = 'linear-gradient(to right, #1e4650 0%, #1e4650 ' + (this.value-this.min)/(this.max-this.min)*100 + '%, transparent ' + (this.value-this.min)/(this.max-this.min)*100 + '%, transparent 100%)'
				document.documentElement.style.setProperty('--cj-cookie-duration-percent', (this.value-this.min)/(this.max-this.min)*100 + "%");
			});
			document.getElementById('cookie_duration').addEventListener('change', function(ev){
				document.getElementById('cookie_duration_slider').value = ev.target.value
			});


            function toggleCookieDuration(){
                if ( true || document.getElementById('storage_mechanism').value === 'cookies'){
					document.getElementById('cj-cookie-duration').style.display = 'block';
					document.getElementById('cj-cookie-duration-unavailable').style.display = 'none'
                } else {
                    document.getElementById('cj-cookie-duration').style.display = 'none';
					document.getElementById('cj-cookie-duration-unavailable').style.display = 'block';
                }
            }
			document.addEventListener('DOMContentLoaded', function(){
                //document.getElementById('storage_mechanism').addEventListener('change', toggleCookieDuration)
				toggleCookieDuration()
			})

        </script>
        <?php
    }

    public function other_params_callback()
    {
        printf(
            '<details><summary>Deprecated, In the future this will only be available through code that utilizes the cj_settings filter.</summary><p>Items listed below will also show up on your CJ.com dashboard in the order summary. Add each item on a separate line, separating the field and the value with an equal sign, like so:</p>
            <pre>a=1
b=2
            </pre>
            <textarea id="other_params" name="ow_cj_tracking[other_params]" value="%s" /> </textarea></details>',
            isset( $this->options['other_params'] ) ? esc_attr( $this->options['other_params'] ) : ''
        );
    }

    public function extending_callback()
    {
        ?><details style="max-width: 900px;">

            <summary style="display:revert;">For developers</summary>
<br/><h3>Filter Settings:</h3>

<pre style='font-family: inherit;'>
The <code>cj_settings</code> filter allows you to conditionally change the Action Tracker ID, Enterprise ID, and other settings. This can be useful when you have want to use multiple IDs.

Example usage:
</pre><pre><code style="display:block;padding: 8px 9px 7px 9px;">add_filter('cj_settings', function($account_data, $submission_method, $order){

    // make changes to $account_data here

    return $account_data;
}, 10, 3);
</code></pre><pre style='font-family: inherit;'>

Example 2:
</pre><pre><code style="display:block;padding: 8px 9px 7px 9px;">add_filter('cj_settings', function($settings, $submission_method, $order_or_form_data, $order_or_form_id){

    // make changes to the settings here

    return $settings;
}, 10, 4);
</code></pre>

<b>Paramaters:</b><br/>

<pre style='font-family: inherit;'>
1) The first parameter is an array containing all of the settings to filter.
More settings may be added later, but the array currently holds:
</pre><ul style="line-height: 1.4em;margin-left: 19px;list-style: disc;">
    <li>'implementation'</li>
    <li>'tag_id'</li>
    <li>'enterprise_id'</li>
    <li>'cid' - legacy, used with the pixel implementation</li>
    <li>'type' - legacy, used with the pixel implementation</li>
    <li><strike>notate_urls</strike> 'notate_order_data' - Logs the return data. Not all integrations may support this. For the WooCommerce integration the data is added to the order notes in the WooCommerce backend.</li>
    <li>'other_params' - A query string (do not include the initial question mark) of additional items to submit to CJ. These will appear in the CJ dashboard.</li>
    <li>'storage_mechanism' - Either 'woo_session' or 'cookies'. Both options work. There is normally no need to change this.</li>
</ul><pre style='font-family: inherit;'>

2) The second parameter will be either "woocommerce" or "gravity_forms" depending on which one initiated everything (more integrations will be added later as they're requested).

3 & 4) Based on the above parameter the next 2 will either be the form data and form id or the order data and order id.

</pre>

<h3>Manipulating the JavaScript Data Layer</h3>
If you would like to send additional data to CJ, you may use the following filter

<pre><code style="display:block;padding: 8px 9px 7px 9px;">
    add_filter('cj_data_layer', function($data, $tag_type){
        // Add additional key/values to $data here (may need to add them to $data[$tag_type])
        // $tag_type will be either 'sitePage' or 'order'
        return $data;
    }, 10, 2);
</code></pre>

<h3>Changing Cookie duration</h3>
The duration of the cje cookie can be changed with the following filter.
<pre><code style="display:block;padding: 8px 9px 7px 9px;">
    add_filter('cj_cookie_duration', function($default_val){
        return 365;
    });
    }, 10, 1);
</code></pre>

<h3>Other filters</h3>
<p>If you need me to add additional filters, would like to request an integration with a plugin other than Gravity Forms and WooCommerce, or if you have any questions, please fill out out the form below or shoot me an email at russell@wp-overwatch.com.</p>
        </details><?php
    }

    public function auto_detect_integrations_callback()
    {
        printf(
            '<input type="checkbox" class="ow-toggle ow-toggle-enable-disable" id="auto_detect_integration" name="ow_cj_tracking[auto_detect_integrations]" value="true" %s />
            <label for=auto_detect_integration class="ow-toggle-label"></label><br/>',
            checked( ! isset( $this->options['auto_detect_integrations_inverted'] ) || ! $this->options['auto_detect_integrations_inverted'], true, false)
        );
    }

    public function enable_integrations_callback()
    {
        $installed_integrations = $this->integrations_installed;

        echo '<span id=cj-integrations-checkboxes>';
        foreach ($installed_integrations as $integration){
            $url_friendly = $this->make_url_friendly($integration);
            $checked = checked( ! isset( $this->options['integrations'][$url_friendly] ) || $this->options['integrations'][$url_friendly], true, false);
            echo "<input type='checkbox' id='integration_$url_friendly' name='ow_cj_tracking[integrations][$url_friendly]' value='true' $checked />";
            echo "<label for='integration_$url_friendly'>$integration — " . CJ_INTEGRATION_DESCRIPTIONS[$integration] . "</label><br/>";
        }
        echo '</span>';

        echo '<span id=cj-integrations-checkboxes-auto-detected>';
        foreach($installed_integrations as $integration){
            echo '<input type=checkbox style="visibility:hidden" />';
            echo "<label>$integration — " . CJ_INTEGRATION_DESCRIPTIONS[$integration] . "</label><br/>";
        }
        echo '</span>';

        ?>
        <script>
            document.getElementById('cj-integrations-checkboxes').style.display = 'none';
            function toggleAutoDetectIntegration(){
                document.getElementById('cj-integrations-checkboxes').style.display = this.checked ? 'none' : 'inline';
                document.getElementById('cj-integrations-checkboxes-auto-detected').style.display = (! this.checked) ? 'none' : 'inline';
            }
            document.getElementById('auto_detect_integration').addEventListener('change', toggleAutoDetectIntegration)
            if ( ! document.getElementById('auto_detect_integration').checked){
                toggleAutoDetectIntegration()
            }

            function hide_gravity_form_settings_when_disabled(){
                if ( ! jQuery('#auto_detect_integration').is(':checked') && ! jQuery('#integration_gravityforms').is(':checked') ){
                    document.getElementById('cj-limit-gravity-forms-enabled').parentNode.parentNode.style.display = 'none'
                    document.getElementById('cj-blank-field-handling').parentNode.parentNode.style.display = 'none'
                    document.getElementById('gf_confirmation_workaround').parentNode.parentNode.style.display = 'none'
                } else {
                    document.getElementById('cj-limit-gravity-forms-enabled').parentNode.parentNode.style.display = ''
                    document.getElementById('cj-blank-field-handling').parentNode.parentNode.style.display = ''
                    document.getElementById('gf_confirmation_workaround').parentNode.parentNode.style.display = ''
                }
            }
            jQuery('#auto_detect_integration, #integration_gravityforms').change(hide_gravity_form_settings_when_disabled)
            function hide_woocommerce_settings_when_disabled(){
                if ( ! jQuery('#auto_detect_integration').is(':checked') && ! jQuery('#integration_woocommerce').is(':checked') ){
                    document.getElementById('storage_mechanism').parentNode.parentNode.style.display = 'none'
                } else {
                    document.getElementById('storage_mechanism').parentNode.parentNode.style.display = ''
                }
            }
            jQuery('#auto_detect_integration, #integration_woocommerce').change(hide_woocommerce_settings_when_disabled)
        </script>
        <?php
    }

    public function choose_gravity_forms_callback()
    {
        // printf(
        //     '<input type="checkbox" id="cj-limit-gravity-forms-enabled" name="ow_cj_tracking[limit_gravity_forms]" value="true" %s /><label for=cj-limit-gravity-forms-enabled>All forms containing a pricing field</label><br/>',
        //     checked( ! isset( $this->options['limit_gravity_forms'] ) || $this->options['limit_gravity_forms'], true, false)
        // );

        printf(
            '<input type="checkbox" class="ow-toggle ow-toggle-enable-disable" id="cj-limit-gravity-forms-enabled" name="ow_cj_tracking[limit_gravity_forms]" value="true" %s />
            <label for=cj-limit-gravity-forms-enabled class="ow-toggle-label"></label><br/>',
            checked( isset( $this->options['limit_gravity_forms'] ) && $this->options['limit_gravity_forms'], true, false)
        );

        $installed_integrations = $this->integrations_installed;
        $forms = GFAPI::get_forms();
        echo '<span id=cj-enabled-gravity-forms-checkboxes>';
        echo '<p>A form must have at least 1 pricing field before it will start reporting submissions to cj.com.</p>';
        foreach ($forms as $form){
            $id = $form['id'];
            $title = $form['title'];
            $description = $form['description'];
            $checked = checked( isset( $this->options['enabled_gravity_forms'][$id] ) && $this->options['enabled_gravity_forms'][$id], true, false);
            echo "<input type='checkbox' id='integration_$id' name='ow_cj_tracking[enabled_gravity_forms][$id]' value='true' $checked />";
            echo "<label for='integration_$id'>$title " . ($description ? " - ".$description : '') . "</label><br/>";
        }
        echo '</span>';

        //echo '<span id=cj-enabled-gravity-forms-checkboxes-disabled>';
        // foreach ($forms as $form){
        //     echo $form['title'] . "<br/>";
        // }
        //echo '</span>';

        ?>
        <script>
            //document.getElementById('cj-enabled-gravity-forms-checkboxes').style.display = document.getElementById('cj-limit-gravity-forms-enabled').checked ? 'inline' : 'none';
            function toggleLimitGravityForms(){
                document.getElementById('cj-enabled-gravity-forms-checkboxes').style.display = document.getElementById('cj-limit-gravity-forms-enabled').checked ? 'inline' : 'none';
                // document.getElementById('cj-enabled-gravity-forms-checkboxes').style.display = ( ! this.checked ) ? 'none' : 'inline';
                // document.getElementById('cj-enabled-gravity-forms-checkboxes-disabled').style.display = this.checked ? 'none' : 'inline';
            }
            toggleLimitGravityForms()
            document.getElementById('cj-limit-gravity-forms-enabled').addEventListener('change', toggleLimitGravityForms)
            document.getElementById('cj-limit-gravity-forms-enabled').parentNode.style['vertical-align'] = 'top'
        </script>
        <?php
    }

    public function uninstall_callback($input){
        ?>
        <p aria-labelledby=uninstall-btn>Before deleting this plugin, use the button below to remove any settings that were stored in the database</p><br/>
        <button id=uninstall-btn>Remove Plugin Data</button>
        </form>
        <script>
            document.getElementById('uninstall-btn').addEventListener('click', function(ev){
                ev.preventDefault()
                if (confirm('This will delete the plugin settings. Are you sure?')){
                    var data = {
                        'action': 'cj_tracking_uninstall',
            			'nonce': "<?= wp_create_nonce( 'cj-tracking-uninstall' ) ?>"
            		};
                    jQuery.post(ajaxurl, data, function(response) {
                        if (response.includes('success')){
                            document.getElementById('wpbody-content').innerHTML = ''
                        }
                        setTimeout(function(){
                            alert(response)
                            document.getElementById('wpbody-content').innerHTML = '<br/> <h3>Reloading page...</h3>'
                            location.reload()
                        }, 0);
            		})
                }
            })
        </script>
        <?php
    }

    public function blank_field_handling_callback(){
        printf(
            '<p>If you are conditionally showing/hiding fields, you may choose to try out one of the following options to not send everything to CJ.</p><br/>' .
            '<select id="cj-blank-field-handling" name="ow_cj_tracking[blank_field_handling]" >' .
            '<option value=report_all_fields %s>Report all pricing fields (default)</option>' .
            '<option value=ignore_blank_fields %s>Ignore $0 fields that were not filled out</option>' .
            '<option value=ignore_0_dollar_items %s>Ignore all $0 fields</option>' .
            '</select>',
            ( ! isset( $this->options['blank_field_handling'] ) || $this->options['blank_field_handling'] === 'report_all_fields' ) ? 'selected' : '',
            ( isset( $this->options['blank_field_handling'] ) && $this->options['blank_field_handling'] === 'ignore_blank_fields' ) ? 'selected' : '',
            ( isset( $this->options['blank_field_handling'] ) && $this->options['blank_field_handling'] === 'ignore_0_dollar_items' ) ? 'selected' : ''
        );
    }

    public function confirmation_message_workaround_callback(){
        printf(
            '<input type="checkbox" id="gf_confirmation_workaround" name="ow_cj_tracking[gf_confirmation_workaround]" value="true" %s />%s',
            checked( ! isset( $this->options['gf_confirmation_workaround_inverted'] ) || ! $this->options['gf_confirmation_workaround_inverted'], true, false),
            '<label for=gf_confirmation_workaround> We are currently disabling the ability for all Gravity Forms that send data to CJ to redirect to another page when submitted.
            Disable this checkbox to enable an expiremental fix that does not require this workaround. The expiremental fix probably does not work, but if it does, let us know so we can make it permament.</label>'
        );
    }

    private function make_url_friendly($input){
        return cj_make_url_friendly($input);
    }

} /* end of CJTrackingSettingsPage class */

$my_settings_page = new CJTrackingSettingsPage();



function remove_footer_admin(){
    ?>
    <span id="footer-thankyou">P.S. If you take care of multiple clients, you'll want to check out our suite of <a href="https://hostingutilities.com" >Hosting Utilities</a></span>
    <?php
}
add_filter('admin_footer_text', 'remove_footer_admin');
