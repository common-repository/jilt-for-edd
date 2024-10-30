<?php

/**
 * Jilt for Easy Digital Downloads
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
 * @package   EDD-Jilt
 * @author    Jilt
 * @copyright Copyright (c) 2015-2020, SkyVerge, Inc.
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

use Jilt\EDD\Widget;

/**
 * The main class for EDD Jilt. This handles all non-integration tasks, like
 * loading translations, handling plugin activation/deactivation & install/upgrades.
 *
 * @since 1.0.0
 */
class EDD_Jilt extends SV_EDD_Plugin {


	/** plugin version number */
	const VERSION = '1.5.4';

	/** plugin id */
	const PLUGIN_ID = 'jilt';

	/** the app hostname */
	const HOSTNAME = 'jilt.com';

	/** @var string plugin filename */
	protected $plugin_file;

	/** @var \EDD_Jilt_Admin instance */
	protected $admin;

	/** @var \EDD_Jilt_AJAX instance */
	private $ajax;

	/** @var \EDD_Jilt_Integration instance */
	protected $integration;

	/** @var \EDD_Jilt_Cron instance */
	protected $cron;

	/** @var \EDD_Jilt_Customer_Handler instance */
	protected $customer_handler;

	/** @var \EDD_Jilt_Cart_Handler instance */
	protected $cart_handler;

	/** @var \EDD_Jilt_Checkout_Handler instance */
	protected $checkout_handler;

	/** @var \EDD_Jilt_Frontend instance */
	protected $frontend;

	/** @var  \EDD_Jilt_Recovery_Handler instance */
	protected $recovery_handler;

	/** @var \EDD_Jilt_EDD_API_Handler instance */
	protected $edd_api_handler;

	/** @var \EDD_Jilt_Logger instance */
	protected $logger;

	/** @var \EDD_Jilt_Integrations instance */
	protected $integrations;


