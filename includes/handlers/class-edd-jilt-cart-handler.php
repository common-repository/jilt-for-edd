<?php
/**
 * Jilt for EDD
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * https://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@jilt.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Jilt for EDD to newer
 * versions in the future. If you wish to customize Jilt for EDD for your
 * needs please refer to http://help.jilt.com/for-developers
 *
 * @package   EDD-Jilt/Handlers
 * @author    Jilt
 * @category  Frontend
 * @copyright Copyright (c) 2015-2020, SkyVerge, Inc.
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

/**
 * Cart class
 *
 * Handles cart interactions
 *
 * @since 1.0.0
 */
class EDD_Jilt_Cart_Handler {


	/** The cipher method name to use to encrypt the cart data */
	const CIPHER_METHOD = 'AES-128-CBC';

	/** The HMAC hash algorithm to use to sign the encrypted cart data */
	const HMAC_ALGO = 'sha256';


	/**
	 * Setup class
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		if ( defined( 'DOING_CART_RECOVERY' ) && DOING_CART_RECOVERY ) {
			return;
		}

		add_action( 'wp_loaded', array( $this, 'handle_persistent_cart' ) );

		// most cart updates will take place in the browser, but a few we should keep here
		add_action( 'edd_jilt_ajax_update_customer', array( $this, 'cart_updated' ) );
		add_action( 'wp_login',                      array( $this, 'cart_updated' ) );

		// delete the order in Jilt if the last item is removed from the cart
		add_action( 'edd_post_remove_from_cart', array( $this, 'maybe_delete_cart' ) );

		// listen for the edd_empty_cart call
		add_action( 'edd_empty_cart', array( $this, 'cart_emptied' ) );
	}


	/**
	 * Enqueues the frontend JS
	 *
	 * TODO remove this method by version 1.5 {FN 2018-05-23}
	 *
	 * @since 1.2.0
	 * @deprecated since 1.3.3
	 */
	public function enqueue_scripts() {

		_deprecated_function( 'EDD_Jilt_Cart_Handler::enqueue_scripts()', '1.3.3', 'EDD_Jilt_Frontend::enqueue_script_styles()' );

		edd_jilt()->get_frontend()->enqueue_scripts_styles();
	}


	/**
	 * Handle loading/setting Jilt data for logged in customers
	 *
	 * @since 1.0.0
	 */
	public function handle_persistent_cart() {

		// bail for guest users or when the cart is empty
		if ( ! is_user_logged_in() || ! edd_jilt()->get_integration()->is_linked() ) {
			return;
		}

		$cart_contents = edd_get_cart_contents();
		if ( empty( $cart_contents ) ) {
			return;
		}

		$user_id    = get_current_user_id();
		$cart_token = get_user_meta( $user_id, '_edd_jilt_cart_token', true );

		if ( $cart_token && ! EDD_Jilt_Session::get_cart_token() ) {

			// for a logged in user with a persistent cart, set the cart token  to the session
			EDD_Jilt_Session::set_jilt_order_data( $cart_token );

		} elseif ( ! $cart_token && EDD_Jilt_Session::get_cart_token() ) {

			// when a guest user with an existing cart logs in, save the cart token/Jilt order ID to user meta
			update_user_meta( $user_id, '_edd_jilt_cart_token', EDD_Jilt_Session::get_cart_token() );
		}
	}


	/**
	 * Renders the popover HTML for collecting an email address when products are added to the cart.
	 *
	 * TODO remove this deprecated method by version 1.7 or higher {FN 2018-09-27}
	 *
	 * @internal
	 *
	 * @since 1.3.0
	 * @deprecated since 1.4.3
	 */
	public function render_add_to_cart_popover() {

		_deprecated_function( 'EDD_Jilt_Cart_Handler::render_add_to_cart_popover()', '1.4.3' );
	}


	/** Event handlers ******************************************************/


