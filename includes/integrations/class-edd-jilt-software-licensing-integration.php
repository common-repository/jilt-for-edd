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
 * @package   EDD-Jilt/Integrations
 * @author    Jilt
 * @copyright Copyright (c) 2015-2020, SkyVerge, Inc.
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

/**
 * Adds support for the EDD Software Licensing plugin.
 *
 * @since 1.2.0
 */
class EDD_Jilt_Software_Licensing_Integration extends EDD_Jilt_Integration_Base {


	/**
	 * Sets up the Software Licensing integration class.
	 *
	 * @since 1.2.0
	 */
	public function __construct() {

		add_filter( 'edd_jilt_get_cart_properties',  array( $this, 'get_cart_properties' ) );
		add_filter( 'edd_jilt_get_order_properties', array( $this, 'get_order_properties' ), 10, 2 );
		add_filter( 'edd_jilt_get_client_session',   array( $this, 'get_client_session' ) );

		add_action( 'init', array( $this, 'init' ) );
	}


	/**
	 * Adds delayed hooks.
	 *
	 * @since 1.4.0
	 */
	public function init() {

		add_action( 'edd_sl_renewals_added_to_cart',     array( edd_jilt()->get_cart_handler(), 'cart_updated' ) );
		add_action( 'edd_sl_renewals_removed_from_cart', array( edd_jilt()->get_cart_handler(), 'cart_updated' ) );
	}


	/**
	 * Returns the title for this integration.
	 *
	 * @see EDD_Jilt_Integration::get_title()
	 *
	 * @since 1.2.0
	 *
	 * @return string integration title
	 */
	public function get_title() {
		return __( 'Software Licensing', 'jilt-for-edd' );
	}


	/**
	 * Checks whether the integration is active.
	 *
	 * @see EDD_Jilt_Integration::is_active()
	 *
	 * @since 1.2.0
	 *
	 * @return boolean
	 */
	public function is_active() {
		// TODO: return false unless the Software Licensing plugin is installed/active {JS: 2017-10-02}
		return true;
	}


	/**
	 * Adds the Software Licensing cart properties, if needed.
	 *
	 * @since 1.2.0
	 *
	 * @param array $properties associative array of cart properties
	 * @return array associative array of cart properties
	 */
	public function get_cart_properties( $properties ) {

		$is_renewal = EDD()->session->get( 'edd_is_renewal' );

		if ( ! empty( $is_renewal ) ) {
			$properties['is_renewal']   = true;
			$properties['renewal_keys'] = EDD()->session->get( 'edd_renewal_keys' );
		}

		return $properties;
	}


	/**
	 * Adds the Software Licensing order properties, if needed.
	 *
	 * @since 1.2.0
	 *
	 * @param array $properties associative array of order properties
	 * @param \EDD_Jilt_Payment $payment payment object
	 * @return array associative array of order properties
	 */
	public function get_order_properties( $properties, $payment ) {

		$is_renewal = $payment->get_meta( '_edd_sl_is_renewal' );

		if ( ! empty( $is_renewal ) ) {
			$properties['is_renewal'] = true;
			// retrieve renewal_keys directly so that we can get multiple
			// values in case multiple licenses were renewed
			$properties['renewal_keys'] = get_post_meta( $payment->ID, '_edd_sl_renewal_key', false );
		}

		return $properties;
	}


	/**
	 * Adds the Software Licensing order properties, if needed.
	 *
	 * @since 1.2.0
	 *
	 * @param array $session associative array of session data
	 * @return array associative array of session properties
	 */
	public function get_client_session( $session ) {

		$is_renewal = EDD()->session->get( 'edd_is_renewal' );

		if ( ! empty( $is_renewal ) ) {
			$session['options'] = array(
				'edd_is_renewal'   => EDD()->session->get( 'edd_is_renewal' ),
				'edd_renewal_keys' => EDD()->session->get( 'edd_renewal_keys' ),
			);
		}

		return $session;
	}


}
