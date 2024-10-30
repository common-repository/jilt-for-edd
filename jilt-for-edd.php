<?php
/**
 * Plugin Name: Jilt for Easy Digital Downloads
 * Plugin URI: https://wordpress.org/plugins/jilt-for-edd/
 * Description: Recover abandoned carts and boost revenue by 15% or more in under 15 minutes
 * Author: Jilt
 * Author URI: https://jilt.com
 * Version: 1.5.4
 * Text Domain: jilt-for-edd
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2015-2020 SkyVerge, Inc. (info@skyverge.com)
 *
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   EDD-Jilt
 * @author    Jilt
 * @copyright Copyright (c) 2015-2020, SkyVerge, Inc.
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

// EDD active check
if ( ! EDD_Jilt_Loader::is_edd_active() ) {
	return;
}

/**
 * The plugin loader class.
 *
 * @since 1.4.0
 */
class EDD_Jilt_Loader {


	/** minimum PHP version required by this plugin */
	const MINIMUM_PHP_VERSION = '5.6.0';

	/** minimum WordPress version required by this plugin */
	const MINIMUM_WP_VERSION = '4.4';

	/** minimum EDD version required by this plugin */
	const MINIMUM_EDD_VERSION = '2.7.7';

	/** the plugin name, for displaying notices */
	const PLUGIN_NAME = 'Jilt for Easy Digital Downloads';


	/** @var EDD_Jilt_Loader single instance of this class */
	protected static $instance;

	/** @var array the admin notices to add */
	protected $notices = array();


	/**
	 * Constructs the class.
	 *
	 * @since 1.4.0
	 */
	protected function __construct() {

		register_activation_hook( __FILE__, array( $this, 'activation_check' ) );

		add_action( 'admin_init', array( $this, 'check_environment' ) );
		add_action( 'admin_init', array( $this, 'add_plugin_notices' ) );

		add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );

