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
 * EDD API Handler Class
 *
 * Handles custom data and endpoints on the EDD API
 *
 * @since 1.4.0
 */
class EDD_Jilt_EDD_API_Handler {


	/** @var array containing the endpoint controller instances */
	protected $controllers;


	/**
	 * Sets up the EDD API handler class.
	 *
	 * @since 1.4.0
	 */
	public function __construct() {

		// register the custom EDD REST API routes
		add_action( 'init', array( $this, 'register_rest_routes' ) );

		// set error response status code
		add_action( 'edd_api_output_before', array( $this, 'set_edd_jilt_api_error_codes' ), 10, 3 );

		// register supported query vars
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
	}


	/**
	 * Registers the custom EDD REST API routes.
	 *
	 * @since 1.4.0
	 *
	 * @internal
	 */
	public function register_rest_routes() {

		require_once( edd_jilt()->get_plugin_path() . '/includes/api/edd/abstract-edd-jilt-edd-api-base.php' );
		require_once( edd_jilt()->get_plugin_path() . '/includes/api/edd/class-edd-jilt-edd-api-discounts-controller.php' );
		require_once( edd_jilt()->get_plugin_path() . '/includes/api/edd/class-edd-jilt-edd-api-sales-controller.php' );
		require_once( edd_jilt()->get_plugin_path() . '/includes/api/edd/class-edd-jilt-edd-api-sales-count-controller.php' );
		require_once( edd_jilt()->get_plugin_path() . '/includes/api/edd/class-edd-jilt-edd-api-customers-count-controller.php' );
		require_once( edd_jilt()->get_plugin_path() . '/includes/api/edd/class-edd-jilt-edd-api-products-count-controller.php' );
		require_once( edd_jilt()->get_plugin_path() . '/includes/api/edd/class-edd-jilt-edd-api-settings-controller.php' );
		require_once( edd_jilt()->get_plugin_path() . '/includes/api/edd/class-edd-jilt-edd-api-system-status-controller.php' );

		$controllers = array(
			'EDD_Jilt_EDD_API_Discounts_Controller',
			'EDD_Jilt_EDD_API_Sales_Controller',
			'EDD_Jilt_EDD_API_Sales_Count_Controller',
			'EDD_Jilt_EDD_API_Customers_Count_Controller',
			'EDD_Jilt_EDD_API_Products_Count_Controller',
			'EDD_Jilt_EDD_API_Settings_Controller',
			'EDD_Jilt_EDD_API_System_Status_Controller',
		);

		foreach( $controllers as $controller ) {
			$this->$controller = new $controller();
		}
	}


	/**
	 * Registers query vars for API access
	 *
	 * @since 1.4.0
	 *
	 * @internal
	 *
	 * @param array $vars
	 * @return array $vars
	 */
	public function query_vars( $vars ) {

		$vars[] = 'jilt_cart_token';

		return $vars;
	}


	/**
	 * Sets the correct status code whenever an error is returned by EDD Jilt API.
	 *
	 * @since 1.4.0
	 *
	 * @internal
	 *
	 * @param array $data
	 * @param \EDD_API $api_instance
	 * @param string $format the response format e.g. xml, json
	 */
	public function set_edd_jilt_api_error_codes( $data, $api_instance, $format ) {

		if ( isset( $data['error'] ) && isset( $data['status_code'] ) ) {
			status_header( (int) $data['status_code'] );
		}
	}


	/**
	 * Gets a brief text error message describing REST API configuration
	 * issues.
	 *
	 * Appropriate for help tips.
	 *
	 * @since 1.4.0
	 *
	 * @return string
	 */
	public function get_api_configuration_error_short() {

		$message = '';

		if ( ! $this->permalinks_configured() ) {

			$message = 'Pretty permalinks are disabled.';

		} elseif ( ! $this->key_exists() ) {

			$message = 'API key does not exist.';

		} elseif ( ! $this->key_owner_permissions_are_correct() ) {

			$message = 'API key owner permissions are not sufficient.';

		}

		return $message;
	}


