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

defined( 'ABSPATH' ) or exit;

/**
 * EDD REST API Sales Controller
 *
 * The Sales endpoint is built in to the EDD API, so here we just tack on the extra data we need for Jilt.
 *
 * @since 1.4.0
 */
class EDD_Jilt_EDD_API_Sales_Controller extends EDD_Jilt_EDD_API_Base {


	/** @var string route name */
	protected $route = 'sales';

	/** @var array accepted HTTP methods for this route */
	protected $accepted_methods = array( 'GET' );

	/** @var bool this endpoint extends the existing EDD API sales endpoint. */
	protected $extends_edd_endpoint = true;


	/**
	 * Handles a request to this endpoint.
	 *
	 * A note on searching by jilt cart token: EDD doesn't seem to provide a
	 * way to filter the search query or easily hook in before an API request
	 * is handled, so to support searching by cart token, we hook in afterwards.
	 * When searching by cart token, include the parameter id=0 to avoid
	 * querying for the 10 most recent sales.
	 *
	 * @since 1.4.0
	 *
	 * @param array $data
	 * @param string $route
	 * @param \EDD_API $api_instance
	 * @return array
	 */
	public function handle_request( $data, $route, $api_instance ) {
		global $wp_query;

		if ( 'GET' === $this->get_method() ) {

			// searching by jilt cart token
			if ( isset( $wp_query->query_vars['jilt_cart_token'] ) && $wp_query->query_vars['jilt_cart_token'] ) {

				$payment_id = $this->find_payment_id_by_cart_token( $wp_query->query_vars['jilt_cart_token'] );

				// rewrite the query parameters
				unset( $wp_query->query_vars['jilt_cart_token'] );
				$wp_query->query_vars['id'] = $payment_id;

				// run the endpoint again
				$versions = EDD()->api->get_versions();
				$routes = new $versions[ EDD()->api->get_queried_version() ];

				$data = $routes->get_recent_sales();
			}

			$data = $this->add_jilt_data_to_sales( $data );
		}

		return $data;
	}


	/**
	 * Return the ID for a payment with the given jilt cart token
	 *
	 * @since 1.4.0
	 *
	 * @param string $cart_token
	 * @return int|bool payment ID or 0 if payment doesn't exist for cart token
	 */
	protected function find_payment_id_by_cart_token( $cart_token ) {
		global $wpdb;

		$payment_id = $wpdb->get_var( $wpdb->prepare( "
			SELECT post_id
			FROM {$wpdb->postmeta}
			WHERE meta_key = '_edd_jilt_cart_token'
			AND meta_value = %s
		", $cart_token ) );

		return (int) $payment_id;
	}


	/**
	 * Adds Jilt data to each sale in the data array.
	 *
	 * @since 1.4.0
	 *
	 * @param array $data
	 * @return array
	 */
	protected function add_jilt_data_to_sales( $data ) {

		if ( isset( $data['sales'] ) && is_array( $data['sales'] ) ) {
			foreach( $data['sales'] as $index => $sale ) {
				$data['sales'][ $index ] = $this->add_jilt_data_to_sale( $sale, $this->get_fields() );
			}
		}

		return $data;
	}


	/**
	 * Adds the Jilt data for a single sale to the sale data.
	 *
	 * @since 1.4.0
	 *
	 * @param array $sale
	 * @param array $fields fields to include in jilt data ( see EDD_Jilt_Payment::get_jilt_order_data() )
	 * @return array
	 */
	protected function add_jilt_data_to_sale( $sale, $fields = array() ) {

		if ( ! empty( $sale['key'] ) ) {

			if ( $payment = edd_get_payment_by( 'key', $sale['key'] ) ) {

				$order      = new EDD_Jilt_Payment( $payment->ID );
				$order_data = $order->get_jilt_order_data();

				$sale['jilt'] = ! empty( $fields ) ? array_intersect_key( $order_data, array_flip( $fields ) ) : $order_data;
			}
		}

		return $sale;
	}


}
