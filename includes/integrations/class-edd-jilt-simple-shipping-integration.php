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
class EDD_Jilt_Simple_Shipping_Integration extends EDD_Jilt_Integration_Base {


	/**
	 * Sets up the Simple Shipping integration class.
	 *
	 * @since 1.2.0
	 */
	public function __construct() {

		if ( $this->is_active() ) {
			add_filter( 'edd_jilt_order_needs_shipping', array( $this, 'needs_shipping' ), 10, 2 );
			add_filter( 'edd_jilt_get_order_fulfillment_status', array( $this, 'get_fulfillment_status' ), 10, 2 );
		}
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
		return __( 'Simple Shipping', 'jilt-for-edd' );
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
		return function_exists( 'edd_simple_shipping' );
	}


	/**
	 * Checks whether this payment need shipping.
	 *
	 * @since 1.2.0
	 *
	 * @param boolean $needs_shipping
	 * @param \EDD_Jilt_Payment $payment
	 * @return boolean true if shipping is needed for this payment, false otherwise
	 */
	public function needs_shipping( $needs_shipping, $payment ) {

		$edd_simple_shipping = edd_simple_shipping();

		if ( $edd_simple_shipping && is_callable( array( $edd_simple_shipping, 'payment_needs_shipping' ) ) ) {

			$needs_shipping = (bool) $edd_simple_shipping->payment_needs_shipping( $payment->ID );
		}

		return $needs_shipping;
	}


	/**
	 * Returns the fulfillment status for the payment.
	 *
	 * @since 1.2.0
	 *
	 * @param string $fulfillment_status one of 'fulfilled', 'unfulfilled', 'partial', or null
	 * @param \EDD_Jilt_Payment $payment
	 * @return string one of 'fulfilled', 'unfulfilled', 'partial', or null
	 */
	public function get_fulfillment_status( $fulfillment_status, $payment ) {

		$shipping_status = (int) get_post_meta( $payment->ID, '_edd_payment_shipping_status', true );

		if ( 2 === $shipping_status ) {
			$fulfillment_status = 'fulfilled';
		} elseif ( 1 === $shipping_status || in_array( $payment->get_financial_status(), array( 'authorized', 'pending' ), true ) ) {
			$fulfillment_status = 'unfulfilled';
		}

		return $fulfillment_status;
	}


}