	/**
	 * Sets up the plugin.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {

		parent::__construct(
			self::PLUGIN_ID,
			self::VERSION
		);

		// required files
		$this->includes();

		// handle jilt connection requests
		add_action( 'edd_jilt-connect', [ $this, 'route_connection_request' ] );

		// GDPR handling: log and send request to Jilt to unschedule emails
		add_filter( 'wp_privacy_personal_data_erasers', [ $this, 'register_personal_data_eraser' ] );

		// load our widgets
		add_action( 'widgets_init', [ $this, 'init_widget' ] );
	}


	/**
	 * Includes required files and load core class instances as needed.
	 *
	 * @since 1.0.0
	 */
	public function includes() {

		// helper
		require_once( $this->get_plugin_path() . '/includes/Helper.php' );

		// settings
		require_once( $this->get_plugin_path() . '/includes/class-edd-jilt-settings.php' );

		// debug log
		require_once( $this->get_plugin_path() . '/includes/class-edd-jilt-logger.php' );
		require_once( $this->get_plugin_path() . '/includes/class-sv-edd-plugin-exception.php' );

		// API
		require_once( $this->get_plugin_path() . '/includes/api/class-edd-jilt-api-exception.php' );
		require_once( $this->get_plugin_path() . '/includes/api/class-edd-jilt-api-base.php' );
		require_once( $this->get_plugin_path() . '/includes/api/class-edd-jilt-api.php' );
		require_once( $this->get_plugin_path() . '/includes/api/class-edd-jilt-requests.php' );
		require_once( $this->get_plugin_path() . '/includes/api/class-edd-jilt-oauth-access-token.php' );

		// main integration
		require_once( $this->get_plugin_path() . '/includes/class-edd-jilt-session.php' );
		require_once( $this->get_plugin_path() . '/includes/class-edd-jilt-download.php' );
		require_once( $this->get_plugin_path() . '/includes/class-edd-jilt-payment.php' );
		require_once( $this->get_plugin_path() . '/includes/Contacts/EDD_Contact.php' );
		$this->integration = $this->load_class( '/includes/class-edd-jilt-integration.php', 'EDD_Jilt_Integration' );

		// this needs to happen after the integration class is instantiated
		$this->add_api_request_logging();

		// handlers
		$this->cart_handler     = $this->load_class( '/includes/handlers/class-edd-jilt-cart-handler.php', 'EDD_Jilt_Cart_Handler' );
		$this->checkout_handler = $this->load_class( '/includes/handlers/class-edd-jilt-checkout-handler.php', 'EDD_Jilt_Checkout_Handler' );
		$this->recovery_handler = $this->load_class( '/includes/handlers/class-edd-jilt-recovery-handler.php', 'EDD_Jilt_Recovery_Handler' );
		$this->customer_handler = $this->load_class( '/includes/handlers/class-edd-jilt-customer-handler.php', 'EDD_Jilt_Customer_Handler' );
		$this->edd_api_handler  = $this->load_class( '/includes/handlers/class-edd-jilt-edd-api-handler.php', 'EDD_Jilt_EDD_API_Handler' );
		$this->frontend         = $this->load_class( '/includes/frontend/class-edd-jilt-frontend.php', 'EDD_Jilt_Frontend' );
		require_once( $this->get_plugin_path() . '/includes/handlers/class-edd-jilt-discount-handler.php' );

		// admin handler
		if ( defined( 'DOING_CRON' ) || is_admin() ) {

			$this->admin = $this->load_class( '/includes/admin/class-edd-jilt-admin.php', 'EDD_Jilt_Admin' );
			$this->cron  = $this->load_class( '/includes/class-edd-jilt-cron.php', 'EDD_Jilt_Cron' );
		}

		// ajax handler
		if ( defined( 'DOING_AJAX' ) ) {
			$this->ajax = $this->load_class( '/includes/class-edd-jilt-ajax.php', 'EDD_Jilt_AJAX' );
		}

		// 3rd party integrations
		require_once( $this->get_plugin_path() . '/includes/integrations/abstract-edd-jilt-integration-base.php' );
		require_once( $this->get_plugin_path() . '/includes/integrations/class-edd-jilt-free-downloads-integration.php' );
		require_once( $this->get_plugin_path() . '/includes/integrations/class-edd-jilt-software-licensing-integration.php' );
		require_once( $this->get_plugin_path() . '/includes/integrations/class-edd-jilt-simple-shipping-integration.php' );
		$this->integrations = $this->load_class( '/includes/integrations/class-edd-jilt-integrations.php', 'EDD_Jilt_Integrations' );
	}


	/**
	 * Jilt makes requests back to the plugin to verify whether a payment is placed
	 * before sending recovery emails.
	 *
	 * @since 1.2.0
	 * @deprecated 1.4.0
	 */
	public function route_incoming_api_request() {

		// identify the responses as coming from the Jilt for EDD plugin
		@header( 'x-jilt-version: ' . $this->get_version() );

		status_header( 410 );
		wp_send_json( array( 'error' => array( 'message' => 'This connection method has been discontinued.' ) ) );
	}


	/**
	 * Routes connection requests to connection handler.
	 *
	 * @internal
	 *
	 * @since 1.3.0
	 *
	 * @param array $params request params
	 */
	public function route_connection_request( $params ) {

		if ( isset( $params['init'] ) || isset( $params['done'] ) ) {
			$this->load_class( '/includes/handlers/class-edd-jilt-connection-handler.php', 'EDD_Jilt_Connection_Handler' );
		}
	}


	/**
	 * Loads the Jilt subscribe widget.
	 *
	 * @since 1.5.0
	 */
	public function init_widget() {

		require_once( $this->get_plugin_path() . '/includes/Widget.php' );

		register_widget( Widget::class );
	}


	/** Admin methods ******************************************************/


