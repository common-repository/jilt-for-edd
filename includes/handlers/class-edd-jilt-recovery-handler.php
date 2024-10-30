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
 * @copyright Copyright (c) 2015-2020, SkyVerge, Inc.
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

/**
 * EDD Recovery handler class
 *
 * @since 1.0.0
 */
class EDD_Jilt_Recovery_Handler {


	/**
	 * Setup class
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		// the wp action is late enough to use the edd_is_checkout() function, but
		// early enough to create the cart session before the checkout form is rendered
		add_action( 'wp', array( $this, 'maybe_recreate_cart' ) );

		// handle legacy URLs created before 1.2.0
		add_action( 'edd_jilt-recover', array( $this, 'redirect_legacy_urls' ) );

		add_action( 'edd_cart_discount_set', 'EDD_Jilt_Discount_Handler::enforce_single_jilt_discount', 10, 2 );
	}


	/**
	 * Redirect legacy recovery URLs created before 1.2.0 to the checkout
	 * page
	 *
	 * @since 1.2.0
	 */
	public function redirect_legacy_urls() {

		if ( ! empty( $_GET['token'] ) && ! empty( $_GET['hash'] ) ) {

			$params = array(
				'recover' => true,
				// must encode or else edd_get_checkout_uri() strips out double equal sign chars
				'token'    => rawurlencode( $_GET['token'] ),
				'hash'     => $_GET['hash'],
			);

			// forward along any UTM params
			foreach ( $_GET as $key => $val ) {
				if ( 0 === strpos( $key, 'utm_' ) ) {
					$params[ $key ] = $val;
				}
			}

			// if a discount was provided in the recovery URL, set it so it will be applied by EDD on the checkout page
			if ( isset( $_GET['discount'] ) && $discount = rawurldecode( $_GET['discount'] ) ) {
				$params['discount'] = $discount;
			}

			wp_safe_redirect( edd_get_checkout_uri( $params ) );
			exit;
		}
	}


	/**
	 * Maybe recreate the cart when on the checkout page with recovery parameters set
	 *
	 * recovery URLs look like example.tld/checkout?recover=1&token=abc123&hash=xyz
	 *
	 * @since 1.2.0
	 */
	public function maybe_recreate_cart() {

		if ( isset( $_GET['token'], $_GET['hash'] ) && edd_is_checkout() ) {

			try {

				$this->recreate_cart();

			} catch ( EDD_Jilt_Plugin_Exception $e ) {

				edd_jilt()->get_logger()->warning( 'Could not recreate cart: ' . $e->getMessage() );
			}
		}
	}


	/**
	 * Recreate the cart immediately before the checkout page loads.
	 *
	 * @since 1.0.0
	 *
	 * @throws EDD_Jilt_Plugin_Exception
	 */
	protected function recreate_cart() {

		define( 'DOING_CART_RECOVERY', true );

		$cart_token = rawurldecode( $_GET['token'] );

		$this->verify_url_signature( $cart_token, $_GET['hash'] );

		$jilt_order = $this->get_jilt_order( $cart_token );

		$cart_token = $jilt_order->cart_token;

		// check if the payment for this cart has already been placed
		if ( $payment_id = $this->get_placed_payment_id( $cart_token ) ) {

			// prepare the cart so that a placed payment can be resumed
			$this->setup_cart_for_placed_payment( $payment_id );
		}

		// re-log in the user if they abandoned the payment while logged-in
		$this->maybe_log_in_user( $cart_token );

		// regenerate the cart from the Jilt order details
		$this->recreate_cart_from_jilt_order( $jilt_order );
	}


	/**
	 * Verify the recovery URL hasn't been tampered with and was created via the plugin.
	 *
	 * @since 1.2.0
	 *
	 * @param string $cart_token
	 * @param string $hash
	 *
	 * @throws \EDD_Jilt_Plugin_Exception
	 */
	protected function verify_url_signature( $cart_token, $hash ) {

		$secret_keys   = edd_jilt()->get_integration()->get_secret_key_stash();
		$is_valid_hash = false;

		// try to verify the hash with all the secret keys from the stash, starting from the latest (last) one
		while ( $secret_key = array_pop( $secret_keys ) ) {

			if ( hash_equals( hash_hmac( 'sha256', $cart_token, $secret_key ), $hash ) ) {
				$is_valid_hash = true;
				break;
			}
		}

		// verify hash
		if ( ! $is_valid_hash ) {
			throw new EDD_Jilt_Plugin_Exception( 'Hash verification failed' );
		}
	}


