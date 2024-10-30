<?php
/**
 * Jilt for EDD
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@jilt.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Jilt for EDD to newer
 * versions in the future. If you wish to customize Jilt for EDD for your
 * needs please refer to http://help.jilt.com/jilt-for-easy-digital-downloads
 *
 * @package   EDD-Jilt/Handlers
 * @author    Jilt
 * @copyright Copyright (c) 2015-2020, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

/**
 * Connection class.
 *
 * Handles Jilt connections.
 *
 * @since 1.3.0
 */
class EDD_Jilt_Connection_Handler {


	/**
	 * Initializes the class.
	 *
	 * @since 1.3.0
	 */
	public function __construct() {

		if ( isset( $_GET['init'] ) ) {
			$this->handle_connect();
		} elseif ( isset( $_GET['done'] ) ) {
			$this->handle_connect_callback();
		}
	}


	/**
	 * Initiates the auth flow to connect the plugin to Jilt.
	 *
	 * @since 1.3.0
	 */
	private function handle_connect() {

		// check nonce
		if ( ! wp_verify_nonce( $_GET['nonce'], 'edd-jilt-connect-init' ) ) {
			return;
		}

		// only shop managers can connect to jilt
		if ( ! current_user_can( 'shop_manager' ) && ! current_user_can( 'administrator' ) ) {
			return;
		}

		$integration = edd_jilt()->get_integration();

		// no client id or secret present, or duplicate site, try to request client credentials
		if ( ! $integration->has_client_credentials() || $integration->is_duplicate_site() ) {
			$this->request_client_credentials(); // if this fails, user is redirected back to admin with a notice
		}

		$client_id = $integration->get_client_id();
		$state     = wp_create_nonce( 'edd-jilt-connect' );

		$redirect_to = add_query_arg( array(
			'client_id'     => $client_id,
			'domain'        => urlencode( edd_jilt()->get_shop_domain() ),
			'email'         => urlencode( edd_jilt()->get_admin_email() ),
			'first_name'    => urlencode( edd_jilt()->get_admin_first_name() ),
			'last_name'     => urlencode( edd_jilt()->get_admin_last_name() ),
			'ssl'           => is_ssl(),
			'state'         => $state,
			'redirect_uri'  => rawurlencode( edd_jilt()->get_callback_url() ),
			'response_type' => 'code',
		), $integration->get_api()->get_connect_endpoint() );

		wp_redirect( $redirect_to );
		exit();
	}


	/**
	 * Requests installation-specific OAuth client credentials from Jilt.
	 *
	 * @since 1.3.0
	 */
	private function request_client_credentials() {

		$response = null;

		try {
			$response = edd_jilt()->get_integration()->get_api()->get_client_credentials( edd_jilt()->get_shop_domain(), edd_jilt()->get_callback_url(), edd_jilt()->get_installation_id() );

		} catch ( EDD_Jilt_API_Exception $e ) {

			$error = $e->getMessage();

			/* translators: Placeholders: %1$s - <strong> tag, %2$s - </strong> tag, %3$s - error message, %3$s - solution message */
			$notice = sprintf( __( '%1$sError communicating with Jilt%2$s: %3$s %4$s', 'jilt-for-edd' ),
				'<strong>',
				'</strong>',
				$error ? ( ': ' . $error . '.' ) : '', // add full stop
				 sprintf(__( 'Please %1$sget in touch with Jilt Support%2$s to resolve this issue.', 'jilt-for-edd' ),
					'<a target="_blank" href="' . esc_url( edd_jilt()->get_support_url( array( 'message' => $error ) ) ) . '">',
					'</a>'
				)
			);

			$message_handler = edd_jilt()->get_message_handler();
			$message_handler->add_error( $notice );

			wp_redirect( edd_jilt()->get_settings_url() );
			exit;
		}

		// TODO: consider adding dedicated setters for these {IT 2018-01-09}
		update_option( 'edd_jilt_client_id', $response->client_id );
		update_option( 'edd_jilt_client_secret', $response->client_secret );

		// stash the current client secret so that if a new client is created at some point,
		// we can still verify the recovery urls created by previous clients
		edd_jilt()->get_integration()->stash_secret_key( $response->client_secret );
	}


