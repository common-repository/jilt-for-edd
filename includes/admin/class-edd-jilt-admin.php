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
 * @package   EDD-Jilt/Admin
 * @author    Jilt
 * @copyright Copyright (c) 2015-2020, SkyVerge, Inc.
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

/**
 * Admin class
 *
 * @since 1.0.0
 */
class EDD_Jilt_Admin {


	/** @var \EDD_Jilt_Admin_Settings instance */
	private $settings;

	/** @var \EDD_Jilt_Admin_Orders instance */
	private $orders;

	/** @var \EDD_Jilt_Admin_Tools instance */
	private $tools;


	/**
	 * Sets up the main admin handler.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		// set priority to 5 so we can hook into edd before it sets up on its `init` action
		add_action( 'init', array( $this, 'init' ), 5 );

		// load styles/scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'load_styles_scripts' ) );

		// add configure/connect and support links to plugin install screen when already installed
		add_filter( 'plugin_install_action_links', array( $this, 'add_action_links' ), 10, 2 );

		// report connection errors
		add_action( 'admin_notices', array( $this, 'show_connection_notices' ) );

		// whenever EDD settings are changed, update data in Jilt app
		add_action( 'update_option_edd_settings', array( $this, 'update_shop' ) );

		// show a log viewer when debug-level logging is enabled
		add_filter( 'edd_tools_tabs',                array( $this, 'maybe_add_log_viewer_tab' ) );
		add_action( 'edd_tools_tab_jilt_log_viewer', array( $this, 'render_log_viewer_tab' ) );

		add_filter( 'edd_load_admin_scripts', [ $this, 'load_edd_admin_scripts_for_widget' ], 10, 2 );

		// generate EDD API credentials
		add_action( 'admin_action_edd_jilt_generate_edd_api_key', array( $this, 'configure_edd_api' ) );

		// upon deactivation outputs a modal to prompt for customer feedback
		add_action( 'admin_footer', array( $this, 'output_deactivation_modal' ) );
	}


	/**
	 * Adds delayed hooks.
	 *
	 * @since 1.4.0
	 */
	public function init() {

		$this->settings = edd_jilt()->load_class( '/includes/admin/class-edd-jilt-admin-settings.php', 'EDD_Jilt_Admin_Settings' );
		$this->orders   = edd_jilt()->load_class( '/includes/admin/class-edd-jilt-admin-orders.php', 'EDD_Jilt_Admin_Orders' );
		$this->tools    = edd_jilt()->load_class( '/includes/admin/class-edd-jilt-admin-tools.php', 'EDD_Jilt_Admin_Tools' );

		if ( edd_jilt()->get_integration()->is_jilt_connected() ) {

			// update the shop info in Jilt once per day
			add_action( 'edd_daily_scheduled_events', array( edd_jilt()->get_integration(), 'update_shop' ) );
		}

		// handle deactivation modal
		add_action( 'wp_ajax_edd_jilt_deactivation_modal', [ $this, 'handle_deactivation_modal' ] );
	}


	/**
	 * Outputs the HTML of the modal displayed upon plugin deactivation.
	 *
	 * @internal
	 *
	 * @since 1.4.0
	 */
	public function output_deactivation_modal() {

		$screen = get_current_screen();

		if ( ! $screen
		     || ! in_array( $screen->id, array( 'plugins', 'plugins-network' ), true )
		     // check if user meta to not show the modal again is set
		     || ! empty( get_user_meta( get_current_user_id(), '_edd_jilt_do_not_show_deactivation_modal' ) ) ) {

			return;
		}

		?>
		<div id="edd-jilt-deactivation-modal">
			<div class="edd-jilt-deactivation-modal-content">
				<form id="edd-jilt-deactivation-form" method="post">

					<h1><?php esc_html_e( 'Heads up!', 'jilt-for-edd' ); ?></h1>

					<p>
						<?php
							/* translators: Placeholders: %1$s - <strong> tag, %2$s - </strong> tag */
							printf(
								esc_html__( 'Please note that %1$sdeactivating this plugin will not cancel your Jilt account%2$s. If you want to cancel your Jilt account, please do so from your Jilt billing area.', 'jilt-for-edd' ),
								'<strong>',
								'</strong>'
							);
						?>
					</p>

					<div>
						<p>
							<label for="edd-jilt-for-edd-deactivation-do-not-show">
								<input id="edd-jilt-for-edd-deactivation-do-not-show" type="checkbox" name="do_not_show" />
								<span><?php esc_html_e( 'Don\'t show me this again', 'jilt-for-edd' ); ?></span>
							</label>
						</p>
					</div>

					<div class="modal-footer">
						<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Deactivate plugin', 'jilt-for-edd' ); ?></button>
						<a href="https://app.jilt.com/account/edit" class="js-cancel button button-large" target="_blank"><?php esc_html_e( 'Go to Jilt account', 'jilt-for-edd' ); ?></a>
					</div>
				</form>
			</div>
		</div>
		<?php
	}