	/**
	 * Render a notice for the user to read the docs before adding add-ons
	 *
	 * @see SV_EDD_Plugin::add_delayed_admin_notices()
	 *
	 * @since 1.1.0
	 */
	public function add_delayed_admin_notices() {

		// show any dependency notices
		parent::add_delayed_admin_notices();

		// warn users if random_bytes is unavailable
		try {
			random_bytes(1);
		} catch ( Exception $e ) {
			$this->get_admin_notice_handler()->add_admin_notice(
				__( 'Jilt works best with the PHP function random_bytes(), which is not available on your site. Please ask your hosting provider to assist with this', 'jilt-for-edd' ),
				'random-bytes-missing',
				array( 'notice_class' => 'error', 'always_show_on_settings' => true )
			);
		}

		$screen = get_current_screen();

		if ( $this->get_integration()->is_jilt_connected() && ! $this->get_edd_api_handler()->is_configured() ) {

			// display a persistent notice if the EDD REST API is unavailable or misconfigured
			$reason = $this->get_edd_api_handler()->get_api_configuration_error_long();

			$message = sprintf(
				/* translators: Placeholders: %1$s - connection error reason */
				__( 'Heads up! Jilt for EDD is not able to communicate with the EDD REST API: %1$s', 'jilt-for-edd' ),
				$reason
			);

			$this->get_admin_notice_handler()->add_admin_notice(
				$message,
				'edd-rest-api-unavailable',
				array(
					'always_show_on_settings' => true,
					'notice_class'            => 'notice-error',
				)
			);
		}

		// no messages to display if the plugin is already configured
		if ( $this->get_integration()->is_configured() ) {

			// ...unless the shop is still using secret key authentication
			if ( 'secret_key' === $this->get_integration()->get_auth_method() ) {

				if ( $this->is_plugin_settings() ) {
					$message = __( "Heads up! There's a faster and more secure way to connect your shop to Jilt. Click the Reconnect button below to upgrade now.", 'jilt-for-edd' );
				} else {
					// plugins page, link to settings
					/* translators: Placeholders: %1$s - <a> tag, %2$s - </a> tag */
					$message = sprintf( __( 'There\'s a faster and more secure way to connect your shop to Jilt. %1$sReconnect your shop%2$s now to upgrade, it only takes 30 seconds :)', 'jilt-for-edd' ), '<a href="' . esc_url( $this->get_settings_url() ) . '">', '</a>' );
				}

				$this->get_admin_notice_handler()->add_admin_notice(
					$message,
					'upgrade-auth-method-notice',
					array( 'always_show_on_settings' => true, 'notice_class' => 'notice-warning' )
				);
			}

			return;
		}

		// plugins page, link to settings
		if ( null !== $screen && 'plugins' === $screen->id ) {
			/* translators: Placeholders: %1$s - <a> tag, %2$s - </a> tag */
			$message = sprintf( __( 'Thanks for installing Jilt! To get started, %1$sconnect your shop to Jilt%2$s :)', 'jilt-for-edd' ), '<a href="' . esc_url( $this->get_settings_url() ) . '">', '</a>' );
		} elseif ( $this->is_plugin_settings() ) {
			$message = __( 'Thanks for installing Jilt! To get started, connect your shop to Jilt below :)', 'jilt-for-edd' );
		}

		// only render on plugins or settings screen
		if ( ! empty( $message ) ) {
			$this->get_admin_notice_handler()->add_admin_notice(
				$message,
				'get-started-notice',
				array( 'always_show_on_settings' => false )
			);
		}
	}


	/** Accessors  *******************************************************/


	/**
	 * Returns the integration class instance.
	 *
	 * @since 1.0.0
	 *
	 * @return \EDD_Jilt_Integration
	 */
	public function get_integration() {
		return $this->integration;
	}


	/**
	 * Returns the general admin handler instance.
	 *
	 * @since 1.4.0
	 *
	 * @return \EDD_Jilt_Admin
	 */
	public function get_admin() {
		return $this->admin;
	}


