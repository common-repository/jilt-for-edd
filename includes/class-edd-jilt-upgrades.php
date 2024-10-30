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
 * @author    Jilt
 * @copyright Copyright (c) 2015-2020, SkyVerge, Inc.
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

/**
 * Handles plugin upgrades.
 *
 * @since 1.4.3
 */
class EDD_Jilt_Upgrades {


	/**
	 * Runs upgrade scripts.
	 *
	 * @since 1.4.3
	 *
	 * @param string $upgrade_from_version version upgrading from
	 */
	public static function upgrade( $upgrade_from_version ) {

		$upgrade_path = [
			'1.1.0' => 'update_to_1_1_0',
			'1.2.0' => 'update_to_1_2_0',
			'1.3.0' => 'update_to_1_3_0',
			'1.3.1' => 'update_to_1_3_1',
			'1.3.2' => 'update_to_1_3_2',
			'1.4.1' => 'update_to_1_4_1',
			'1.4.3' => 'update_to_1_4_3',
			'1.5.0' => 'update_to_1_5_0',
		];

		foreach ( $upgrade_path as $upgrade_to_version => $upgrade_script ) {

			if ( version_compare( $upgrade_from_version, $upgrade_to_version, '<' ) ) {

				self::$upgrade_script();

				edd_jilt()->log( sprintf( 'Updated to version %1$s from version %2$s', $upgrade_to_version, $upgrade_from_version ) );
			}
		}
	}


	/**
	 * Upgrades auth.
	 *
	 * @since 1.4.3
	 */
	private static function upgrade_auth() {

		$plugin      = edd_jilt();
		$integration = $plugin->get_integration();

		// try upgrading to oauth
		if ( $integration->get_linked_shop_id() && $integration->get_secret_key() ) {

			try {

				$response = $integration->get_api()->update_auth(
					$integration->get_linked_shop_id(), $plugin->get_shop_domain(),
					$plugin->get_callback_url(),
					$plugin->get_installation_id()
				);

				$token    = $response->token;
				$shop_id  = $token->shop_uuid;

				// don't store shop id twice
				unset( $token->shop_uuid );

				$integration->set_access_token( (array) $response->token );
				$integration->set_linked_shop_id( $shop_id );

				update_option( 'edd_jilt_client_id', $response->client_id );

				$integration->set_client_secret( $response->client_secret );

				// remove secret key
				$integration->set_secret_key( null );

				EDD_Jilt_Settings::delete_setting( 'secret_key' );

			} catch ( EDD_Jilt_API_Exception $exception ) {

				edd_jilt()->get_logger()->error( "Automatic upgrade to OAuth failed: {$exception->getMessage()}" );
			}
		}
	}


	/**
	 * Updates to version 1.1.0
	 *
	 * Renames 'jilt_enable_debug' setting to 'log_threshold' with an appropriate level.
	 *
	 * @since 1.4.3
	 */
	private static function update_to_1_1_0() {

		$integration = edd_jilt()->get_integration();

		// get existing settings
		$settings = EDD_Jilt_Settings::get_settings();

		$settings['log_threshold'] = isset( $settings['enable_debug'] ) && (bool) $settings['enable_debug'] ? EDD_Jilt_Logger::INFO : EDD_Jilt_Logger::OFF;

		unset( $settings['enable_debug'] );

		// update to new settings
		EDD_Jilt_Settings::update_settings( $settings );

		if ( $integration->is_linked() ) {

			$integration->set_linked_shop_domain();
			$integration->stash_secret_key( $integration->get_secret_key() );
		}
	}


	/**
	 * Updates to version 1.2.0
	 *
	 * Masks the shop domain.
	 *
	 * @since 1.4.3
	 */
	private static function update_to_1_2_0() {

		$integration = edd_jilt()->get_integration();

		if ( $integration->is_linked() ) {

			$shop_domain = str_replace( '.', '[.]', $integration->get_linked_shop_domain() );

			update_option( 'edd_jilt_shop_domain', $shop_domain );
		}
	}


	/**
	 * Updates to version 1.3.0
	 *
	 * @since 1.4.3
	 */
	private static function update_to_1_3_0() {

		// Generate the installation id - but only if it does not exist already.
		// This check should not be necessary, you may argue. However, there may be cases where the upgrade fails at a
		// point where the installation id is already generated and oauth client is created, but the credentials never
		// made it to EDD. This check acts as a safety net for such cases, so that we don't lock out users that
		// encountered and error during upgrade and the upgrade needs to re-run. Otherwise, the installation ID would be
		// re-generated on every upgrade attempt and the auth upgrade as well as manually reconnecting would always fail.
		if ( ! edd_jilt()->get_installation_id() ) {
			edd_jilt()->generate_installation_id();
		}

		self::upgrade_auth();
	}


	/**
	 * Updates to version 1.3.1
	 *
	 * @since 1.4.3
	 */
	private static function update_to_1_3_1() {

		if ( $client_secret = edd_jilt()->get_integration()->get_client_secret() ) {

			edd_jilt()->get_integration()->stash_secret_key( $client_secret );
		}
	}