	/**
	 * Handles the deactivation modal, optionally setting user metadata to not show the modal again.
	 *
	 * @internal
	 *
	 * @since 1.7.6-dev.1
	 */
	public function handle_deactivation_modal() {

		if ( ! wp_verify_nonce( ! empty( $_POST['nonce'] ) ? $_POST['nonce'] : '', 'edd_jilt_deactivation_modal' ) ) {
			wp_die();
		}

		if ( ! empty( $_POST['do_not_show'] ) && 'true' === $_POST['do_not_show'] ) {

			$user_id = get_current_user_id();
			update_user_meta( $user_id, '_edd_jilt_do_not_show_deactivation_modal', true );
		}

		exit;
	}


	/**
	 * Returns the admin settings handler.
	 *
	 * @since 1.4.0
	 *
	 * @return null|\EDD_Jilt_Admin_Settings
	 */
	public function get_settings_instance() {
		return $this->settings;
	}


	/**
	 * Returns the admin orders handler.
	 *
	 * @since 1.4.0
	 *
	 * @return null|\EDD_Jilt_Admin_Orders
	 */
	public function get_orders_instance() {
		return $this->orders;
	}


	/**
	 * Returns the tools instance.
	 *
	 * @since 1.4.0
	 *
	 * @return null|\EDD_Jilt_Admin_Tools
	 */
	public function get_tools_instance() {
		return $this->tools;
	}


	/**
	 * Loads admin styles and scripts.
	 *
	 * @internal
	 *
	 * @since 1.4.0
	 */
	public function load_styles_scripts() {

		wp_enqueue_script( 'jilt-for-edd-admin', edd_jilt()->get_plugin_url() . '/assets/js/admin/edd-jilt-admin.min.js', array( 'jquery' ), EDD_Jilt::VERSION );

		wp_localize_script( 'jilt-for-edd-admin', 'edd_jilt', array(
			'ajax_url'                => admin_url('admin-ajax.php'),
			'show_deactivation_modal' => empty( get_user_meta( get_current_user_id(), '_edd_jilt_do_not_show_deactivation_modal' ) ),
			'deactivation_nonce'      => wp_create_nonce( 'edd_jilt_deactivation_modal' ),
			'admin_email'             => wp_get_current_user()->user_email,
			'edd_api_user_id'         => edd_jilt()->get_edd_api_handler()->get_api_user_id(),
			'shop_domain'             => edd_jilt()->get_shop_domain(),
			'i18n'                    => [
				'confirm_disconnect' => esc_html__( 'Are you sure you want to disconnect your shop from your Jilt account?', 'jilt-for-edd' ),
				'select_an_option'             => esc_html__( 'Please select an option', 'jilt-for-edd' ),
				'confirm_key_deletion'         => esc_html__( 'Are you sure you want to revoke this API key? Jilt for EDD will not work properly.', 'jilt-for-edd' ),
				'profile_key_deletion_warning' => esc_html__( 'Jilt for EDD will no longer work properly', 'jilt-for-edd' ),
			],
		) );

		wp_enqueue_style( 'jilt-for-edd-admin', edd_jilt()->get_plugin_url() . '/assets/css/admin/edd-jilt-admin.min.css', array(), EDD_Jilt::VERSION );
	}


	/**
	 * Adds action links in the plugin's row in the plugins page.
	 *
	 * @internal
	 *
	 * @since 1.4.0
	 *
	 * @param array $actions associative array of action names to anchor tags
	 * @param array $plugin plugin currently being listed
	 * @return array associative array of plugin action links
	 */
	public function add_action_links( $actions, $plugin ) {

		if ( 'jilt-for-edd' === $plugin['slug'] ) {

			$actions[] = sprintf( '<a href="%s">%s</a>', esc_url( edd_jilt()->get_support_url() ), esc_html_x( 'Support', 'noun', 'jilt-for-edd' ) );

			// connect url if not connected, settings url otherwise
			if ( ! edd_jilt()->get_integration()->is_jilt_connected() ) {
				$actions[] = sprintf( '<a href="%s" class="button button-primary edd-jilt-connect">%s</a>', edd_jilt()->get_connect_url(), esc_html__( 'Connect to Jilt', 'jilt-for-edd' ) );
			} elseif ( edd_jilt()->get_settings_link( edd_jilt()->get_id() ) ) {
				$actions[] = edd_jilt()->get_settings_link( edd_jilt()->get_id() );
			}
		}

		// add the links to the front of the actions list
		return $actions;
	}