	/**
	 * Gets a verbose HTML error message describing REST API configuration
	 * issues.
	 *
	 * Includes an anchor tag linking to a solution page, if relevant.
	 *
	 * @since 1.4.0
	 *
	 * @return string
	 */
	public function get_api_configuration_error_long() {

		$message = '';

		// build a URL that will create a key if needed
		$url = wp_nonce_url( add_query_arg( 'action', 'edd_jilt_generate_edd_api_key', admin_url( 'admin.php' ) ), 'edd_jilt_generate_edd_api_key' );

		if ( ! $this->permalinks_configured() ) {

			/* translators: Placeholders: %1$s - <a> tag, %2$s - </a> tag */
			$message = sprintf(
				__( 'Pretty permalinks are disabled. %1$sPlease update your permalink settings%2$s to enable API access for Jilt.', 'jilt-for-edd' ),
				'<a href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '">', '</a>'
			);

		} elseif ( ! $this->key_exists() ) {

			/* translators: Placeholders: %1$s - <a> tag, %2$s - </a> tag */
			$message = sprintf(
				__( 'API key does not exist. %1$sClick here%2$s to create an EDD API key for Jilt.', 'jilt-for-edd' ),
				'<a href="' . $url . '">', '</a>'
			);

		} elseif ( ! $this->key_owner_permissions_are_correct() ) {

			/* translators: Placeholders: %1$s - <a> tag, %2$s - </a> tag */
			$message = sprintf(
				__( 'API key owner permissions are not sufficient. %1$sClick here%2$s to correct the EDD API key for Jilt.', 'jilt-for-edd' ),
				'<a href="' . $url . '">', '</a>'
			);
		}

		return $message;
	}


	/** API Key Handling Methods **********************************************/


	/**
	 * Determines if the EDD REST API is configured and ready for Jilt by checking that:
	 *
	 * - permalinks settings are correct
	 * - an EDD API key exists
	 * - the key owner has permissions to manage EDD
	 *
	 * @since 1.5.0
	 *
	 * @return bool true if the EDD REST API is configured and available
	 */
	public function is_configured() {

		return $this->permalinks_configured()
			&& $this->has_valid_key();
	}


	/**
	 * Checks if permalinks are correctly configured to support the EDD REST API
	 *
	 * @since 1.4.0
	 *
	 * @return bool
	 */
	public function permalinks_configured() {

		return '' !== get_option( 'permalink_structure' );
	}


	/**
	 * Checks if an API key has been configured for Jilt.
	 *
	 * @since 1.4.0
	 *
	 * @return bool
	 */
	public function key_exists() {

		$api_user_id = $this->get_api_user_id();
		$public_key  = $this->get_public_key();
		$token       = $this->get_token();

		return $api_user_id && $public_key && $token;
	}


	/**
	 * Determines if the EDD REST API key exists and that the key owner has
	 * appropriate permissions
	 *
	 * @since 1.4.0
	 *
	 * @return bool
	 */
	public function has_valid_key() {

		return $this->key_owner_permissions_are_correct();
	}


	/**
	 * Verifies that the key owner has correct permissions: can manage edd
	 *
	 * @since 1.4.0
	 *
	 * @return boolean
	 */
	public function key_owner_permissions_are_correct() {

		if ( ! $this->key_exists() ) {
			return false;
		}

		return $this->is_eligible_user( $this->get_api_user_id() );
	}


	/**
	 * Configures the EDD API for use with Jilt.
	 *
	 * Selects an existing key to use, or generates a new key if one
	 * doesn't already exist.
	 *
	 * @since 1.4.0
	 *
	 * @param int user_id (optional) ID of the preferred user for key configuration
	 * @return bool whether configuration was successful
	 * @throws \EDD_Jilt_Plugin_Exception
	 */
	public function configure_key( $user_id = null ) {

		// sanity check
		if ( $this->has_valid_key() ) {
			return true;
		}

		// no user id passed, or ineligible user id passed, see if we can find one
		if ( ! $user_id || ! $this->is_eligible_user( $user_id ) ) {
			$user_id = $this->get_eligible_user_id();
		}

		if ( ! $this->is_eligible_user( $user_id ) ) {
			throw new EDD_Jilt_Plugin_Exception( 'No eligible users could be found' );
		}

		$user_has_key = $this->user_has_edd_api_key( $user_id ) ? true : $this->create_key( $user_id );

		if ( $user_has_key ) {

			$this->set_api_user_id( $user_id );
		}

		return $user_has_key;
	}