	/**
	 * Retrieve the Jilt order details from the API.
	 *
	 * @since 1.2.0
	 *
	 * @param string $cart_token
	 * @return \stdClass
	 *
	 * @throws \EDD_Jilt_API_Exception API error
	 * @throws \EDD_Jilt_Plugin_Exception order ID or cart token verification failures
	 */
	protected function get_jilt_order( $cart_token ) {

		if ( ! edd_jilt()->get_integration()->is_jilt_connected() ) {
			throw new EDD_Jilt_Plugin_Exception( 'Plugin is not properly configured' );
		}

		// decode
		$data = json_decode( base64_decode( $cart_token ) );

		// readability
		$cart_token = $data->cart_token;

		if ( ! $cart_token ) {
			throw new EDD_Jilt_Plugin_Exception( 'Jilt order cart token is empty.' );
		}

		// get Jilt order for verifying URL and recreating cart if session is not present
		$jilt_order = edd_jilt()->get_integration()->get_api()->get_order( $cart_token );

		if ( ! $jilt_order->cart_token ) {
			throw new EDD_Jilt_Plugin_Exception( "Unable to recreate cart - Jilt cart token missing from order for cart token: {$cart_token}" );
		}

		// verify cart token matches
		if ( ! hash_equals( $jilt_order->cart_token, $cart_token ) ) {
			throw new EDD_Jilt_Plugin_Exception( "cart token verification failed for Jilt order ID: {$cart_token}" );
		}

		return $jilt_order;
	}


