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
 * @package   EDD-Jilt/Integration
 * @author    Jilt
 * @copyright Copyright (c) 2015-2020, SkyVerge, Inc.
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

/**
 * Main integration class
 *
 * @since 1.0.0
 */
class EDD_Jilt_Integration {


	/** @var EDD_Jilt_API instance */
	protected $api;

	/** @var EDD_Jilt_OAuth_Access_Token the access token instance */
	private $access_token;

	/** @var string the API secret key */
	protected $secret_key;


	/**
	 * Initializes the integration class.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		if ( $this->is_linked() ) {

			// keep the financial status of the Jilt order in sync with the EDD payment status
			add_action( 'edd_update_payment_status', array( $this, 'payment_status_changed' ), 110, 3 );
		}
	}


	/**
	 * Return the URL to the specified page within the Jilt web app, useful
	 * for direct linking to internal pages, like campaigns.
	 *
	 * @since 1.0.0
	 * @param string $page page URL partial, e.g. 'dashboard' (default)
	 * @return string
	 */
	public function get_jilt_app_url( $page = 'dashboard' ) {
		return sprintf( $this->get_plugin()->get_app_endpoint() . 'shops/%1$d/%2$s', (int) $this->get_linked_shop_id(), rawurlencode( $page ) );
	}


	/**
	 * Gets the plugin settings.
	 *
	 * @since 1.1.0
	 * @deprecated since 1.4.0
	 *
	 * @return array associative array of plugin settings
	 */
	public function get_settings() {

		_deprecated_function( 'EDD_Jilt_Integration::get_settings()', '1.4.0', 'EDD_Jilt_Settings::get_settings()' );

		return EDD_Jilt_Settings::get_settings();
	}


	/**
	 * Updates the plugin settings.
	 *
	 * @since 1.1.0
	 * @deprecated since 1.4.0
	 *
	 * @param array $new_settings associative array of plugin settings to update
	 */
	public function update_settings( $new_settings ) {

		_deprecated_function( 'EDD_Jilt_Integration::update_settings()', '1.4.0', 'EDD_Jilt_Settings::update_settings()' );

		EDD_Jilt_Settings::update_settings( $new_settings );
	}


	/**
	 * Returns the option setting by key.
	 *
	 * @since 1.1.0
	 * @deprecated since 1.4.0
	 *
	 * @param string $key the option setting key
	 * @return false|mixed the setting value, or false
	 */
	public function get_option( $key ) {

		_deprecated_function( 'EDD_Jilt_Integration::get_option()', '1.4.0', 'EDD_Jilt_Settings::get_setting()' );

		return EDD_Jilt_Settings::get_setting( $key );
	}


	/**
	 * Clears out the the Jilt connection data.
	 *
	 * This includes: access token, public key, shop id, current shop domain, is disabled.
	 *
	 * @since 1.1.0
	 *
	 * @param bool $clear_client_credentials (optional) whether to clear the oauth client credentials, defaults to false
	 */
	public function clear_connection_data( $clear_client_credentials = true ) {

		// TODO: remove the two following lines when dropping support for secret key auth
		delete_option( 'edd_jilt_secret_key' );
		delete_option( 'edd_jilt_public_key' );

		delete_option( 'edd_jilt_shop_id' );
		delete_option( 'edd_jilt_shop_uuid' );
		delete_option( 'edd_jilt_shop_domain' );
		delete_option( 'edd_jilt_disabled' );

		$this->clear_access_token();

		if ( $clear_client_credentials ) {
			delete_option( 'edd_jilt_client_id' );
			delete_option( 'edd_jilt_client_secret' );
		}

		$this->delete_storefront_params();

		$this->api = null; // reset API instance
	}


	/** Getter methods ******************************************************/


	/**
	 * Returns the Jilt API instance.
	 *
	 * Since 1.3.0 this always returns an API instance, even if not authenticated.
	 *
	 * @since 1.0.0
	 *
	 * @return EDD_Jilt_API the API instance
	 */
	public function get_api() {

		// override the current auth token with a new one?
		if ( null !== $this->api && $this->api->get_auth_token() != $this->get_auth_token() ) {
			$this->api = null;
		}

		// prefer UUID when making API requests
		$shop_identifier = $this->get_linked_shop_uuid() ?: $this->get_linked_shop_id();

		if ( null === $this->api ) {
			$this->set_api(
				new EDD_Jilt_API(
					$shop_identifier,
					$this->get_auth_token()
				)
			);
		}

		return $this->api;
	}