	/**
	 * Create or update a Jilt order when cart is updated
	 *
	 * @since 1.0.0
	 */
	public function cart_updated() {

		if ( did_action( 'edd_insert_payment' ) || ! edd_jilt()->get_integration()->is_jilt_connected() ) {
			return;
		}

		$cart_contents = edd_get_cart_contents();

		$cart_token = EDD_Jilt_Session::get_cart_token();

		if ( $cart_token ) {

			try {

				// update the existing Jilt order
				$this->get_api()->update_order( $cart_token, $this->get_cart_data() );

			} catch ( EDD_Jilt_API_Exception $exception ) {

				// clear session so a new Jilt order can be created
				if ( 404 == $exception->getCode() ) {
					EDD_Jilt_Session::unset_jilt_order_data();
					// try to create the order below
					$cart_token = null;
				}

				edd_jilt()->get_logger()->error( "Error communicating with Jilt: {$exception->getMessage()}" );
			}

		}

		// if we're on the success page (meaning a payment just happened) then
		// don't create a new order. This is so that when returning from PayPal
		// where the Jilt session is cleared but the EDD session is not, we
		// avoid creating and then immediately destroying an order in Jilt
		if ( ! $cart_token && ! ( isset( $_GET['payment-confirmation'] ) && edd_is_success_page() ) && ! empty( $cart_contents ) ) {

			try {

				// create a new Jilt order
				$this->get_api()->create_order( $this->get_cart_data() );

			} catch ( EDD_Jilt_API_Exception $exception ) {

				edd_jilt()->get_logger()->error( "Error communicating with Jilt: {$exception->getMessage()}" );
			}
		}
	}


	/**
	 * Deletes the cart from Jilt if the last item was removed.
	 *
	 * @internal
	 *
	 * @since 1.4.0
	 */
	public function maybe_delete_cart() {

		// bail if we just processed a payment or the integration is disabled
		if ( did_action( 'edd_insert_payment' ) || ! edd_jilt()->get_integration()->is_jilt_connected() ) {
			return;
		}

		$cart_contents = edd_get_cart_contents();

		// if the cart is empty and we just explicitly removed an item, delete it from Jilt
		if ( empty( $cart_contents ) && doing_action( 'edd_post_remove_from_cart' ) ) {

			$this->cart_emptied();
		}
	}


	/**
	 * When a user intentionally empties their cart, delete the associated Jilt
	 * order
	 *
	 * @since 1.0.0
	 */
	public function cart_emptied() {

		// bail if:
		// integration is disabled
		// on the payment success page or any payment/purchased related actions were fired
		if ( did_action( 'edd_insert_payment' ) || did_action( 'edd_complete_purchase' ) || did_action( 'edd_confirm_paypal_express' ) || edd_is_success_page() || ! edd_jilt()->get_integration()->is_jilt_connected() ) {
			return;
		}

		$cart_contents = edd_get_cart_contents();

		// bail if the cart is not actually empty
		if ( ! empty( $cart_contents ) ) {
			return;
		}

		$cart_token = EDD_Jilt_Session::get_cart_token();

		if ( ! $cart_token ) {
			return;
		}

		EDD_Jilt_Session::unset_jilt_order_data();

		try {

			$this->get_api()->delete_order( $cart_token );

		} catch ( EDD_Jilt_API_Exception $exception ) {

			edd_jilt()->get_logger()->error( "Error communicating with Jilt: {$exception->getMessage()}" );
		}
	}


	/**
	 * Returns whether cart data sent to the browser should be encrypted or not.
	 *
	 * @since 1.4.0
	 *
	 * @return bool whether cart data should be encrypted
	 */
	public function should_encrypt_cart_data() {

		$should_encrypt = extension_loaded( 'openssl' );

		if ( ! edd_jilt()->get_integration()->get_client_secret() ) {
			$should_encrypt = false;
		}

		if ( ! in_array( self::CIPHER_METHOD, openssl_get_cipher_methods(), true ) || ! in_array( self::HMAC_ALGO, hash_algos(), true ) ) {
			$should_encrypt = false;
		}

		/**
		 * Filters whether or not cart data sent to the browser should be encrypted.
		 *
		 * @since 1.4.0
		 *
		 * @param bool $should_encrypt
		 */
		return apply_filters( 'edd_jilt_should_encrypt_cart_data', $should_encrypt );
	}


	/**
	 * Encrypts the given cart data.
	 *
	 * @since 1.4.0
	 *
	 * @param $cart_data
	 * @return string encrypted cart data
	 * @throws \EDD_Jilt_Plugin_Exception if the openssl extension is not loaded
	 */
	public function encrypt_cart_data( $cart_data ) {

		if ( ! extension_loaded( 'openssl' ) ) {
			throw new EDD_Jilt_Plugin_Exception( 'Cannot encrypt cart data - the OpenSSL extension is not loaded.' );
		}

		$key = substr( edd_jilt()->get_integration()->get_client_secret(), 0, 16 );

		$ivlen          = openssl_cipher_iv_length( self::CIPHER_METHOD );
		$iv             = openssl_random_pseudo_bytes( $ivlen );
		$ciphertext_raw = openssl_encrypt( $cart_data, self::CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv );
		$hmac           = hash_hmac( self::HMAC_ALGO, $ciphertext_raw, $key, true );

		return base64_encode( $iv . $hmac . $ciphertext_raw );
	}


