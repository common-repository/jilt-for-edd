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
 * Customer Handler class
 *
 * Handles populating and updating additional customer session data that's not
 * handled by EDD core.
 *
 * @since 1.1.0
 */
class EDD_Jilt_Customer_Handler {


	/**
	 * Constructor
	 *
	 * @since 1.1.0
	 */
	public function __construct() {
		$this->init();
	}


	/**
	 * Add required actions.
	 *
	 * @since 1.0.6
	 */
	protected function init() {

		add_action( 'wp_ajax_nopriv_edd_jilt_set_customer', array( $this, 'ajax_set_customer' ) );

		add_action( 'wp_ajax_edd_jilt_set_customer_email_collection_opt_out', array( $this, 'ajax_set_customer_email_collection_opt_out' ) );
		add_action( 'wp_ajax_nopriv_edd_jilt_set_customer_email_collection_opt_out', array( $this, 'ajax_set_customer_email_collection_opt_out' ) );

		// set customer info into the session upon login: hook into this action
		// very early so that we are able to set the customer info *before* our
		// cart handler hook runs
		add_action( 'wp_login', array( $this, 'customer_login' ), 1, 2 );

	}


	/**
	 * Ajax handler for setting customer data. This is as a result of calling
	 * the client side edd_jilt.set_customer() javascript method.
	 *
	 * @since 1.0.1
	 */
	public function ajax_set_customer() {

		// security check
		check_ajax_referer( 'jilt-for-edd' );

		// prevent overriding the logged in user's email address
		if ( is_user_logged_in() ) {
			wp_send_json_error( array(
				'message' => __( 'You cannot set a customer email for logged-in user.', 'jilt-for-edd' ),
			) );
		}

		if ( ! empty( $_POST['add_to_cart_opt_out'] ) ) {

			if ( ! empty( $_POST['email_capture_opt_out'] ) && edd_jilt()->get_integration()->show_email_usage_notice() ) {
				// additionally flag the customer to opt out any email collection
				EDD_Jilt_Session::set_customer_email_collection_opt_out( true );
			} else {
				// do not ask again this customer to collect an email by adding to cart
				EDD()->session->set( 'jilt_opt_out_add_to_cart_email_capture', true );
			}
		}

		$first_name = ! empty( $_POST['first_name'] ) ? sanitize_user( $_POST['first_name'] ) : null;
		$last_name  = ! empty( $_POST['last_name'] ) ? sanitize_user( $_POST['last_name'] ) : null;
		$email      = ! empty( $_POST['email'] ) ? filter_var( $_POST['email'], FILTER_VALIDATE_EMAIL ) : null;

		$this->set_customer_info( $first_name, $last_name, $email );

		/**
		 * Fires when customer data is updated via AJAX.
		 *
		 * This is primarily used by EDD_Jilt_Cart_Handler to fire a cart updated
		 * request with the new customer data.
		 *
		 * @since 1.3.0
		 *
		 * @param array $customer_data the customer data that was updated
		 */
		do_action( 'edd_jilt_ajax_update_customer', array(
			'first_name' => $first_name,
			'last_name' => $last_name,
			'email' => $email,
		) );

		wp_send_json_success( array(
			'message' => 'Successfully set customer data.'
		) );
	}


	/**
	 * Handle setting first/last name and email when a customer logs in.
	 *
	 * @since 1.1.0
	 * @param string $username, unused
	 * @param \WP_User $user
	 */
	public function customer_login( $username, $user ) {

		$this->set_customer_info( $user->first_name, $user->last_name, $user->user_email );
	}


	/**
	 * Set the first name, last name, and email address for Customer session
	 * object.
	 *
	 * @since 1.1.0
	 * @param string $first_name
	 * @param string $last_name
	 * @param string $email
	 */
	private function set_customer_info( $first_name, $last_name, $email ) {

		$customer_data = array(
			'email'      => $email,
			'first_name' => $first_name,
			'last_name'  => $last_name,
		);

		$customer = EDD()->customers->get_customer_by( 'email', $email );

		if ( $customer ) {
			$customer_data['customer_id'] = $customer->id;
			$customer_data['admin_url']   = admin_url( 'edit.php?post_type=download&page=edd-customers&view=overview&id=' . $customer->id );
		}

		EDD()->session->set( 'customer', $customer_data );
	}


	/**
	 * Flags a customer to opt out from email notifications from Jilt via AJAX.
	 *
	 * @internal
	 *
	 * @since 1.3.3
	 */
	public function ajax_set_customer_email_collection_opt_out() {

		check_ajax_referer( 'jilt-for-edd', 'security' );

		if ( ! empty( $_POST['email_capture_opt_out'] ) ) {

			$user_id = get_current_user_id();

			if ( EDD_Jilt_Session::set_customer_email_collection_opt_out( true, $user_id ) ) {
				wp_send_json_success( $user_id );
			}
		}

		wp_send_json_error();
	}


}