	/**
	 * Checks the site URL to determine whether this is likely a duplicate site.
	 *
	 * The typical case is when a production site is copied to a staging server
	 * in which case all of the Jilt keys will be copied as well, and staging
	 * will happily make production API requests.
	 *
	 * The one false positive that can happen here is if the site legitimately
	 * changes domains. Not sure yet how you would handle this, might require
	 * some administrator intervention
	 *
	 * @since 1.1.0
	 *
	 * @return boolean true if this is likely a duplicate site
	 */
	public function is_duplicate_site() {
		$shop_domain = $this->get_linked_shop_domain();

		return $shop_domain && $shop_domain != $this->get_plugin()->get_shop_domain();
	}


	/**
	 * Returns the auth token for Jilt API - either OAuth access token or secret api key.
	 *
	 * @since 1.3.0
	 *
	 * @return EDD_Jilt_OAuth_Access_Token|string|null OAuth access token or secret api key, or null if not available
	 */
	public function get_auth_token() {
		return 'secret_key' === $this->get_auth_method() ? $this->get_secret_key() : $this->get_access_token();
	}


	/**
	 * Returns the configured secret key.
	 *
	 * @since 1.0.0
	 *
	 * @return string the secret key, if set, null otherwise
	 */
	public function get_secret_key() {

		if ( null === $this->secret_key ) {

			// retrieve from db if not already set
			$this->set_secret_key( EDD_Jilt_Settings::get_setting( 'secret_key' ) );
		}

		return $this->secret_key;
	}


	/**
	 * Sets the secret key.
	 *
	 * @since 1.1.0
	 *
	 * @param string $secret_key the secret key
	 */
	public function set_secret_key( $secret_key ) {

		$this->secret_key = $secret_key;
	}


	/**
	 * Checks whether the plugin configured.
	 *
	 * @since 1.0.0
	 *
	 * @return boolean true if the plugin is configured, false otherwise
	 */
	public function is_configured() {
		// if we have an authentication token (either the legacy secret key or an oauth access token), we're good to go
		return (bool) $this->get_auth_token();
	}


	/**
	 * Checks whether the plugin has connected to Jilt.
	 *
	 * @since 1.0.0
	 *
	 * @return bool true if the plugin has connected to Jilt
	 */
	public function has_connected() {

		if ( 'secret_key' === $this->get_auth_method() ) {

			// since the public key is returned by the REST API it serves as a
			// reasonable proxy for whether we've connected with the current secret key
			// note that we get the option directly
			return (bool) get_option( 'edd_jilt_public_key' );

		} else {

			// since the oauth access token is saved only after the site is authorized,
			// we can use it to determine whether the site is connected to Jilt
			return (bool) $this->get_auth_token();
		}
	}


	/**
	 *Checks whether this shop has linked itself to a Jilt account.
	 *
	 * @since 1.0.0
	 *
	 * @return boolean true if this shop is linked
	 */
	public function is_linked() {
		return (bool) $this->get_linked_shop_uuid() || (bool) $this->get_linked_shop_id();
	}


	/**
	 * Get the linked Jilt Shop identifier for this site, if any
	 *
	 * @since 1.0.0
	 *
	 * @return int Jilt shop identifier, or null
	 */
	public function get_linked_shop_id() {
		return get_option( 'edd_jilt_shop_id', null );
	}


	/**
	 * Persists the given linked Shop identifier.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id the linked Shop identifier
	 * @return int the provided $id
	 */
	public function set_linked_shop_id( $id ) {

		update_option( 'edd_jilt_shop_id', $id );

		if ( 'secret_key' === $this->get_auth_method() ) {

			$this->stash_secret_key( $this->get_secret_key() );

			// clear the API object so that the new shop id can be used for subsequent requests
			if ( null !== $this->api && $this->api->get_shop_id() !== $id ) {
				$this->api->set_shop_id( $id );
			}
		}

		return $id;
	}


