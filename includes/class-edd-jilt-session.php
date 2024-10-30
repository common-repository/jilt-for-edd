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
 * @package   EDD-Jilt/Integration
 * @author    Jilt
 * @copyright Copyright (c) 2015-2020, SkyVerge, Inc.
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

/**
 * Jilt session helper methods
 *
 * @since 1.2.0
 */
class EDD_Jilt_Session {


	/**
	 * Set Jilt order data to the session and user meta, if customer is logged in
	 *
	 * @since 1.2.0
	 * @param $cart_token
	 */
	public static function set_jilt_order_data( $cart_token ) {

		EDD()->session->set( 'edd_jilt_cart_token', $cart_token );

		if ( $user_id = get_current_user_id() ) {

			update_user_meta( $user_id, '_edd_jilt_cart_token', $cart_token );
		}
	}


	/**
	 * Unset Jilt order id from session and user meta
	 *
	 * @since 1.2.0
	 */
	public static function unset_jilt_order_data() {

		EDD()->session->set( 'edd_jilt_cart_token', '' );
		EDD()->session->set( 'edd_jilt_pending_recovery', '' );

		if ( $user_id = get_current_user_id() ) {
			delete_user_meta( $user_id, '_edd_jilt_cart_token' );
			delete_user_meta( $user_id, '_edd_jilt_pending_recovery' );
		}
	}