		// if the environment check fails, initialize the plugin
		if ( $this->is_environment_compatible() ) {
			add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
			add_action( 'init', [ $this, 'shop_update_on_activation' ] );
		}
	}


	/**
	 * Cloning instances is forbidden due to singleton pattern.
	 *
	 * @since 1.4.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, sprintf( 'You cannot clone instances of %s.', get_class( $this ) ), '1.4.0' );
	}


	/**
	 * Unserializing instances is forbidden due to singleton pattern.
	 *
	 * @since 1.4.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, sprintf( 'You cannot unserialize instances of %s.', get_class( $this ) ), '1.4.0' );
	}


	/**
	 * Initializes the plugin.
	 *
	 * @since 1.4.0
	 */
	public function init_plugin() {

		if ( ! $this->plugins_compatible() ) {
			return;
		}

		 $this->load_framework();

		// load the main plugin class
		require_once( plugin_dir_path( __FILE__ ) . 'class-edd-jilt.php' );

		// include the functions file
		require_once( plugin_dir_path( __FILE__ ) . 'includes/Functions.php' );

		// fire it up!
		edd_jilt();
	}


	/**
	 * Performs a shop update when the plugin is known to have been recently activated.
	 *
	 * @since 1.5.0
	 */
	public function shop_update_on_activation() {
		global $wpdb;

		$recently_activated = $wpdb->get_var( "SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = 'edd_jilt_activated'");

		if ( 'yes' === $recently_activated ) {

			delete_option( 'edd_jilt_activated' );

			// update shop data in Jilt (especially plugin version), note this will
			// will be triggered when the plugin is downgraded to an older version
			edd_jilt()->get_admin()->update_shop( true );
		}
	}


	/**
	 * Loads the base framework classes.
	 *
	 * @since 1.4.0
	 */
	protected function load_framework() {

		if ( ! class_exists( 'SV_EDD_Plugin' ) ) {
			require_once( plugin_dir_path( __FILE__ ) . 'includes/class-sv-edd-plugin.php' );
		}
	}


	/**
	 * Checks the server environment and other factors and deactivates plugins as necessary.
	 *
	 * Based on http://wptavern.com/how-to-prevent-wordpress-plugins-from-activating-on-sites-with-incompatible-hosting-environments
	 *
	 * @since 1.4.0
	 */
	public function activation_check() {

		if ( ! $this->is_environment_compatible() ) {

			$this->deactivate_plugin();

			wp_die( self::PLUGIN_NAME . ' could not be activated. ' . $this->get_environment_message() );
		}

		// set an option so that we know Jilt has been activated and needs to
		// send a message to the REST API once the plugin loads
		update_option( 'edd_jilt_activated', 'yes' );
	}

	/**
	 * Checks the environment on loading WordPress, just in case the environment changes after activation.
	 *
	 * @since 1.4.0
	 */
	public function check_environment() {

		if ( ! $this->is_environment_compatible() && is_plugin_active( plugin_basename( __FILE__ ) ) ) {

			$this->deactivate_plugin();

			$this->add_admin_notice( 'bad_environment', 'error', self::PLUGIN_NAME . ' has been deactivated. ' . $this->get_environment_message() );
		}
	}


	/**
	 * Adds notices for out-of-date WordPress and/or EDD versions.
	 *
	 * @since 1.4.0
	 */
	public function add_plugin_notices() {

		if ( ! $this->is_wp_compatible() ) {

			$this->add_admin_notice( 'update_wordpress', 'error', sprintf(
				'%s requires WordPress version %s or higher. Please %supdate WordPress &raquo;%s',
				'<strong>' . self::PLUGIN_NAME . '</strong>',
				self::MINIMUM_WP_VERSION,
				'<a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">', '</a>'
			) );
		}

		if ( ! $this->is_edd_compatible() ) {

			$this->add_admin_notice( 'update_edd', 'error', sprintf(
				'%s requires Easy Digital Downloads version %s or higher. Please %supdate Easy Digital Downloads &raquo;%s',
				'<strong>' . self::PLUGIN_NAME . '</strong>',
				self::MINIMUM_EDD_VERSION,
				'<a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">', '</a>'
			) );
		}
	}


	/**
	 * Determines if the required plugins are compatible.
	 *
	 * @since 1.4.0
	 *
	 * @return bool
	 */
	protected function plugins_compatible() {

		return $this->is_wp_compatible() && $this->is_edd_compatible();
	}


	/**
	 * Determines if the WordPress version is compatible.
	 *
	 * @since 1.4.0
	 *
	 * @return bool
	 */
	protected function is_wp_compatible() {

		if ( ! self::MINIMUM_WP_VERSION ) {
			return true;
		}

		return version_compare( get_bloginfo( 'version' ), self::MINIMUM_WP_VERSION, '>=' );
	}


	/**
	 * Determines if the EDD version is compatible.
	 *
	 * @since 1.4.0
	 *
	 * @return bool
	 */
	protected function is_edd_compatible() {

		if ( ! self::MINIMUM_EDD_VERSION ) {
			return true;
		}

		return defined( 'EDD_VERSION' ) && version_compare( EDD_VERSION, self::MINIMUM_EDD_VERSION, '>=' );
	}


	/**
	 * Deactivates the plugin.
	 *
	 * @since 1.4.0
	 */
	protected function deactivate_plugin() {

		deactivate_plugins( plugin_basename( __FILE__ ) );

		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}
	}


	/**
	 * Adds an admin notice to be displayed.
	 *
	 * @since 1.4.0
	 */
	public function add_admin_notice( $slug, $class, $message ) {

		$this->notices[ $slug ] = array(
			'class'   => $class,
			'message' => $message
		);
	}


	/**
	 * Displays any admin notices added with \EDD_Jilt_Loader::add_admin_notice()
	 *
	 * @since 1.4.0
	 */
	public function admin_notices() {

		foreach ( (array) $this->notices as $notice_key => $notice ) {

			echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
			echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
			echo "</p></div>";
		}
	}


	/**
	 * Determines if the server environment is compatible with this plugin.
	 *
	 * Override this method to add checks for more than just the PHP version.
	 *
	 * @since 1.4.0
	 *
	 * @return bool
	 */
	protected function is_environment_compatible() {

		return version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '>=' );
	}


	/**
	 * Gets the message for display when the environment is incompatible with this plugin.
	 *
	 * @since 1.4.0
	 *
	 * @return string
	 */
	protected function get_environment_message() {

		$message = sprintf( 'The minimum PHP version required for this plugin is %1$s. You are running %2$s.', self::MINIMUM_PHP_VERSION, PHP_VERSION );

		return $message;
	}


	/**
	 * Checks if EDD is active.
	 *
	 * @since 1.4.0
	 *
	 * @return bool true if EDD is active, false otherwise
	 */
	public static function is_edd_active() {
		$active_plugins = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
		}
		return in_array( 'easy-digital-downloads/easy-digital-downloads.php', $active_plugins ) || array_key_exists( 'easy-digital-downloads/easy-digital-downloads.php', $active_plugins );
	}


	/**
	 * Gets the main \EDD_Jilt_Loader instance.
	 *
	 * Ensures only one instance can be loaded.
	 *
	 * @since 1.4.0
	 *
	 * @return \EDD_Jilt_Loader
	 */
	public static function instance() {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}


}

// fire it up!
EDD_Jilt_Loader::instance();
