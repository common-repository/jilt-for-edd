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
 * Discount Handler class
 *
 * Handles managing discounts that were created by Jilt.
 *
 * @since 1.5.0
 */
class EDD_Jilt_Discount_Handler {


	/**
	 * Enforces only one Jilt discount allowed in the cart at any time.
	 *
	 * @since 1.5.0
	 *
	 * @param string $code discount code just added to the cart
	 * @param string[] $discounts array of discounts in the cart
	 */
	public static function enforce_single_jilt_discount( $code, $discounts ) {

		if ( self::is_jilt_discount( $code ) ) {

			foreach ( $discounts as $index => $discount ) {

				if ( $discount !== $code && self::is_jilt_discount( $discount ) ) {
					unset( $discounts[ $index ] );
				}
			}

			EDD()->session->set( 'cart_discounts', implode( '|', $discounts ) );
		}
	}


	/**
	 * Check if there are Jilt discounts on the site.
	 *
	 * @since 1.5.0
	 *
	 * @return bool true if the site has discounts
	 */
	public static function site_has_jilt_discounts() {

		$discount_ids = get_posts( self::get_expired_unused_jilt_discounts_query_args() );

		return ! empty ( $discount_ids );
	}


	/**
	 * Determines if a discount code is a Jilt discount or not.
	 *
	 * @since 1.5.0
	 *
	 * @param string $discount_code the discount code
	 * @return bool
	 */
	public static function is_jilt_discount( $discount_code ) {

		$discount_id = (int) edd_get_discount_id_by_code( $discount_code );

		if ( $discount_id ) {

			$jilt_discount_id = get_post_meta( $discount_id, 'jilt_discount_id', true );

			return $jilt_discount_id && ! empty( $jilt_discount_id );
		}

		return false;
	}


	/**
	 * Deletes a batch of expired, unused Jilt discounts.
	 *
	 * Batch size is determined by self::get_delete_discounts_tool_per_run_limit()
	 *
	 * @since 1.5.0
	 *
	 * @return int number of discounts deleted
	 */
	public static function delete_discounts() {

		$discount_ids = edd_get_discounts( self::get_expired_unused_jilt_discounts_query_args( self::get_delete_discounts_tool_per_run_limit() ) );
		$deleted      = 0;

		if ( ! empty( $discount_ids ) ) {

			foreach ( $discount_ids as $discount_id ) {

				$discount = new EDD_Discount( $discount_id );

				// sanity check to ensure this is a valid Jilt discount
				if ( ! $discount->get_ID() || ! get_post_meta( $discount->get_ID(), 'jilt_discount_id', true ) ) {
					continue;
				}

				// if this is an expired discount that has never been used, it's toast
				edd_remove_discount( $discount->get_ID() );
				$deleted++;
			}
		}

		return $deleted;
	}


	/**
	 * Gets the args needed to query WP for expired and unused Jilt discounts.
	 *
	 * @since 1.5.0
	 *
	 * @param int $posts_per_page (optional) posts per page param
	 * @return array
	 */
	public static function get_expired_unused_jilt_discounts_query_args( $posts_per_page = 1 ) {

		return [
			'fields'         => 'ids',
			'post_type'      => 'edd_discount',
			'post_status'    => 'any',
			'posts_per_page' => (int) $posts_per_page,
			'meta_query'     => [
				[
					'key'     => 'jilt_discount_id',
					'compare' => 'EXISTS',
				],
				[
					'key'   => '_edd_discount_uses',
					'value' => '0',
				],
				[
					'key'     => '_edd_discount_status',
					'value'   => 'expired',
				],
			],
		];
	}


	/**
	 * Gets the number of Jilt discounts to delete in one run of the Delete Discounts tool.
	 *
	 * @since 1.5.0
	 *
	 * @return int
	 */
	public static function get_delete_discounts_tool_per_run_limit() {

		/**
		 * Filters the number of Jilt coupons to delete in one run of the Delete Coupons tool.
		 *
		 * Sites can increase or decrease this value to handle timeouts.
		 *
		 * @since 1.4.0
		 *
		 * @param int $per_run number of Jilt coupons to delete in one run
		 */
		$per_run = (int) apply_filters( 'edd_jilt_admin_delete_discounts_tool_per_run_limit', 200 );

		return max( -1, $per_run );
	}


}