	/**
	 * Returns the linked Jilt shop UUID for this site, if any.
	 *
	 * @since 1.4.0
	 *
	 * @return string|null Jilt shop UUID, or null
	 */
	public function get_linked_shop_uuid() {
		return get_option( 'edd_jilt_shop_uuid', null );
	}


	/**
	 * Persists the given linked shop UUID.
	 *
	 * @since 1.4.0
	 *
	 * @param string $uuid the linked shop UUID
	 * @return string the provided $uuid
	 */
	public function set_linked_shop_uuid( $uuid ) {

		update_option( 'edd_jilt_shop_uuid', $uuid );

		// clear the API object so that the new shop id can be used for subsequent requests
		if ( null !== $this->api && $this->api->get_shop_id() !== $uuid ) {
			$this->api->set_shop_id( $uuid );
		}

		return $uuid;
	}


	/**
	 * Puts the integration into disabled mode.
	 *
	 * The plugin will still respond to remote API requests, but it won't send requests over the REST API any longer.
	 *
	 * @since 1.1.0
	 */
	public function disable() {
		update_option( 'edd_jilt_disabled', 'yes' );
	}


	/**
	 * Re-enables the integration.
	 *
	 * @since 1.1.0
	 */
	public function enable() {
		update_option( 'edd_jilt_disabled', 'no' );
	}


	/**
	 * Checks whether the integration is disabled.
	 *
	 * When disabled, this indicates that although the plugin is
	 * installed, activated, and configured, it should not send any requests
	 * over the Jilt REST API.
	 *
	 * Since 1.3.0 this simply indicates that the site is detected to be duplicated (e.g.
	 * a production site that was migrated to staging).
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public function is_disabled() {
		return $this->is_duplicate_site();
	}


	/**
	 * Checks whether the shop is connected to Jilt and active or not.
	 *
	 * @since 1.3.0
	 *
	 * @return bool
	 */
	public function is_jilt_connected() {
		return $this->is_configured() && $this->is_linked() && ! $this->is_disabled();
	}


	/**
	 * Get the secret key stash
	 *
	 * @since 1.1.0
	 * @return array of secret key strings
	 */
	public function get_secret_key_stash() {
		$stash = get_option( 'edd_jilt_secret_key_stash', array() );

		if ( ! is_array( $stash ) ) {
			$stash = array();
		}

		return $stash;
	}


	/**
	 * Stashes the current secret key into the db.
	 *
	 * @since 1.1.0
	 *
	 * @param string $secret_key the secret key to stash
	 */
	public function stash_secret_key( $secret_key ) {

		// What is the purpose of all this you might ask? Well it provides us a
		// future means of validating/handling recovery URLs that were generated
		// with a prior secret key
		$stash = $this->get_secret_key_stash();

		if ( ! in_array( $secret_key, $stash ) ) {
			$stash[] = $secret_key;
		}

		update_option( 'edd_jilt_secret_key_stash', $stash );
	}


	/**
	 * Sets the access token.
	 *
	 * @since 1.3.0
	 *
	 * @param array $token oauth access token
	 */
	public function set_access_token( $token ) {

		update_option( 'edd_jilt_access_token', $token );

		$this->init_access_token( $token );
	}


