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
 * EDD REST API Sales Count Controller
 *
 * Simple controller to return the count of sales (orders).
 *
 * @since 1.4.0
 */
class EDD_Jilt_EDD_API_Sales_Count_Controller extends EDD_Jilt_EDD_API_Base {


	/** @var string route name */
	protected $route = 'jilt/sales-count';

	/** @var array accepted HTTP methods for this route */
	protected $accepted_methods = array( 'GET' );


	/**
	 * Handles a request to this endpoint.
	 *
	 * @since 1.4.0
	 *
	 * @param array $data
	 * @param string $route
	 * @param \EDD_API $api_instance
	 * @return array
	 */
	public function handle_request( $data, $route, $api_instance ) {

		if ( 'GET' === $this->get_method() ) {
			$data = array( 'count' => $this->get_sales_count() );
		}

		return $data;
	}


	/**
	 * Gets the count of total sales.
	 *
	 * @since 1.4.0
	 * @return int
	 */
	protected function get_sales_count() {

		$status      = $this->get_request_param( 'status' );
		$counts      = edd_count_payments();
		$total_count = 0;

		foreach ( edd_get_payment_status_keys() as $status_key ) {

			if ( ! $status || $status === $status_key || ( 'complete' === $status && 'publish' === $status_key ) ) {

				$total_count += $counts->$status_key;
			}
		}

		return $total_count;
	}


}