	/**
	 * Handles callbacks from Jilt connect requests.
	 *
	 * @since 1.3.0
	 */
	private function handle_connect_callback() {

		// verify state
		if ( empty( $_GET['state'] ) || ! wp_verify_nonce( $_GET['state'], 'edd-jilt-connect' ) ) {
			wp_die( 'Missing or invalid param: state' );
		}

		if ( empty( $_GET['code'] ) ) {
			wp_die( 'Missing or invalid param: code' );
		}

		$response = null;

		try {
			$integration = edd_jilt()->get_integration();
			$response    = $integration->get_api()->get_oauth_tokens( $_GET['code'], edd_jilt()->get_callback_url(), $integration->get_client_id(), $integration->get_client_secret() );
		} catch ( EDD_Jilt_API_Exception $e ) {

			$error = $e->getMessage();

			/* translators: Placeholders: %1$s - <strong> tag, %2$s - </strong> tag, %3$s - error message, %3$s - solution message */
			$notice = sprintf( __( '%1$sError communicating with Jilt%2$s: %3$s %4$s', 'jilt-for-edd' ),
				'<strong>',
				'</strong>',
				$error ? ( ': ' . $error . '.' ) : '', // add full stop
				sprintf(__( 'Please %1$sget in touch with Jilt Support%2$s to resolve this issue.', 'jilt-for-edd' ),
					'<a target="_blank" href="' . esc_url( edd_jilt()->get_support_url( array( 'message' => $error ) ) ) . '">',
					'</a>'
				)
			);

			$message_handler = edd_jilt()->get_message_handler();
			$message_handler->add_error( $notice );

			wp_redirect( edd_jilt()->get_settings_url() );
			exit;
		}

		$data      = $response;
		$shop_id   = $data->shop_id;
		$shop_uuid = $data->shop_uuid;

		unset( $data->shop_uuid, $data->shop_id ); // don't store shop identifiers twice

		$integration->set_access_token( (array) $data );
		$integration->set_linked_shop_id( $shop_id );
		$integration->set_linked_shop_uuid( $shop_uuid );
		$integration->set_linked_shop_domain(); // store a historical reference to the connected shop's domain

		// remove secret key, if it was used previously
		if ( $integration->get_secret_key() ) {

			EDD_Jilt_Settings::delete_setting( 'secret_key' );

			$integration->set_secret_key( null );
		}

		$redirect_url = edd_jilt()->get_settings_url();

		try {

			$integration->refresh_public_key();

			$edd_api_handler             = edd_jilt()->get_edd_api_handler();
			$edd_api_credentials_changed = false;

			if ( ! $edd_api_handler->key_exists() ) {

				$edd_api_credentials_changed = $edd_api_handler->configure_key();
			}

			// update the linked shop record with the latest settings
			$integration->update_shop( $edd_api_credentials_changed );

			// now that we're connected, dismiss the get started notice
			edd_jilt()->get_admin_notice_handler()->dismiss_notice( 'get-started-notice' );

			// if the shop is connected for the first time, show the welcome splash screen
			if ( ! get_option( 'edd_jilt_skip_welcome_screen', false ) ) {

				$redirect_url = add_query_arg( 'tab', 'welcome', $redirect_url );

				update_option( 'edd_jilt_skip_welcome_screen', true );

			} else {

				$message = sprintf( __( 'Congratulations! Your shop is now connected to Jilt. You\'re now ready to %1$ssetup your first campaign%2$s to start sending emails to your customers.', 'jilt-for-edd' ),
					'<a target="_blank" href="' . esc_url( edd_jilt()->get_app_endpoint() ) . '">',
					'</a>'
				);

				edd_jilt()->get_message_handler()->add_message( $message );
			}

		} catch ( EDD_Jilt_API_Exception $exception ) {

			// well, this sucks... let's add a message and redirect back to admin

			/* translators: Placeholders: %1$s - <strong> tag, %2$s - </strong> tag, %3$s - error message, %4$s - solution message */
			$message = sprintf( __( '%1$sError communicating with Jilt%2$s: %3$s %4$s', 'jilt-for-edd' ),
				'<strong>',
				'</strong>',
				': ' . $exception->getMessage() . '.', // add full stop
				sprintf(__( 'Please %1$sget in touch with Jilt Support%2$s to resolve this issue.', 'jilt-for-edd' ),
					'<a target="_blank" href="' . esc_url( edd_jilt()->get_support_url( array( 'message' => $exception->getMessage() ) ) ) . '">',
					'</a>'
				)
			);

			edd_jilt()->get_message_handler()->add_error( $message );

		} catch ( EDD_Jilt_Plugin_Exception $exception ) {

			$message = sprintf( __( '%1$sError creating EDD API credentials%2$s: %3$s %4$s', 'jilt-for-edd' ),
				'<strong>',
				'</strong>',
				': ' . $exception->getMessage() . '.', // add full stop
				sprintf(__( 'Please %1$sget in touch with Jilt Support%2$s to resolve this issue.', 'jilt-for-edd' ),
					'<a target="_blank" href="' . esc_url( edd_jilt()->get_support_url( array( 'message' => $exception->getMessage() ) ) ) . '">',
					'</a>'
				)
			);

			edd_jilt()->get_message_handler()->add_error( $message );
		}

		wp_redirect( $redirect_url );
		exit;
	}


}
