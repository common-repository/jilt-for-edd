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
 * Adds support for the EDD Free Downloads plugin.
 *
 * @since 1.5.0
 */
class EDD_Jilt_Free_Downloads_Integration extends EDD_Jilt_Integration_Base {


	/**
	 * Sets up the Free Downloads integration class.
	 *
	 * @since 1.5.0
	 */
	public function __construct() {

		add_action( 'edd_free_downloads_post_complete_payment', [ $this, 'handle_free_download_completion' ] );
	}


	/**
	 * Returns the title for this integration.
	 *
	 * @see EDD_Jilt_Integration::get_title()
	 *
	 * @since 1.5.0
	 *
	 * @return string integration title
	 */
	public function get_title() {
		return __( 'Free Downloads', 'jilt-for-edd' );
	}


	/**
	 * Adds free download payment data to Jilt.
	 *
	 * @since 1.5.0
	 *
	 * @param int $payment_id the Payment ID
	 */
	public function handle_free_download_completion( $payment_id ) {

		$payment = new EDD_Jilt_Payment( (int) $payment_id );

		if ( $payment && ! $payment->get_jilt_cart_token() ) {

			// assign a cart token and trigger an update to Jilt
			$cart_token = EDD_Jilt_Session::get_cart_token();

			$payment->update_meta( '_edd_jilt_cart_token', $cart_token );

			edd_jilt()->get_checkout_handler()->handle_completed_payment( $payment->ID );
		}
	}


}