	/**
	 * Returns the AJAX handler.
	 *
	 * @since 1.4.0
	 *
	 * @return \EDD_Jilt_AJAX
	 */
	public function get_ajax() {
		return $this->ajax;
	}


	/**
	 * Returns the frontend instance.
	 *
	 * @since 1.2.0
	 *
	 * @return \EDD_Jilt_Frontend
	 */
	public function get_frontend() {
		return $this->frontend;
	}


	/**
	 * Returns the checkout handler instance.
	 *
	 * @since 1.0.0
	 *
	 * @return \EDD_Jilt_Checkout_Handler
	 */
	public function get_checkout_handler() {
		return $this->checkout_handler;
	}


	/**
	 * Returns the cart handler instance.
	 *
	 * @since 1.2.0
	 *
	 * @return \EDD_Jilt_Cart_Handler
	 */
	public function get_cart_handler() {
		return $this->cart_handler;
	}


	/**
	 * Returns the EDD API handler.
	 *
	 * @since 1.4.0
	 *
	 * @return \EDD_Jilt_EDD_API_Handler
	 */
	public function get_edd_api_handler() {
		return $this->edd_api_handler;
	}


	/**
	 * Returns the customer handler instance.
	 *
	 * @since 1.3.3
	 *
	 * @return \EDD_Jilt_Customer_Handler
	 */
	public function get_customer_handler() {
		return $this->customer_handler;
	}


	/**
	 * Returns the cron class instance.
	 *
	 * @since 1.5.0
	 *
	 * @return \EDD_Jilt_Cron
	 */
	public function get_cron_instance() {
		return $this->cron;
	}


	/**
	 * Returns the main EDD Jilt Plugin instance, ensures only one instance is/can be loaded
	 *
	 * @see edd_jilt()
	 *
	 * @since 1.0.0
	 *
	 * @return EDD_Jilt
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}


	/** Helper methods ******************************************************/


	/**
	 * When the Jilt API indicates a customer's Jilt account has been cancelled,
	 * deactivate the plugin.
	 *
	 * @since 1.0.0
	 */
	public function handle_account_cancellation() {

		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		deactivate_plugins( $this->get_file() );
	}


	/**
	 * Returns the plugin name, localized.
	 *
	 * @see SV_EDD_Plugin::get_plugin_name()
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_plugin_name() {
		return __( 'Jilt for Easy Digital Downloads', 'jilt-for-edd' );
	}


	/**
	 * Returns __FILE__
	 *
	 * @see SV_EDD_Plugin::get_file()
	 *
	 * @since 1.0.0
	 *
	 * @return string the full path and filename of the plugin file
	 */
	public function get_file() {

		// filter this so that in development the plugin file within the wp
		// install can be specified, which allows the storefront javascript to
		// be correctly included. this because PHP resolves symlinked __FILE__
		return apply_filters( 'edd_jilt_get_plugin_file', __FILE__ );
	}


	/**
	 * Returns true if on the plugin settings page.
	 *
	 * @see \SV_EDD_Plugin::is_plugin_settings()
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public function is_plugin_settings() {

		return isset( $_GET['page'] ) && 'edd-jilt' === $_GET['page'];
	}


	/**
	 * Returns the plugin configuration URL.
	 *
	 * @see \SV_EDD_Plugin::get_settings_link()
	 *
	 * @since 1.1.0
	 *
	 * @param string $plugin_id optional plugin identifier.
	 * @return string plugin settings URL
	 */
	public function get_settings_url( $plugin_id = null ) {
		return admin_url( 'admin.php?page=edd-jilt' );
	}


	/**
	 * Returns the wordpress.org plugin page URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string wordpress.org product page url
	 */
	public function get_product_page_url() {

		return 'https://wordpress.org/plugins/jilt-for-edd/';
	}


