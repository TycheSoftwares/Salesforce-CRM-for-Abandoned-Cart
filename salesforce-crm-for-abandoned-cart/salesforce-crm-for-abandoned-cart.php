<?php
/*
Plugin Name: Salesforce CRM Addon for Abandoned Cart Pro for WooCommerce
Plugin URI: http://www.tychesoftwares.com/store/premium-plugins/woocommerce-abandoned-cart-pro
Description: This plugin allow you to export the abandoned cart data to your Salesforce CRM.
Version: 1.0
Author: Tyche Softwares
Author URI: http://www.tychesoftwares.com/
*/

require_once ( "cron/wcap_salseforce_add_abandoned_data.php" );
require_once ( "includes/class_add_to_salesforce_crm.php" );
require_once ('soapclient/SforcePartnerClient.php');


// Add a new interval of 1 Day
add_filter( 'cron_schedules', 'wcap_salseforce_add_data_schedule' );

function wcap_salseforce_add_data_schedule( $schedules ) {

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
if ( ! wp_next_scheduled( 'wcap_salseforce_add_abandoned_data_schedule' ) ) {
    wp_schedule_event( time(), '1_day', 'wcap_salseforce_add_abandoned_data_schedule' );
}

register_uninstall_hook( __FILE__, 'wcap_salseforce_crm_uninstall' );

function wcap_salseforce_crm_uninstall (){
	global $wpdb;
	
	$wcap_salseforce_table_name = $wpdb->prefix . "wcap_salseforce_abandoned_cart";
 	$sql_wcap_salseforce_table_name = "DROP TABLE " . $wcap_salseforce_table_name ;
 	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    $wpdb->get_results( $sql_wcap_salseforce_table_name );

    delete_option( 'wcap_enable_salseforce_crm' );
    delete_option( 'wcap_add_automatically_to_salseforce_crm' );
    delete_option( 'wcap_sf_add_automatically_add_after_email_frequency' );
    delete_option( 'wcap_sf_add_automatically_add_after_time_day_or_hour' );
    delete_option( 'wcap_salseforce_last_id_checked' );

    delete_option( 'wcap_salseforce_user_name' );
    delete_option( 'wcap_salseforce_password' );
    delete_option( 'wcap_salseforce_security_token' );
    delete_option( 'wcap_salseforce_user_type' );

    wp_clear_scheduled_hook( 'wcap_salseforce_add_abandoned_data_schedule' );
}

if ( ! class_exists( 'Wcap_salseforce_CRM' ) ) {

	class Wcap_Salseforce_CRM {

		public function __construct( ) {
			register_activation_hook( __FILE__,                         array( &$this, 'wcap_salseforce_crm_create_table' ) );
            if ( ! has_action ('wcap_add_tabs' ) ){
                add_action ( 'wcap_add_tabs',     				        array( &$this, 'wcap_salseforce_crm_add_tab' ) );
            }
			add_action ( 'admin_init',          				        array( &$this, 'wcap_salseforce_crm_initialize_settings_options' ), 11 );
			add_action ( 'wcap_display_message', 				        array( &$this, 'wcap_salseforce_crm_display_message' ),11 );
			add_action ( 'wcap_crm_data', 				                array( &$this, 'wcap_salseforce_crm_display_data' ), 15 );
			add_action ( 'wcap_add_buttons_on_abandoned_orders',        array( &$this, 'wcap_add_export_all_data_to_salseforce_crm' ) );
			add_filter ( 'wcap_abandoned_orders_single_column' ,        array( &$this, 'wcap_add_individual_record_to_salseforce_crm' ), 11 , 2 );
			add_filter ( 'wcap_abandoned_order_add_bulk_action',        array( &$this, 'wcap_add_bulk_record_to_salseforce_crm' ), 11 , 1 );
			add_action ( 'wp_ajax_wcap_add_to_salseforce_crm', 	        array( &$this, 'wcap_add_to_salseforce_crm_callback' ));
			add_action ( 'admin_enqueue_scripts',                       array( &$this, 'wcap_salseforce_enqueue_scripts_js' ) );
			add_action ( 'admin_enqueue_scripts',                       array( &$this, 'wcap_salseforce_enqueue_scripts_css' ) );
			add_action ( 'wcap_salseforce_add_abandoned_data_schedule', array( 'Wcap_Salesforce_CRM_Add_Cron_Data', 'wcap_add_salesforce_abandoned_cart_data' ),11 );
			/*
             * When cron job time changed this function will be called.
             * It is used to reset the cron time again.
             */
            add_action ( 'update_option_wcap_sf_add_automatically_add_after_email_frequency',  array( &$this,'wcap_salseforce_reset_cron_time_duration' ),11 );
            add_action ( 'update_option_wcap_sf_add_automatically_add_after_time_day_or_hour', array( &$this,'wcap_salseforce_reset_cron_time_duration' ),11 );
		}

		function wcap_salseforce_crm_create_table (){

			global $wpdb;

			$wcap_collate = '';
            if ( $wpdb->has_cap( 'collation' ) ) {
                $wcap_collate = $wpdb->get_charset_collate();
            }
            $table_name = $wpdb->prefix . "wcap_salseforce_abandoned_cart";

            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `abandoned_cart_id` int(11) COLLATE utf8_unicode_ci NOT NULL,
                    `date_time` TIMESTAMP on update CURRENT_TIMESTAMP COLLATE utf8_unicode_ci NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`)
                    ) $wcap_collate AUTO_INCREMENT=1 ";           
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            $wpdb->query( $sql );

            if ( !get_option ( 'wcap_enable_salseforce_crm' ) ){
                add_option ( 'wcap_enable_salseforce_crm', 'on' );
            }

            if ( !get_option ( 'wcap_salseforce_user_type' ) ){
                add_option ( 'wcap_salseforce_user_type', 'lead' );
            }
        }

		function wcap_salseforce_enqueue_scripts_js( $hook ) {
            if ( $hook != 'woocommerce_page_woocommerce_ac_page' ) {
                return;
            } else {
                wp_register_script( 'wcap-salseforce', plugins_url()  . '/salesforce-crm-for-abandoned-cart/assets/js/wcap_salseforce.js', array( 'jquery' ) );
                wp_enqueue_script( 'wcap-salseforce' );
                wp_localize_script( 'wcap-salseforce', 'wcap_salseforce_params', array(
	                                'ajax_url' => admin_url( 'admin-ajax.php' )
                ) );
            }
        }

        function wcap_salseforce_enqueue_scripts_css( $hook ) {
            
            if ( $hook != 'woocommerce_page_woocommerce_ac_page' ) {
                return;
            } else {
                wp_enqueue_style( 'wcap-salseforce',  plugins_url() . '/salesforce-crm-for-abandoned-cart/assets/css/wcap_salseforce_style.css'     );
			}
        }

        function wcap_salseforce_reset_cron_time_duration (){
            wp_clear_scheduled_hook( 'wcap_salseforce_add_abandoned_data_schedule' );
        }

        function wcap_salseforce_crm_add_tab () {
            $wcap_action           = "";
            if ( isset( $_GET['action'] ) ) {
                $wcap_action = $_GET['action'];
            }

            $wcap_salseforce_crm_active = "";
        	if (  'wcap_crm' == $wcap_action ) {
                $wcap_salseforce_crm_active = "nav-tab-active";
            }
            ?>
            <a href="admin.php?page=woocommerce_ac_page&action=wcap_crm" class="nav-tab <?php if( isset( $wcap_salseforce_crm_active ) ) echo $wcap_salseforce_crm_active; ?>"> <?php _e( 'Addon Settings', 'woocommerce-ac' );?> </a>
			<?php
            
        }

		function wcap_salseforce_crm_initialize_settings_options () {

            // First, we register a section. This is necessary since all future options must belong to a
            add_settings_section(
                'wcap_salseforce_crm_general_settings_section',         // ID used to identify this section and with which to register options
                __( 'Salesforce CRM Settings', 'woocommerce-ac' ),                  // Title to be displayed on the administration page
                array($this, 'wcap_salseforce_crm_general_settings_section_callback' ), // Callback used to render the description of the section
                'wcap_salseforce_crm_section'     // Page on which to add this section of options
            );
             
            add_settings_field(
                'wcap_enable_salseforce_crm',
                __( 'Export abandoned cart data to Salesforce CRM', 'woocommerce-ac' ),
                array( $this, 'wcap_enable_salseforce_crm_callback' ),
                'wcap_salseforce_crm_section',
                'wcap_salseforce_crm_general_settings_section',
                array( __( 'Enable to export the abandoned carts data to the Salesforce CRM.', 'woocommerce-ac' ) )
            );

            add_settings_field(
                'wcap_add_automatically_to_salseforce_crm',
                __( 'Automatically add abandoned cart data to salesforce CRM', 'woocommerce-ac' ),
                array( $this, 'wcap_add_automatically_to_salseforce_crm_callback' ),
                'wcap_salseforce_crm_section',
                'wcap_salseforce_crm_general_settings_section',
                array( __( 'When any abandoned cart is displayed to the Abandoned Orders tab, it will be automatically exported to the salesforce CRM.', 'woocommerce-ac' ) )
            );

            add_settings_field(
                '',
                __( 'Automatically add abandoned cart data to salesforce CRM after set time.', 'woocommerce-ac' ),
                array( $this, 'wcap_add_automatically_add_after_time_callback' ),
                'wcap_salseforce_crm_section',
                'wcap_salseforce_crm_general_settings_section',
                array( __( 'When any abandoned cart is displayed to the Abandoned Orders tab, it will be automatically exported to the salesforce CRM after set time.', 'woocommerce-ac' ) )
            );

            add_settings_field(
                'wcap_salseforce_user_name',
                __( 'Salesforce Username', 'woocommerce-ac' ),
                array( $this, 'wcap_salseforce_user_name_callback' ),
                'wcap_salseforce_crm_section',
                'wcap_salseforce_crm_general_settings_section',
                array( __( 'Please provide your Salesforce username.', 'woocommerce-ac' ) )
            );

            add_settings_field(
                'wcap_salseforce_password',
                __( 'Salesforce Password', 'woocommerce-ac' ),
                array( $this, 'wcap_salseforce_password_callback' ),
                'wcap_salseforce_crm_section',
                'wcap_salseforce_crm_general_settings_section',
                array( __( 'Please provide your Salesforce password.', 'woocommerce-ac' ) )
            );

            add_settings_field(
                'wcap_salseforce_security_token',
                __( 'Salesforce Security Token', 'woocommerce-ac' ),
                array( $this, 'wcap_salseforce_security_token_callback' ),
                'wcap_salseforce_crm_section',
                'wcap_salseforce_crm_general_settings_section',
                array( __( 'Please provide your Salesforce Security Token. Please, login to your salesforce account then click on the Settings > My Personal Information > Reset My Security Token. Once you have clicked on the Reset Security Token, you will receive an email with the security token.', 'woocommerce-ac' ) )
            );

            add_settings_field(
                'wcap_salseforce_user_type',
                __( 'Create abandoned carts as', 'woocommerce-ac' ),
                array( $this, 'wcap_salseforce_user_type_callback' ),
                'wcap_salseforce_crm_section',
                'wcap_salseforce_crm_general_settings_section',
                array( __( 'Please select the user type which you want to create on your salesforce account.', 'woocommerce-ac' ) )
            );

            add_settings_field(
                'wcap_salseforce_lead_company',
                __( 'Company name of lead', 'woocommerce-ac' ),
                array( $this, 'wcap_salseforce_lead_company_callback' ),
                'wcap_salseforce_crm_section',
                'wcap_salseforce_crm_general_settings_section',
                array( __( 'Please select the user type which you want to create on your salesforce account.', 'woocommerce-ac' )  )
            );
                        
            // Finally, we register the fields with WordPress
            register_setting(
                'wcap_salseforce_crm_setting',
                'wcap_enable_salseforce_crm'
            );
            register_setting(
                'wcap_salseforce_crm_setting',
                'wcap_add_automatically_to_salseforce_crm'
            );
            register_setting(
                'wcap_salseforce_crm_setting',
                'wcap_sf_add_automatically_add_after_email_frequency'
            );
            register_setting(
                'wcap_salseforce_crm_setting',
                'wcap_sf_add_automatically_add_after_time_day_or_hour'
            );
            register_setting(
                'wcap_salseforce_crm_setting',
                'wcap_salseforce_user_name'
            );
            register_setting(
                'wcap_salseforce_crm_setting',
                'wcap_salseforce_password'
            );
            register_setting(
                'wcap_salseforce_crm_setting',
                'wcap_salseforce_security_token'
            );
            register_setting(
                'wcap_salseforce_crm_setting',
                'wcap_salseforce_user_type'
            );
            register_setting(
                'wcap_salseforce_crm_setting',
                'wcap_salseforce_lead_company'
            );
		}

		/***************************************************************
         * WP Settings API callback for section
        **************************************************************/
        function wcap_salseforce_crm_general_settings_section_callback() {
             
        }

        /***************************************************************
         * WP Settings API callback for enable exporting the abandoned data to salseforce crm
        **************************************************************/
        function wcap_enable_salseforce_crm_callback( $args ) {
            // First, we read the option
            $wcap_enable_salseforce_crm = get_option( 'wcap_enable_salseforce_crm' );
            // This condition added to avoid the notie displyed while Check box is unchecked.
            if (isset( $wcap_enable_salseforce_crm ) &&  $wcap_enable_salseforce_crm == "") {
                $wcap_enable_salseforce_crm = 'off';
            }
        
            // Next, we update the name attribute to access this element's ID in the context of the display options array
            // We also access the show_header element of the options collection in the call to the checked() helper function 
            $html = '<input type="checkbox" id="wcap_enable_salseforce_crm" name="wcap_enable_salseforce_crm" value="on" ' . checked( 'on', $wcap_enable_salseforce_crm, false ) . '/>';
            
            // Here, we'll take the first argument of the array and add it to a label next to the checkbox
            $html .= '<label for="wcap_enable_salseforce_crm_lable"> '  . $args[0] . '</label>';
            echo $html;
        }
        /***************************************************************
         * WP Settings API callback for automatically exporting the abandoned data to salseforce crm
        **************************************************************/
        function wcap_add_automatically_to_salseforce_crm_callback( $args ) {
            // First, we read the option
            $wcap_add_automatically_to_salseforce_crm = get_option( 'wcap_add_automatically_to_salseforce_crm' );
            // This condition added to avoid the notie displyed while Check box is unchecked.
            if (isset( $wcap_add_automatically_to_salseforce_crm ) &&  $wcap_add_automatically_to_salseforce_crm == "") {
                $wcap_add_automatically_to_salseforce_crm = 'off';
            }
        
            $html  = '<input type="checkbox" id="wcap_add_automatically_to_salseforce_crm" name="wcap_add_automatically_to_salseforce_crm" value="on" ' . checked( 'on', $wcap_add_automatically_to_salseforce_crm, false ) . '/>';
            $html .= '<label for="wcap_add_automatically_to_salseforce_crm_lable"> '  . $args[0] . '</label>';
            echo $html;
        }

        /***************************************************************
         * WP Settings API callback for automatically exporting the abandoned data to salseforce crm
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

        function wcap_salseforce_crm_display_message (){

        	$wcap_action           = "";
            if ( isset( $_GET['action'] ) ){
                $wcap_action = $_GET['action'];
            }
            /*
				It will display the message when abandoned cart data successfuly added to salseforce crm.
            */
            ?>
            <div id="wcap_salseforce_message" class="updated fade settings-error notice is-dismissible">
                <p class="wcap_salseforce_message_p">
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
            <?php 
        }
        /***************************************************************
         * WP Settings API callback for salesforce user name
        **************************************************************/
        function wcap_salseforce_user_name_callback($args) {
            
            // First, we read the option
            $wcap_salseforce_user_name = get_option( 'wcap_salseforce_user_name' );
            
            // Next, we update the name attribute to access this element's ID in the context of the display options array
            // We also access the show_header element of the options collection in the call to the checked() helper function
            printf(
                '<input type="text" id="wcap_salseforce_user_name" name="wcap_salseforce_user_name" value="%s" />',
                isset( $wcap_salseforce_user_name ) ? esc_attr( $wcap_salseforce_user_name ) : ''
            );
            
            // Here, we'll take the first argument of the array and add it to a label next to the checkbox
            $html = '<label for="wcap_salseforce_user_name_label"> '  . $args[0] . '</label> <br>  <span id ="wcap_salseforce_user_name_label_error" > Please enter your Salesforce username token. </span>';
            echo $html;
        }

        /***************************************************************
         * WP Settings API callback for salesforce password
        **************************************************************/
        function wcap_salseforce_password_callback($args) {
            
            // First, we read the option
            $wcap_salseforce_password = get_option( 'wcap_salseforce_password' );
            
            // Next, we update the name attribute to access this element's ID in the context of the display options array
            // We also access the show_header element of the options collection in the call to the checked() helper function
            printf(
                '<input type="text" id="wcap_salseforce_password" name="wcap_salseforce_password" value="%s" />',
                isset( $wcap_salseforce_password ) ? esc_attr( $wcap_salseforce_password ) : ''
            );
            
            // Here, we'll take the first argument of the array and add it to a label next to the checkbox
            $html = '<label for="wcap_salseforce_password_label"> '  . $args[0] . '</label> <br>  <span id ="wcap_salseforce_password_label_error" > Please enter your Salesforce password token. </span>';
            echo $html;
        }

        /***************************************************************
         * WP Settings API callback for salesforce security token
        **************************************************************/
        function wcap_salseforce_security_token_callback($args) {
            
            // First, we read the option
            $wcap_salseforce_security_token = get_option( 'wcap_salseforce_security_token' );
            
            // Next, we update the name attribute to access this element's ID in the context of the display options array
            // We also access the show_header element of the options collection in the call to the checked() helper function
            printf(
                '<input type="text" id="wcap_salseforce_security_token" name="wcap_salseforce_security_token" value="%s" />',
                isset( $wcap_salseforce_security_token ) ? esc_attr( $wcap_salseforce_security_token ) : ''
            );
            
            // Here, we'll take the first argument of the array and add it to a label next to the checkbox
            $html = '<label for="wcap_salseforce_security_token_label"> '  . $args[0] . '</label> <br>  <span id ="wcap_salseforce_security_token_label_error"> Please enter your Salesforce security token. </span>';
            echo $html;
        }

        /***************************************************************
         * WP Settings API callback for salesforce User type
        **************************************************************/
        function wcap_salseforce_user_type_callback($args) {
            
            // First, we read the option
            $wcap_salseforce_user_type = get_option( 'wcap_salseforce_user_type' );
            
            // Next, we update the name attribute to access this element's ID in the context of the display options array
            // We also access the show_header element of the options collection in the call to the checked() helper function
            printf(
                '<input type="radio" id="wcap_salseforce_user_type" name="wcap_salseforce_user_type" value="lead" %s /> Lead
                &nbsp; &nbsp; 
                <input type="radio" id="wcap_salseforce_user_type" name="wcap_salseforce_user_type" value="contact" %s /> Contact &nbsp;&nbsp; ',
                isset( $wcap_salseforce_user_type ) && 'lead' == $wcap_salseforce_user_type  ? 'checked' : '',
                isset( $wcap_salseforce_user_type ) && 'contact' == $wcap_salseforce_user_type ? 'checked' : ''
                
            );
            
            // Here, we'll take the first argument of the array and add it to a label next to the checkbox
            $html = '<label for="wcap_salseforce_security_token_label"> '  . $args[0] . '</label>';
            echo $html;
        }

        /***************************************************************
         * WP Settings API callback for salesforce lead company 
        **************************************************************/
        function wcap_salseforce_lead_company_callback($args) {

            $wcap_salseforce_user_type = get_option( 'wcap_salseforce_user_type' );

            $display = 'none';
            if ( 'lead' == $wcap_salseforce_user_type ){
                $display = 'block';
            }
            
            // First, we read the option
            $wcap_salseforce_lead_company = get_option( 'wcap_salseforce_lead_company' );
            
            // Next, we update the name attribute to access this element's ID in the context of the display options array
            // We also access the show_header element of the options collection in the call to the checked() helper function
            printf(
                '<input type="text" class = "wcap_salseforce_lead_company" id="wcap_salseforce_lead_company" name="wcap_salseforce_lead_company" value="%s" style = "display:%s" />',
                isset( $wcap_salseforce_lead_company ) ? esc_attr( $wcap_salseforce_lead_company ) : '',
                $display
            );
            // Here, we'll take the first argument of the array and add it to a label next to the checkbox
            $html = '<label class = "wcap_salseforce_lead_company" for="wcap_salseforce_lead_company_label" style= "display:'.$display.'"> '  . $args[0] . '</label>' ;
            echo $html;
        }

		function wcap_salseforce_crm_display_data () {
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

            if ( '' == $wcap_action && isset( $_GET['action'] )) { 
                $wcap_action = $_GET['action'];
            }
			/*
			 *	It will add the settings in the New tab.
             */
            if ( 'wcap_crm' == $wcap_action ){
            	?>
            	<p><?php _e( 'Change settings for exporting the abandoned cart data to the Salesforce CRM.', 'woocommerce-ac' ); ?></p>

                <form method="post" action="options.php">
                    <?php settings_fields     ( 'wcap_salseforce_crm_setting' ); ?>
                    <?php do_settings_sections( 'wcap_salseforce_crm_section' ); ?>
                    <?php settings_errors(); ?>
                    <?php submit_button('Save Salesforce changes'); ?>
                </form>
                <?php
            }
		}

		function wcap_add_to_salseforce_crm_callback (){

			global $wpdb, $woocommerce;
			$ids = array();
			
			if ( $_POST [ 'wcap_all' ] == 'yes' ) {

				$blank_cart_info         = '{"cart":[]}';
	    		$blank_cart_info_guest   = '[]';
				$wcap_get_all_abandoned_carts = "SELECT id FROM `".$wpdb->prefix."ac_abandoned_cart_history` WHERE user_id > 0 AND recovered_cart = 0 AND abandoned_cart_info NOT LIKE '$blank_cart_info_guest' AND abandoned_cart_info NOT LIKE '%$blank_cart_info%'";
				
				$abandoned_cart_results  = $wpdb->get_results( $wcap_get_all_abandoned_carts );

        		foreach ( $abandoned_cart_results as $abandoned_cart_results_key => $abandoned_cart_results_value ) {
        			$ids [] = $abandoned_cart_results_value->id;
        		}
			} else {
                $ids = $_POST ['wcap_abandoned_cart_ids'];
			}
			
			$abandoned_order_count 	    = count ( $ids );
			
            foreach ( $ids as $id ) {

				$get_abandoned_cart     = "SELECT * FROM `".$wpdb->prefix."ac_abandoned_cart_history` WHERE id = $id";
        		$abandoned_cart_results = $wpdb->get_results( $get_abandoned_cart );
        		$wcap_user_id			= 0;
        		$wcap_contact_email	    = '';
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
                        $query_guest         = "SELECT billing_first_name, billing_last_name, email_id FROM `" . $wpdb->prefix . "ac_guest_abandoned_cart_history` WHERE id = %d";
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
                        $product_name   .= $prod_name->post_title;
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

                       $wcap_product_details = html_entity_decode ( $wcap_product_details . "Product Name: " . $product_name . " , Quantity: " . $quantity_total ) . "\n";
					}

                    $wcap_sf_username       = get_option ('wcap_salseforce_user_name');
                    $wcap_sf_password       = get_option ('wcap_salseforce_password');
                    $wcap_sf_security_token = get_option ('wcap_salseforce_security_token');
                    $wcap_sf_user_type      = get_option ('wcap_salseforce_user_type');

                    $wcap_lead_company = get_option ('wcap_salseforce_lead_company') == '' ? 'Abandoned Cart Plugin ' : get_option ('wcap_salseforce_lead_company');

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
                            "company"    => $wcap_lead_company
                        );
                    }else if ( 'contact' == $wcap_sf_user_type ){
                        
                        $wcap_contact = array(
                            "firstname" => $wcap_user_first_name,
                            "lastname"  => $wcap_user_last_name,
                            "email"     => $wcap_contact_email,
                            "phone"     => $wcap_user_phone
                        );
                    }
                    
                    $wcap_posted_result = Wcap_Add_To_Salsforce_CRM::wcap_add_data_to_salseforce_crm ( $wcap_contact, $wcap_sf_username, $wcap_sf_password, $wcap_sf_security_token, $wcap_sf_user_type, $wcap_product_details );
                }
				
				$wcap_insert_abandoned_id = "INSERT INTO `" . $wpdb->prefix . "wcap_salseforce_abandoned_cart` ( abandoned_cart_id, date_time )
                                          VALUES ( '" . $id . "', '" . current_time( 'mysql' ) . "' )";      
                $wpdb->query( $wcap_insert_abandoned_id );
            }

            echo $abandoned_order_count . ',' . $wcap_posted_result ;
            wp_die();
		}

		function wcap_add_export_all_data_to_salseforce_crm (){
			?>
			<a href="javascript:void(0);"  id = "add_all_carts_salesforce" class="button-secondary"><?php _e( 'Export to salseforce CRM', 'woocommerce-ac' ); ?></a>
			<?php
		}

		function wcap_add_individual_record_to_salseforce_crm ( $actions, $abandoned_row_info ){

			$wcap_salseforce_crm_check = get_option ( 'wcap_enable_salseforce_crm' );

			if ( 'on' == $wcap_salseforce_crm_check ) { 

				if ( $abandoned_row_info->user_id != 0 ){
					$abandoned_order_id         = $abandoned_row_info->id ;
					$class_abandoned_orders     = new WCAP_Abandoned_Orders_Table();
			        $abandoned_orders_base_url  = $class_abandoned_orders->base_url;
			        
			        $inserted['wcap_add_agile'] = '<a href="javascript:void(0);" class="add_single_cart_salesforce" data-id="' . $abandoned_order_id . '">' . __( 'Add to Salseforce CRM', 'woocommerce-ac' ) . '</a>';

			        $count = count ( $actions ) - 1 ;

			        array_splice( $actions, $count, 0, $inserted ); // it will add the new data just before the Trash link.
		    	}
	    	}

	        return $actions;
		}

		function wcap_add_bulk_record_to_salseforce_crm ( $wcap_abandoned_bulk_actions ){
			$wcap_salseforce_crm_check = get_option ( 'wcap_enable_salseforce_crm' );
			if ( 'on' == $wcap_salseforce_crm_check ) {
				$inserted = array(
		        	'wcap_add_salseforce' => __( 'Add to Salseforce CRM', 'woocommerce-ac' )
		    	);
				
		        $wcap_abandoned_bulk_actions =  $wcap_abandoned_bulk_actions + $inserted ;
	    	}
	        return $wcap_abandoned_bulk_actions;
		}

	}
}
$wcap_agile_crm_call = new Wcap_Salseforce_CRM();