	/**
	 * Shows connection notices.
	 *
	 * We already show connection error notices when the plugin settings save
	 * post is happening; this method makes those notices more persistent by
	 * showing a connection notice on a regular page load if there's an issue
	 * with the Jilt connection.
	 *
	 * @internal
	 *
	 * @since 1.1.0
	 */
	public function show_connection_notices() {

		// show generic/misc notices
		edd_jilt()->get_message_handler()->show_messages();

		// show the duplicate site warning pretty much everywhere
		if ( edd_jilt()->get_integration()->is_duplicate_site() ) {

			/* translators: Placeholders: %1$s - <strong> tag, %2$s - </strong> tag, %3$s - <a> tag, %4$s </a> tag */
			$message = sprintf( __( 'It looks like this site has moved or is a duplicate site. %1$sJilt for Easy Digital Downloads%2$s has been disabled to prevent sending recovery emails from a staging or test environment. For more information please %3$sget in touch%4$s.', 'jilt-for-edd' ),
				'<strong>', '</strong>',
				'<a target="_blank" href="' . edd_jilt()->get_support_url() . '">', '</a>'
			);

			edd_jilt()->get_admin_notice_handler()->add_admin_notice(
				$message,
				'duplicate-site-unlink-notice',
				array( 'notice_class' => 'error' )
			);

		// shows other messages if we're on the Jilt settings page or the plugin is not configured
		} elseif ( edd_jilt()->is_plugin_settings() && edd_jilt()->get_integration()->is_configured() ) {

			// call to action based on error state
			if ( ! edd_jilt()->get_integration()->has_connected() ) {

				/* translators: Placeholders: %1$s - <a> tag, %2$s - </a> tag, %3$s - <a> tag, %4$s - </a> tag */
				$message = sprintf( __( 'Please try re-connecting to Jilt or %1$sget in touch with Jilt Support%2$s to help resolve this issue.', 'jilt-for-edd' ),
					'<a target="_blank" href="' . esc_url( edd_jilt()->get_support_url() ) . '">',
					'</a>'
				);

				// shows notice to shop admin
				$this->add_api_error_notice( array( 'solution_message' => $message ) );

			} elseif ( ! edd_jilt()->get_integration()->is_linked() ) {

				// sends message to Jilt support staff
				$this->add_api_error_notice( array( 'support_message' => "I'm having an issue linking my shop to Jilt" ) );
			}
		}
	}


	/**
	 * Reports an API error message in an admin notice with a link to the Jilt support page.
	 *
	 * Optionally logs the error.
	 *
	 * @since 1.1.0
	 *
	 * @param array $params Associative array of params:
	 *   'error_message': optional error message
	 *   'solution_message': optional solution message (defaults to "get in touch with support")
	 *   'support_message': optional message to include in a support request
	 *     (defaults to error_message)
	 */
	public function add_api_error_notice( $params ) {

		if ( ! isset( $params['error_message'] ) ) {
			$params['error_message'] = null;
		}

		// this will be pre-populated in any support request form. Defaults to
		// the error message, if not set
		if ( empty( $params['support_message'] ) ) {
			$params['support_message'] = $params['error_message'];
		}

		if ( empty( $params['solution_message'] ) ) {
			// generic solution message: get in touch with support
			/* translators: Placeholders: %1$s - <a> tag, %2$s - </a> tag */
			$params['solution_message'] = sprintf(__( 'Please %1$sget in touch with Jilt Support%2$s to resolve this issue.', 'jilt-for-edd' ),
				'<a target="_blank" href="' . esc_url( edd_jilt()->get_support_url( array( 'message' => $params['support_message'] ) ) ) . '">',
				'</a>'
			);
		}

		if ( ! empty( $params['error_message'] ) ) {
			// add a full stop
			$params['error_message'] .= '.';
		}

		/* translators: Placeholders: %1$s - <strong> tag, %2$s - </strong> tag, %3$s - error message, %4$s - solution message */
		$notice = sprintf( __( '%1$sError communicating with Jilt%2$s: %3$s %4$s', 'jilt-for-edd' ),
			'<strong>',
			'</strong>',
			$params['error_message'],
			$params['solution_message']
		);

		edd_jilt()->get_admin_notice_handler()->add_admin_notice(
			$notice,
			'api-error',
			array(
				'notice_class' => 'error',
			)
		);
	}


