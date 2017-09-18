<?php

defined( 'ABSPATH' ) or exit;

class WC_MPL_Admin {

      /** @var string sub-menu page hook suffix */
     private $settings_tab_id = 'mpl';

     public function __construct() {
          // add 'Litmos' tab to WC settings
     add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 100 );
     // show settings
     add_action( 'woocommerce_settings_tabs_' . $this->settings_tab_id, array( $this, 'render_settings' ) );
     // save settings
     add_action( 'woocommerce_update_options_' . $this->settings_tab_id, array( $this, 'save_settings' ) );

     add_filter( 'woocommerce_get_sections_mpl', 'add_section' );
     }


     /**
* Add tab to WooCommerce Settings tabs
*
* @since 1.0
* @param array $settings_tabs tabs array sans 'Litmos' tab
* @return array $settings_tabs now with 100% more 'Litmos' tab!
*/
public function add_settings_tab( $settings_tabs ) {

     $settings_tabs[ $this->settings_tab_id ] = __( 'MPL', 'woocommerce-mpl' );

     return $settings_tabs;
}

function add_section( $sections ) {

	$sections['test'] = __( 'mpl', 'text-domain' );
	return $sections;

}


/**
* Render the 'Litmos' settings page
*
* @since 1.0
*/
public function render_settings() {
     woocommerce_admin_fields( $this->get_settings() );
}


