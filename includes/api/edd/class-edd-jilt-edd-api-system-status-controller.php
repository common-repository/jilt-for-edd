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
 * EDD REST API System Status Controller
 *
 * Get shop data and installed plugins
 *
 * @since 1.4.0
 */
class EDD_Jilt_EDD_API_System_Status_Controller extends EDD_Jilt_EDD_API_Base {


	/** @var string route name */
	protected $route = 'jilt/system-status';

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

			return array(
				'shop'           => $this->get_shop_data(),
				'active_plugins' => $this->get_active_plugins(),
			);
		}

		return $this->get_error_response( 'Unable to process request', 400 );
	}


	/**
	 * Get the shop data
	 *
	 * @since 1.4.0
	 *
	 * @return array
	 */
	private function get_shop_data() {

		$shop_data = edd_jilt()->get_integration()->get_shop_data();

		unset( $shop_data['profile_type'] );

		return $shop_data;
	}



	/**
	 * Get a list of plugins active on the site.
	 *
	 * Inspired by WC_REST_System_Status_Controller::get_active_plugins()
	 *
	 * @return array
	 */
	private function get_active_plugins() {

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/update.php';

		if ( ! function_exists( 'get_plugin_updates' ) ) {
			return array();
		}

		// Get both site plugins and network plugins.
		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			$network_activated_plugins = array_keys( get_site_option( 'active_sitewide_plugins', array() ) );
			$active_plugins            = array_merge( $active_plugins, $network_activated_plugins );
		}

		$active_plugins_data = array();
		$available_updates   = get_plugin_updates();

		foreach ( $active_plugins as $plugin ) {
			$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );

			// convert plugin data to json response format.
			$active_plugins_data[] = array(
				'plugin'            => $plugin,
				'name'              => $data['Name'],
				'version'           => $data['Version'],
				'url'               => $data['PluginURI'],
				'author_name'       => $data['AuthorName'],
				'author_url'        => esc_url_raw( $data['AuthorURI'] ),
				'network_activated' => $data['Network'],
			);
		}

		return $active_plugins_data;
	}


}