	/**
	 * Get the cart data for updating/creating a Jilt order via the API
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_cart_data() {

		$cart_token   = $this->get_cart_token();
		$checkout_url = EDD_Jilt_Checkout_Handler::get_checkout_recovery_url( $cart_token );

		$params = array(
			'total_price'       => $this->amount_to_int( edd_get_cart_total() ),
			'subtotal_price'    => $this->amount_to_int( edd_get_cart_subtotal() ),
			'total_tax'         => $this->amount_to_int( edd_get_cart_tax() ),
			'total_discounts'   => $this->amount_to_int( edd_get_cart_discounted_amount() ),
			'total_shipping'    => 0,
			'requires_shipping' => false,
			'currency'          => edd_jilt()->get_integration()->get_currency(),
			'checkout_url'      => $checkout_url,
			'line_items'        => $this->get_cart_product_line_items(),
			'fee_items'         => $this->get_cart_fee_line_items(),
			'client_details'    => $this->get_client_details(),
			'client_session'    => EDD_Jilt_Session::get_client_session(),
			'properties'        => $this->get_cart_properties(),
			'cart_token'        => $cart_token,
		);

		$params = array_merge( $params, $this->get_customer_data() );

		// if customer has opted out from cart recovery emails, we should not send any of the personal data anymore
		if ( isset( $params['customer']['declines_cart_reminders'] ) && true === $params['customer']['declines_cart_reminders'] ) {

			foreach ( array_keys( $params ) as $key ) {
				if ( ! in_array( $key, array( 'customer', 'cart_token' ), true ) ) {
					unset( $params[ $key ] );
				} elseif ( 'customer' === $key && is_array( $params['customer'] ) ) {
					foreach ( array_keys( $params['customer'] ) as $sub_key ) {
						if ( 'declines_cart_reminders' !== $sub_key ) {
							unset( $params['customer'][ $sub_key ] );
						}
					}
				}
			}

		} elseif ( ! empty( $params['client_details']['browser_ip'] ) && empty( $params['customer']['email'] ) ) {
			$ip_str  = $params['client_details']['browser_ip'];
			$ip_type = is_string( $ip_str ) && '' !== $ip_str ? strlen( @inet_pton( $ip_str ) ) : null;
			$ip_mask = array( '4' => '255.255.255.0', '16' => 'ffff:ffff:ffff:ffff:0000:0000:0000:0000' );
			$anon_ip = null;
			if ( $ip_type && array_key_exists( $ip_type, $ip_mask ) ) {
				$anon_ip = @inet_ntop( @inet_pton( $ip_str ) & @inet_pton( $ip_mask[ (string) $ip_type ] ) );
			}
			if ( is_string( $anon_ip ) && '' !== $anon_ip ) {
				$params['client_details']['browser_ip'] = $anon_ip;
			} else {
				unset( $params['client_details']['browser_ip'] );
			}
		}

		/**
		 * Filters the cart data used for creating or updating a Jilt order
		 * via the API.
		 *
		 * @since 1.0.0
		 *
		 * @param array $params
		 * @param int $order_id optional
		 */
		return apply_filters( 'edd_jilt_order_cart_params', $params, $this );
	}


	/**
	 * Returns a hash for the given cart data, or gets the cart data if none is given.
	 *
	 * @since 1.4.0
	 *
	 * @param array|null $cart_data cart data or null if retrieving cart
	 * @return string cart hash
	 */
	public function get_cart_hash( $cart_data = null ) {

		$cart_data = $cart_data ? $cart_data : $this->get_cart_data();

		return md5( wp_json_encode( $cart_data ) );
	}


	/**
	 * Returns the cart token.
	 *
	 * Generates the cart token if necessary and stores it in the session.
	 *
	 * @since 1.3.0
	 *
	 * @return string
	 */
	public function get_cart_token() {

		$cart_token = EDD_Jilt_Session::get_cart_token();

		if ( ! $cart_token ) {

			$cart_token = $this->generate_cart_token();

			EDD_Jilt_Session::set_jilt_order_data( $cart_token );
		}

		return $cart_token;
	}


	/**
	 * Generates a UUIDv4 cart token.
	 *
	 * @since 1.3.0
	 *
	 * @see https://stackoverflow.com/a/15875555
	 *
	 * @return string
	 */
	private function generate_cart_token() {

		try {
			$data = random_bytes( 16 );

			$data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 ); // set version to 0100
			$data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 ); // set bits 6-7 to 10

			return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );

		} catch ( Exception $e ) {

			// fall back to mt_rand if random_bytes is unavailable
			return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',

				// 32 bits for "time_low"
				mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

				// 16 bits for "time_mid"
				mt_rand( 0, 0xffff ),

				// 16 bits for "time_hi_and_version",
				// four most significant bits holds version number 4
				mt_rand( 0, 0x0fff ) | 0x4000,

				// 16 bits, 8 bits for "clk_seq_hi_res",
				// 8 bits for "clk_seq_low",
				// two most significant bits holds zero and one for variant DCE1.1
				mt_rand( 0, 0x3fff ) | 0x8000,

				// 48 bits for "node"
				mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
			);
		}

	}


	/**
	 * Gets an array of cart properties
	 *
	 * @since 1.1.2
	 *
	 * @return array cart properties
	 */
	protected function get_cart_properties() {

		$properties = array();

		/**
		 * Get the Jilt properties for the cart. These will be sent over the
		 * Jilt REST API
		 *
		 * @since 1.2.0
		 *
		 * @param array $properties associative array of cart properties
		 */
		$properties = apply_filters( 'edd_jilt_get_cart_properties', $properties );

		return $properties;
	}


	/**
	 * Get the customer data (email/ID, billing / shipping address) used when
	 * creating/updating an order in Jilt
	 *
	 * @since 1.2.0
	 *
	 * @return array
	 */
	public function get_customer_data() {

		$user   = null;
		$params = array(
			'billing_address' => array(),
		);

		if ( is_user_logged_in() ) {
			// populate customer data based on current user

			$user = get_user_by( 'id', get_current_user_id() );

			// look up the EDD customer by email (user id isn't always set e.g:
			// place an order with the same email as a WP user when not signed in
			$customer = EDD()->customers->get_customer_by( 'email', $user->user_email );

			// TODO: WP User data: consider sending this in a customer properties field {JS 2017-04-19}
			//$params['customer'] = array(
			//	'customer_id' => $user->ID,
			//	'admin_url'   => esc_url_raw( add_query_arg( 'user_id', $user->ID, self_admin_url( 'user-edit.php' ) ) ),
			//);

			$params['customer'] = array(
				'email'      => $user->user_email,
				'first_name' => $user->first_name,
				'last_name'  => $user->last_name,
			);

			if ( $customer ) {
				$params['customer']['customer_id'] = $customer->id;
				$params['customer']['admin_url']   = admin_url( 'edit.php?post_type=download&page=edd-customers&view=overview&id=' . $customer->id );
			}

			$params['billing_address'] = array(
				'email'      => $user->user_email,
				'first_name' => $user->first_name,
				'last_name'  => $user->last_name,
			);

		} elseif ( $customer = EDD()->session->get( 'customer' ) ) {
			// this is updated by EDD_Jilt_Recovery_Handler::recreate_cart_content() after following a recovery url

			$params['customer'] = array(
				'email'      => isset( $customer['email'] ) ? $customer['email'] : null,
				'first_name' => isset( $customer['first_name'] ) ? $customer['first_name'] : null,
				'last_name'  => isset( $customer['last_name'] ) ? $customer['last_name'] : null,
			);

			// set these if available
			if ( isset( $customer['customer_id'] ) ) {
				$params['customer']['customer_id'] = $customer['customer_id'];
			}
			if ( isset( $customer['admin_url'] ) ) {
				$params['customer']['admin_url'] = $customer['admin_url'];
			}

			$params['billing_address'] = array(
				'email'      => isset( $customer['email'] ) ? $customer['email'] : null,
				'first_name' => isset( $customer['first_name'] ) ? $customer['first_name'] : null,
				'last_name'  => isset( $customer['last_name'] ) ? $customer['last_name'] : null,
			);
		}

		return $params;
	}


	/**
	 * Map EDD cart items to Jilt line items
	 *
	 * @since 1.0.0
	 *
	 * @return array Mapped line items
	 */
	private function get_cart_product_line_items() {

		$line_items = array();

		// edd_get_cart_content_details() has been seen to return false in same cases
		// which might require some additional handling, e.g. empty cart?
		if ( ! is_array( edd_get_cart_content_details() ) ) {
			return $line_items;
		}

		foreach ( edd_get_cart_content_details() as $item_key => $item ) {

			$download = new EDD_Download( $item['id'] );

			// prepare main line item params
			$line_item = array(
				'title'      => html_entity_decode( $download->get_name() ),
				'product_id' => $item['id'],
				'quantity'   => $item['quantity'],
				'url'        => get_the_permalink( $item['id'] ),
				'image_url'  => EDD_Jilt_Download::get_download_image_url( $download ),
				'key'        => $item_key,
				'price'      => $this->get_item_price( $item ),
				'tax_lines'  => $this->get_tax_lines( $item ),
			);

			if ( edd_use_skus() ) {
				$line_item['sku'] = $download->get_sku();
			}

			// add variation data
			if ( $download->has_variable_prices() ) {
				$variant_id  = $item['item_number']['options']['price_id'];
				$option_name = edd_get_price_option_name( $download->ID, $variant_id );

				$line_item['variant_id']    = $variant_id;
				$line_item['variant_title'] = $option_name;
				$line_item['variation']     = array( 'name' => $option_name );
			}

			// line item properties (excludes price_id/quantity options)
			$line_item['properties'] = array_diff_key(
				$item['item_number']['options'],
				array_flip( array( 'price_id', 'quantity' ) )
			);

			/**
			 * Filters cart item params used for creating/updating a Jilt order
			 * via the API.
			 *
			 * @since 1.0.0
			 *
			 * @param array $line_item Jilt line item data
			 * @param array $item EDD line item data
			 * @param string $item_key EDD cart key for item
			 */
			$line_items[] = apply_filters( 'edd_jilt_order_cart_item_params', $line_item, $item, $item_key );
		}

		return $line_items;
	}


	/**
	 * Get the tax lines, if any, for this item
	 *
	 * @since 1.2.0
	 *
	 * @param array Item associative array
	 *
	 * @return array of tax lines, e.g. [ [ 'amount' => 135 ] ]
	 */
	private function get_tax_lines( $item ) {
		// a simplistic implementation for now, but if EDD identifies the actual
		// taxes a la Shopify, we can make this more comprehensive
		return array(
			array(
				'amount' => $this->amount_to_int( $item['tax'] ),
			),
		);
	}


	/**
	 * Map EDD cart fee line items to Jilt fee items
	 *
	 * @since 1.2.0
	 * @return array Mapped fee items
	 */
	private function get_cart_fee_line_items() {

		$fee_items = array();

		// fees
		if ( $fees = edd_get_cart_fees() ) {
			foreach ( $fees as $key => $fee ) {

				$fee_item = array(
					'title'  => html_entity_decode( $fee['label'] ),
					'key'    => $key,
					'amount' => $this->amount_to_int( $fee['amount'] ),
				);

				/**
				 * Filters cart fee item params used for creating/updating a Jilt order
				 * via the API.
				 *
				 * @since 1.2.0
				 *
				 * @param array $fee_item Jilt fee item data
				 * @param \stdClass $fee EDD fee object
				 */
				$fee_items[] = apply_filters( 'edd_jilt_order_cart_fee_params', $fee_item, $fee );
			}
		}

		return $fee_items;
	}


	/**
	 * Get the download price, either inclusive or exclusive of tax, depending
	 * on the EDD "Display during checkout inclusive/exclusive of taxes"
	 * setting.
	 *
	 * @since 1.2.0
	 *
	 * @param array $item the product
	 *
	 * @return int the item price in pennies
	 */
	private function get_item_price( $item ) {

		$price = $item['item_price'];

		if ( edd_prices_show_tax_on_checkout() && ! edd_prices_include_tax() ) {
			$price += $item['tax'];
		}

		if ( ! edd_prices_show_tax_on_checkout() && edd_prices_include_tax() ) {
			$price -= $item['tax'];
		}

		return $this->amount_to_int( $price );
	}


	/**
	 * Get any client details for the cart
	 *
	 * @since 1.2.0
	 *
	 * @return array associative array of client details (if available)
	 */
	private function get_client_details() {

		$client_details = array();

		if ( $browser_ip = edd_get_ip() ) {
			$client_details['browser_ip'] = $browser_ip;
		}
		if ( ! empty( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
			$client_details['accept_language'] = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
		}
		if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$client_details['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
		}

		return $client_details;
	}


	/**
	 * Convert a price/total to the lowest currency unit (e.g. cents)
	 *
	 * @since 1.0.2
	 *
	 * @param string|float $number
	 *
	 * @return int
	 */
	private function amount_to_int( $number ) {

		return round( $number * 100, 0 );
	}


	/**
	 * Helper method to improve the readability of methods calling the API
	 *
	 * @since 1.0.0
	 *
	 * @return \EDD_Jilt_API instance
	 */
	private function get_api() {
		return edd_jilt()->get_integration()->get_api();
	}


}
