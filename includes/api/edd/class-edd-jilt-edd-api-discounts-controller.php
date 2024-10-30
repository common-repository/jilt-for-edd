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
 * EDD REST API Discounts Controller
 *
 * The ability to GET discounts is built-in to the EDD API; this class is for creating new discounts via the API.
 *
 * @since 1.4.0
 */
class EDD_Jilt_EDD_API_Discounts_Controller extends EDD_Jilt_EDD_API_Base {


	/** @var array accepted HTTP methods for this route */
	protected $accepted_methods = array( 'POST' );

	/** @var bool extends an existing EDD API endpoint */
	protected $extends_edd_endpoint = true;

	/** @var string route name */
	protected $route = 'discounts';


	/**
	 * Handles a request to this endpoint.
	 *
	 * @since 1.4.0
	 *
	 * @param array $data
	 * @param string $route
	 * @param \EDD_API $api_instance
	 * @return array
	 * @throws EDD_Jilt_Plugin_Exception
	 */
	public function handle_request( $data, $route, $api_instance ) {

		if ( 'POST' === $this->get_method() ) {
			$data = $this->post_discounts();
		}

		return $data;
	}


	/**
	 * Creates a discount.
	 *
	 * Routed from POST edd-api/v2/jilt/discounts
	 *
	 * @since 1.4.0
	 *
	 * @return array
	 * @throws EDD_Jilt_Plugin_Exception
	 */
	protected function post_discounts() {

		$discount_data = $this->get_discount_data();

		$this->validate_post_discounts( $discount_data );

		// pull the remote discount id
		$discount_id = $discount_data['discount_id'];
		unset( $discount_data['discount_id'] );

		$id = edd_store_discount( $discount_data );

		if ( false === $id ) {
			throw new EDD_Jilt_Plugin_Exception( 'Error creating discount', 422 );
		}

		// identify the coupon as having been created by jilt by setting the remote discount id
		update_post_meta( $id, 'jilt_discount_id', $discount_id );

		$response = array(
			'discount' => array(
				'id'   => $id,
				'code' => $discount_data['code'],
			),
		);

		return $response;
	}


	/**
	 * Gets the discount data from the POST request.
	 *
	 * See EDD_Discount::build_meta() for a list of available params.
	 *
	 * @since 1.4.0
	 */
	protected function get_discount_data() {

		return $this->get_only_params( array(
			'discount_id',
			'code',
			'name',
			'status',
			'uses',
			'max_uses',
			'amount',
			'start',
			'expiration',
			'type',
			'min_price',
			'product_reqs',
			'product_condition',
			'excluded_products',
			'is_not_global',
			'is_single_use',
		) );
	}


	/**
	 * Validate the post discounts request data
	 *
	 * @since 1.1.0
	 * @param array $discount_data associative array of discount data
	 * @throws EDD_Jilt_Plugin_Exception
	 */
	protected function validate_post_discounts( $discount_data ) {

		// validate required params
		$required_params = array( 'code', 'discount_id', 'name', 'type', 'amount' );
		$missing_params  = array();

		foreach ( $required_params as $required_param ) {
			if ( empty( $discount_data[ $required_param ] ) ) {
				$missing_params[] = $required_param;
			}
		}

		if ( $missing_params ) {
			throw new EDD_Jilt_Plugin_Exception( 'Missing required params: ' . implode( ', ', $missing_params ), 422 );
		}

		// Validate coupon types
		$valid_types = array( 'percent', 'flat' );
		if ( ! in_array( $discount_data['type'], $valid_types, true ) ) {
			throw new EDD_Jilt_Plugin_Exception( sprintf( 'Invalid discount type - the type must be any of these: %s', implode( ', ', $valid_types ) ), 422 );
		}

		$discount = edd_get_discount_by_code( $discount_data['code'] );

		if ( false !== $discount ) {
			throw new EDD_Jilt_Plugin_Exception( "Discount code '{$discount_data['code']}' already exists", 422 );
		}
	}

}