	/**
	 * Updates the remote shop resource with the latest data.
	 *
	 * Renders an error message if there's a failure to communicate.
	 *
	 * @since 1.4.0
	 *
	 * @param bool $include_api_credentials whether or not to include api credentials in the update
	 */
	public function update_shop( $include_api_credentials = false ) {

		// update shop data in Jilt (especially plugin version)
		try {

			edd_jilt()->get_integration()->update_shop( $include_api_credentials );

		} catch ( EDD_Jilt_API_Exception $exception ) {

			$solution_message = null;

			if ( 404 === (int) $exception->getCode() ) {

				/* translators: Placeholders: %1$s - <a> tag, %2$s - </a> tag */
				$solution_message = sprintf( __( 'Shop not found, please try re-connecting to Jilt or %1$sget in touch with Jilt Support%2$s to resolve this issue.', 'jilt-for-edd' ),
					'<a target="_blank" href="' . esc_url( edd_jilt()->get_support_url( array( 'message' => $exception->getMessage() ) ) ) . '">',
					'</a>'
				);

			} elseif ( 401 === (int) $exception->getCode() ) {

				/* translators: Placeholders: %1$s - <a> tag, %2$s - </a> tag */
				$solution_message = sprintf( __( 'Shop not authorized, please try re-connecting to Jilt or %1$sget in touch with Jilt Support%2$s to resolve this issue.', 'jilt-for-edd' ),
					'<a target="_blank" href="' . esc_url( edd_jilt()->get_support_url( array( 'message' => $exception->getMessage() ) ) ) . '">',
					'</a>'
				);
			}

			$this->add_api_error_notice( array( 'error_message' => $exception->getMessage(), 'solution_message' => $solution_message ) );
		}
	}


	/**
	 * Maybe adds a 'Log Viewer' tab to Downloads > Tools if debug level logging is enabled.
	 *
	 * @internal
	 *
	 * @since 1.2.0
	 *
	 * @param array $tabs
	 * @return array
	 */
	public function maybe_add_log_viewer_tab( $tabs ) {

		if ( edd_jilt()->get_logger()->logging_enabled( EDD_Jilt_Logger::DEBUG ) ) {
			$tabs['jilt_log_viewer'] = __( 'Jilt Log Viewer', 'jilt-for-edd' );
		}

		return $tabs;
	}


	/**
	 * Render the log viewer tab content.
	 *
	 * @internal
	 *
	 * @since 1.2.0
	 */
	public function render_log_viewer_tab() {

		if ( ! current_user_can( 'manage_shop_settings' ) ) {
			return;
		}

		?>
		<div style="background: #FFF; border 1px solid #e5e5e5; padding: 5px 20px;">
			<pre style="font-family: monospace; white-space: pre-wrap;"><?php echo esc_html( file_get_contents( edd_jilt()->get_logger()->get_log_file_path() ) ); ?></pre>
		</div>
		<?php
	}


	/**
	 * Loads the EDD Admin Scripts on the Widget page.
	 *
	 * @since 1.5.0
	 */
	public function load_edd_admin_scripts_for_widget( $load_scripts, $hook ) {

		if ( 'widgets.php' === $hook ) {
			$load_scripts = true;
		}

		return $load_scripts;
	}


	/**
	 * Configures new EDD API credentials.
	 *
	 * @since 1.4.0
	 *
	 * @internal
	 */
	public function configure_edd_api() {

		check_admin_referer( 'edd_jilt_generate_edd_api_key' );

		if ( ! current_user_can( 'manage_shop_settings' ) ) {
			wp_die( __( 'Sorry, you don\'t have permission to do that.', 'jilt-for-edd' ) );
		}

		try {

			if ( edd_jilt()->get_edd_api_handler()->configure_key( get_current_user_id() ) ) {
				$this->update_shop( true );
				edd_jilt()->get_message_handler()->add_message( __( 'Success! The EDD REST API has been configured for Jilt.', 'jilt-for-edd' ) );
			} else {
				edd_jilt()->get_message_handler()->add_error( __( 'Oops! Something went wrong. Please try re-connecting to Jilt.', 'jilt-for-edd' ) );
			}

		} catch ( EDD_Jilt_Plugin_Exception $exception ) {

			edd_jilt()->get_logger()->error( "Error configuring EDD REST API: {$exception->getMessage()}" );

			/* translators: Placeholders: %1$s - error message */
			$error_message = sprintf(
				__( 'Error configuring EDD REST API: %1$s', 'jilt-for-edd' ),
				$exception->getMessage()
			);
			edd_jilt()->get_message_handler()->add_error( $error_message );
		}

		wp_safe_redirect( edd_jilt()->get_settings_url() );
		exit;
	}


}
