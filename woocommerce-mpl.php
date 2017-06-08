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

function accessProtected($obj, $propmeth, $type = 'property') {
	if ($type == 'method') {
		$r = new ReflectionMethod($obj, $propmeth);
		$r->setAccessible(true);
		return $r->invoke(new $obj());
	} else {
		$r = new ReflectionClass($obj);
		$property = $r->getProperty($propmeth);
		$property->setAccessible(true);
		return $property->getValue($obj);
	}
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
			array( accessProtected(wc_litmos(), 'admin'), 'add_simple_product_course_selection'), 10);

		/**
		 * Credit WooCommerce: https://docs.woocommerce.com/document/customise-the-woocommerce-breadcrumb/
		 */
		remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20, 0 );

		add_filter( 'wc_add_to_cart_message_html',
		 	array($this, 'zh_remove_cart_message_button'));

		add_filter('woocommerce_checkout_fields',
			array($this, 'filterWooCheckoutFields'));

		add_filter('woocommerce_create_account_default_checked',
			function ($checked){ return true; });

		add_action('woocommerce_checkout_update_order_meta',
			array($this, 'actionWooCheckoutUpdateOrderMeta'));

		add_action('woocommerce_product_options_general_product_data',
			array($this, 'wcProductLitmosCourseCode'));
		add_action('woocommerce_process_product_meta',
			array($this, 'wcProductLitmosCourseCodeUpdateMeta'));

		add_action('woocommerce_before_cart_contents',
			array($this, 'cartBackToVendorStorePage'));

		add_action('woocommerce_before_single_product',
			array($this, 'productBackToVendorStorePage'));

		add_filter( 'woocommerce_return_to_shop_redirect',
			array($this, 'zh_return_link'));

		add_action( 'woocommerce_before_single_product',
			array($this, 'zh_single_prod_load' ));

	}

	public function zh_remove_cart_message_button($message) {
		global $woocommerce;
		$regex = '/<[^>]*>[^<]*<[^>]*>/';
		return preg_replace($regex, '', $message);
	}

	public function wcProductLitmosCourseCode() {
		global $post;
		// TODO: broken on product creation
		// Notice: Uninitialized string offset: 0 in /home/markport/public_html/dev/wp-content/plugins/woocommerce-mpl/woocommerce-mpl.php on line 206
		// Warning: array_splice() expects parameter 1 to be array, string given in /home/markport/public_html/dev/wp-content/plugins/woocommerce-mpl/woocommerce-mpl.php on line 207
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
			<?php
				foreach ( accessProtected(accessProtected(wc_litmos(), 'admin'), 'get_courses_for_select_input', 'method') as $value => $label ) {
					printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $value, $zh_course_id[$i], false ), esc_html__( $label ) );
				}
			?>
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

	// TODO: REPLACE THIS CODE in class-wc-litmos-handler.php->export()
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
				// // REVIEW: should always be an array
				// } elseif ( $course_id ){
				// 		$courses[ $course_id ] = (int) $order_item['qty'];
				// }

	/**
	 * Credit Nicola Mustone: https://nicola.blog/2015/07/20/change-the-return-to-shop-button-url-in-the-cart-page/
	 * Credit HappyKite: http://www.happykite.co.uk/
	 */
	public static function zh_return_link() {
		$cat_referer = get_transient( 'recent_cat');
		if ( !empty( $cat_referer ) ) {
				$returnlink = $cat_referer;
		} else {
				$shop_id = get_option( 'woocommerce_shop_page_id' );
				$returnlink = get_permalink( $shop_id );
		}
		return $returnlink;
	}

	function zh_single_prod_load() {
		if ( isset( $_SERVER["HTTP_REFERER"] ) ) {
			$referringURL = $_SERVER[ "HTTP_REFERER" ];
		} else {
			$referringURL = '';
		}

		if ( strpos( $referringURL, 'basket' ) == false
		  && strpos( $referringURL, '/product/' ) == false
		  && strpos( $referringURL, '/cart/' ) == false
		) {
			$this::zh_save_recent_category( $referringURL );
		} else {
			return;
		}
	}

	public static function zh_save_recent_category( $referrer ) {
		delete_transient( 'recent_cat' );
		set_transient( 'recent_cat', $referrer, 60*60*12 );
		return;
	}

	public static function cartBackToVendorStorePage($vendor) {
		?>
			<div style="padding: 0 0 1.5em 0;">
				<a href="<?php echo esc_url(self::zh_return_link()) ?>" class="zh-button-left" style="font-size:18px !important;">Continue Shopping</a>
			</div>
		<?php
	}

	public function productBackToVendorStorePage($vendor) {
		global $product;
		$zh_category_ids = $product->get_category_ids();
		$zh_category_link = get_term_link($zh_category_ids[0], 'product_cat');
		?>
			<div style="padding: 0 0 1.5em 0;">
				<a href="<?php echo esc_url($zh_category_link) ?>" class="zh-button-left" style="font-size:18px !important;">Back to Store</a>
			</div>
		<?php
	}

	// Deprecated functions for time being, in favor of YITH WooCommerce Affiliates
	function addProductVendor() {
		global $post;
		$zh_vendor = get_post_meta( $post->ID, '_wc_vendor', true );
		$zh_pagelist = get_pages(array( 'child_of' => 12061));
		for ($i = 0; $i < count($zh_vendor); $i++) {
			if (!$zh_vendor[$i] || $zh_vendor[$i] == "none") {
				array_splice($zh_vendor, $i);
				break;
			}
		}
		?>
			<div class="options_group vendor show_if_simple show_if_external">
		<?php for ($i = 0; $i <= count($zh_vendor); $i++) : ?>
			<p class="form-field _wc_vendor_field">
			<?php	printf('<label for="_wc_vendor[%1$b]">Vendor Store Page %2$s</label>
				<select id="_wc_vendor[%1$b]" name="_wc_vendor[]" style="min-width: 250px; max-width: 300px;">', $i, $i + 1); ?>
			<option value="none"></option>
			<?php // Parent = WooCom shop page
				foreach ( $zh_pagelist as $page ) {
					printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $page->ID ), selected( $page->ID, $zh_vendor[$i], false ), esc_html__( $page->post_title ) );
				}
			?>
			</select>
			</p>
		<?php endfor; ?>
			</div>
		<?php
	}

	public function updateProductVendorMeta($post_id) {
		if ( isset( $_POST[ '_wc_vendor' ] ) && 'none' != $_POST[ '_wc_vendor' ] ) {
			update_post_meta( $post_id, '_wc_vendor', $_POST[ '_wc_vendor' ] );
		}
	}

	/**
	* add custom fields to WooCommerce checkout
	* @param array fields
	* @return array
	* Credit GeekTweaks: http://geektweaks.swishersolutions.com
	*/
	public static function filterWooCheckoutFields($fields) {
		global $woocommerce;
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
