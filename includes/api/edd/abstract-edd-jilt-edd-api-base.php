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
 * EDD REST API Base Class
 *
 * Provides base functions for custom EDD API endpoint controllers.
 *
 * @since 1.4.0
 */
abstract class EDD_Jilt_EDD_API_Base {


	/** @var array accepted HTTP methods for this route */
	protected $accepted_methods;

	/** @var bool true if this extends an existing EDD API endpoint, false if it's a new endpoint we are adding. */
	protected $extends_edd_endpoint = false;

	/** @var array the fields to include in the response data */
	protected $fields;

	/** @var string the current request method */
	protected $method;

	/** @var array the params sent with the incoming request */
	protected $params;

	/** @var string route name */
	protected $route;


	/**
	 * Sets up the controller class.
	 *
	 * @since 1.4.0
	 */
	public function __construct() {

		add_filter( 'edd_api_valid_query_modes', array( $this, 'register_route' ) );
		add_filter( 'edd_api_output_data',       array( $this, 'get_output_data' ), 10, 3 );
	}


	/**
	 * Adds the route for this controller to the list of valid endpoints in the EDD API.
	 *
	 * @internal
	 *
	 * @since 1.4.0
	 *
	 * @param array $valid_query_modes
	 * @return array
	 */
	public function register_route( $valid_query_modes ) {

		if ( $this->route && ! in_array( $this->route, $valid_query_modes, true ) ) {
			$valid_query_modes[] = $this->route;
		}

		return $valid_query_modes;
	}


	/**
	 * Gets the output data for a given route.
	 *
	 * @internal
	 *
	 * @since 1.4.0
	 *
	 * @param array $data
	 * @param string $route
	 * @param \EDD_API $api_instance
	 * @return array
	 */
	public function get_output_data( $data, $route, $api_instance ) {

		if ( $route === $this->route ) {

			$this->method = $_SERVER['REQUEST_METHOD'];
			$this->params = $this->get_params();
			$this->fields = $this->get_fields();

			try {

				$this->set_jilt_version_header();
				$this->validate_request_method();

				$data = $this->handle_request( $data, $route, $api_instance );

			} catch ( EDD_Jilt_Plugin_Exception $exception ) {
				$data = $this->get_error_response( $exception->getMessage(), $exception->getCode() );
			}
		}

		return $data;
	}


	/**
	 * Adds a response header indicating the current Jilt for EDD version.
	 *
	 * @since 1.4.0
	 */
	protected function set_jilt_version_header() {

		@header( 'x-jilt-version: ' . edd_jilt()->get_version() );
	}


	/**
	 * Validates that the current request method is one of the accepted methods for this endpoint.
	 *
	 * The stock EDD API does not distinguish between request methods, and normally returns its results
	 * no matter which HTTP verb is used. Therefore, any time we are extending an existing EDD API endpoint,
	 * this validation is skipped. For any custom endpoints we add to the API, we check for the correct method.
	 *
	 * @since 1.4.0
	 *
	 * @throws EDD_Jilt_Plugin_Exception
	 */
	protected function validate_request_method() {

		if ( ! $this->extends_edd_endpoint && ! in_array( $this->method, $this->accepted_methods, true ) ) {
			throw new EDD_Jilt_Plugin_Exception( 'Invalid Method', 405 );
		}
	}


	/**
	 * Gets the params sent in the incoming request in an array format.
	 *
	 * @since 1.4.0
	 *
	 * @return array
	 */
	protected function get_params() {

		if ( $this->params ) {
			return $this->params;
		}

		$unsupported_methods = array( 'DELETE', 'PATCH', 'PUT' );
		$params              = $_REQUEST;

		if ( in_array( $this->method, $unsupported_methods, true ) ) {

			$extra_params = array();
			parse_str( file_get_contents( 'php://input' ), $extra_params );

			$params = array_merge( $params, $extra_params );
		}

		$this->params = $params;

		return $this->params;
	}


	/**
	 * Gets the request params filtered by a list of allowed params.
	 *
	 * @since 1.4.0
	 *
	 * @param string[] $filter_params
	 * @return array
	 */
	protected function get_only_params( $filter_params = array() ) {

		return array_intersect_key( $this->get_params(), array_flip( $filter_params ) );
	}


	/**
	 * Gets a param from the incoming request.
	 *
	 * @since 1.4.0
	 *
	 * @param string $param name of the param
	 * @return string|null
	 */
	protected function get_request_param( $param ) {

		$params = $this->get_params();

		return isset( $params[ $param ] ) ? $params[ $param ] : null;
	}


	/**
	 * Gets the fields param from the request data, if it exists.
	 *
	 * The fields param is a comma-delimited string that determines which fields to include in the response.
	 * Each controller may have a different set of fields that can be passed here
	 *
	 * @since 1.4.0
	 *
	 * @return array
	 */
	protected function get_fields() {

		if ( $this->fields ) {
			return $this->fields;
		}

		$fields = array();
		$param  = $this->get_request_param( 'fields' );

		if ( $param && is_string( $param ) ) {

			if ( $param_array = explode(',', $param ) ) {

				$fields = array_map( 'trim', $param_array );
			}
		}

		$this->fields = $fields;

		return $this->fields;
	}


	/**
	 * Gets the request method.
	 *
	 * @since 1.4.0
	 *
	 * @return string
	 */
	protected function get_method() {

		$this->method = $this->method ? $this->method : $_SERVER['REQUEST_METHOD'];

		return $this->method;
	}


	/**
	 * Handles a request to this custom route.
	 *
	 * @since 1.4.0
	 *
	 * @param array $data
	 * @param string $route
	 * @param \EDD_API $api_instance
	 * @return array
	 */
	public function handle_request( $data, $route, $api_instance ) {
		// override in child class
		return $data;
	}


	/**
	 * Turns a message and status code into an error response formatted correctly for the API.
	 *
	 * @since 1.4.0
	 *
	 * @param string $message
	 * @param int $status_code
	 * @return array
	 */
	public function get_error_response( $message, $status_code = 400 ) {

		return array(
			'error'       => $message,
			'status_code' => $status_code,
		);
	}

}
