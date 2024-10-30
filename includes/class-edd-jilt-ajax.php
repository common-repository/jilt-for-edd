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
 * @package   EDD-Jilt/API
 * @author    Jilt
 * @copyright Copyright (c) 2015-2020, SkyVerge, Inc.
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

use Jilt\EDD\Contacts\EDD_Contact;
use Jilt\EDD\Helper;

defined( 'ABSPATH' ) or exit;

/**
 * AJAX handler.
 *
 * @since 1.4.0
 */
class EDD_Jilt_AJAX {


	/**
	 * Adds AJAX callbacks.
	 *
	 * @since 1.4.0
	 */
	public function __construct() {

		add_action( 'wp_ajax_edd_jilt_rated', array( $this, 'mark_plugin_rated' ) );

		add_action( 'wp_ajax_edd_jilt_get_cart_data',        array( $this, 'get_cart_data' ) );
		add_action( 'wp_ajax_nopriv_edd_jilt_get_cart_data', array( $this, 'get_cart_data' ) );

		// handle the subscribe form AJAX submit
		add_action( 'wp_ajax_edd_jilt_widget_subscribe',        [ $this, 'process_widget_subscribe' ] );
		add_action( 'wp_ajax_nopriv_edd_jilt_widget_subscribe', [ $this, 'process_widget_subscribe' ] );
	}


	/**
	 * Marks the plugin as rated.
	 *
	 * @internal
	 *
	 * @since 1.4.0
	 */
	public function mark_plugin_rated() {

		if ( ! current_user_can( 'manage_shop_settings' ) ) {
			wp_die( -1 );
		}

		update_option( 'edd_jilt_admin_footer_text_rated', 1 );

		wp_send_json_success();
	}


	/**
	 * Gets the current cart data.
	 *
	 * @internal
	 *
	 * @since 1.4.0
	 */
	public function get_cart_data() {

		$cart_handler   = edd_jilt()->get_cart_handler();
		$cart_data      = $cart_handler->get_cart_data();
		$cart_data_json = wp_json_encode( $cart_data );
		$response_data  = array(
			'cart'       => $cart_data,
			'cart_token' => $cart_handler->get_cart_token(),
			'cart_hash'  => $cart_data ? $cart_handler->get_cart_hash( $cart_data ) : ''
		);

		// log plaintext response
		$this->log_get_cart_data_request( $response_data );

		if ( $cart_handler->should_encrypt_cart_data() ) {
			$response_data['cart'] = $cart_handler->encrypt_cart_data( $cart_data_json );
		}

		wp_send_json_success( $response_data );
	}


	/**
	 * Processes the widget AJAX subscribe.
	 *
	 * @internal
	 *
	 * @since 1.5.0
	 */
	public function process_widget_subscribe() {

		// security check
		check_ajax_referer( 'edd_jilt_subscribe', 'security' );

		// send an error for honeypot submissions
		if ( ! empty( $_POST['honeypot'] ) ) {
			wp_send_json_error( '<div class="edd_errors edd-alert edd-alert-error">' . __( 'Oops, something went wrong. Please try again.', 'jilt-for-edd' ) . '</div>' );
		}

		// set base details
		$button = ! empty( $_POST['button'] ) ? Helper::jilt_clean( $_POST['button'] ) : __( 'Subscribe', 'jilt-for-edd' );
		$email  = ! empty( $_POST['email'] )  ? Helper::jilt_clean( $_POST['email'] )  : null;
		$fname  = ! empty( $_POST['fname'] )  ? Helper::jilt_clean( $_POST['fname'] )  : null;
		$lname  = ! empty( $_POST['lname'] )  ? Helper::jilt_clean( $_POST['lname'] )  : null;
		$lists  = ! empty( $_POST['lists'] )  ? explode( ',', Helper::jilt_clean( $_POST['lists'] ) ) : [];
		$tags   = ! empty( $_POST['tags'] )   ? explode( ',', Helper::jilt_clean( $_POST['tags'] ) )  : [];

		if ( ! is_email( $email ) ) {

			wp_send_json_error( '<div class="edd_errors edd-alert edd-alert-error">' . __( 'Please enter a valid email address.', 'jilt-for-edd' ) . '</div>' );

		} else {

			// attempt to load contact by logged-in user ID
			$contact    = new EDD_Contact( get_current_user_id(), true );
			$ip_address = function_exists( 'edd_get_ip' ) ? edd_get_ip() : false;

			// if needed, attempt to load contact by email address
			if ( empty( $contact->id ) ) {
				$contact = new EDD_Contact( $email );
			}

			$contact->set_subscribe_email( $email );

			if ( $fname ) {
				$contact->set_subscribe_first_name( $fname );
			}

			if ( $lname ) {
				$contact->set_subscribe_last_name( $lname );
			}

			$success = $contact->subscribe( $lists, $tags );

			if ( $success['result'] ) {

				// store some local Jilt information
				if ( ! $contact->is_guest() ) {

					$contact->store_opt_in_details( 'edd_jilt_signup_form', $button, $ip_address );
					$contact->set_jilt_remote_id( $success['message']->id );
				}

				// set the data in the Jilt session for guests; resaves for logged in users
				// but we should separate that from session handling in the future
				\EDD_Jilt_Session::set_customer_marketing_consent( true );

				wp_send_json_success( '<div class="edd_success edd-alert edd-alert-success">' . __( 'Thanks for subscribing!', 'jilt-for-edd' ) . '</div>' );

			} else {

				wp_send_json_error( '<div class="edd_errors edd-alert edd-alert-error">' . __( 'Oops, something went wrong. Please try again.', 'jilt-for-edd' ) . '</div>' );
				edd_jilt()->log( sprintf( 'Widget signup error: %s', $success['message'] ) );
			}
		}
	}


	/**
	 * Logs the get cart data request/response.
	 *
	 * @since 1.4.0
	 *
	 * @param array $cart_data
	 */
	private function log_get_cart_data_request( $cart_data ) {

		// pieces of the request that we care about
		$request = array(
			'method'    => $_SERVER['REQUEST_METHOD'],
			'uri'       => $this->get_client_request_url(),
			'remote-ip' => $_SERVER['REMOTE_ADDR'],
			'headers'   => array(),
		);

		// certain HTTP flags might not be set in some enviroments
		if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$request['user-agent'] = $_SERVER['HTTP_USER_AGENT'];
		}
		if ( isset( $_SERVER['HTTP_ACCEPT'] ) ) {
			$request['headers']['accept'] = $_SERVER['HTTP_ACCEPT'];
		}
		if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
			$request['headers']['referer'] = $_SERVER['HTTP_REFERER'];
		}

		// the response format used by wp_send_json_success()
		$response_body = array(
			'success' => true,
			'data'    => $cart_data
		);

		$response = array(
			'code'    => 200,
			'message' => 'OK',
			'headers' => array(
				'content-type' => 'application/json; charset=' . get_option( 'blog_charset' ),
			),
			'body' => json_encode( $response_body, JSON_PRETTY_PRINT),
		);

		edd_jilt()->get_logger()->log_api_request( $request, $response );
	}


	/**
	 * Gets the full client request URL.
	 *
	 * @since 1.4.0
	 *
	 * @return string the request URL e.g. https://example.com/checkout/
	 */
	private function get_client_request_url() {

		if ( isset( $_SERVER['REQUEST_SCHEME'] ) ) {
			$scheme = $_SERVER['REQUEST_SCHEME'];
		} elseif ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ) {
			$scheme = 'https';
		} else {
			$scheme = 'http';
		}

		return isset( $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] ) ? "{$scheme}://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}" : '';
	}


}