	/**
	 * Flags a customer to not receive emails from Jilt.
	 *
	 * @since 1.3.3
	 *
	 * @param bool $value true to opt out, false to opt in
	 * @param null|int|\WP_User $user_id optional user to set the preference for not receiving Jilt emails, defaults to current user
	 * @return bool
	 */
	public static function set_customer_email_collection_opt_out( $value, $user_id = null ) {

		$user_id = null === $user_id ? get_current_user_id() : $user_id;

		if ( $user_id instanceof WP_User ) {
			$user_id = $user_id->ID;
		}

		$pretty_value = $value ? 'yes' : 'no';

		if ( $user_id ) {
			$success = update_user_meta( $user_id, '_edd_jilt_email_collection_opt_out', $pretty_value );
		} else {
			// legacy session var, use true/false
			EDD()->session->set( 'jilt_opt_out_add_to_cart_email_capture', $value );

			EDD()->session->set( 'jilt_email_collection_opt_out', $pretty_value );
			$success = true;
		}

		// update the customer in Jilt with the declines_cart_reminders attribute
		if ( $cart_token = self::get_cart_token() ) {
			try {

				// update the existing Jilt order
				edd_jilt()->get_integration()->get_api()->update_order( $cart_token, array( 'customer' => array( 'declines_cart_reminders' => true ) ) );

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

		return (bool) $success;
	}


	/**
	 * Flags a customer to not receive marketing emails from Jilt.
	 *
	 * @since 1.3.3
	 *
	 * @param bool $value true to give consent, false to decline consent
	 * @param null|int|\WP_User $user_id optional user to set the preference for not receiving Jilt emails, defaults to current user
	 * @return bool success
	 */
	public static function set_customer_marketing_consent( $value, $user_id = null ) {

		$user_id = null === $user_id ? get_current_user_id() : $user_id;

		if ( $user_id instanceof WP_User ) {
			$user_id = $user_id->ID;
		}

		$pretty_value = $value ? 'yes' : 'no';

		if ( $user_id ) {
			$success = update_user_meta( $user_id, '_edd_jilt_marketing_email_consent', $pretty_value );
		} else {
			EDD()->session->set( 'jilt_marketing_email_consent', $pretty_value );
			$success = true;
		}

		return (bool) $success;
	}


	/** Getter methods ******************************************************/


	/**
	 * Return the cart token from the session
	 *
	 * @since 1.2.0
	 * @return string|null
	 */
	public static function get_cart_token() {

		return EDD()->session->get( 'edd_jilt_cart_token' );
	}


	/**
	 * Return the Jilt order ID from the session
	 *
	 * @since 1.2.0
	 * @deprecated since 1.3.0
	 * @return string|null
	 */
	public static function get_jilt_order_id() {

		_deprecated_function( 'EDD_Jilt_Session::get_jilt_order_id()', '1.3.0', 'EDD_Jilt_Session::get_cart_token' );

		return self::get_cart_token();
	}


	/**
	 * Returns true if the current checkout was created by a customer visiting
	 * a Jilt provided recovery URL
	 *
	 * @since 1.2.0
	 * @return bool
	 */
	public static function is_pending_recovery() {
		return (bool) ( is_user_logged_in() ? get_user_meta( get_current_user_id(), '_edd_jilt_pending_recovery', true ) : EDD()->session->get( 'edd_jilt_pending_recovery' ) );
	}


	/**
	 * Return the client session data that should be stored in Jilt. This is used
	 * to recreate the cart for guest customers who do not have an active session.
	 *
	 * Note that we're intentionally *not* saving the entire session, as it could
	 * contain confidential information that we don't want stored in Jilt. For
	 * future integrations with other extensions, the filter can be used to include
	 * their data.
	 *
	 * @since 1.2.0
	 * @return array
	 */
	public static function get_client_session() {

		$session = array(
			'cart'      => EDD()->session->get( 'edd_cart' ),
			'customer'  => EDD()->session->get( 'customer' ),
			'discounts' => EDD()->session->get( 'cart_discounts' ),
			'fees'      => EDD()->session->get( 'edd_cart_fees' ),
			'options'   => array(
				'gateway' => edd_get_chosen_gateway(),
			),
		);

		/**
		 * Allow actors to filter the client session data sent to Jilt. This is
		 * potentially useful for adding support for other extensions.
		 *
		 * @since 1.0.0
		 * @param array $session session data
		 */
		return apply_filters( 'edd_jilt_get_client_session', $session );
	}


	/**
	 * Checks whether a user has opted out from receiving emails from Jilt.
	 *
	 * @since 1.3.3
	 *
	 * @param null|int|\WP_User $user_id user optional user to check opt out status for, defaults to current user
	 * @return bool|null true if opted out, false if opted in, null if not yet set
	 */
	public static function get_customer_email_collection_opt_out( $user_id = null ) {

		$user_id = null === $user_id ? get_current_user_id() : $user_id;

		if ( $user_id instanceof WP_User ) {
			$user_id = $user_id->ID;
		}

		if ( ! $user_id ) {
			$opt_out = EDD()->session->get( 'jilt_email_collection_opt_out' );
		} else {
			$opt_out = get_user_meta( $user_id, '_edd_jilt_email_collection_opt_out', true );
		}

		if ( 'yes' === $opt_out ) {
			$opt_out = true;
		} elseif ( 'no' === $opt_out ) {
			$opt_out = false;
		} else {
			$opt_out = null;
		}

		return $opt_out;
	}


	/**
	 * Checks whether a user has consented to receiving marketing emails from Jilt.
	 *
	 * @since 1.3.3
	 *
	 * @param null|int|\WP_User $user_id user optional user to check opt out status for, defaults to current user
	 * @return bool|null true if consented, false if declined consent, null if not yet set
	 */
	public static function get_customer_marketing_consent( $user_id = null ) {

		$user_id = null === $user_id ? get_current_user_id() : $user_id;

		if ( $user_id instanceof WP_User ) {
			$user_id = $user_id->ID;
		}

		if ( ! $user_id || 0 === $user_id ) {
			$consented = EDD()->session->get( 'jilt_marketing_email_consent' );
		} else {
			$consented = get_user_meta( $user_id, '_edd_jilt_marketing_email_consent', true );
		}

		if ( 'yes' === $consented ) {
			$consented = true;
		} elseif ( 'no' === $consented ) {
			$consented = false;
		} else {
			$consented = null;
		}

		return $consented;
	}


}