	/**
	 * Generates an EDD API key for Jilt to use.
	 *
	 * @since 1.4.0
	 *
	 * @param int $user_id WordPress user ID
	 * @return bool
	 * @throws \EDD_Jilt_Plugin_Exception
	 */
	public function create_key( $user_id = null ) {

		$user_id = $user_id ? (int) $user_id : $this->get_eligible_user_id();

		if ( ! $user_id ) {
			throw new EDD_Jilt_Plugin_Exception( 'No eligible users could be found' );
		}

		if ( $this->user_has_edd_api_key( $user_id ) ) {
			throw new EDD_Jilt_Plugin_Exception( "User {$user_id} already has an API key" );
		}

		if ( ! $this->is_eligible_user( $user_id ) ) {
			throw new EDD_Jilt_Plugin_Exception( "User {$user_id} does not have permission" );
		}

		$result = EDD()->api->generate_api_key( $user_id );

		if ( ! $result ) {
			throw new EDD_Jilt_Plugin_Exception( 'The key could not be created' );
		}

		$this->set_api_user_id( $user_id );

		// the EDD API requires an initial rewrite flush after being set up for the first
		// time in order to work properly. To stay safe, we can just flush whenever
		// we generate a new key, which should be relatively infrequent
		flush_rewrite_rules();

		return $result;
	}


	/**
	 * Gets the configured public key.
	 *
	 * @since 1.4.0
	 *
	 * @return string|false EDD API public key
	 */
	public function get_public_key() {

		$user_id = $this->get_api_user_id();
		return $user_id ? EDD()->api->get_user_public_key( $user_id ) : false;
	}


	/**
	 * Gets the configured EDD API token.
	 *
	 * @since 1.4.0
	 *
	 * @return string|false EDD API token
	 */
	public function get_token() {

		$user_id = $this->get_api_user_id();
		return $user_id ? EDD()->api->get_token( $user_id ) : false;
	}


	/**
	 * Gets the configured EDD API secret key.
	 *
	 * @since 1.4.0
	 *
	 * @return string|false EDD API secret key
	 */
	public function get_secret_key() {

		$user_id = $this->get_api_user_id();
		return $user_id ? EDD()->api->get_user_secret_key( $user_id ) : false;
	}


	/**
	 * Gets the WordPress user ID that was used to register the EDD API key.
	 *
	 * @since 1.4.0
	 *
	 * @return int
	 */
	public function get_api_user_id() {

		return (int) get_option( 'edd_jilt_edd_api_user_id' );
	}


	/**
	 * Sets the WordPress user ID that was used to register the EDD API key.
	 *
	 * @since 1.4.0
	 *
	 * @param int $user_id WordPress user ID
	 */
	public function set_api_user_id( $user_id ) {

		update_option( 'edd_jilt_edd_api_user_id', $user_id );
	}


	/**
	 * Checks if a given user already has an EDD API key or not.
	 *
	 * @since 1.4.0
	 *
	 * @param int $user_id the user ID to check
	 * @return bool
	 */
	protected function user_has_edd_api_key( $user_id ) {

		$public_key = EDD()->api->get_user_public_key( $user_id );
		$secret_key = EDD()->api->get_user_secret_key( $user_id );

		return ! empty( $public_key ) && ! empty( $secret_key );
	}


	/**
	 * Checks if a given user is eligible to use for the Jilt API connection.
	 *
	 * @since 1.4.0
	 *
	 * @param int $user_id optional user ID. Defaults to the EDD API user id setting
	 * @return bool
	 */
	protected function is_eligible_user( $user_id ) {

		return user_can( $user_id, 'manage_shop_settings' );
	}


	/**
	 * Gets a WordPress user ID that is eligible to generate EDD API keys.
	 *
	 * @since 1.4.0
	 *
	 * @return int an eligible user id or 0 if no eligible users can be found
	 */
	protected function get_eligible_user_id() {

		$user_id = get_current_user_id();

		if ( ! $this->is_eligible_user( $user_id ) ) {

			$user_id = 0;
			$eligible_roles = array( 'administrator', 'shop_manager' );

			foreach ( $eligible_roles as $role ) {

				$admin_ids = get_users( array(
					'role'   => $role,
					'fields' => 'ID',
				) );

				foreach( $admin_ids as $admin_id ) {

					// sanity check
					if ( $this->is_eligible_user( $admin_id ) ) {

						return $admin_id;
					}
				}
			}
		}

		return $user_id;
	}


}
