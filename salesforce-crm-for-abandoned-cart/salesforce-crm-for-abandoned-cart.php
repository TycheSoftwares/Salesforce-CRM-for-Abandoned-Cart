<?php
/*
Plugin Name: Salesforce CRM Addon for Abandoned Cart Pro for WooCommerce
Plugin URI: http://www.tychesoftwares.com/store/premium-plugins/woocommerce-abandoned-cart-pro
Description: This plugin allows you to export the abandoned cart data to your Salesforce CRM. 
Version: 1.0
Author: Tyche Softwares
Author URI: http://www.tychesoftwares.com/
*/
require_once ( "cron/wcap_salesforce_add_abandoned_data.php" );
require_once ( "includes/class_add_to_salesforce_crm.php" );
require_once ('soapclient/SforcePartnerClient.php');
// Add a new interval of 1 Day
add_filter( 'cron_schedules', 'wcap_salesforce_add_data_schedule' );

function wcap_salesforce_add_data_schedule( $schedules ) {
    $hour_seconds     = 3600; // 60 * 60
    $day_seconds      = 86400; // 24 * 60 * 60    
    $duration         = get_option( 'wcap_sf_add_automatically_add_after_email_frequency' );
    $wcap_day_or_hour = get_option( 'wcap_sf_add_automatically_add_after_time_day_or_hour' );    
    if ( $wcap_day_or_hour == 'Days' ) {
        $duration_in_seconds = $duration * $day_seconds;
    } elseif ( $wcap_day_or_hour == 'Hours' ) {
        $duration_in_seconds = $duration * $hour_seconds;
    } else {
        $duration_in_seconds = $day_seconds;
    }
    $schedules['1_day'] = array(
                'interval' => $duration_in_seconds,  
                'display'  => __( 'Once in a day.' ),
    );
    return $schedules;
}
// Schedule an action if it's not already scheduled
if ( ! wp_next_scheduled( 'wcap_salesforce_add_abandoned_data_schedule' ) ) {
    wp_schedule_event( time(), '1_day', 'wcap_salesforce_add_abandoned_data_schedule' );
}
register_uninstall_hook( __FILE__, 'wcap_salesforce_crm_uninstall' );

function wcap_salesforce_crm_uninstall (){
    global $wpdb;
    
    $wcap_salesforce_table_name = $wpdb->prefix . "wcap_salesforce_abandoned_cart";
    $sql_wcap_salesforce_table_name = "DROP TABLE " . $wcap_salesforce_table_name ;
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    $wpdb->get_results( $sql_wcap_salesforce_table_name );

    delete_option( 'wcap_enable_salesforce_crm' );
    delete_option( 'wcap_add_automatically_to_salesforce_crm' );
    delete_option( 'wcap_sf_add_automatically_add_after_email_frequency' );
    delete_option( 'wcap_sf_add_automatically_add_after_time_day_or_hour' );
    delete_option( 'wcap_salesforce_last_id_checked' );
    delete_option( 'wcap_salesforce_user_name' );
    delete_option( 'wcap_salesforce_password' );
    delete_option( 'wcap_salesforce_security_token' );
    delete_option( 'wcap_salesforce_user_type' );
    delete_option( 'wcap_salesforce_lead_company' );

    delete_option ( 'wcap_salesforce_connection_established' );

    wp_clear_scheduled_hook( 'wcap_salesforce_add_abandoned_data_schedule' );
}

