<?php
/*
Plugin Name: WooCommerce MPL
Plugin URI: https://github.com/zachhardesty7/woocommerce-mpl
Description: WooCommerce MPL custom plugin
Author: Zach Hardesty
Author URI: http://zachhardesty.com
Version: 1.0.0

	Copyright: © 2016 Zach Hardesty (email : hello@zachhardesty.com)
	License: GNU General Public License v3.0
	License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

/**
 * Check if WooCommerce is active
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	if ( ! class_exists( 'WC_MPL' ) ) {

		/**
		 * Localisation
		 **/
		load_plugin_textdomain( 'wc_mpl', false, dirname( plugin_basename( __FILE__ ) ) . '/' );

		class WC_MPL {
			public function __construct() {
				// called only after woocommerce has finished loading
				add_action( 'woocommerce_init', array( &$this, 'woocommerce_loaded' ) );

				// called after all plugins have loaded
				add_action( 'plugins_loaded', array( &$this, 'plugins_loaded' ) );

				// called just before the woocommerce template functions are included
				add_action( 'init', array( &$this, 'include_template_functions' ), 20 );

				// indicates we are running the admin
				if ( is_admin() ) {
					// ...
				}

				// indicates we are being served over ssl
				if ( is_ssl() ) {
					// ...
				}



				// take care of anything else that needs to be done immediately upon plugin instantiation, here in the constructor
				add_action( 'init',
					array(__CLASS__, 'remove_wc_breadcrumbs') );

				add_filter('woocommerce_checkout_fields',
					array(__CLASS__, 'filterWooCheckoutFields'));

				add_action('woocommerce_checkout_update_order_meta',
					array(__CLASS__, 'actionWooCheckoutUpdateOrderMeta'));

				//add_filter('woocommerce_email_order_meta_keys',
				//    array(__CLASS__, 'filterWooEmailOrderMetaKeys'));

				add_action('woocommerce_before_cart_contents',
					array(__CLASS__, 'cartBackToVendorStorePage'));

				add_action('woocommerce_before_single_product',
					array(__CLASS__, 'productBackToVendorStorePage'));
			}

			/**
			 * Take care of anything that needs woocommerce to be loaded.
			 * For instance, if you need access to the $woocommerce global
			 */
			public function woocommerce_loaded() {
				// ...
			}

			/**
			 * Take care of anything that needs all plugins to be loaded
			 */
			public function plugins_loaded() {
				// ...
			}

			/**
			 * Override any of the template functions from woocommerce/woocommerce-template.php
			 * with our own template functions file
			 */
			public function include_template_functions() {
				include( 'woocommerce-template.php' );
			}

			/**
			 * https://docs.woocommerce.com/document/customise-the-woocommerce-breadcrumb/
			 */
			function remove_wc_breadcrumbs() {
			    remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20, 0 );
			}

			/**
	 * add custom fields to WooCommerce checkout
	 * @param array fields
	 * @return array
	 */
	 public static function filterWooCheckoutFields($fields) {
			 global $woocommerce;

			 // add field at end of billing fields section for terms of service
			 $fields['billing']['terms_of_service'] = array(
					 'type' => 'checkbox',
					 'label' => 'I have read and agree to the <a href="/terms-of-service/" target="_blank">terms of service</a>',
					 'placeholder' => 'Agree to terms of service',
					 'required' => true,
					 'class' => array(),
					 'label_class' => array(),
					 'default' => false,
					 'custom_attributes' => array('style' => 'display: inline-block'),
			 );

			 // add field at end of billing fields section for newsletter
			 $fields['billing']['our_mailing_subscribe'] = array(
					 'type' => 'checkbox',
					 'label' => 'Sign me up for the MPL Newsletter!',
					 'placeholder' => 'Subscribe to mailing list',
					 'required' => false,
					 'class' => array(),
					 'label_class' => array(),
					 'default' => true,
					 'custom_attributes' => array('style'=>'display: inline-block'),
			 );

			 return $fields;
	 }

	 /**
    * save custom order fields
    * @param int $order_id
    */
    public static function actionWooCheckoutUpdateOrderMeta($order_id) {
        if (isset($_POST['our_mailing_subscribe'])) {

		$emailAddress = $_POST['billing_email'];
		$firstname = $_POST["billing_first_name"];
		$lastname = $_POST["billing_last_name"];

// Your Constant Contact Username
		$username = "";
// Your Constant Contact Access Token
		$accessToken = "";
// The Constant Contact List you want the New Contact to be created in
		$listid = "";

		$dt = date('c');

// The XML to be sent
		$body = "
<entry xmlns=\"http://www.w3.org/2005/Atom\">
<title type=\"text\"> </title>
  <updated>$dt</updated>
  <author></author>
  <id>data:,none</id>
  <summary type=\"text\">Contact</summary>
  <content type=\"application/vnd.ctct+xml\">
    <Contact xmlns=\"http://ws.constantcontact.com/ns/1.0/\">
      <EmailAddress>$emailAddress</EmailAddress>
      <FirstName>$firstname</FirstName>
      <LastName>$lastname</LastName>
      <OptInSource>ACTION_BY_CONTACT</OptInSource>
      <ContactLists>
        <ContactList id=\"http://api.constantcontact.com/ws/customers/$username/lists/$listid\" />
      </ContactLists>
    </Contact>
  </content>
</entry>";

		include_once( ABSPATH . WPINC. '/class-http.php' );

		$url = "https://api.constantcontact.com/ws/customers/$username/contacts?access_token=$accessToken";
		$request = new WP_Http;
		$headers = array( 'Content-Type' => 'application/atom+xml' );
		$result = $request->request( $url, array( 'method' => 'POST', 'body' => $body, 'headers' => $headers ));

	}
}
	update_post_meta($order_id, 'Subscribe to mailing list',
		stripslashes($_POST['our_mailing_subscribe']));
    }

		}

		// finally instantiate our plugin class and add it to the set of globals
		$GLOBALS['wc_mpl'] = new WC_MPL();
	}
}