	/**
	 * Returns the access token.
	 *
	 * @since 1.3.0
	 *
	 * @return EDD_Jilt_OAuth_Access_token|null jilt access token instance or null if not available
	 */
	public function get_access_token() {
		global $wpdb;

		if ( ! isset( $this->access_token ) ) {
			$this->init_access_token( maybe_unserialize( $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name='edd_jilt_access_token'" ) ) );
		}

		return $this->access_token;
	}


	/**
	 * Initializes the access token.
	 *
	 * @since 1.3.0
	 *
	 * @param array $token token args
	 */
	private function init_access_token( $token ) {
		$this->access_token = is_array( $token ) ? new EDD_Jilt_OAuth_Access_token( $token ) : null;
	}


	/**
	 * Clears the access token.
	 *
	 * @since 1.3.0
	 */
	public function clear_access_token() {

		delete_option( 'edd_jilt_access_token' );

		$this->access_token = null;
	}


	/**
	 * Sets the shop public key.
	 *
	 * @since 1.3.0
	 *
	 * @param string $key shop public key
	 */
	public function set_public_key( $key ) {
		update_option( 'edd_jilt_public_key', $key );
	}


	/**
	 * Returns the authentication method used for REST API calls.
	 *
	 * Secret key authentication is deprecated since 1.3.0 and is used only to provide
	 * backwards compatibility for shops that haven't upgraded to OAuth2 access token yet.
	 *
	 * @since 1.3.0
	 *
	 * @return string the authentication method, either 'secret_key' or 'access_token'
	 */
	public function get_auth_method() {
		return $this->get_secret_key() ? 'secret_key' : 'access_token';
	}



	/**
	 * Persists the given linked Shop identifier.
	 *
	 * @since 1.1.0
	 *
	 * @return String the shop domain that was set
	 */
	public function set_shop_domain() {

		_deprecated_function( 'EDD_Jilt_Integration::set_shop_domain()', '1.3.0', 'EDD_Jilt_Integration::set_linked_shop_domain' );

		return $this->set_linked_shop_domain();
	}


	/**
	 * Persists the linked shop domain for historical reference.
	 *
	 * @since 1.3.0
	 *
	 * @return string the shop domain that was set
	 */
	public function set_linked_shop_domain() {

		$shop_domain = $this->get_plugin()->get_shop_domain();

		// prevent migration plugins from overriding the domain by masking it, so we can
		// detect later if the site has been moved and act accordingly
		$shop_domain = str_replace( '.', '[.]', $shop_domain );

		update_option( 'edd_jilt_shop_domain', $shop_domain );
		return $shop_domain;
	}


	/**
	 * Returns the stored shop domain.
	 *
	 * @since 1.2.0
	 *
	 * @return string the shop domain that was stored when connecting to Jilt
	 */
	public function get_linked_shop_domain() {
		return str_replace( '[.]', '.', get_option( 'edd_jilt_shop_domain', '' ) );
	}


	/**
	 * Checks whether an email usage notice should be displayed to customers.
	 *
	 * @since 1.3.3
	 *
	 * @return bool
	 */
	public function show_email_usage_notice() {

		return 'yes' === $this->get_storefront_param( 'show_email_usage_notice', 'no' );
	}


	/**
	 * Determines if the post-checkout registration prompt is enabled.
	 *
	 * @since 1.2.0
	 *
	 * @return bool
	 */
	public function allow_post_checkout_registration() {

		// when updating settings, make sure we have the new value
		if ( isset( $_POST['edd_settings']['jilt_post_checkout_registration'] ) ) {
			$setting = '1' === $_POST['edd_settings']['jilt_post_checkout_registration'];
		} else {
			$setting = '1' === EDD_Jilt_Settings::get_setting( 'post_checkout_registration' );
		}

		return $setting;
	}


	/**
	 * Checks if the add-to-cart email prompt is enabled.
	 *
	 * @since 1.3.0
	 *
	 * @param string $context optional, if 'frontend' is specified will perform additional checks whether the notice can be displayed
	 * @return bool
	 */
	public function capture_email_on_add_to_cart( $context = 'option' ) {

		$capture = 'yes' === $this->get_storefront_param( 'capture_email_on_add_to_cart', 'no' );

		if ( $capture && 'frontend' === $context ) {

			$capture =    ! is_user_logged_in()
			           && ! EDD()->session->get( 'jilt_opt_out_add_to_cart_email_capture' )
			           && ! $this->has_customer_email();
		}

		return $capture;
	}


	/**
	 * Checks whether a checkbox to be ticked for consent should be displayed at checkout.
	 *
	 * @since 1.3.3
	 *
	 * @return bool
	 */
	public function ask_consent_at_checkout() {

		return 'yes' === $this->get_storefront_param( 'show_marketing_consent_opt_in', 'no' );
	}


	/**
	 * Returns the checkout consent prompt.
	 *
	 * @since 1.3.3
	 *
	 * @return string may include HTML
	 */
	public function get_checkout_consent_prompt() {

		return (string) $this->get_storefront_param( 'checkout_consent_prompt', '' );
	}


	/**
	 * Checks whether we have the customer's email or not.
	 *
	 * @since 1.3.3
	 *
	 * @return bool
	 */
	private function has_customer_email() {

		$customer_data = edd_jilt()->get_cart_handler()->get_customer_data();

		return ! empty( $customer_data['customer']['email'] );
	}


	/**
	 * Get base data for creating/updating a linked shop in Jilt
	 *
	 * @since 1.0.0
	 *
	 * @param bool $include_api_credentials whether or not to include EDD API credentials in this data
	 * @return array
	 */
	public function get_shop_data( $include_api_credentials = false ) {

		$theme = wp_get_theme();

		// note: owner email/name for now is included only in the initial shop link request
		$data = array(
			'domain'              => $this->get_plugin()->get_shop_domain(),
			'admin_url'           => admin_url(),
			'wordpress_site_url'  => get_site_url(), // including install directory, if any
			'profile_type'        => 'edd',
			'edd_version'         => EDD_VERSION,
			'wordpress_version'   => get_bloginfo( 'version' ),
			'integration_version' => $this->get_plugin()->get_version(),
			'php_version'         => PHP_VERSION,
			'name'                => html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'main_theme'          => $theme->name,
			'currency'            => $this->get_currency(),
			'province_code'       => edd_get_option( 'base_state', '' ),
			'country_code'        => edd_get_option( 'base_country', '' ),
			'primary_locale'      => strtolower( get_locale() ),
			'timezone'            => $this->get_store_timezone(),
			'created_at'          => $this->get_plugin()->get_edd_created_at(),
			'integration_enabled' => $this->is_linked() && ! $this->is_disabled(),
			'taxes_included'      => edd_prices_show_tax_on_checkout(),
		);

		// avoid sending false negatives
		if ( $this->is_ssl() ) {
			$data['supports_ssl'] = true;
		}

		$edd_api_handler = edd_jilt()->get_edd_api_handler();

		if ( $include_api_credentials && $edd_api_handler->key_exists() ) {

			$data['edd_api_public_key'] = $edd_api_handler->get_public_key();
			$data['edd_api_token']      = $edd_api_handler->get_token();
		}

		/**
		 * Filter shop data params used for updating the remote shop record via
		 * the API
		 *
		 * @since 1.2.0
		 *
		 * @param array $data the shop data
		 * @param EDD_Jilt_Integration $this
		 */
		$data = apply_filters( 'edd_jilt_shop_data', $data, $this );

		return $data;
	}


	/**
	 * Get the EDD configured shop currency
	 *
	 * @since 1.3.0
	 * @return string the 3-letter shop currency ISO code
	 */
	public function get_currency() {

		$currency = edd_get_currency();

		// EDD uses a non-iso code for the Iranian RIAL currency
		if ( 'RIAL' === $currency ) {
			$currency = 'IRR';
		}

		return $currency;
	}


	/** API methods ******************************************************/


	/**
	 * Link this shop to Jilt. The basic algorithm is to first attempt to
	 * create the shop over the Jilt API. If this request fails with a
	 * "Domain has already been taken" error, we try to find it over the Jilt
	 * API by domain, and update with the latest shop data.
	 *
	 * @since 1.0.0
	 * @return int the Jilt linked shop id
	 * @throws EDD_Jilt_API_Exception on network exception or API error
	 */
	public function link_shop() {

		if ( $this->is_configured() && ! $this->is_duplicate_site() ) {

			$args = $this->get_shop_data();

			// set shop owner/email
			$current_user       = wp_get_current_user();
			$args['shop_owner'] = $current_user->user_firstname . ' ' . $current_user->user_lastname;
			$args['email']      = $current_user->user_email;

			try {

				$shop = $this->get_api()->create_shop( $args );
				$this->set_linked_shop_domain();

				return $this->set_linked_shop_id( $shop->id );

			} catch ( EDD_Jilt_API_Exception $exception ) {

				if ( false !== strpos( $exception->getMessage(), 'Domain has already been taken' ) ) {

					// log the exception and continue attempting to recover
					$this->get_plugin()->get_logger()->error( "Error communicating with Jilt: {$exception->getMessage()}" );

				} else {

					// for any error other than "Domain has already been taken" rethrow so the calling code can handle
					throw $exception;
				}
			}

			// if we're down here, it means that our attempt to create the
			// shop failed with "domain has already been taken". Lets try to
			// recover gracefully by finding the shop over the API
			$shop = $this->get_api()->find_shop( array( 'domain' => $args['domain'] ) );

			// no shop found? it might even exist, but the current API user might not have access to it
			if ( ! $shop ) {
				return false;
			}

			// we successfully found our shop. attempt to update it and save the ID
			try {

				// update the linked shop record with the latest settings
				$this->get_api()->update_shop( $args, $shop->id );

			} catch ( EDD_Jilt_API_Exception $exception ) {

				// otherwise, log the exception
				$this->get_plugin()->get_logger()->error( "Error communicating with Jilt: {$exception->getMessage()}" );
			}

			$this->set_linked_shop_domain();

			return $this->set_linked_shop_id( $shop->id );
		}
	}


	/**
	 * Unlink shop from Jilt
	 *
	 * @since 1.1.0
	 */
	public function unlink_shop() {

		// there is no remote Jilt shop for a duplicate site
		if ( $this->is_duplicate_site() ) {
			return;
		}

		try {
			// if the plugin is not configured properly (expired token, unable to refresh, no legacy secret key) we cannot unlink the shop
			if ( $this->is_configured() ) {
				$this->get_api()->delete_shop();
			}
		} catch ( EDD_Jilt_API_Exception $exception ) {
			// quietly log any exception
			$this->get_plugin()->get_logger()->error( "Error communicating with Jilt when unlinking shop: {$exception->getMessage()}" );
		}
	}


	/**
	 * Revokes the integration plugin authorization.
	 *
	 * @since 1.3.0
	 */
	public function revoke_authorization() {

		try {
			// if the plugin is not configured properly (expired token, unable to refresh, no legacy secret key) we cannot revoke the token
			if ( $this->is_configured() ) {
				$this->get_api()->revoke_oauth_token( $this->get_client_id(), $this->get_client_secret() );
			}
		} catch ( EDD_Jilt_API_Exception $exception ) {
			// quietly log any exception
			$this->get_plugin()->get_logger()->error( "Error communicating with Jilt when revoking OAuth token: {$exception->getMessage()}" );
		}
	}


	/**
	 * Update the shop info in Jilt once per day, useful for keeping track
	 * of which WP/EDD versions are in use
	 *
	 * @since 1.0.0
	 *
	 * @param bool $include_api_credentials whether or not to include EDD API credentials in this update
	 *
	 * @throws EDD_Jilt_API_Exception
	 */
	public function update_shop( $include_api_credentials = false ) {

		if ( ! $this->is_jilt_connected() ) {
			return;
		}

		try {

			// update the linked shop record with the latest settings
			$this->get_api()->update_shop( $this->get_shop_data( $include_api_credentials ) );

		} catch ( EDD_Jilt_API_Exception $exception ) {

			// disconnect if the shop isn't found or is not authorized (e.g. remote private key was changed)
			if ( in_array( (int) $exception->getCode(), array( 401, 404 ), true ) ) {
				$this->clear_connection_data();
			}

			// log and rethrow the exception
			$this->get_plugin()->get_logger()->error( "Error communicating with Jilt: {$exception->getMessage()}" );

			if ( ! defined( 'DOING_CRON' ) ) {
				throw $exception;
			}
		}
	}


	/**
	 * Get and persist the public key for the current API user from the Jilt REST
	 * API
	 *
	 * @since 1.0.0
	 * @return string the public key
	 * @throws EDD_Jilt_API_Exception on network exception or API error
	 */
	public function refresh_public_key() {

		return $this->get_public_key( true );
	}


	/**
	 * Returns the configured public key.
	 *
	 * Passing true will refresh the key from the Jilt REST API.
	 *
	 * @since 1.0.0
	 *
	 * @param boolean $refresh true if the current API user public key should be fetched from the Jilt API
	 * @return string the public key, if set
	 * @throws EDD_Jilt_API_Exception on network exception or API error
	 */
	public function get_public_key( $refresh = false ) {

		$public_key = get_option( 'edd_jilt_public_key', null );

		if ( ( $refresh || ! $public_key ) && $this->is_configured() ) {
			update_option( 'edd_jilt_public_key', $this->get_api()->get_public_key() );
		}

		return $public_key;
	}


	/**
	 * Checks whether we have OAuth client credentials.
	 *
	 * @since 1.3.0
	 *
	 * @return bool true if we have credentials, false otherwise
	 */
	public function has_client_credentials() {
		return $this->get_client_id() && $this->get_client_secret();
	}


	/**
	 * Returns the OAuth client id.
	 *
	 * @since 1.3.0
	 *
	 * @return string
	 */
	public function get_client_id() {
		return get_option( 'edd_jilt_client_id' );
	}


	/**
	 * Returns the OAuth client secret.
	 *
	 * @since 1.3.0
	 *
	 * @return string
	 */
	public function get_client_secret() {
		return get_option( 'edd_jilt_client_secret' );
	}


	/**
	 * Sets the OAuth client secret.
	 *
	 * @since 1.3.0
	 *
	 * @param string $secret the new client secret
	 */
	public function set_client_secret( $secret ) {

		update_option( 'edd_jilt_client_secret', $secret );
		$this->stash_secret_key( $secret );
	}


	/** Other methods ******************************************************/


	/**
	 * Update related Jilt order when payment status changes
	 *
	 * Note: EDD uses the post status 'publish' to represent the payment status 'complete'
	 *
	 * @since 1.0.0
	 * @see https://docs.easydigitaldownloads.com/article/1180-what-do-the-different-payment-statuses-mean
	 * @param int $payment_id payment ID
	 * @param string $new_status one of: 'publish' (complete), 'pending', 'refunded',
	 *   'failed', 'abandoned', 'revoked', 'preapproved', 'cancelled', 'subscription'
	 * @param string $old_status, unused
	 */
	public function payment_status_changed( $payment_id, $new_status, $old_status ) {

		if ( ! $this->is_jilt_connected() ) {
			return;
		}

		$payment           = new EDD_Jilt_Payment( $payment_id );
		$jilt_cart_token   = $payment->get_jilt_cart_token();
		$jilt_placed_at    = $payment->get_jilt_placed_at();
		$jilt_cancelled_at = $payment->get_jilt_cancelled_at();

		// bail out if this order is not associated with a Jilt order
		if ( empty( $jilt_cart_token ) ) {
			return;
		}

		if ( ! $jilt_cancelled_at && 'cancelled' === $new_status ) {
			$jilt_cancelled_at = current_time( 'timestamp', true );
			$payment->update_meta( '_edd_jilt_cancelled_at', $jilt_cancelled_at );
			$jilt_cancelled_at = date( 'Y-m-d\TH:i:s\Z', $jilt_cancelled_at );
		}

		$params = array(
			'status'           => $payment->get_status(),
			'financial_status' => $payment->get_financial_status(),
		);

		if ( $jilt_placed_at ) {
			$params['placed_at'] = $jilt_placed_at;
		}
		if ( $jilt_cancelled_at ) {
			$params['cancelled_at'] = $jilt_cancelled_at;
		}

		// update Jilt order details
		try {

			$this->get_api()->update_order( $jilt_cart_token, $params );

		} catch ( EDD_Jilt_API_Exception $exception ) {

			$this->get_plugin()->get_logger()->error( "Error communicating with Jilt: {$exception->getMessage()}" );
		}
	}


	/** Storefront params methods ******************************************************/


	/**
	 * Gets a stored Storefront parameter.
	 *
	 * @since 1.4.3
	 *
	 * @param string $name parameter name
	 * @param string $default default value to return if the setting isn't set
	 * @return string|null
	 */
	public function get_storefront_param( $name, $default = null ) {

		if ( ! is_string( $default ) ) {
			$default = null;
		}

		$params = $this->get_storefront_params();

		return isset( $params[ $name ] ) ? $params[ $name ] : $default;
	}


	/**
	 * Gets the stored Storefront parameters.
	 *
	 * @since 1.4.3
	 *
	 * @return array
	 */
	public function get_storefront_params() {

		return (array) get_option( 'jilt_storefront_params', array() );
	}


	/**
	 * Updates the stored Storefront parameters.
	 *
	 * @since 1.4.3
	 *
	 * @param array $params updated Storefront parameters
	 * @return bool success
	 */
	public function update_storefront_params( array $params ) {

		return update_option( 'jilt_storefront_params', $params );
	}


	/**
	 * Updates an individual Storefront parameter.
	 *
	 * @since 1.4.3
	 *
	 * @param string $param parameter name
	 * @param mixed $value parameter value
	 * @return bool success
	 */
	public function update_storefront_param( $param, $value ) {

		$success = false;

		if ( is_string( $param ) ) {

			$params = $this->get_storefront_params();

			$params[ $param ] = $value;

			$success = $this->update_storefront_params( $params );
		}

		return $success;
	}


	/**
	 * Clears stored Storefront parameters.
	 *
	 * @since 1.4.3
	 *
	 * @return bool
	 */
	public function delete_storefront_params() {

		return $this->update_storefront_params( array() );
	}


	/**
	 * Synchronizes stored Storefront params with upstream Jilt App.
	 *
	 * Useful for example upon client plugin re-activation after settings may have changed in the Jilt app.
	 *
	 * @since 1.4.3
	 *
	 * @return bool success
	 */
	public function sync_storefront_params() {

		$success = false;

		if ( $this->is_jilt_connected() ) {

			try {

				// get the latest shop data
				$shop_data     = $this->get_api()->get_shop();
				// get the currently stored params, and the params we know to look for
				$stored_params = $this->get_storefront_params();
				$known_params  = array(
					'recover_held_orders',
					'capture_email_on_add_to_cart',
					'show_email_usage_notice',
					'show_marketing_consent_opt_in',
					'checkout_consent_prompt',
				);

				// locate each known param from the shop data and set it locally
				foreach ( $known_params as $param ) {

					if ( isset( $shop_data->$param ) ) {

						$value = $shop_data->$param;

						// convert booleans to yes/no
						if ( is_numeric( $value ) ) {
							$value = (bool) $value ? 'yes' : 'no';
						}

						$stored_params[ $param ] = $value;
					}
				}

				// store the updated params
				$success = $this->update_storefront_params( $stored_params );

			} catch ( EDD_Jilt_API_Exception $exception ) {

				$this->get_plugin()->get_logger()->error( "Error getting the Storefront params from Jilt: {$exception->getMessage()}" );
			}
		}

		return $success;
	}


	/** Helper methods ******************************************************/


	/**
	 * Return the timezone string for a store, copied from edd_timezone_string()
	 *
	 * @since 1.0.0
	 * @return string
	 */
	protected function get_store_timezone() {

		// if site timezone string exists, return it
		if ( $timezone = get_option( 'timezone_string' ) ) {
			return $timezone;
		}

		// get UTC offset, if it isn't set then return UTC
		if ( 0 === ( $utc_offset = get_option( 'gmt_offset', 0 ) ) ) {
			return 'UTC';
		}

		// adjust UTC offset from hours to seconds
		$utc_offset *= 3600;

		// attempt to guess the timezone string from the UTC offset
		$timezone = timezone_name_from_abbr( '', $utc_offset, 0 );

		// last try, guess timezone string manually
		if ( false === $timezone ) {
			$is_dst = date( 'I' );

			foreach ( timezone_abbreviations_list() as $abbr ) {
				foreach ( $abbr as $city ) {
					if ( $city['dst'] === $is_dst && $city['offset'] === $utc_offset ) {
						return $city['timezone_id'];
					}
				}
			}

			// fallback to UTC
			return 'UTC';
		}

		return $timezone;
	}


	/**
	 * Is the current request being performed over ssl?
	 *
	 * This implementation does not use the edd_site_is_https() approach of
	 * testing the "home" wp option for "https" because that has been found not
	 * to be a very reliable indicator of SSL support.
	 *
	 * @since 1.1.0
	 * @return boolean true if the site is configured to use HTTPS
	 */
	protected function is_ssl() {
		return is_ssl();
	}


	/**
	 * Set the API object
	 *
	 * @since 1.1.0
	 * @param EDD_Jilt_API $api the Jilt API object
	 */
	protected function set_api( $api ) {
		$this->api = $api;
	}


	/**
	 * Get the main plugin instance
	 *
	 * @since 1.1.0
	 * @return \EDD_Jilt
	 */
	protected function get_plugin() {
		return edd_jilt();
	}


}
