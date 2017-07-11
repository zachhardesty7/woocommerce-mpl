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

		register_activation_hook(__FILE__,
			array($this, 'zh_activate_litmos_set_inactive_daily' ));
		register_deactivation_hook(__FILE__,
			array($this, 'zh_deactivate_litmos_set_inactive_daily' ));

		add_action( 'zh_litmos_set_inactive_daily',
			array($this, 'zh_litmos_set_inactive' ));
	}

	/**
	 * Take care of anything that needs woocommerce to be loaded.
	 * For instance, if you need access to the $woocommerce global
	 */
	public function woocommerce_loaded() {
	}

	/**
	 * Take care of anything that needs all plugins to be loaded
	 */
	public function plugins_loaded() {
		remove_action( 'woocommerce_product_options_general_product_data',
			array( accessProtected(wc_litmos(), 'admin'), 'add_simple_product_course_selection'), 10);

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

		add_action( 'yith_wcaf_affiliate_panel',
			array($this, 'zh_add_settings_affiliate_link' ));

		add_action( 'woocommerce_thankyou',
			array($this, 'custom_woocommerce_auto_complete_order' ));

		add_filter( 'woocommerce_coupons_enabled',
			array($this, 'hide_coupon_field_on_cart' ));

		add_filter('gettext',
			array($this, 'translate_reply' ));
		add_filter('ngettext',
			array($this, 'translate_reply' ));

		add_action( 'after_setup_theme',
			array($this, 'zh_add_cart_button' ));

		add_filter( 'woocommerce_billing_fields',
			array($this, 'zh_move_checkout_fields_woo_3' ), 10, 1 );

		add_action('wp_head',
				array($this, 'zh_clean_checkout' ));

		add_action('loop_start',
				array($this, 'zh_checkout_header' ));

		add_action('wp_footer',
				array($this, 'zh_kn_courses' ));
	}

	public function zh_kn_courses() {
		// hide ATC button on keynote courses
		?>
		<style media="screen">
			.product_cat-keynote-courses a:nth-child(2) {
				display: none;
			}
		</style>
		<script>
			jQuery(document).ready(function($) {
				let urlDef = "http://keynotecommunity.com/take-course/";
				let urlBase = "https://www.keynoteseries.com/course_details/";
				let keynoteProductLinks = $(".product_cat-keynote-courses");

				function setCoursesUrl(url) {
					for (let i = 0; i < keynoteProductLinks.length; i++) {
						var output = url;
						if ($('#stateSelectDropdown').val()) {
							var urlCourse = encodeURI(keynoteProductLinks[i].firstElementChild.children[1].textContent);
							var output = url + "\/" + urlCourse;
						}
						keynoteProductLinks[i].firstElementChild.href = output;
						keynoteProductLinks[i].firstElementChild.target = "_blank"
					}
				}

				setCoursesUrl(urlDef);
				$("#stateSelectDropdown").change(function() {
					if (!$('#stateSelectDropdown').val()) {
						setCoursesUrl(urlDef);
					} else {
						let urlPartner = $('#stateSelectDropdown').val();
						let urlNew = urlBase + urlPartner;
						setCoursesUrl(urlNew);
					}
				})
			})

		</script>






			<?php
	}

	public function zh_checkout_header()
	{
			if(is_checkout()){
					?>
					<style>
							@media only screen and (min-width: 501px) {
									#checkout-header {
											display: flex;
											align-items: center;
									}
									#checkout-header a {
											position: absolute;
									}
									#checkout-header h1 {
											margin: auto;
									}
							}
							@media only screen and (max-width: 500px) {
									#checkout-header {
											text-align: center;
									}
									#checkout-header h1 {
											padding-top: 15px;
									}
							}
					</style>
					<div id='checkout-header'>
							<a href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" rel="home">
									 <img src="<?php echo get_stylesheet_directory_uri(); ?>/images/logo.png" alt="Logo" width="100px" height="100px" />
							</a>
							<h1>Checkout</h1>
					</div>
					<?php
			}
	}

	public function zh_clean_checkout()
	{
			if(is_checkout()){
					?>

					<style type="text/css">
					#top-header, #main-header, #main-footer, #footer-bottom {
							display: none;
					}
					#page-container {
							padding-top: 0 !important;
							margin-top: 0 !important;
					}

					</style>
					<?php
			}
	}

	public function zh_activate_litmos_set_inactive_daily() {
    if (! wp_next_scheduled ( 'zh_litmos_set_inactive_daily' )) {
			wp_schedule_event(time(), 'daily', 'zh_litmos_set_inactive_daily');
    }
	}
	public static function zh_deactivate_litmos_set_inactive_daily() {
		wp_clear_scheduled_hook('zh_litmos_set_inactive_daily');
	}

	public static function zh_litmos_set_inactive() {
		$userlist = wc_litmos()->get_api()->get_users();
		foreach ($userlist as $user) {
			if ($user['Active'] && $user['AccessLevel'] == 'Learner') {
				$assigned_courses = wc_litmos()->get_api()->get_courses_assigned_to_user( $user["Id"] );
				foreach ($assigned_courses as $course) {
					// Credit: Farkie @ https://stackoverflow.com/a/16750349/5299167
					//         jurka @ https://stackoverflow.com/a/3923228/5299167
					$match = preg_match('/\/Date\((\d+)([-+])(\d+)\)\//', $course['AssignedDate'], $date);
					$timestamp = $date[1]/1000;
					$operator = $date[2];
					$hours = $date[3]*36; // Get the seconds
					$assigned_date = new DateTime();
					$assigned_date->setTimestamp($timestamp);
					$assigned_date->modify($operator . $hours . ' seconds');
					$current_date = new DateTime();
					$interval = $current_date->diff($assigned_date);
					$date_diff_days = $interval->days;
					// set user inactive only if all assigned courses are 30+ days old
					if ( $date_diff_days < 30 ) {
						$user['Active'] = true;
						break;
					} else {
						$user['Active'] = false;
					}
				}
				// update if 'active' state changed to 'inactive'
				if (!$user['Active'])
					wc_litmos()->get_api()->update_user( $user );
			}
		}
	}

	/**
	 * @snippet       Move / ReOrder Fields @ Checkout Page, WooCommerce version 3.0+
	 * @how-to        Watch tutorial @ https://businessbloomer.com/?p=19055
	 * @sourcecode    https://businessbloomer.com/?p=19571
	 * @author        Rodolfo Melogli
	 * @testedwith    WooCommerce 3.0.4
	 */
	function zh_move_checkout_fields_woo_3( $fields ) {
	  $fields['billing_email']['priority'] = 8;
	  return $fields;
	}

	// https://jonathanbossenger.com/adding-the-cart-button-to-your-divi-shop-pages/
	function zh_add_cart_button () {
    add_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_add_to_cart', 10 );
	}

	// Credit: Rahul S
	// https://stackoverflow.com/questions/31017626/how-to-change-woocommerce-text-shipping-in-checkout
	// https://businessbloomer.com/woocommerce-edit-translate-shipping-handling-cart-checkout-page/
	function translate_reply($translated) {
		$translated = str_ireplace('Coupon code', 'Promo Code', $translated);
		$translated = str_ireplace('Coupon', 'Promo Code', $translated);
		return $translated;
	}

	// entirety of bottom cart row (coupon and update quantity) hidden by css
	function hide_coupon_field_on_cart( $enabled ) {
		if ( is_cart() ) {
			$enabled = false;
		}
		return $enabled;
	}

 	function custom_woocommerce_auto_complete_order( $order_id ) {
   if ( ! $order_id ) {
       return;
   }

   $order = wc_get_order( $order_id );
   $order->update_status( 'completed' );
 }

	// Unnecessary
	public function zh_add_settings_affiliate_link($id = '') {
		$shop_page_url = get_permalink( wc_get_page_id( 'shop' ) );
		$product_categories = get_terms( 'product_cat' );

		?>
			<div id="affiliate-link">
				<h3>Affiliates' Link Help</h3>
				<h4>Template</h4>
				Vendor Category and Product Name are optional!
				<p>https://{shop_base}/{vendor_category}/{product_name}/?ref={vendor_token}</p>
				<h4>Base:</h4>
				<p><?=$shop_page_url?></p>
				<h4>Categories:</h4>
				<p><?php foreach( $product_categories as $cat ) { echo $cat->slug; } ?></p>
				<h4>Vendor Tokens:</h4>
				<p>listed below</p>
				<h4>EXAMPLES:</h4>
				<p><?php echo $shop_page_url . '?ref=45'?></p>
				<p><?php echo $shop_page_url . $product_categories[0]->slug . '/?ref=12'?></p>
				<p><?php echo $shop_page_url . $product_categories[0]->slug . '/legal-update' . '/?ref=3'?></p>
			</div>
		<?php
	}

	public function zh_remove_cart_message_button($message) {
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
				<a href="<?php echo esc_url($zh_category_link) ?>" class="zh-button-left" style="font-size:18px !important;">Return to shop</a>
			</div>
		<?php
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
			'priority' => 100,
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
			$username = "travelmark1";
			// Your Constant Contact Access Token
			$accessToken = "9d5e7feb-85cc-44cb-bffc-6b08c68ffee7";
			// The Constant Contact List you want the New Contact to be created in
			$listid = "1792291391";

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
