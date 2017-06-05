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

		defined( 'ABSPATH' ) or exit;
		add_action( 'plugins_loaded', 'wc_mpl' );

		function accessProtected($obj, $prop) {
			$reflection = new ReflectionClass($obj);
			$property = $reflection->getProperty($prop);
			$property->setAccessible(true);
			return $property->getValue($obj);
		}

		class WC_MPL {
			protected static $instance;
			public static function instance() {
				if ( is_null( self::$instance ) ) {
					self::$instance = new self();
				}
				return self::$instance;
			}



			public function __construct() {
				// called only after woocommerce has finished loading
				add_action( 'woocommerce_init', array( &$this, 'woocommerce_loaded' ) );

				// called after all plugins have loaded
				add_action( 'plugins_loaded', array( &$this, 'plugins_loaded' ) );

				// take care of anything else that needs to be done immediately upon plugin instantiation, here in the constructor
				add_action( 'init',
					array($this, 'remove_wc_breadcrumbs') );

				add_filter('woocommerce_checkout_fields',
					array($this, 'filterWooCheckoutFields'));

				add_action('woocommerce_checkout_update_order_meta',
					array($this, 'actionWooCheckoutUpdateOrderMeta'));

				// add_filter('woocommerce_email_order_meta_keys',
				//    array($this, 'filterWooEmailOrderMetaKeys'));

				add_action('woocommerce_before_cart_contents',
					array($this, 'cartBackToVendorStorePage'));

				add_action('woocommerce_before_single_product',
					array($this, 'productBackToVendorStorePage'));

				add_action('woocommerce_product_options_general_product_data',
					array($this, 'wcProductLitmosCourseCode'));
				add_action('woocommerce_process_product_meta',
					array($this, 'wcProductLitmosCourseCodeUpdateMeta'));
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
				remove_action( 'woocommerce_product_options_general_product_data',
					array( accessProtected(wc_litmos(), 'admin') , 'add_simple_product_course_selection'), 10);
				}

			/**
			 * ZH Test Function
			 *
			 */
			public function wcProductLitmosCourseCode() {
				global $post;
							$zh_course_id = get_post_meta( $post->ID, '_wc_litmos_course_id', true );
							for ($i = 0; $i < count($zh_course_id); $i++) {
								if (!$zh_course_id[$i] || $zh_course_id[$i] == "none") {
									array_splice($zh_course_id, $i);
									break;
								}
							}
							?>
								<div class="options_group litmos show_if_simple show_if_external">
							<?php for ($i = 0; $i <= count($zh_course_id); $i++) : ?>
								<p class="form-field _wc_litmos_course_id_field">
								<?php	printf('<label for="_wc_litmos_course_id[%1$b]">Litmos Course Code %2$s</label>
									<select id="_wc_litmos_course_id[%1$b]" name="_wc_litmos_course_id[]" style="min-width: 250px; max-width: 300px;">', $i, $i + 1); ?>
								<option value="none"></option>
								<?php foreach ( $this->get_courses_for_select_input() as $value => $label ) : ?>
									<?php printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $value, $zh_course_id[$i], false ), esc_html__( $label ) ); ?>
								<?php endforeach; ?>
								</select>
								</p>
							<?php endfor; ?>
								</div>
							<?php
			}

			public function wcProductLitmosCourseCodeUpdateMeta($post_id) {
				if ( isset( $_POST[ '_wc_litmos_course_id' ] ) && 'none' != $_POST[ '_wc_litmos_course_id' ] ) {
					update_post_meta( $post_id, '_wc_litmos_course_id', $_POST[ '_wc_litmos_course_id' ] );
				}
			}

			// TODO: REPLACE THIS CODE in class-wc-litmos-handler.php - export()
				// Add course ID & quantity to array
				//if ( $course_id ) {
				//	$courses[ $course_id ] = (int) $order_item['qty'];
				//}
			// WITH THIS CODE
				// if (is_array($course_id)) {
				// 	foreach( $course_id as $splitcoursearray) {
				// 			if ($splitcoursearray != "none")
				// 				$courses [ $splitcoursearray ] = (int) $order_item['qty'];
				// 	}
				// // TODO: should always be an array
				// } elseif ( $course_id ){
				// 		$courses[ $course_id ] = (int) $order_item['qty'];
				// }


			// TODO: extra
			/**
	 * Create array of courses in format required for select input box display
	 *
	 * @since 1.0
	 * @return array associative array in format key = Course ID, value = Course Code - Course Name
	 */
	private function get_courses_for_select_input() {

		// check if course transient exists
		if ( false === ( $select_options = get_transient( 'wc_litmos_courses' ) ) ) {

			// try to fetch fresh course list from API
			try {
				$courses = wc_litmos()->get_api()->get_courses();
			}

			// log any errors
			catch( Exception $e ) {

				wc_litmos()->log( $e->getMessage() );

				// return a blank array so select box is valid
				return array();
			}

			// build course list array for use in select input box
			foreach ( $courses as $course ) {

				$select_options[ $course['Id'] ] = sprintf( '%s - %s', $course['Code'], $course['Name'] );
			}

			// set 15 minute transient
			set_transient( 'wc_litmos_courses', $select_options, 60*15 );
		}

		return $select_options;
	}



			/**
			 * Credit WooCommerce: https://docs.woocommerce.com/document/customise-the-woocommerce-breadcrumb/
			 */
			public static function remove_wc_breadcrumbs() {
			    remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20, 0 );
			}

			public static function cartBackToVendorStorePage($vendor) {
					echo '<div style="padding: 0 0 1.5em 0;">
										 <a href="/continuing-education/" class="zh-button-left" style="color: #c6000c; font-size:18px !important;">Continue Shopping</a>
										 </div>';
			}
			public static function productBackToVendorStorePage($vendor) {
					echo '<div style="padding: 0 0 1.5em 0;">
										 <a href="/continuing-education/" class="zh-button-left" style="color: #c6000c; font-size:18px !important;">Back to Store</a>
										 </div>';
			}

			function addVendorSetting() {

			}

	/**
	* add custom fields to WooCommerce checkout
	* @param array fields
	* @return array
	* Credit GeekTweaks: http://geektweaks.swishersolutions.com
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

	update_post_meta($order_id, 'Subscribe to mailing list',
		stripslashes($_POST['our_mailing_subscribe']));
  }



	}



	/**
	 * Returns the One True Instance of WC MPL.
	 *
	 * @return WC_MPL
	 */
	function wc_mpl() {
		return WC_MPL::instance();
	}

	wc_mpl();

?>