if ( ! class_exists( 'Wcap_Salesforce_CRM' ) ) {

    class Wcap_Salesforce_CRM {

        public function __construct( ) {
            register_activation_hook( __FILE__,                         array( &$this, 'wcap_salesforce_crm_create_table' ) );
            if ( ! has_action ('wcap_add_tabs' ) ){
                add_action ( 'wcap_add_tabs',                           array( &$this, 'wcap_salesforce_crm_add_tab' ) );
            }
            // Add settings link on plugins page
            add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( &$this, 'wcap_salesforce_plugin_action_links' ) );
            add_action ( 'admin_init',                                        array( &$this, 'wcap_salesforce_crm_initialize_settings_options' ), 11 );
            add_action ( 'wcap_display_message',                              array( &$this, 'wcap_salesforce_crm_display_message' ),11 );
            add_action ( 'wcap_crm_data',                                     array( &$this, 'wcap_salesforce_crm_display_data' ), 15 );
            add_action ( 'wcap_add_buttons_on_abandoned_orders',              array( &$this, 'wcap_add_export_all_data_to_salesforce_crm' ) );
            add_filter ( 'wcap_abandoned_orders_single_column' ,              array( &$this, 'wcap_add_individual_record_to_salesforce_crm' ), 11 , 2 );
            add_filter ( 'wcap_abandoned_order_add_bulk_action',              array( &$this, 'wcap_add_bulk_record_to_salesforce_crm' ), 11 , 1 );
            add_action ( 'wp_ajax_wcap_add_to_salesforce_crm',                array( &$this, 'wcap_add_to_salesforce_crm_callback' ));
            add_action ( 'admin_enqueue_scripts',                             array( &$this, 'wcap_salesforce_enqueue_scripts_js' ) );
            add_action ( 'admin_enqueue_scripts',                             array( &$this, 'wcap_salesforce_enqueue_scripts_css' ) );
            add_action ( 'wcap_salesforce_add_abandoned_data_schedule',       array( 'Wcap_Salesforce_CRM_Add_Cron_Data', 'wcap_add_salesforce_abandoned_cart_data' ),11 );
            /*
             * When cron job time changed this function will be called.
             * It is used to reset the cron time again.
             */
            add_action ( 'update_option_wcap_sf_add_automatically_add_after_email_frequency',  array( &$this,'wcap_salesforce_reset_cron_time_duration' ),11 );
            add_action ( 'update_option_wcap_sf_add_automatically_add_after_time_day_or_hour', array( &$this,'wcap_salesforce_reset_cron_time_duration' ),11 );

            /*
            Test Connection for saved settings
            */
            add_action ( 'wp_ajax_wcap_salesforce_check_connection',                           array( &$this, 'wcap_salesforce_check_connection_callback' ));
        }

        function wcap_salesforce_check_connection_callback(){

            $wcap_sf_password = $_POST['wcap_sf_password'];
            $wcap_sf_username = $_POST['wcap_sf_user_name'];
            $wcap_sf_api      = $_POST['wcap_sf_token'];
            $wacp_pswd_token  = $wcap_sf_password . $wcap_sf_api;
            $result           = '';  
            try{
                $wcap_SforceConnection = new SforcePartnerClient();
                $wcap_plguins_url      = plugins_url() . '/salesforce-crm-for-abandoned-cart';
          
                $wcap_mySoapClient     = $wcap_SforceConnection->createConnection( $wcap_plguins_url .'/soapclient/partner.wsdl.xml');
                $wcap_loginResult      = $wcap_SforceConnection->login( $wcap_sf_username, $wacp_pswd_token );
                $result                = "The Salesforce CRM connection successfuly established!";
                update_option ( 'wcap_salesforce_connection_established', 'yes' );
            } catch (Exception $e) {
                $wcap_login_result = $e->faultstring; 
                if ( preg_match( "/INVALID_LOGIN/i", $wcap_login_result ) ) {
                    $result = "The Salesforce CRM connection has FAILED! Please check your credentials!";
                    update_option ( 'wcap_salesforce_connection_established', 'no' );
                }
            } 
            echo $result;
            wp_die();
        }

        function wcap_salesforce_crm_create_table() {
            global $wpdb;
            $wcap_collate = '';
            if ( $wpdb->has_cap( 'collation' ) ) {
                $wcap_collate = $wpdb->get_charset_collate();
            }
            $table_name = $wpdb->prefix . "wcap_salesforce_abandoned_cart";
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `abandoned_cart_id` int(11) COLLATE utf8_unicode_ci NOT NULL,
                    `date_time` TIMESTAMP on update CURRENT_TIMESTAMP COLLATE utf8_unicode_ci NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`)
                    ) $wcap_collate AUTO_INCREMENT=1 ";           
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            $wpdb->query( $sql );
            if ( !get_option( 'wcap_salesforce_user_type' ) ) {
                add_option( 'wcap_salesforce_user_type', 'lead' );
            }
        }

        /**
         * Show action links on the plugin screen.
         *
         * @param   mixed $links Plugin Action links
         * @return  array
         */
        
        public static function wcap_salesforce_plugin_action_links( $links ) {
            $action_links = array(
                'settings' => '<a href="' . admin_url( 'admin.php?page=woocommerce_ac_page&action=wcap_crm' ) . '" title="' . esc_attr( __( 'View Salesforce Settings', 'woocommerce-ac' ) ) . '">' . __( 'Settings', 'woocommerce-ac' ) . '</a>',
            );
            return array_merge( $action_links, $links );
        }

        function wcap_salesforce_enqueue_scripts_js( $hook ) {
            if ( $hook != 'woocommerce_page_woocommerce_ac_page' ) {
                return;
            } else {
                wp_register_script( 'wcap-salesforce', plugins_url()  . '/salesforce-crm-for-abandoned-cart/assets/js/wcap_salesforce.js', array( 'jquery' ) );
                wp_enqueue_script( 'wcap-salesforce' );
                $wcap_sf_user_name              = get_option ( 'wcap_salesforce_user_name' );
                $wcap_sf_password               = get_option ( 'wcap_salesforce_password' );
                $wcap_sf_token                  = get_option ( 'wcap_salesforce_security_token' );
                $wcap_sf_connection_established = get_option ( 'wcap_salesforce_connection_established' );
                wp_localize_script( 'wcap-salesforce', 'wcap_salesforce_params', array(
                                    'ajax_url'                       => admin_url( 'admin-ajax.php' ),
                                    'wcap_sf_user_name'              => $wcap_sf_user_name,
                                    'wcap_sf_password'               => $wcap_sf_password,
                                    'wcap_sf_token'                  => $wcap_sf_token,
                                    'wcap_sf_connection_established' => $wcap_sf_connection_established
                ) );
            }
        }

        function wcap_salesforce_enqueue_scripts_css( $hook ) {
            
            if ( $hook != 'woocommerce_page_woocommerce_ac_page' ) {
                return;
            } else {
                wp_enqueue_style( 'wcap-salesforce',  plugins_url() . '/salesforce-crm-for-abandoned-cart/assets/css/wcap_salesforce_style.css'     );
            }
        }

        function wcap_salesforce_reset_cron_time_duration (){
            wp_clear_scheduled_hook( 'wcap_salesforce_add_abandoned_data_schedule' );
        }

        function wcap_salesforce_crm_add_tab () {
            $wcap_action           = "";
            if ( isset( $_GET['action'] ) ) {
                $wcap_action = $_GET['action'];
            }
            $wcap_salesforce_crm_active = "";
            if (  'wcap_crm' == $wcap_action ) {
                $wcap_salesforce_crm_active = "nav-tab-active";
            }
            ?>
            <a href="admin.php?page=woocommerce_ac_page&action=wcap_crm" class="nav-tab <?php if( isset( $wcap_salesforce_crm_active ) ) echo $wcap_salesforce_crm_active; ?>"> <?php _e( 'Addon Settings', 'woocommerce-ac' );?> </a>
            <?php
            
        }

        function wcap_salesforce_crm_initialize_settings_options () {
            // First, we register a section. This is necessary since all future options must belong to a
            add_settings_section(
                'wcap_salesforce_crm_general_settings_section',         // ID used to identify this section and with which to register options
                __( 'Salesforce CRM Settings', 'woocommerce-ac' ),                  // Title to be displayed on the administration page
                array($this, 'wcap_salesforce_crm_general_settings_section_callback' ), // Callback used to render the description of the section
                'wcap_salesforce_crm_section'     // Page on which to add this section of options
            );
             
            add_settings_field(
                'wcap_enable_salesforce_crm',
                __( 'Export abandoned cart data to Salesforce CRM', 'woocommerce-ac' ),
                array( $this, 'wcap_enable_salesforce_crm_callback' ),
                'wcap_salesforce_crm_section',
                'wcap_salesforce_crm_general_settings_section',
                array( __( 'Enable to export the abandoned carts data to the Salesforce CRM.', 'woocommerce-ac' ) )
            );

            add_settings_field(
                'wcap_add_automatically_to_salesforce_crm',
                __( 'Automatically add abandoned cart data to Salesforce CRM', 'woocommerce-ac' ),
                array( $this, 'wcap_add_automatically_to_salesforce_crm_callback' ),
                'wcap_salesforce_crm_section',
                'wcap_salesforce_crm_general_settings_section',
                array( __( 'When any abandoned cart is displayed to the Abandoned Orders tab, it will be automatically exported to the Salesforce CRM.', 'woocommerce-ac' ) )
            );

            add_settings_field(
                '',
                __( 'Automatically add abandoned cart data to salesforce CRM after the set time.', 'woocommerce-ac' ),
                array( $this, 'wcap_add_automatically_add_after_time_callback' ),
                'wcap_salesforce_crm_section',
                'wcap_salesforce_crm_general_settings_section',
                array( __( 'When any abandoned cart is displayed to the Abandoned Orders tab, it will be automatically exported to the Salesforce CRM after set time.', 'woocommerce-ac' ) )
            );

            add_settings_field(
                'wcap_salesforce_user_name',
                __( 'Salesforce Username', 'woocommerce-ac' ),
                array( $this, 'wcap_salesforce_user_name_callback' ),
                'wcap_salesforce_crm_section',
                'wcap_salesforce_crm_general_settings_section',
                array( __( 'Please provide your Salesforce username.', 'woocommerce-ac' ) )
            );

            add_settings_field(
                'wcap_salesforce_password',
                __( 'Salesforce Password', 'woocommerce-ac' ),
                array( $this, 'wcap_salesforce_password_callback' ),
                'wcap_salesforce_crm_section',
                'wcap_salesforce_crm_general_settings_section',
                array( __( 'Please provide your Salesforce password.', 'woocommerce-ac' ) )
            );

            add_settings_field(
                'wcap_salesforce_security_token',
                __( 'Salesforce Security Token', 'woocommerce-ac' ),
                array( $this, 'wcap_salesforce_security_token_callback' ),
                'wcap_salesforce_crm_section',
                'wcap_salesforce_crm_general_settings_section',
                array( __( 'Please provide your Salesforce Security Token. Please, login to your salesforce account. In the Lightning Experience view, in the View Profile option, click on the Settings > My Personal Information > Reset My Security Token menu. Clicking on the Reset Security Token button, you will receive an email with your new security token.', 'woocommerce-ac' ) )
            );

            add_settings_field(
                'wcap_salesforce_user_type',
                __( 'Export abandoned cart data as', 'woocommerce-ac' ),
                array( $this, 'wcap_salesforce_user_type_callback' ),
                'wcap_salesforce_crm_section',
                'wcap_salesforce_crm_general_settings_section',
                array( __( "Please select in which type the abandoned cart data should be exported to Salesforce CRM. <br/> Note: The product details in the abandoned cart data will be exported in a note when exported as a Contact. When exporting as a Lead, the product detail in the abandoned cart will be exported in the Lead's Detail Description along with the Customer's personal information.", 'woocommerce-ac' ) )
            );

            add_settings_field(
                'wcap_salesforce_lead_company',
                __( 'Company name of lead', 'woocommerce-ac' ),
                array( $this, 'wcap_salesforce_lead_company_callback' ),
                'wcap_salesforce_crm_section',
                'wcap_salesforce_crm_general_settings_section',
                array( __( 'Please set the Company name for lead.', 'woocommerce-ac' )  )
            );

            add_settings_field(
               'wcap_salesforce_test_connection',
               '',
               array( $this, 'wcap_salesforce_test_connection_callback' ),
               'wcap_salesforce_crm_section',
               'wcap_salesforce_crm_general_settings_section'
            );
                        
            // Finally, we register the fields with WordPress
            register_setting(
                'wcap_salesforce_crm_setting',
                'wcap_enable_salesforce_crm'
            );
            register_setting(
                'wcap_salesforce_crm_setting',
                'wcap_add_automatically_to_salesforce_crm'
            );
            register_setting(
                'wcap_salesforce_crm_setting',
                'wcap_sf_add_automatically_add_after_email_frequency'
            );
            register_setting(
                'wcap_salesforce_crm_setting',
                'wcap_sf_add_automatically_add_after_time_day_or_hour'
            );
            register_setting(
                'wcap_salesforce_crm_setting',
                'wcap_salesforce_user_name'
            );
            register_setting(
                'wcap_salesforce_crm_setting',
                'wcap_salesforce_password'
            );
            register_setting(
                'wcap_salesforce_crm_setting',
                'wcap_salesforce_security_token'
            );
            register_setting(
                'wcap_salesforce_crm_setting',
                'wcap_salesforce_user_type'
            );
            register_setting(
                'wcap_salesforce_crm_setting',
                'wcap_salesforce_lead_company'
            );
        }

        /***************************************************************
         * WP Settings API callback for section
        **************************************************************/
        function wcap_salesforce_crm_general_settings_section_callback() {
             
        }

        /***************************************************************
         * WP Settings API callback for enable exporting the abandoned data to salesforce crm
        **************************************************************/
        function wcap_enable_salesforce_crm_callback( $args ) {
            // First, we read the option
            $wcap_enable_salesforce_crm = get_option( 'wcap_enable_salesforce_crm' );
            // This condition added to avoid the notie displyed while Check box is unchecked.
            if (isset( $wcap_enable_salesforce_crm ) &&  $wcap_enable_salesforce_crm == "") {
                $wcap_enable_salesforce_crm = 'off';
            }
        
            // Next, we update the name attribute to access this element's ID in the context of the display options array
            // We also access the show_header element of the options collection in the call to the checked() helper function 
            $html = '<input type="checkbox" id="wcap_enable_salesforce_crm" name="wcap_enable_salesforce_crm" value="on" ' . checked( 'on', $wcap_enable_salesforce_crm, false ) . '/>';
            
            // Here, we'll take the first argument of the array and add it to a label next to the checkbox
            $html .= '<label for="wcap_enable_salesforce_crm_lable"> '  . $args[0] . '</label>';
            echo $html;
        }
        /***************************************************************
         * WP Settings API callback for automatically exporting the abandoned data to salesforce crm
        **************************************************************/
        function wcap_add_automatically_to_salesforce_crm_callback( $args ) {
            // First, we read the option
            $wcap_add_automatically_to_salesforce_crm = get_option( 'wcap_add_automatically_to_salesforce_crm' );
            // This condition added to avoid the notie displyed while Check box is unchecked.
            if (isset( $wcap_add_automatically_to_salesforce_crm ) &&  $wcap_add_automatically_to_salesforce_crm == "") {
                $wcap_add_automatically_to_salesforce_crm = 'off';
            }
        
            $html  = '<input type="checkbox" id="wcap_add_automatically_to_salesforce_crm" name="wcap_add_automatically_to_salesforce_crm" value="on" ' . checked( 'on', $wcap_add_automatically_to_salesforce_crm, false ) . '/>';
            $html .= '<label for="wcap_add_automatically_to_salesforce_crm_lable"> '  . $args[0] . '</label>';
            echo $html;
        }

        /***************************************************************
         * WP Settings API callback for automatically exporting the abandoned data to salesforce crm
        **************************************************************/
        function wcap_add_automatically_add_after_time_callback( $args ) {
            // First, we read the option
            $wcap_add_automatically_add_after_time = get_option( 'wcap_add_automatically_add_after_time' );
            // This condition added to avoid the notie displyed while Check box is unchecked.
            if (isset( $wcap_add_automatically_add_after_time ) &&  $wcap_add_automatically_add_after_time == "") {
                $wcap_add_automatically_add_after_time = 'off';
            }
            ?>
            <select name="wcap_sf_add_automatically_add_after_email_frequency" id="wcap_sf_add_automatically_add_after_email_frequency">
            <?php
            $frequency_edit = '';
            $frequency_edit = get_option( 'wcap_sf_add_automatically_add_after_email_frequency' );
            for ( $i=1;$i<60;$i++ ) {
                printf( "<option %s value='%s'>%s</option>\n",
                    selected( $i, $frequency_edit, false ),
                    esc_attr( $i ),
                    $i
                );
            }
            ?>
            </select>
            <select name="wcap_sf_add_automatically_add_after_time_day_or_hour" id="wcap_sf_add_automatically_add_after_time_day_or_hour">
                <?php
                    
                    $days_or_hours_edit = get_option( 'wcap_sf_add_automatically_add_after_time_day_or_hour' );
                    $days_or_hours = array(
                       'Days'      => 'Day(s)',
                       'Hours'     => 'Hour(s)'
                    );
                    foreach( $days_or_hours as $k => $v ) {
                        printf( "<option %s value='%s'>%s</option>\n",
                            selected( $k, $days_or_hours_edit, false ),
                            esc_attr( $k ),
                            $v
                        );
                    }
                ?>
                </select>
            <?
            $html = '<label for="wcap_add_automatically_add_after_time_lable"> '  . $args[0] . '</label>';
            echo $html;
        }

        function wcap_salesforce_crm_display_message (){
            $wcap_action     = "";
            if ( isset( $_GET['action'] ) ){
                $wcap_action = $_GET['action'];
            }
            /*
                It will display the message when abandoned cart data successfuly added to salesforce crm.
            */
            ?>
            <div id="wcap_salesforce_message" class="updated fade settings-error notice is-dismissible">
                <p class="wcap_salesforce_message_p">
                    <strong>
                        <?php _e( "" ); ?>
                    </strong>
                </p>
            </div>
            <div id="wcap_ac_bulk_message" class="error settings-error notice is-dismissible">
                <p class="wcap_ac_bulk_message_p">
                    <strong>
                        <?php _e( "" ); ?>
                    </strong>
                </p>
            </div>

            <div id="wcap_salesforce_message_error" class="error settings-error notice is-dismissible">
                <p class="wcap_salesforce_message_p_error">
                    <strong>
                        <?php _e( "" ); ?>
                    </strong>
                </p>
            </div>

            <?php 
        }
        /***************************************************************
         * WP Settings API callback for salesforce user name
        **************************************************************/
        function wcap_salesforce_user_name_callback($args) {
            
            // First, we read the option
            $wcap_salesforce_user_name = get_option( 'wcap_salesforce_user_name' );
            
            // Next, we update the name attribute to access this element's ID in the context of the display options array
            // We also access the show_header element of the options collection in the call to the checked() helper function
            printf(
                '<input type="text" id="wcap_salesforce_user_name" name="wcap_salesforce_user_name" value="%s" />',
                isset( $wcap_salesforce_user_name ) ? esc_attr( $wcap_salesforce_user_name ) : ''
            );
            
            // Here, we'll take the first argument of the array and add it to a label next to the checkbox
            $html = '<label for="wcap_salesforce_user_name_label"> '  . $args[0] . '</label> <br>  <span id ="wcap_salesforce_user_name_label_error" > Please enter your Salesforce username. </span>';
            echo $html;
        }

        /***************************************************************
         * WP Settings API callback for salesforce password
        **************************************************************/
        function wcap_salesforce_password_callback($args) {            
            // First, we read the option
            $wcap_salesforce_password = get_option( 'wcap_salesforce_password' );
            
            // Next, we update the name attribute to access this element's ID in the context of the display options array
            // We also access the show_header element of the options collection in the call to the checked() helper function
            printf(
                '<input type="text" id="wcap_salesforce_password" name="wcap_salesforce_password" value="%s" />',
                isset( $wcap_salesforce_password ) ? esc_attr( $wcap_salesforce_password ) : ''
            );            
            // Here, we'll take the first argument of the array and add it to a label next to the checkbox
            $html = '<label for="wcap_salesforce_password_label"> '  . $args[0] . '</label> <br>  <span id ="wcap_salesforce_password_label_error" > Please enter your Salesforce password. </span>';
            echo $html;
        }

        /***************************************************************
         * WP Settings API callback for salesforce security token
        **************************************************************/
        function wcap_salesforce_security_token_callback($args) {           
            // First, we read the option
            $wcap_salesforce_security_token = get_option( 'wcap_salesforce_security_token' );            
            // Next, we update the name attribute to access this element's ID in the context of the display options array
            // We also access the show_header element of the options collection in the call to the checked() helper function
            printf(
                '<input type="text" id="wcap_salesforce_security_token" name="wcap_salesforce_security_token" value="%s" />',
                isset( $wcap_salesforce_security_token ) ? esc_attr( $wcap_salesforce_security_token ) : ''
            );            
            // Here, we'll take the first argument of the array and add it to a label next to the checkbox
            $html = '<label for="wcap_salesforce_security_token_label"> '  . $args[0] . '</label> <br>  <span id ="wcap_salesforce_security_token_label_error"> Please enter your Salesforce security token. </span>';
            echo $html;
        }

        /***************************************************************
         * WP Settings API callback for salesforce User type
        **************************************************************/
        function wcap_salesforce_user_type_callback($args) {            
            // First, we read the option
            $wcap_salesforce_user_type = get_option( 'wcap_salesforce_user_type' );            
            // Next, we update the name attribute to access this element's ID in the context of the display options array
            // We also access the show_header element of the options collection in the call to the checked() helper function
            printf(
                '<input type="radio" id="wcap_salesforce_user_type" name="wcap_salesforce_user_type" value="lead" %s /> Lead
                &nbsp; &nbsp; 
                <input type="radio" id="wcap_salesforce_user_type" name="wcap_salesforce_user_type" value="contact" %s /> Contact &nbsp;&nbsp; ',
                isset( $wcap_salesforce_user_type ) && 'lead' == $wcap_salesforce_user_type  ? 'checked' : '',
                isset( $wcap_salesforce_user_type ) && 'contact' == $wcap_salesforce_user_type ? 'checked' : ''
                
            );            
            // Here, we'll take the first argument of the array and add it to a label next to the checkbox
            $html = '<label for="wcap_salesforce_security_token_label"> '  . $args[0] . '</label>';
            echo $html;
        }

        /***************************************************************
         * WP Settings API callback for salesforce lead company 
        **************************************************************/
        function wcap_salesforce_lead_company_callback($args) {
            $wcap_salesforce_user_type = get_option( 'wcap_salesforce_user_type' );
            $display                   = '';
            if ( 'contact' == $wcap_salesforce_user_type ){
                $display = 'none';
            }            
            // First, we read the option
            $wcap_salesforce_lead_company = get_option( 'wcap_salesforce_lead_company' );            
            // Next, we update the name attribute to access this element's ID in the context of the display options array
            // We also access the show_header element of the options collection in the call to the checked() helper function
            printf(
                '<input type="text" placeholder = "Abandoned Cart Plugin" class = "wcap_salesforce_lead_company" id="wcap_salesforce_lead_company" name="wcap_salesforce_lead_company" value="%s" style = "display:%s" />',
                isset( $wcap_salesforce_lead_company ) ? esc_attr( $wcap_salesforce_lead_company ) : '',
                $display
            );
            // Here, we'll take the first argument of the array and add it to a label next to the checkbox
            $html = '<label class = "wcap_salesforce_lead_company" for="wcap_salesforce_lead_company_label" style= "display:'.$display.'"> '  . $args[0] . '</label>' ;
            echo $html;
        }

        public static function wcap_salesforce_test_connection_callback() {
            
            print "<a href='' id='wcap_salesforce_test' class= 'wcap_salesforce_test' >" . __( 'Test Connection', 'woocommerce-ac' ) . "</a> &nbsp &nbsp
                <img src='" . plugins_url() . "/woocommerce-abandon-cart-pro/assets/images/loading.gif' alt='Loading...' id='wcap_salesforce_test_connection_ajax_loader' class = 'wcap_salesforce_test_connection_ajax_loader' >";
            print "<div id='wcap_salesforce_test_connection_message'></div>";
        }

        function wcap_salesforce_crm_display_data () {
            ?>
            <div id="wcap_manual_email_data_loading" >
                <img  id="wcap_manual_email_data_loading_image" src="<?php echo plugins_url(); ?>/woocommerce-abandon-cart-pro/assets/images/loading.gif" alt="Loading...">
            </div>
            <?php
            /*
                When we use the bulk action it will allot the action and mode.
            */
            $wcap_action = "";
            /*
            When we click on the hover link it will take the action.
            */
            if ( '' == $wcap_action && isset( $_GET['action'] ) ) { 
                $wcap_action = $_GET['action'];
            }
            /*
             *  It will add the settings in the New tab.
             */
            if ( 'wcap_crm' == $wcap_action ) {
                ?>
                <p><?php _e( 'Change settings for exporting the abandoned cart data to the Salesforce CRM.', 'woocommerce-ac' ); ?></p>
                <form method="post" action="options.php" id="wcap_salesforce_crm_form">
                    <?php settings_fields     ( 'wcap_salesforce_crm_setting' ); ?>
                    <?php do_settings_sections( 'wcap_salesforce_crm_section' ); ?>
                    <?php submit_button( 'Save Salesforce Setting', 'primary', 'wcap-save-salesforce-settings' ); ?>
                </form>
                <?php
            }
        }

        function wcap_add_to_salesforce_crm_callback (){
            global $wpdb, $woocommerce;
            $ids = array();            
            if ( $_POST [ 'wcap_all' ] == 'yes' ) {
                $blank_cart_info         = '{"cart":[]}';
                $blank_cart_info_guest   = '[]';
                $wcap_get_all_abandoned_carts = "SELECT id FROM `".$wpdb->prefix."ac_abandoned_cart_history` WHERE `id` NOT IN ( SELECT abandoned_cart_id FROM `".$wpdb->prefix."wcap_salesforce_abandoned_cart`) AND user_id > 0 AND recovered_cart = 0 AND abandoned_cart_info NOT LIKE '$blank_cart_info_guest' AND abandoned_cart_info NOT LIKE '%$blank_cart_info%'";                
                $abandoned_cart_results  = $wpdb->get_results( $wcap_get_all_abandoned_carts );

                if ( empty( $abandoned_cart_results ) ){
                    echo 'no_record';
                    wp_die();
                }
                foreach ( $abandoned_cart_results as $abandoned_cart_results_key => $abandoned_cart_results_value ) {
                    $ids [] = $abandoned_cart_results_value->id;
                }
            } else {
                $ids = $_POST ['wcap_abandoned_cart_ids'];

                $wcap_check_duplicate_record = $wpdb->get_var ( "SELECT abandoned_cart_id FROM `".$wpdb->prefix."wcap_salesforce_abandoned_cart` WHERE `abandoned_cart_id` = $ids[0]" ); 
                if ( $wcap_check_duplicate_record > 0 ){
                    echo 'duplicate_record';
                    wp_die();
                }
            }
            
            $abandoned_order_count      = count ( $ids ); 

            $wcap_sf_username       = get_option ( 'wcap_salesforce_user_name' );
            $wcap_sf_password       = get_option ( 'wcap_salesforce_password' );
            $wcap_sf_security_token = get_option ( 'wcap_salesforce_security_token' );
            $wcap_sf_user_type      = get_option ( 'wcap_salesforce_user_type' );
            $wcap_lead_company      = get_option ( 'wcap_salesforce_lead_company' ) == '' ? 'Abandoned Cart Plugin ' : get_option ( 'wcap_salesforce_lead_company' );           
            foreach ( $ids as $id ) {
                $get_abandoned_cart     = "SELECT * FROM `".$wpdb->prefix."ac_abandoned_cart_history` WHERE id = $id";
                $abandoned_cart_results = $wpdb->get_results( $get_abandoned_cart );
                $wcap_user_id           = 0;
                $wcap_contact_email     = '';
                $wcap_user_last_name    = '';
                $wcap_user_first_name   = '';
                $wcap_user_phone        = '';
                $wcap_user_address      = '';
                $wcap_user_city         = '';
                $wcap_user_state        = '';
                $wcap_user_country      = '';                                

                if ( !empty( $abandoned_cart_results ) ) {
                    $wcap_user_id = $abandoned_cart_results[0]->user_id;
                    if ( $abandoned_cart_results[0]->user_type == "GUEST" && $abandoned_cart_results[0]->user_id != '0' ) {
                        $query_guest         = "SELECT billing_first_name, billing_last_name, email_id, phone FROM `" . $wpdb->prefix . "ac_guest_abandoned_cart_history` WHERE id = %d";
                        $results_guest       = $wpdb->get_results( $wpdb->prepare( $query_guest, $wcap_user_id ) );                        
                        if ( count ($results_guest) > 0 ) {
                            $wcap_contact_email   = $results_guest[0]->email_id;
                            $wcap_user_first_name = $results_guest[0]->billing_first_name;
                            $wcap_user_last_name  = $results_guest[0]->billing_last_name;
                            $wcap_user_phone      = $results_guest[0]->phone;
                        }       
                    } else {                                          
                        $wcap_contact_email = get_user_meta( $wcap_user_id, 'billing_email', true );                            
                        if( $wcap_contact_email == ""){  
                            $user_data = get_userdata( $wcap_user_id ); 
                            $wcap_contact_email = $user_data->user_email;   
                        }
                        
                        $user_first_name_temp = get_user_meta( $wcap_user_id, 'billing_first_name', true );
                        if( isset( $user_first_name_temp ) && "" == $user_first_name_temp ) {
                            $user_data  = get_userdata( $wcap_user_id );
                            $wcap_user_first_name = $user_data->first_name;
                        } else {
                            $wcap_user_first_name = $user_first_name_temp;
                        }
                                        
                        $user_last_name_temp = get_user_meta( $wcap_user_id, 'billing_last_name', true );
                        if( isset( $user_last_name_temp ) && "" == $user_last_name_temp ) {
                            $user_data  = get_userdata( $wcap_user_id );
                            $wcap_user_last_name = $user_data->last_name;
                        } else {
                            $wcap_user_last_name = $user_last_name_temp;
                        }

                        $user_billing_phone_temp = get_user_meta( $wcap_user_id, 'billing_phone' );
                        
                        if ( isset( $user_billing_phone_temp[0] ) ){
                            
                            $wcap_user_phone = $user_billing_phone_temp[0];
                        }

                        $user_billing_address_1_temp = get_user_meta( $wcap_user_id, 'billing_address_1' );
                        $user_billing_address_1 = "";
                        if ( isset( $user_billing_address_1_temp[0] ) ) {
                            $user_billing_address_1 = $user_billing_address_1_temp[0];
                        }
                        
                        $user_billing_address_2_temp = get_user_meta( $wcap_user_id, 'billing_address_2' );
                        $user_billing_address_2 = "";
                        if ( isset( $user_billing_address_2_temp[0] ) ) {
                            $user_billing_address_2 = $user_billing_address_2_temp[0];
                        }
                        $wcap_user_address = $user_billing_address_1 . $user_billing_address_2;

                        $user_billing_city_temp = get_user_meta( $wcap_user_id, 'billing_city' );
                        
                        if ( isset( $user_billing_city_temp[0] ) ) {
                            $wcap_user_city = $user_billing_city_temp[0];
                        }

                        $user_billing_country_temp = get_user_meta( $wcap_user_id, 'billing_country' );
                        
                        if ( isset( $user_billing_country_temp[0] ) ){
                            $user_billing_country = $user_billing_country_temp[0];
                            $wcap_user_country = $woocommerce->countries->countries[ $user_billing_country ];
                        }

                        $user_billing_state_temp = get_user_meta( $wcap_user_id, 'billing_state' );
                        if ( isset( $user_billing_state_temp[0] ) ){
                            $user_billing_state = $user_billing_state_temp[0];
                            $wcap_user_state = $woocommerce->countries->states[ $user_billing_country_temp[0] ][ $user_billing_state ];
                        }
                    }

                    $address = array(
                      
                    );

                    $cart_info_db_field = json_decode( $abandoned_cart_results[0]->abandoned_cart_info );
                    if( !empty( $cart_info_db_field ) ) {
                        $cart_details           = $cart_info_db_field->cart;
                    }
                    $product_name = '';
                    $wcap_product_details = '';
                    foreach( $cart_details as $cart_details_key => $cart_details_value ) {
                        $quantity_total = $cart_details_value->quantity;
                        $product_id     = $cart_details_value->product_id;
                        $prod_name      = get_post( $product_id );
                        $product_name   = $prod_name->post_title;
                        if( isset( $cart_details_value->variation_id ) && '' != $cart_details_value->variation_id ){
                            $variation_id               = $cart_details_value->variation_id;
                            $variation                  = wc_get_product( $variation_id );
                            $name                       = $variation->get_formatted_name() ;
                            $explode_all                = explode( "&ndash;", $name );
                            $pro_name_variation         = array_slice( $explode_all, 1, -1 );
                            $product_name_with_variable = '';
                            $explode_many_varaition     = array();
                        
                            foreach ( $pro_name_variation as $pro_name_variation_key => $pro_name_variation_value ){
                                $explode_many_varaition = explode ( ",", $pro_name_variation_value );
                                if ( !empty( $explode_many_varaition ) ) {
                                    foreach( $explode_many_varaition as $explode_many_varaition_key => $explode_many_varaition_value ){
                                        $product_name_with_variable = $product_name_with_variable . "\n". html_entity_decode ( $explode_many_varaition_value );
                                    }
                                } else {
                                    $product_name_with_variable = $product_name_with_variable . "\n". html_entity_decode ( $explode_many_varaition_value );
                                }
                            }
                            $product_name = $product_name_with_variable;
                        }
                       $wcap_product_details .= html_entity_decode ( "Product Name: " . $product_name . " , Quantity: " . $quantity_total ) . "\n";
                    }
                    $wcap_contact = array();
                    if ( 'lead' == $wcap_sf_user_type ){                        
                        $wcap_contact = array(
                            "firstname"  => $wcap_user_first_name,
                            "lastname"   => $wcap_user_last_name,
                            "email"      => $wcap_contact_email,
                            "phone"      => $wcap_user_phone,
                            "street"     => $wcap_user_address,
                            "city"       => $wcap_user_city,
                            "state"      => $wcap_user_state,
                            "country"    => $wcap_user_country,
                            "company"    => $wcap_lead_company,
                            "description" => $wcap_product_details
                        );
                    } else if ( 'contact' == $wcap_sf_user_type ){                        
                        $wcap_contact = array(
                            "firstname" => $wcap_user_first_name,
                            "lastname"  => $wcap_user_last_name,
                            "email"     => $wcap_contact_email,
                            "phone"     => $wcap_user_phone
                        );
                    }
                    
                    $wcap_posted_result = Wcap_Add_To_Salesforce_CRM::wcap_add_data_to_salesforce_crm ( $wcap_contact, $wcap_sf_username, $wcap_sf_password, $wcap_sf_security_token, $wcap_sf_user_type, $wcap_product_details );
                }                
                $wcap_insert_abandoned_id = "INSERT INTO `" . $wpdb->prefix . "wcap_salesforce_abandoned_cart` ( abandoned_cart_id, date_time )
                                          VALUES ( '" . $id . "', '" . current_time( 'mysql' ) . "' )";      
                $wpdb->query( $wcap_insert_abandoned_id );
            }

            echo $abandoned_order_count ;
            wp_die();
        }

        function wcap_add_export_all_data_to_salesforce_crm () {
           $wcap_salesforce_crm_check = get_option ( 'wcap_enable_salesforce_crm' );
           $wcap_sf_crm_check_connection = get_option ( 'wcap_salesforce_connection_established' );
           if ( 'on' == $wcap_salesforce_crm_check && 'yes' == $wcap_sf_crm_check_connection ) { 
            ?>
            <a href="javascript:void(0);"  id = "add_all_carts_salesforce" class="button-secondary"><?php _e( 'Export to Salesforce CRM', 'woocommerce-ac' ); ?></a>
            <?php
           }
        }

        function wcap_add_individual_record_to_salesforce_crm ( $actions, $abandoned_row_info ) {
            $wcap_salesforce_crm_check = get_option ( 'wcap_enable_salesforce_crm' );
            $wcap_sf_crm_check_connection = get_option ( 'wcap_salesforce_connection_established' );
            if ( 'on' == $wcap_salesforce_crm_check && 'yes' == $wcap_sf_crm_check_connection ) { 
                if ( $abandoned_row_info->user_id != 0 ) {
                    $abandoned_order_id         = $abandoned_row_info->id ;
                    $class_abandoned_orders     = new WCAP_Abandoned_Orders_Table();
                    $abandoned_orders_base_url  = $class_abandoned_orders->base_url;                    
                    $inserted['wcap_add_salesforce'] = '<a href="javascript:void(0);" class="add_single_cart_salesforce" data-id="' . $abandoned_order_id . '">' . __( 'Add to Salesforce CRM', 'woocommerce-ac' ) . '</a>';
                    $count                      = count ( $actions ) - 1 ;
                    array_splice( $actions, $count, 0, $inserted ); // it will add the new data just before the Trash link.
                }
            }
            return $actions;
        }

        function wcap_add_bulk_record_to_salesforce_crm ( $wcap_abandoned_bulk_actions ){
            $wcap_salesforce_crm_check    = get_option ( 'wcap_enable_salesforce_crm' );
            $wcap_sf_crm_check_connection = get_option ( 'wcap_salesforce_connection_established' );
            if ( 'on' == $wcap_salesforce_crm_check && 'yes' == $wcap_sf_crm_check_connection ) {
                $inserted = array(
                    'wcap_add_salesforce' => __( 'Add to Salesforce CRM', 'woocommerce-ac' )
                );                
                $wcap_abandoned_bulk_actions =  $wcap_abandoned_bulk_actions + $inserted ;
            }
            return $wcap_abandoned_bulk_actions;
        }
    }
}
$wcap_sf_crm_call = new Wcap_Salesforce_CRM();