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

// allows access to protected class functions and values (properties and methods)
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
	// enforces singleton
	protected static $instance;
	/** @var \WC_Litmos_Admin class instance */
	protected $admin;
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		$this->includes();

		add_action( 'plugins_loaded', array( &$this, 'plugins_loaded' ) );

		// enable / disable Litmos auto user deactivation CRON task
		register_activation_hook(__FILE__,
			array($this, 'zh_activate_litmos_set_inactive_daily' ));
		register_deactivation_hook(__FILE__,
			array($this, 'zh_deactivate_litmos_set_inactive_daily' ));
		add_action( 'zh_litmos_set_inactive_daily',
			array($this, 'zh_litmos_set_inactive' ));
	}

	public function plugins_loaded() {
		if( class_exists( 'WC_Litmos' ) ) {
			remove_action( 'woocommerce_product_options_general_product_data', array( accessProtected(wc_litmos(), 'admin'), 'add_simple_product_course_selection'), 10);
		}

		remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20, 0 );

		add_filter( 'wc_add_to_cart_message_html', array($this, 'zh_remove_cart_message_button'));

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

		add_action( 'woocommerce_product_query',
			array($this, 'custom_pre_get_posts_query' ));

		if ( get_option( 'exclude_jetpack_related_from_products' ) ) {
			add_filter( 'jetpack_relatedposts_filter_options',
				array($this, 'exclude_jetpack_related_from_products' ));
		}
	}


	/**
	 * Loads admin class
	 *
	 */
	public function includes() {
		if ( is_admin() ) {
			require_once(plugin_dir_path( __FILE__ ) . '/woocommerce-mpl-admin.php');
			$this->admin = new WC_MPL_Admin;
		}
	}


	/**
	 *  @snippet   Hide related posts on WC products
	 *  @link      https://jetpack.com/support/related-posts/customize-related-posts/#woocommerce
	 */
	function exclude_jetpack_related_from_products( $options ) {
	    if ( is_product() && get_option( 'exclude_jetpack_related_from_products' ) === 'yes' ) {
	        $options['enabled'] = false;
	    }
	    return $options;
	}


	/**
	 *  @snippet   Dynamically change links on custom Keynote courses based on dropdown selection
	 *  @link      https://docs.woocommerce.com/document/exclude-a-category-from-the-shop-page/
	 */
	function custom_pre_get_posts_query( $query ) {
    $tax_query = (array) $query->get( 'tax_query' );
    $tax_query[] = array(
           'taxonomy' => 'product_cat',
           'field' => 'slug',
           'terms' => array( 'keynote' ), // Don't display products in the clothing category on the shop page.
           'operator' => 'NOT IN'
    );
    $query->set( 'tax_query', $tax_query );
	}

	/**
	 *  @snippet    Dynamically change links on custom Keynote courses based on dropdown selection
	 */
	public function zh_kn_courses() {
		?>
			<script>
				jQuery(document).ready(function($) {
					let urlDef = "http://keynotecommunity.com/take-course/";
					let urlBase = "https://www.keynoteseries.com/course_details/";
					let keynoteCatProducts = $(".product_cat-keynote");

					// remove ATC button and price
					$(".product_cat-keynote .button").remove();
					$(".product_cat-keynote a .price").remove();

					// dynamic link generation for keynote products
					function setCoursesUrl(url) {
						for (let i = 0; i < keynoteCatProducts.length; i++) {
							let output = url + "?code=markporter";
							// append encoded product title (course) to @url
							if ($('#stateSelectDropdown').val()) {
								let urlCourse = encodeURI(keynoteCatProducts[i].firstElementChild.children[1].textContent);
								output = url + "\/" + urlCourse + "?code=markporter";
							}
							// set product link to new url
							keynoteCatProducts[i].firstElementChild.href = output;
							keynoteCatProducts[i].firstElementChild.target = "_blank"
						}
					}

					// set link to default on first run
					setCoursesUrl(urlDef);

					// on change handler for dropdown selector
					// dynamically generates link for each product
					$("#stateSelectDropdown").change(function() {

						// reset disabled courses style
						$("#keynoteCourseDisable").remove();
						// remove notice on Other States selection
						$('#cenoticemain').remove();
						// remove individual CE notice on disabled courses
						$('.cenotice').remove();
						// enable divi overlay
						$("a .et_shop_image .et_overlay").show();

						// if select "Select state:" set default URL
						if (!$('#stateSelectDropdown').val()) {
							setCoursesUrl(urlDef);
						// else append value of option to URL and set
						} else {
							let urlPartner = $('#stateSelectDropdown').val();
							let urlNew = urlBase + urlPartner;
							setCoursesUrl(urlNew);

							// if "Other State" selected, display main CE notice
							if ($('#stateSelectDropdown').val() == "keynoteseriesprofessionaldevelopment") {
								$('<p style="color:#c6000c">These courses do not offer CE credit and are for professional development only.</p>').attr('id', 'cenoticemain').appendTo($('#stateSelect').parent());
							}

							// grab product IDs of disabled (not offered) courses from data-*
							let courseExclPostId = $('#stateSelectDropdown option:selected').data("courseExclPostId");
							// if only 1 (returned integer)
							if (Number.isInteger(courseExclPostId)) {
								// append <style>, greys out all children of href
								let courseClass = ".post-" + courseExclPostId;
								let nodeString = "<style id='keynoteCourseDisable'> " + courseClass + " a > * {opacity: .3} </style>"
								$(document.head).append(nodeString);
								// disable href
								$(courseClass + " a").filter(':first').removeAttr('href');
								// disable Divi overlay hover
								$(courseClass + " a .et_shop_image .et_overlay").hide();
								// notice below each product
								$(courseClass).append($('<p style="color:#c6000c">Course not for CE credit\nPlease select "Other State"</p>').addClass("cenotice"));

							// if multiple (returned comma delineated string)
							} else if (typeof courseExclPostId === "string") {
								// string -> array
								let courseExclPostIds = courseExclPostId.split(",");
								// begin <style>
								nodeString = "<style id='keynoteCourseDisable'>"
								// for each disabled product ID
								courseExclPostIds.forEach(function(courseExclPostId, i) {
									// grey out all children of href
									let courseClass = " .post-" + courseExclPostId;
									let nodeStringHolder = courseClass + " a > *";
									if (i != courseExclPostIds.length - 1) nodeStringHolder += ",";
									nodeString += nodeStringHolder;
									// disable href
									$(courseClass + " a").filter(':first').removeAttr('href');
									// disable Divi overlay hover
									$(courseClass + " a .et_shop_image .et_overlay").hide();
									// notice below each product
									$(courseClass).append($('<p style="color:#c6000c">Course not for CE credit\nPlease select "Other State"</p>').addClass("cenotice"));
								})
								// append closing tag, append to <head>
								nodeString += "{opacity: .3} </style>"
								$(document.head).append(nodeString);
							}
						}
					})
				})
			</script>
		<?php
	}

	/**
	 *  @snippet    Add "Checkout" and site logo on WC checkout page
	 *  @TODO       settings option to choose logo location
	 */
	public function zh_checkout_header() {
		if(is_checkout() && get_option( 'clean_wc_checkout_page' ) === 'yes' ) {
			?>
			<div id='checkout-header'>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" rel="home">
					<img src="https://markporterlive.com/wp-content/uploads/MPL-Logo-Transparent-optimized.png" alt="Site Logo" width="100px" height="100px" />
				</a>
				<h1>Checkout</h1>
			</div>
			<?php
		}
	}

	/**
	 *  @snippet    Hide noise on WC checkout page and style new header
	 */
	public function zh_clean_checkout() {
		if(is_checkout() && get_option( 'clean_wc_checkout_page' ) === 'yes'){
			?>
			<style type="text/css">
				#top-header, #main-header, #main-footer, #footer-bottom {
						display: none;
				}
				#page-container {
						padding-top: 0 !important;
						margin-top: 0 !important;
				}

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
			<?php
		}
	}

	/**
	 *  @snippet    Schedule zh_litmos_set_inactive CRON task for daily if unscheduled
	 */
	public function zh_activate_litmos_set_inactive_daily() {
    if (!wp_next_scheduled ( 'zh_litmos_set_inactive_daily' ) && get_option( 'deactivate_litmos_users' ) === 'yes' ) {
			wp_schedule_event(time(), 'daily', 'zh_litmos_set_inactive_daily');
    }
	}
	/**
	 *  @snippet   Unschedule zh_litmos_set_inactive
	 */
	public static function zh_deactivate_litmos_set_inactive_daily() {
		wp_clear_scheduled_hook('zh_litmos_set_inactive_daily');
	}
	/**
	 *  @snippet   Check date of assigned Litmos courses. If all courses assigned > 30 days ago,
	 *             set user inactive - Litmos charges on an active user basis
	 */
	public static function zh_litmos_set_inactive() {
		if ( get_option( 'deactivate_litmos_users' ) === 'yes' ) {
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
						// keeps user active by breaking if any course was assigned < 30 days ago
						if ( $date_diff_days < get_option( 'woocommerce_order_number_start', 30 ) ) {
							$user['Active'] = true;
							break;
						} else {
							$user['Active'] = false;
						}
					}
					// update thru api if 'active' state changed to 'inactive'
					if (!$user['Active'])
						wc_litmos()->get_api()->update_user( $user );
				}
			}
		}
	}

	/**
	 * @snippet       Move email billing field to top of form
	 * @how-to        Watch tutorial @ https://businessbloomer.com/?p=19055
	 * @sourcecode    https://businessbloomer.com/?p=19571
	 * @author        Rodolfo Melogli
	 * @testedwith    WooCommerce 3.0.4
	 */
	function zh_move_checkout_fields_woo_3( $fields ) {
		if ( get_option( 'prioritize_email_field' ) === 'yes' ) {
	  	$fields['billing_email']['priority'] = 8;
		}
  	return $fields;
	}

	/**
	 *  @snippet    Enable ATC button on store catalog pages
	 *  @source     https://jonathanbossenger.com/adding-the-cart-button-to-your-divi-shop-pages/
	 */
	function zh_add_cart_button () {
		if ( get_option( 'enable_store_atc' ) === 'yes' ) {
	    add_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_add_to_cart', 10 );
		}
	}

	/**
	 *  @snippet   Change all instances of "Coupon" to "Promo Code"
	 *  @credit    Rahul S
	 *  @source    https://stackoverflow.com/questions/31017626/how-to-change-woocommerce-text-shipping-in-checkout
	 *  @source    https://businessbloomer.com/woocommerce-edit-translate-shipping-handling-cart-checkout-page/
	 */
	function translate_reply($translated) {
		if ( get_option( 'replace_coupon_code' ) === 'yes' ) {
			$translated = str_ireplace('Coupon code', get_option( 'replace_coupon_code_string', 'Promo Code'), $translated);
			$translated = str_ireplace('Coupon', get_option( 'replace_coupon_code_string', 'Promo Code') , $translated);
		}
		return $translated;
	}

	/**
	 *  @snippet   Hide entirety of WC bottom cart row (coupon and update quantity) thru css
	 */
	function hide_coupon_field_on_cart( $enabled ) {
		if ( is_cart() && get_option( 'hide_coupon_field_on_cart') === 'yes' ) {
			$enabled = false;
		}
		return $enabled;
	}

	/**
	 *  @snippet   Auto WC order from processing -> complete
	 */
 	function custom_woocommerce_auto_complete_order( $order_id ) {
		if ( get_option( 'custom_woocommerce_auto_complete_order' ) === 'yes' ) {
		  if ( !$order_id ) {
		    return;
		  }
		  $order = wc_get_order( $order_id );
		  $order->update_status( 'completed' );
		}
	}

	/**
	 *  @snippet   Remove "Continue Shopping" button from successfully added to cart banner message
	 */
	public function zh_remove_cart_message_button($message) {
		if ( get_option( 'zh_remove_cart_message_button' )  === 'yes') {
			$regex = '/<[^>]*>[^<]*<[^>]*>/';
			return preg_replace($regex, '', $message);
		} else { return $message; }
	}

	/**
	 *  @snippet   Create dropdown selector that includes all linked litmos courses
	 */
	public function wcProductLitmosCourseCode() {
		global $post;
		// TODO: implement dropdown in dynamic JS (PHP is dumb)
		// FIXME: broken on initial product creation
		// Notice: Uninitialized string offset: 0 in /home/markport/public_html/dev/wp-content/plugins/woocommerce-mpl/woocommerce-mpl.php on line 206
		// Warning: array_splice() expects parameter 1 to be array, string given in /home/markport/public_html/dev/wp-content/plugins/woocommerce-mpl/woocommerce-mpl.php on line 207
		$zh_course_id = get_post_meta( $post->ID, '_wc_litmos_course_id', true );
		// remove empty options other than one
		for ($i = 0; $i < count($zh_course_id); $i++) {
			if (!$zh_course_id[$i] || $zh_course_id[$i] == "none") {
				array_splice($zh_course_id, $i);
				break;
			}
		}
		// html for options
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

	/**
	 *  @snippet   Update post meta with array of litmos courses linked to WC product
	 */
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
	 *  @snippet   Display "Continue Shopping" on cart
	 */
	public static function cartBackToVendorStorePage($vendor) {
		if ( get_option( 'display_continue_shopping') === 'yes') {
			?>
				<div style="padding: 0 0 1.5em 0;">
					<a href="<?php echo get_permalink( wc_get_page_id('shop')) ?>" class="zh-button-left" style="font-size:18px !important;">Continue Shopping</a>
				</div>
			<?php
		}
	}

	/**
	 *  @snippet   Display "Return to shop" on product page
	 */
	public function productBackToVendorStorePage($vendor) {
		if ( get_option( 'display_return_to_store') === 'yes') {
			?>
				<div style="padding: 0 0 1.5em 0;">
					<a href="<?php echo get_permalink( wc_get_page_id('shop')) ?>" class="zh-button-left" style="font-size:18px !important;">Return to shop</a>
				</div>
			<?php
		}
	}

	/**
	* @snippet    Add newsletter subscription checkbox on checkout
	* @param      array fields
	* @return     array
	* @credit     GeekTweaks: http://geektweaks.swishersolutions.com
	*/
	public static function filterWooCheckoutFields($fields) {
		if ( get_option( 'constant_contact_integration') === 'yes') {
			$fields['billing']['our_mailing_subscribe'] = array(
				'type' => 'checkbox',
				'label' => get_option( 'constant_contact_integration_label'),
				'placeholder' => get_option( 'constant_contact_integration_paceholder'),
				'required' => false,
				'class' => array(),
				'label_class' => array(),
				'default' => true,
				'custom_attributes' => array('style'=>'display: inline-block'),
				'priority' => 100,
			);
		}
		return $fields;
	}

	/**
   * @snippet   Collect submitted checkout data, post to CC, save to meta
   * @param     int $order_id
   */
  public static function actionWooCheckoutUpdateOrderMeta($order_id) {
		if ( get_option( 'constant_contact_integration') === 'yes') {
		  if (isset($_POST['our_mailing_subscribe'])) {

				$emailAddress = $_POST['billing_email'];
				$firstname = $_POST["billing_first_name"];
				$lastname = $_POST["billing_last_name"];

				// Your Constant Contact Username
				$username = get_option('constant_contact_integration_username');
				// Your Constant Contact Access Token
				$accessToken = get_option('constant_contact_integration_access_token');
				// The Constant Contact List you want the New Contact to be created in
				$listid = get_option('constant_contact_integration_list_ids');

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

				// http post
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