/**
* Save the 'Litmos' settings page
*
* @since 1.0
*/
public function save_settings() {
  woocommerce_update_options( $this->get_settings() );
}

  /**
  * Returns settings array for use byrender/save/install default  settings methods
  *
  * @since 1.0
  * @return array settings
  */
  public static function get_settings() {
    return array(
      array(
        'name' => __( 'MPL Custom Plugin Settings', 'woocommerce-mpl' ),
        'type' => 'title'
      ),
      array(
        'id'      => 'exclude_jetpack_related_from_products',
        'name'    => __( 'Disable Jetpack Related Posts on WC Products', 'woocommerce-mpl' ),
        'desc'    => __( 'Disable Jetpack related posts', 'woocommerce-mpl' ),
        'default' => 'yes',
        'type'    => 'checkbox'
      ),
      array(
        'id'      => 'clean_wc_checkout_page',
        'name'    => __( 'Clean WC Checkout Page', 'woocommerce-mpl' ),
        'desc'    => __( 'Add "Checkout" and site logo on WC checkout page and remove all other header distractions.', 'woocommerce-mpl' ),
        'default' => 'yes',
        'type'    => 'checkbox'
       ),
       array(
        'id'      => 'deactivate_litmos_users',
        'name'    => __( 'Deactivate Litmos Users', 'woocommerce-mpl' ),
        'desc'    => __( 'Enable this automatically deactivate Litmos users from the system every day if they\'ve had an account for longer than x days.', 'woocommerce-mpl' ),
        'default' => 'yes',
        'type'    => 'checkbox'
      ),
      array(
        'id'      =>  'deactivate_litmos_users_day',
        'name'    => __( 'Deactivate Litmos Users After',  'woocommerce-mpl' ),
        'desc'    => __( 'days', 'woocommerce-mpl' ),
        'default' => 30,
        'type'    => 'text'
      ),
      array(
        'id'      => 'prioritize_email_field',
        'name'    => __( 'Prioritize Email Field', 'woocommerce-mpl' ),
        'desc'    => __( 'Enable this to move the email address field to the top of the checkout page.', 'woocommerce-mpl' ),
        'default' => 'yes',
        'type'    => 'checkbox'
      ),
			array(
        'id'      => 'enable_store_atc',
        'name'    => __( 'Enable Store ATC', 'woocommerce-mpl' ),
        'desc'    => __( 'Enable this to activate the Add-To-Cart button on the store catalog page.', 'woocommerce-mpl' ),
        'default' => 'yes',
        'type'    => 'checkbox'
      ),
			array(
        'id'      => 'replace_coupon_code',
        'name'    => __( 'Replace Coupon Code', 'woocommerce-mpl' ),
        'desc'    => __( 'Enable this to replace Coupon Code text (will replace on settings page too).', 'woocommerce-mpl' ),
        'default' => 'no',
        'type'    => 'checkbox'
      ),
      array(
        'id'      => 'replace_coupon_code_string',
        'name'    => __( 'Replace Coupon Code String', 'woocommerce-mpl' ),
        'desc'    => __( 'Replacement Text', 'woocommerce-mpl' ),
        'default' => 'Promo Code',
        'type'    => 'text'
      ),
			array(
        'id'      => 'hide_coupon_field_on_cart',
        'name'    => __( 'Hide Coupon Field On Cart', 'woocommerce-mpl' ),
        'desc'    => __( 'Enable this to hide entirety of WC bottom cart row (coupon and update quantity) thru css', 'woocommerce-mpl' ),
        'default' => 'yes',
        'type'    => 'checkbox'
      ),
			array(
        'id'      => 'custom_woocommerce_auto_complete_order',
        'name'    => __( 'Auto Complete Order', 'woocommerce-mpl' ),
        'desc'    => __( 'Enable this to automatically set WC orders from processing -> complete', 'woocommerce-mpl' ),
        'default' => 'yes',
        'type'    => 'checkbox'
      ),


      array(
        'id'      => 'zh_remove_cart_message_button',
        'name'    => __( 'Remove Cart Continue Shopping Message', 'woocommerce-mpl' ),
        'desc'    => __( 'Enable this to hide the "Continue Shopping" button from successfully added to cart banner message.', 'woocommerce-mpl' ),
        'default' => 'yes',
        'type'    => 'checkbox'
      ),

      array(
        'id'      => 'display_continue_shopping',
        'name'    => __( 'Display Continue Shopping on Cart', 'woocommerce-mpl' ),
        'desc'    => __( 'Enable this to show a button that links back to the Store page on the Cart page.', 'woocommerce-mpl' ),
        'default' => 'yes',
        'type'    => 'checkbox'
      ),

      array(
        'id'      => 'display_return_to_store',
        'name'    => __( 'Display "Return to Store" button on Product page', 'woocommerce-mpl' ),
        'desc'    => __( 'Enable this to show a button that links back to the Store page on the Product page.', 'woocommerce-mpl' ),
        'default' => 'yes',
        'type'    => 'checkbox'
      ),
      array( 'type' => 'sectionend' ),

      array(
        'name' => __( 'Constant Contact Integration Settings', 'woocommerce-mpl' ),
        'type' => 'title'
      ),
      array(
        'id'      => 'constant_contact_integration',
        'name'    => __( 'Constant Contact Integration', 'woocommerce-mpl' ),
        'desc'    => __( 'Enable this to add a checkbox for subscribing to a CC list on the checkout page', 'woocommerce-mpl' ),
        'default' => 'no',
        'type'    => 'checkbox'
      ),
      array(
        'id'      => 'constant_contact_integration_paceholder',
        'name'    => __( 'Checkbox Text', 'woocommerce-mpl' ),
        'desc'    => __( '', 'woocommerce-mpl' ),
        'default' => 'Subscribe to mailing list',
        'type'    => 'text'
      ),
      array(
        'id'      => 'constant_contact_integration_label',
        'name'    => __( 'Checkbox Text', 'woocommerce-mpl' ),
        'desc'    => __( '', 'woocommerce-mpl' ),
        'default' => 'Sign me up for the Newsletter!',
        'type'    => 'text'
      ),
      array(
        'id'      => 'constant_contact_integration_username',
        'name'    => __( 'Username', 'woocommerce-mpl' ),
        'desc'    => __( '', 'woocommerce-mpl' ),
        'type'    => 'text'
      ),
      array(
        'id'      => 'constant_contact_integration_access_token',
        'name'    => __( 'Access Token', 'woocommerce-mpl' ),
        'desc'    => __( '', 'woocommerce-mpl' ),
        'type'    => 'text'
      ),
      array(
        'id'      => 'constant_contact_integration_list_id',
        'name'    => __( 'List ID', 'woocommerce-mpl' ),
        'desc'    => __( '', 'woocommerce-mpl' ),
        'type'    => 'text'
      ),

      array( 'type' => 'sectionend' ),
    );
  }

}

?>
