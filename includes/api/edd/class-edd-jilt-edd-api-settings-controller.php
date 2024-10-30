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
 * EDD REST API Settings Controller
 *
 * Get and update Jilt settings
 *
 * @since 1.4.0
 */
class EDD_Jilt_EDD_API_Settings_Controller extends EDD_Jilt_EDD_API_Base {


	/** @var string route name */
	protected $route = 'jilt/settings';

	/** @var array accepted HTTP methods for this route */
	protected $accepted_methods = array( 'GET', 'DELETE', 'POST' );


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
			return $this->get_integration();
		}

		if ( 'POST' === $this->get_method() ) {
			return $this->update_integration();
		}

		if ( 'DELETE' === $this->get_method() ) {
			return $this->delete_integration();
		}

		return $this->get_error_response( 'Unable to process request', 400 );
	}


	/**
	 * Gets the Jilt integration settings.
	 *
	 * Routed from GET edd-api/v2/jilt/settings
	 *
	 * @since 1.4.0
	 *
	 * @return array
	 */
	protected function get_integration() {

		return array(
			// return the plugin settings and Storefront params combined, with the latter overriding any old plugin settings
			'settings' => array_merge( EDD_Jilt_Settings::get_safe_settings(), edd_jilt()->get_integration()->get_storefront_params() ),
		);
	}


	/**
	 * Updates the integration/edd settings.
	 *
	 * Routed from POST edd-api/v2/jilt/settings
	 *
	 * @since 1.4.0
	 *
	 * @return array associative array of updated integration settings
	 */
	protected function update_integration() {

		$integration      = edd_jilt()->get_integration();
		$updated_settings = array_merge( $integration->get_storefront_params(), $this->get_safe_request_params() );

		$integration->update_storefront_params( $updated_settings );

		return array(
			// return the plugin settings and updated Storefront params combined, with the latter overriding any old plugin settings
			'settings' => array_merge( EDD_Jilt_Settings::get_safe_settings( $updated_settings ), $updated_settings ),
		);
	}


	/**
	 * Disconnect the Jilt integration and clear connection data
	 *
	 * Routed from DELETE edd-api/v2/jilt/settings
	 *
	 * @since 1.4.0
	 */
	protected function delete_integration() {

		edd_jilt()->get_integration()->clear_connection_data();
	}


	/**
	 * Gets the request params after filtering them for safety.
	 *
	 * @since 1.4.0
	 *
	 * @return array the integration data
	 */
	protected function get_safe_request_params() {

		$integration_data = array();
		$request_params   = $this->get_params();
		$unsafe_keys      = array( 'key', 'token', 'secret' );

		foreach ( $request_params as $key => $value ) {

			$integration_data[ $key ] = $value;

			foreach ( $unsafe_keys as $unsafe_key ) {

				if ( false !== stripos( $key, $unsafe_key ) ) {

					unset( $integration_data[ $key ] );
				}
			}
		}

		return $integration_data;
	}


}