	/**
	 * Updates to version 1.3.2
	 *
	 * @since 1.4.3
	 */
	private static function update_to_1_3_2() {

		$plugin      = edd_jilt();
		$integration = $plugin->get_integration();

		// missing token due to refresh bug
		if ( '' === get_option( 'edd_jilt_access_token' ) ) {

			$secret_key = null;

			// attempt to gracefully recover if we have the legacy secret key available
			foreach ( $integration->get_secret_key_stash() as $stashed_key ) {

				if ( strpos( $stashed_key, 'sk_' ) === 0 ) {

					$secret_key = $stashed_key;
				}
			}

			if ( $secret_key ) {

				try {

					// Use a reference to the API instance since we're setting the secret key as auth token, rather
					// than relying on get_api() which will force the API instance to use the integration auth token.
					$api = $integration->get_api();
					$api->set_auth_token( $secret_key );

					$response = $api->update_auth(
						$integration->get_linked_shop_id(),
						$plugin->get_shop_domain(),
						$plugin->get_callback_url(),
						$plugin->get_installation_id()
					);

					$token = $response->token;

					// don't store shop id twice
					unset( $token->shop_uuid );

					$integration->set_access_token( (array) $response->token );

				} catch ( EDD_Jilt_API_Exception $exception ) {

					edd_jilt()->get_logger()->error( "Missing OAuth token auto-recovery failed: {$exception->getMessage()}" );
				}
			}

			// we tried, but were unable to recover gracefully, prompt the user to reconnect
			if ( ! $integration->get_access_token() ) {

				$plugin->get_message_handler()->add_error( sprintf(
					/* translators: Placeholders: %1$s - <a> tag, %2$s </a> tag */
					__( 'Connection error with Jilt please %1$sreconnect your shop%2$s now.', 'jilt-for-edd' ),
					'<a href="' . esc_url( $plugin->get_settings_url() ) . '">', '</a>'
				) );
			}
		}
	}


	/**
	 * Updates to version 1.4.1
	 *
	 * @since 1.4.3
	 */
	private static function update_to_1_4_1() {

		$plugin      = edd_jilt();
		$integration = $plugin->get_integration();
		$shop_id     = $integration->get_linked_shop_id();

		if ( ! is_numeric( $shop_id ) ) {

			$integration->set_linked_shop_uuid( $shop_id );

			if ( $integration->is_jilt_connected() ) {

				try {

					$shop_data = $integration->get_api()->get_shop( $shop_id );

					if ( false === $shop_data ) {
						// false would indicate a successful token refresh, so retry the request now
						$shop_data = $integration->get_api()->get_shop( $shop_id );
					}

					if ( ! empty( $shop_data ) ) {
						$integration->set_linked_shop_id( $shop_data->id );
					}

				} catch ( EDD_Jilt_API_Exception $exception ) {

					edd_jilt()->get_logger()->error( "Could not set linked shop ID: {$exception->getMessage()}" );
				}
			}
		}

		// repair any invalid installation IDs
		if ( strlen( $plugin->get_installation_id() ) !== 64 ) {

			$plugin->generate_installation_id();

			// complete the upgrade to oauth process
			self::upgrade_auth();
		}

		try {

			// create a new EDD API key (if needed) if the shop is already connected to Jilt
			if ( $integration->is_jilt_connected() ) {

				$plugin->get_edd_api_handler()->configure_key();
			}

		} catch ( EDD_Jilt_Plugin_Exception $exception ) {

			$plugin->get_logger()->error( "Automatic upgrade to the EDD API failed: {$exception->getMessage()}" );
		}
	}


	/**
	 * Updates to version 1.4.3
	 *
	 * Migrates settings to Storefront.
	 * Legacy settings will be kept temporarily and then removed in a later version.
	 *
	 * @since 1.4.3
	 */
	private static function update_to_1_4_3() {

		$plugin            = edd_jilt();
		$integration       = $plugin->get_integration();

		$recover_held_orders           = EDD_Jilt_Settings::get_setting( 'recover_held_orders', 'no' );
		$capture_email_on_add_to_cart  = EDD_Jilt_Settings::get_setting( 'capture_email_on_add_to_cart', 'no' );
		$show_email_usage_notice       = EDD_Jilt_Settings::get_setting( 'show_email_usage_notice', 'no' );
		$show_marketing_consent_opt_in = EDD_Jilt_Settings::get_setting( 'show_marketing_consent_opt_in', 'no' );

		$storefront_params = array(
			'recover_held_orders'            => in_array( $recover_held_orders, array( 'yes', 1, '1' ), true )           ? 'yes' : 'no',
			'capture_email_on_add_to_cart'   => in_array( $capture_email_on_add_to_cart, array( 'yes', 1, '1' ), true )  ? 'yes' : 'no',
			'show_email_usage_notice'        => in_array( $show_email_usage_notice, array( 'yes', 1, '1' ), true )       ? 'yes' : 'no',
			'show_marketing_consent_opt_in'  => in_array( $show_marketing_consent_opt_in, array( 'yes', 1, '1' ), true ) ? 'yes' : 'no',
			'checkout_consent_prompt'        => (string) EDD_Jilt_Settings::get_setting( 'checkout_consent_prompt', '' ),
		);

		$integration->update_storefront_params( $storefront_params );

		try {
			$integration->get_api()->update_shop( $storefront_params );
		} catch ( EDD_Jilt_API_Exception $exception ) {
			$plugin->get_logger()->error( "Could not send site settings to Jilt: {$exception->getMessage()}" );
		}
	}


	/**
	 * Updates to version 1.5.0
	 *
	 * @since 1.5.0
	 */
	private static function update_to_1_5_0() {

		// all new installs will include the home_url *path* in addition to the home_url *domain* for the Jilt shop record.
		// e.g. shop-name.com/path (previously, 'path' would be truncated unless on a subdirectory multisite install)
		// For all existing installs being upgraded that are *not* subdirectory multisite installs, they should have an
		// exclusion added so that they do not experience any connection interruption.
		if ( ! edd_jilt()->is_multisite_directory_install() ) {

			update_option( 'edd_jilt_exclude_path_from_shop_domain', 'yes' );
		}
	}


}