	/**
	 * Returns the plugin documentation url.
	 *
	 * @since 1.0.0
	 *
	 * @return string documentation URL
	 */
	public function get_documentation_url() {

		return 'http://help.jilt.com/';
	}


	/**
	 * Returns the Jilt hostname.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function get_hostname() {

		/**
		 * Filter the Jilt hostname, used in development for changing to
		 * dev/staging instances
		 *
		 * @since 1.1.0
		 * @param string $hostname
		 * @param \EDD_Jilt $this instance
		 */
		return apply_filters( 'edd_jilt_hostname', self::HOSTNAME, $this );
	}


	/**
	 * Returns the app hostname.
	 *
	 * @since 1.1.0
	 *
	 * @return string app hostname, defaults to app.jilt.com
	 */
	public function get_app_hostname() {

		/**
		 * Filter the Jilt app hostname, used in development for changing to
		 * dev/staging instances
		 *
		 * @since 1.4.0
		 * @param string $hostname
		 * @param \EDD_Jilt $this instance
		 */
		return apply_filters( 'edd_jilt_app_hostname', sprintf( 'app.%s', $this->get_hostname() ), $this );
	}


	/**
	 * Returns the api hostname.
	 *
	 * @since 1.4.4
	 *
	 * @return string api hostname, defaults to api.jilt.com
	 */
	public function get_api_hostname() {

		/**
		 * Filters the API Hostname.
		 *
		 * @since 1.4.4
		 *
		 * @param string api hostname
		 * @param \EDD_Jilt plugin instance
		 */
		return apply_filters( 'edd_jilt_api_hostname', sprintf( 'api.%s', $this->get_hostname() ), $this );
	}


	/**
	 * Returns an app endpoint with an optionally provided path
	 *
	 * @since 1.3.0
	 *
	 * @param string $path
	 * @return string
	 */
	public function get_app_endpoint( $path = '' ) {

		// returns URL like https://app.jilt.com/$path
		return sprintf( 'https://%1$s/%2$s', $this->get_app_hostname(), $path );
	}


	/**
	 * Returns the connection initialization URL.
	 *
	 * @since 1.3.0
	 *
	 * @return string url
	 */
	public function get_connect_url() {

		return add_query_arg( array(
			'edd_action' => 'jilt-connect',
			'init'       => 1,
			'nonce'      => wp_create_nonce( 'edd-jilt-connect-init' )
		), get_home_url() );
	}


	/**
	 * Returns the connection callback URL.
	 *
	 * @since 1.3.0
	 *
	 * @return string url
	 */
	public function get_callback_url() {

		return add_query_arg( array(
			'edd_action' => 'jilt-connect',
			'done'       => 1
		), get_home_url() );
	}


	/**
	 * Returns the app Sign In setup URL.
	 *
	 * @since 1.3.0
	 *
	 * @return string
	 */
	public function get_sign_in_url() {

		return $this->get_app_endpoint( 'signin' );
	}


	/**
	 * Returns the current shop domain, including the path if this is a
	 * Multisite directory install.
	 *
	 * @since 1.1.0
	 *
	 * @return string the current shop domain. e.g. 'example.com' or 'example.com/fr'
	 */
	public function get_shop_domain() {

		$domain = parse_url( get_home_url(), PHP_URL_HOST );
		$path   = parse_url( get_home_url(), PHP_URL_PATH );

		if ( $path && 'yes' !== get_option( 'edd_jilt_exclude_path_from_shop_domain' ) ) {
			$domain .= $path;
		}

		return $domain;
	}


	/**
	 * Is this a multisite directory install?
	 *
	 * @since 1.4.0
	 *
	 * @return boolean
	 */
	public function is_multisite_directory_install() {
		return defined( 'MULTISITE' ) && MULTISITE && ( ! defined( 'SUBDOMAIN_INSTALL' ) || ! SUBDOMAIN_INSTALL );
	}


	/**
	 * Returns the Jilt plugin installation ID, generating it if not present.
	 *
	 * The reasoning for generating the ID if not present is that without the
	 * installation id, connecting the plugin to Jilt would always fail (because the
	 * oauth client must be tied to installation id) without the user having any options
	 * to work around / fix the situation. This way, even if the installation id is
	 * accidentally wiped from the database, it's still possible to connect to Jilt.
	 *
	 * @since 1.3.0
	 *
	 * @return string 64-character installation id
	 */
	public function get_installation_id() {

		$installation_id = get_option( 'edd_jilt_installation_id' );

		if ( ! $installation_id ) {
			$installation_id = $this->generate_installation_id();
		}

		return $installation_id;
	}


	/**
	 * Returns the shop admin email, or current user's email if the former is not available.
	 *
	 * @since 1.3.0
	 *
	 * @return string email
	 */
	public function get_admin_email() {

		$email = get_option( 'admin_email' );

		if ( ! $email ) {
			$current_user = wp_get_current_user();
			$email        = $current_user->user_email;
		}

		return $email;
	}


	/**
	 * Returns the shop admin's first name, or the current user's if the former is not available.
	 *
	 * @since 1.3.0
	 *
	 * @return string the first name
	 */
	public function get_admin_first_name() {

		$user = get_user_by( 'email', $this->get_admin_email() );

		if ( ! $user ) {
			$user = wp_get_current_user();
		}

		return $user->user_firstname;
	}


	/**
	 * Returns the shop admin's last name, or the current user's if the former is not available.
	 *
	 * @since 1.3.0
	 *
	 * @return string the last name
	 */
	public function get_admin_last_name() {

		$user = get_user_by( 'email', $this->get_admin_email() );

		if ( ! $user ) {
			$user = wp_get_current_user();
		}

		return $user->user_lastname;
	}


	/**
	 * Returns the best available timestamp for when EDD was installed in
	 * this site.
	 *
	 * For this we use the create date of the special success page,
	 * if it exists
	 *
	 * @since 1.1.0
	 *
	 * @return string|null the timestamp at which EDD was installed in this shop, in iso8601 format
	 */
	public function get_edd_created_at() {

		$page_id = edd_get_option( 'success_page', 0 );

		$success_page = get_post( $page_id );

		if ( $success_page ) {
			return date( 'Y-m-d\TH:i:s\Z', strtotime( $success_page->post_date_gmt ) );
		}
	}


	/**
	 * Returns the Jilt support URL, with optional parameters.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Optional array of method arguments:
	 *   'domain' defaults to server domain
	 *   'form_type' defaults to 'support'
	 *   'platform' defaults to 'edd'
	 *   'message' defaults to false, if given this will be pre-populated in the support form message field
	 *   'first_name' defaults to current user first name
	 *   'last_name' defaults to current user last name
	 *   'email' defaults to current user email
	 *    Any parameter can be excluded from the returned URL by setting to false.
	 *    If $args itself is null, then no parameters will be added to the support URL
	 * @return string support URL
	 */
	public function get_support_url( $args = array() ) {

		if ( is_array( $args ) ) {

			$current_user = wp_get_current_user();

			$args = array_merge(
				array(
					'domain'     => $this->get_shop_domain(),
					'form_type'  => 'support',
					'platform'   => 'edd',
					'first_name' => $current_user->user_firstname,
					'last_name'  => $current_user->user_lastname,
					'email'      => $current_user->user_email,
				),
				$args
			);

			// strip out empty params, and urlencode the others
			foreach ( $args as $key => $value ) {
				if ( false === $value ) {
					unset( $args[ $key ] );
				} else {
					$args[ $key ] = urlencode( $value );
				}
			}
		}

		return 'https://jilt.com/contact/' . ( null !== $args && count( $args ) > 0 ? '?' . build_query( $args ) : '' );
	}


	/**
	 * Returns the currently released version of the plugin available on wordpress.org
	 *
	 * @since 1.1.0
	 *
	 * @return string the version, e.g. '1.0.0'
	 */
	public function get_latest_plugin_version() {

		if ( false === ( $version_data = get_transient( md5( $this->get_id() ) . '_version_data' ) ) ) {
			$changelog = wp_safe_remote_get( 'https://plugins.svn.wordpress.org/jilt-for-edd/trunk/readme.txt' );
			$cl_lines  = explode( '\n', wp_remote_retrieve_body( $changelog ) );

			if ( ! empty( $cl_lines ) ) {
				foreach ( $cl_lines as $line_num => $cl_line ) {
					if ( preg_match( '/= ([\d\-]{10}) - version ([\d.]+) =/', $cl_line, $matches ) ) {
						$version_data = array( 'date' => $matches[1] , 'version' => $matches[2] );
						set_transient( md5( $this->get_id() ) . '_version_data', $version_data, DAY_IN_SECONDS );
						break;
					}
				}
			}
		}

		if ( isset( $version_data['version'] ) ) {
			return $version_data['version'];
		}
	}


	/**
	 * Checks whether there a plugin update available on wordpress.org
	 *
	 * @since 1.1.0
	 *
	 * @return boolean true if there's an update available
	 */
	public function is_plugin_update_available() {

		$current_plugin_version = $this->get_latest_plugin_version();

		if ( ! $current_plugin_version ) {
			return false;
		}

		return version_compare( $current_plugin_version, $this->get_version(), '>' );
	}


	/** Privacy methods *****************************************************/


	/**
	 * Registers a GDPR compliant personal data eraser in WordPress for handling erasure requests.
	 *
	 * @internal
	 *
	 * @since 1.3.3
	 *
	 * @param array $erasers list of WordPress personal data erasers
	 * @return array
	 */
	public function register_personal_data_eraser( array $erasers ) {

		$erasers['jilt-for-edd'] = array(
			'eraser_friendly_name' => $this->get_plugin_name(),
			'callback'             => array( $this, 'handle_personal_data_erasure_request' ),
		);

		return $erasers;
	}


	/**
	 * Issues a request to Jilt to unschedule emails to be sent to the requester's email address.
	 *
	 * TODO this method needs an endpoint from Jilt API to issue unscheduling requests {FN 2018-05-23}
	 *
	 * @internal
	 *
	 * @since 1.3.3
	 *
	 * @param string $email_address address of the user that issued the erasure request
	 * @return array associative array with erasure response
	 */
	public function handle_personal_data_erasure_request( $email_address ) {

		$response = array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);

		if ( $this->get_integration()->is_jilt_connected() ) {

			// TODO: send a request to Jilt to programmatically unschedule emails targeting the requester's email address {FN 2018-05-23}
			// based on the response we can determine if the request went well or not and populate $response information
			// /* translators: Placeholder: %s - email address */
			// $response['messages'][]    = sprintf( __( 'A request was successfully sent to Jilt to unschedule all emails schedule for %s.', 'jilt-for-edd' ), $email_address );
			// $response['items_removed'] = true;

		} else {

			// TODO: if Jilt is disconnected, we should probably warn admin that the request could not be sent {FN 2018-05-23}
			// $response['messages'][]     = __( 'Could not establish a connection with Jilt to issue a personal data erasure request.', 'jilt-for-edd' );
			// $response['items_retained'] = true;
		}

		return $response;
	}


	/** Logger methods  **********************************************/


	/**
	 * Returns the logger instance.
	 *
	 * @since 1.1.0
	 *
	 * @return \EDD_Jilt_Logger
	 */
	public function get_logger() {

		$log_threshold = (int) EDD_Jilt_Settings::get_setting( 'log_threshold' );

		if ( null === $this->logger ) {
			$this->logger = new EDD_Jilt_Logger( $log_threshold, $this->get_id() );
		} else {
			if ( (int) $this->logger->get_threshold() !== $log_threshold ) {
				$this->logger->set_threshold( $log_threshold );
			}
		}

		return $this->logger;
	}


	/**
	 * Logs a statement at log level INFO.
	 *
	 * @see \EDD_Jilt_Logger::log_with_level()
	 *
	 * @since 1.3.0
	 *
	 * @param string $message error or message to save to log
	 */
	public function log( $message ) {

		// delegate to logger instance and consider this method to be log level INFO
		$this->get_logger()->info( $message );
	}


	/**
	 * Automatically logs API requests/responses when using EDD_Jilt_API_Base.
	 *
	 * @since 1.1.0
	 */
	public function add_api_request_logging() {

		// delegate to logger instance
		$action_name = 'edd_' . $this->get_id() . '_api_request_performed';

		if ( ! has_action( $action_name ) ) {
			add_action( $action_name, array( $this->get_logger(), 'log_api_request' ), 10, 2 );
		}
	}


	/** Lifecycle methods *****************************************************/


	/**
	 * Called when the plugin is activated. Note this is *not* triggered during
	 * auto-updates from WordPress.org, but the upgrade() method above handles that.
	 *
	 * @see SV_EDD_Plugin::activate()
	 *
	 * @since 1.0.0
	 */
	public function activate() {

		// must be loaded manually as the activation hook happens _before_ plugins_loaded
		$this->includes();

		// ensure the Storefront settings are synchronized with the latest set in Jilt app
		if ( version_compare( $this->get_version(), '1.4.3', '>=' ) ) {
			$this->get_integration()->sync_storefront_params();
		}
	}


	/**
	 * Perform any required tasks during deactivation.
	 *
	 * @see SV_EDD_Plugin::deactivate()
	 *
	 * @since 1.0.0
	 */
	public function deactivate() {

		if ( $this->get_integration()->is_linked() ) {
			$this->get_integration()->unlink_shop();
		}
	}


	/**
	 * Installs default settings.
	 *
	 * @see SV_EDD_Plugin::install()
	 *
	 * @since 1.3.0
	 */
	protected function install() {
		$this->generate_installation_id();
	}


	/**
	 * Handles upgrading the plugin to the current version.
	 *
	 * @see SV_EDD_Plugin::upgrade()
	 *
	 * @since 1.0.0
	 *
	 * @param string $installed_version currently installed version
	 */
	protected function upgrade( $installed_version ) {

		require_once( $this->get_plugin_path() . '/includes/class-edd-jilt-upgrades.php' );

		EDD_Jilt_Upgrades::upgrade( $installed_version );

		if ( $this->admin && is_admin() ) {
			$this->admin->update_shop();
		}
	}


	/**
	 * Generates a random plugin installation id.
	 *
	 * @since 1.3.0
	 *
	 * @return string the installation id
	 */
	public function generate_installation_id() {

		$installation_id = strtolower( $this->generate_random_token( 64, false ) );

		update_option( 'edd_jilt_installation_id', $installation_id );

		return $installation_id;
	}


	/**
	 * Generates a unique token at a specified length.
	 *
	 * Based on wp_generate_password() but doesn't filter the result to avoid
	 * plugin conflicts.
	 *
	 * @since 1.4.0
	 *
	 * @param int $length desired token length
	 * @param bool $special_chars whether to include special chars in the token
	 * @return string
	 */
	public function generate_random_token( $length = 12, $special_chars = true ) {

		$length = (int) $length;

		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

		if ( $special_chars ) {
			$chars .= '!@#$%^&*()';
		}

		$token = '';

		for ( $i = 0; $i < $length; $i++ ) {
			$token .= substr( $chars, wp_rand( 0, strlen( $chars ) - 1 ), 1 );
		}

		return $token;
	}


} // End EDD_Jilt