	/**
	 * Return the ID for a placed payment, or false if the payment hasn't been
	 * placed.
	 *
	 * @since 1.2.0
	 *
	 * @param string $cart_token
	 * @return int|bool payment ID or false if payment doesn't exist for cart token
	 */
	protected function get_placed_payment_id( $cart_token ) {
		global $wpdb;

		edd_jilt()->get_logger()->info( "Getting placed payment ID for: {$cart_token}" );

		$payment_id = $wpdb->get_var( $wpdb->prepare( "
			SELECT post_id
			FROM {$wpdb->postmeta}
			WHERE meta_key = '_edd_jilt_cart_token'
			AND meta_value = %s
		", $cart_token ) );

		return $payment_id > 0 ? $payment_id : false;
	}


	/**
	 * Maybe log in the user if they abandoned the checkout while logged in
	 *
	 * @since 1.2.0
	 * @param string $cart_token
	 */
	protected function maybe_log_in_user( $cart_token ) {
		global $wpdb;

		$user_id = $wpdb->get_var( $wpdb->prepare( "
			SELECT user_id
			FROM {$wpdb->usermeta}
			WHERE meta_key = '_edd_jilt_cart_token'
			AND meta_value = %s
		", $cart_token ) );

		if ( ! $user_id ) {
			return;
		}

		edd_jilt()->get_logger()->info( "Logging in user: {$user_id}" );

		if ( is_user_logged_in() ) {

			// another user is logged in
			if ( (int) $user_id !== get_current_user_id() ) {

				wp_logout();

				// log the current user out, log in the new one
				if ( $this->allow_cart_recovery_user_login( $user_id ) ) {

					edd_jilt()->get_logger()->info( "Another user is logged in, logging them out & logging in user {$user_id}" );

					wp_set_current_user( $user_id );
					wp_set_auth_cookie( $user_id );
					update_user_meta( $user_id, '_edd_jilt_pending_recovery', true );

				// safety check fail: do not let an admin to be logged in automatically
				} else {

					edd_jilt()->get_logger()->warning( "Not logging in user {$user_id} with admin rights" );
				}

			} else {

				edd_jilt()->get_logger()->info( 'User is already logged in' );
			}

		} else {

			// log the user in automatically
			if ( $this->allow_cart_recovery_user_login( $user_id ) ) {

				edd_jilt()->get_logger()->info( 'User is not logged in, logging in' );

				wp_set_current_user( $user_id );
				wp_set_auth_cookie( $user_id );
				update_user_meta( $user_id, '_edd_jilt_pending_recovery', true );

			// safety check fail: do not let an admin to be logged in automatically
			} else {

				edd_jilt()->get_logger()->warning( "Not logging in user {$user_id} with admin rights" );
			}
		}
	}


	/**
	 * Check if a user is allowed to be logged in for cart recovery
	 *
	 * @since 1.0.0
	 * @param int $user_id WP_User id
	 * @return bool
	 */
	private function allow_cart_recovery_user_login( $user_id ) {

		/**
		 * Filter users who do not possess high level rights
		 * to be logged in automatically upon cart recovery
		 *
		 * @since 1.0.0
		 * @param bool $allow_user_login Whether to allow or disallow
		 * @param int $user_id The user to log in
		 */
		$allow_user_login = apply_filters( 'edd_jilt_allow_cart_recovery_user_login', ! user_can( $user_id, 'edit_others_posts' ), $user_id );

		return (bool) $allow_user_login;
	}


	/**
	 * Setup the cart to recover a previously placed payment. The EDD core method
	 * [see edd_recover_payment() ] is not used because the Jilt order
	 * has the most up-to-date information, whereas the payment info is only accurate
	 * at the time the payment was originally placed.
	 *
	 * @since 1.2.0
	 * @param int $payment_id
	 * @throws \EDD_Jilt_Plugin_Exception when the logged in user doesn't match the user for the payment
	 */
	protected function setup_cart_for_placed_payment( $payment_id ) {

		edd_jilt()->get_logger()->info( 'Recreating cart from placed payment' );

		$payment = edd_get_payment( $payment_id );

		if ( ! $payment->is_recoverable() ) {

			$this->maybe_redirect_to_purchase_receipt( $payment );
		}

		$payment->add_note( __( 'Customer visited Jilt payment recovery URL.', 'jilt-for-edd' ) );

		// set the user meta from the payment so that we can potentially log the user in
		if ( ! is_user_logged_in() && $payment->user_id ) {

			// add user meta manually, session data is set after this while recreating the cart
			update_user_meta( $payment->user_id, '_edd_jilt_cart_token', $payment->get_meta( '_edd_jilt_cart_token' ) );
			update_user_meta( $payment->user_id, '_edd_jilt_pending_recovery', true );
		}

		EDD()->cart->empty_cart();

		EDD()->session->set( 'edd_resume_payment', $payment->ID );
	}


	/**
	 * Recreate the cart from the Jilt order data. This is preferred even
	 * for previously placed payments because the data represents the most
	 * up-to-date information for the cart.
	 *
	 * @since 1.0.0
	 * @param stdClass $jilt_order
	 */
	protected function recreate_cart_from_jilt_order( $jilt_order ) {

		edd_jilt()->get_logger()->info( 'Recreating cart from Jilt order' );

		/**
		 * Filters the the remote client session data sent from the Jilt App when recreating the local cart.
		 *
		 * This is potentially useful for adding support for other extensions.
		 *
		 * @since 1.3.0
		 *
		 * @param array $client_session session data returned from REST API
		 * @param stdClass $jilt_order returned from REST API
		 */
		$client_session = apply_filters( 'edd_jilt_remote_session_for_cart_recreate', $jilt_order->client_session, $jilt_order );

		// recreate cart
		$cart = maybe_unserialize( $client_session->cart );
		$cart = $this->object_to_array( $cart );

		$existing_cart_hash = md5( wp_json_encode( EDD()->session->get( 'edd_cart' ) ) );
		$loaded_cart_hash   = md5( wp_json_encode( $cart ) );

		// avoid re-setting the cart object if it matches the existing session cart
		if ( ! hash_equals( $existing_cart_hash, $loaded_cart_hash ) ) {
			EDD()->session->set( 'edd_cart', $cart );
		}

		// reload the cart from the session
		EDD()->cart->get_contents_from_session();
		EDD()->cart->get_contents();

		// Take the customer data and customer session and merge them together for use in the customer session
		$customer_data = ! empty( $jilt_order->customer ) ? (array) $jilt_order->customer : array();

		if ( is_array( $client_session->customer ) || is_object( $client_session->customer ) ) {
			// client_session->customer is sometimes bool(false)
			$customer_data = array_merge( (array) $client_session->customer, $customer_data );
		}

		// the session customer address *must* have line1, lin2, city, zip fields
		// also, having this will generate an "Array to String conversion" PHP Notice
		// due to `array_map( 'sanitize_text_field', $customer );` in the
		// edd_user_info_fields() global function
		if ( isset( $customer_data['address'] ) && is_object( $customer_data['address'] ) ) {
			$customer_data['address'] = array_merge(
				array( 'line1' => '', 'line2' => '', 'city' => '', 'zip' => '' ),
				(array) $customer_data['address']
			);
		}

		// set first/last name if not populated from client session
		if ( ! isset( $customer_data['first_name'] ) && ! empty( $jilt_order->billing_address->first_name ) ) {
			$customer_data['first_name'] = $jilt_order->billing_address->first_name;
		}

		if ( ! isset( $customer_data['last_name'] ) && ! empty( $jilt_order->billing_address->last_name ) ) {
			$customer_data['last_name'] = $jilt_order->billing_address->last_name;
		}

		EDD()->session->set( 'customer', $customer_data );

		if ( null !== $client_session->discounts ) {
			EDD()->session->set( 'cart_discounts', $client_session->discounts );
		}

		// this check for discounts in the URL takes place before cart recovery,
		// so we need to call it manually here
		edd_listen_for_cart_discount();
		edd_apply_preset_discount();

		// reset has_discounts
		EDD()->cart->has_discounts = null;

		if ( ! empty( $client_session->fees ) ) {

			$fees = array();

			foreach ( $client_session->fees as $key => $fee ) {
				$fees[ $key ] = $this->object_to_array( $fee );
			}

			EDD()->session->set( 'edd_cart_fees', $fees );
		}

		if ( ! empty( $client_session->options ) ) {
			foreach ( $client_session->options as $session_key => $session_value ) {
				$session_value = $this->object_to_array( $session_value );
				EDD()->session->set( $session_key, $session_value );
			}
		}

		// set Jilt data in session
		EDD()->session->set( 'edd_jilt_cart_token', $jilt_order->cart_token );
		EDD()->session->set( 'edd_jilt_pending_recovery', 1 );

		// if the customer had selected a payment method at checkout, set it
		if ( ! empty( $client_session->options->gateway ) ) {

			$gateway = $client_session->options->gateway;

			if ( edd_is_gateway_active( $gateway ) ) {
				$_REQUEST['payment-method'] = $gateway;
			}
		}

		// refresh the cart data from the session data set in this method
		EDD()->cart->setup_cart();

		edd_jilt()->get_logger()->info( 'Cart recreation complete!' );
	}


	/** Helper methods ****************************************************/


	/**
	 * When recreating the cart for a placed payment, redirect to the purchase
	 * receipt page if the payment is already completed, or the purchase
	 * history page if revoked.
	 *
	 * @since 1.2.0
	 *
	 * @param \EDD_Payment $payment
	 */
	protected function maybe_redirect_to_purchase_receipt( $payment ) {

		// if the payment has been completed already, redirect to the purchase receipt page
		if ( 'publish' === $payment->status ) {

			$redirect  = add_query_arg( array( 'payment_key' => $payment->key ), get_permalink( edd_get_option( 'success_page' ) ) );

		} else {

			edd_jilt()->get_logger()->warning( "Payment ID {$payment->ID} is not recoverable due to {$payment->status} status." );

			// for revoked payments, off to the purchase history page
			$redirect = get_permalink( edd_get_option( 'purchase_history_page' ) );
		}

		wp_safe_redirect( $redirect );
		exit;
	}


	/**
	 * Convert the objects from the Jilt API to arrays recursively
	 *
	 * @since 1.0.0
	 * @param stdClass|object $data
	 * @return array
	 */
	private function object_to_array( $data ) {

		$data = json_decode( json_encode( $data ), true );

		return $data;
	}


}
