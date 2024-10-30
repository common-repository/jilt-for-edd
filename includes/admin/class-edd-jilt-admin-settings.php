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
 * Jilt for EDD Settings admin handler.
 *
 * @see \EDD_Jilt_Settings main handler
 *
 * @since 1.4.0
 */
class EDD_Jilt_Admin_Settings {


	/** @var string the admin settings page hook suffix */
	private $page_hook = '';

	/** @var bool whether to update the shop via API when saving settings */
	private $update_shop = true;


	/**
	 * Sets up the Jilt for EDD settings page.
	 *
	 * @since 1.4.0
	 */
	public function __construct() {

		// add a WordPress menu page and related link in admin sidebar
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );

		// WordPress would automatically convert some HTML entities into emoji in the settings page
		add_action( 'admin_init', array( $this, 'disable_settings_wp_emoji' ) );

		// display connection status
		add_action( 'edd_display_jilt_status', array( $this, 'output_connection_status_field_html' ) );

		// output a prompt to request user reviews on WordPress.org
		add_filter( 'admin_footer_text', array( $this, 'add_settings_page_feedback_prompt' ), 1 );

		// output additional scripts on the settings page
		add_action( 'admin_print_scripts', array( $this, 'add_settings_page_scripts' ), 100 );
	}


	/**
	 * Adds a WordPress page for handling Jilt for EDD settings.
	 *
	 * @internal
	 *
	 * @since 1.4.0
	 */
	public function add_settings_page() {

		$this->page_hook = add_menu_page(
			__( 'Jilt', 'jilt-for-edd' ),
			__( 'Jilt', 'jilt-for-edd' ),
			'manage_shop_settings',
			'edd-jilt',
			array( $this, 'output_settings_page_html' ),
			null,
			'26'
		);
	}


	/**
	 * Renders the settings page HTML.
	 *
	 * @internal
	 *
	 * @since 1.4.0
	 */
	public function output_settings_page_html() {
		global $current_tab, $current_section;

		if ( current_user_can( 'manage_shop_settings' ) ) {

			$tabs            = array( 'settings' => __( 'Settings', 'jilt-for-edd' ) );
			$current_tab     = empty( $_GET['tab'] ) ? 'settings' : sanitize_title( $_GET['tab'] );
			$current_section = empty( $_REQUEST['section'] ) ? '' : sanitize_title( $_REQUEST['section'] );

			// save settings
			if ( ! empty( $_POST ) && 'settings' === $current_tab ) {

				$this->save_settings( $_POST );

				// maybe avoid an unnecessary additional API request
				if ( $this->update_shop ) {
					edd_jilt()->get_admin()->update_shop();
				}

				edd_jilt()->get_message_handler()->add_message( __( 'Your settings have been saved.', 'jilt-for-edd' ) );
			}

			?>
			<div class="wrap">

				<?php if ( 'welcome' === $current_tab ) : ?>

					<?php $this->output_welcome_screen_html(); ?>

				<?php else : ?>

					<h1><img src="<?php echo edd_jilt()->get_plugin_url(); ?>/assets/img/jilt-logo.svg" id="edd-jilt-admin-logo" alt="<?php esc_attr_e( 'Jilt', 'jilt-for-edd' ); ?>"></h1>
					<p><?php esc_html_e( 'Send automated emails to help recover abandoned carts, ask for product reviews, drive repeat purchases, and more.', 'jilt-for-edd' ); ?></p>

					<h2 class="nav-tab-wrapper woo-nav-tab-wrapper">
						<?php foreach ( $tabs as $tab_id => $tab_title ) : ?>

							<?php

							$class = ( $tab_id === $current_tab ) ? array( 'nav-tab', 'nav-tab-active' ) : array( 'nav-tab' );
							$url   = add_query_arg( 'tab', $tab_id, admin_url( 'admin.php?page=edd-jilt' ) );

							printf( '<a href="%1$s" class="%2$s">%3$s</a>', esc_url( $url ), implode( ' ', array_map( 'sanitize_html_class', $class ) ), esc_html( $tab_title ) );

							?>

						<?php endforeach; ?>
					</h2>

					<form
						method="post"
						id="mainform"
						action=""
						enctype="multipart/form-data">
						<?php if ( 'settings' === $current_tab ) : ?>

							<?php $this->output_settings_html(); ?>
							<?php wp_nonce_field( __FILE__ ); ?>
							<?php submit_button( __( 'Save settings', 'jilt-for-edd' ) ); ?>

						<?php endif; ?>
					</form>

				<?php endif; ?>
			</div>
			<?php
		}
	}


	/**
	 * Outputs a welcome splash screen the first time the settings page is loaded.
	 *
	 * @since 1.4.0
	 */
	private function output_welcome_screen_html() {

		?>
		<div class="edd-jilt-welcome-splash">

			<h1><img src="<?php echo esc_url( edd_jilt()->get_plugin_url() ); ?>/assets/img/jilt-logo.svg" class="logo" alt="<?php esc_attr_e( 'Jilt', 'jilt-for-edd' ); ?>"></h1>
			<h2><?php esc_html_e( "Hooray, your store is now connected to Jilt! You're ready to start sending automated emails and driving more revenue!", 'jilt-for-edd' ); ?></h2>
			<h4><?php esc_html_e( "Let's talk next steps:", 'jilt-for-edd' ); ?></h4>

			<div class="next-steps">
				<ol>
					<?php /* translators: Placeholders: %1$s - opening <a> tag, %2$s - closing </a> tag */ ?>
					<li><?php printf( esc_html__( 'You can %1$sview your Jilt dashboard here%2$s — setting up your first campaign takes only minutes and lets you start recovering revenue right away.', 'jilt-for-edd' ), '<a href="' . esc_url( edd_jilt()->get_integration()->get_jilt_app_url() ) . '">', '</a>' ); ?></li>
					<?php /* translators: Placeholders: %1$s - opening <a> tag, %2$s - closing </a> tag */ ?>
					<li><?php printf( esc_html__( 'You can adjust your shop\'s %1$sStorefront settings here%2$s. We recommend enabling add-to-cart popovers to collect more emails from customers (increasing the number of carts you can recover).', 'jilt-for-edd' ), '<a href="' . esc_url( edd_jilt()->get_integration()->get_jilt_app_url( 'edit' ) ) . '">', '</a>' ); ?></li>
					<?php /* translators: Placeholders: %1$s - opening <a> tag, %2$s - closing </a> tag */ ?>
					<li><?php printf( esc_html__( 'You can see the %1$splugin settings here%2$s. We recommend keeping debug mode off unless you\'re working on an issue with our support team.', 'jilt-for-edd' ), '<a href="' . esc_url( edd_jilt()->get_settings_url() ) . '">', '</a>' ); ?></li>
					<?php /* translators: Placeholders: %1$s and %3$s - opening <a> tag, %2$s and %4$s - closing </a> tag */ ?>
					<li><?php printf( esc_html__( 'If you run into any questions or issues, our %1$sknowledge base is here%2$s and you can %3$sreach our support team here%4$s.', 'jilt-for-edd' ), '<a href="' . esc_url( edd_jilt()->get_documentation_url() ) . '">', '</a>', '<a href="' . edd_jilt()->get_support_url() . '">', '</a>' ); ?></li>
				</ol>
			</div>

			<p class="ready-to-go"><?php
				/* translators: Placeholders: %1$s opening <a> tag, %2$s - closing </a> tag */
				printf( esc_html__( 'Ready to keep going? %1$sLet\'s start configuring!%2$s', 'jilt-for-edd' ), '<a href="' . esc_url( edd_jilt()->get_integration()->get_jilt_app_url( 'edit' ) ) . '">', '</a>' ); ?></p>
		</div>
		<?php
	}


	/**
	 * Outputs settings fields HTML for the settings page.
	 *
	 * @since 1.4.0
	 */
	private function output_settings_html() {

		?>
		<table class="form-table">
			<tbody>
				<?php foreach ( EDD_Jilt_Settings::get_setting_fields() as $setting_data ) : ?>

					<?php if ( is_array( $setting_data ) && isset( $setting_data['type'] ) ) : ?>

						<tr>
							<?php if ( isset( $setting_data['name'] ) ) : ?>
								<th><?php echo esc_html( $setting_data['name'] ); ?></th>
							<?php endif; ?>
							<td <?php echo empty( $setting_data['name'] ) ? 'colspan="2"' : ''; ?>>
								<?php

								$field_type   = $setting_data['type'];
								$edd_function = "edd_{$field_type}_callback";

								if ( function_exists( $edd_function ) ) :
									$edd_function( $setting_data );
								else :
									edd_hook_callback( $setting_data );
								endif;

								?>
							</td>
						</tr>

					<?php endif; ?>

				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}


	/**
	 * Outputs an EDD custom field containing the Jilt connection status information:
	 *
	 * "Connected" or "Not Connected"
	 *
	 * @internal
	 *
	 * @since 1.4.0
	 */
	public function output_connection_status_field_html() {

		?>
		<div class="edd-jilt-connection-status">
			<?php if ( edd_jilt()->get_integration()->is_jilt_connected() ) : ?>

				<?php if ( 'secret_key' === edd_jilt()->get_integration()->get_auth_method() ) : ?>

					<mark
						class="warning jilt-connection-status edd-help-tip"
						title="<?php esc_attr_e( 'Jilt is connected, but secret key authentication is deprecated. Please re-connect to Jilt.', 'jilt-for-edd' ); ?>"
						style="color: #ffb900; background-color: transparent; cursor: help;">&#9888;</mark>
					<input
						type="submit"
						class="button button-primary jilt-connection-button"
						name="edd_jilt_connect"
						value="<?php esc_attr_e( 'Re-connect to Jilt', 'jilt-for-edd' ); ?>"
					/>

				<?php else : ?>

					<mark
						class="yes jilt-connection-status edd-help-tip"
						title="<?php esc_attr_e( 'Jilt is connected!', 'jilt-for-edd' ); ?>"
						style="color: #7ad03a; background-color: transparent; cursor: help;">&#10004;</mark>

				<?php endif ; ?>

				<input
					type="submit"
					class="button jilt-connection-button"
					name="edd_jilt_disconnect"
					id="edd_jilt_disconnect"
					value="<?php esc_attr_e( 'Disconnect from Jilt', 'jilt-for-edd' ); ?>"
				/>

			<?php else : ?>

				<?php if ( ! edd_jilt()->get_integration()->has_connected() || ! edd_jilt()->get_integration()->is_linked() ) : ?>
					<?php $tooltip = 'title="' . esc_attr( 'Please ensure the plugin has been successfully connected to Jilt.', 'jilt-for-edd' ) . '"'; ?>
				<?php elseif ( edd_jilt()->get_integration()->is_duplicate_site() ) : ?>
					<?php $tooltip = 'title="' . esc_attr( 'It looks like this site has moved or is a duplicate site.', 'jilt-for-edd' ) . '"'; ?>
				<?php else : ?>
					<?php $tooltip = ''; ?>
				<?php endif; ?>

				<?php $label = edd_jilt()->get_integration()->has_connected() ? esc_attr__( 'Re-connect to Jilt', 'jilt-for-edd' ) : esc_attr__( 'Connect to Jilt', 'jilt-for-edd' ); ?>

				<mark
					<?php echo $tooltip; ?>
					class="error jilt-connection-status edd-help-tip"
					style="color: #a00; background-color: transparent; cursor: help;">&#10005;</mark>
				<a
					href="<?php echo esc_url( edd_jilt()->get_connect_url() ); ?>"
					class="button button-primary jilt-connection-button"
					id="edd_jilt_connect"><?php echo esc_attr( $label ); ?></a>

				<p class="description"><?php esc_html_e( 'Please note an existing Jilt account is required to complete the connection and we\'re no longer accepting new account signups.', 'jilt-for-edd' ); ?></p>

			<?php endif; ?>
		</div>
		<?php
	}


	/**
	 * Updates Jilt for EDD settings.
	 *
	 * Updates Jilt public key and shop ID when updating secret key.
	 * This is invoked prior to the options being persisted to the database.
	 *
	 * @since 1.4.0
	 *
	 * @param array $new_settings new Jilt settings:
	 *   array(
	 *    'jilt_secret_key' => string,
	 *    'jilt_log_threshold' => '100',
	 *   )
	 */
	private function save_settings( array $new_settings ) {

		// when updating settings, make sure we have the new value so we log any
		// API requests that might occur
		if ( isset( $new_settings['edd_settings']['jilt_log_threshold'] ) ) {

			edd_jilt()->get_logger()->set_threshold( (int) $new_settings['edd_settings']['jilt_log_threshold'] );
		}

		// disconnect from Jilt - either when pressing the disconnect button or if secret key has been removed
		if ( isset( $new_settings['edd_jilt_disconnect'] ) || ( isset( $new_settings['jilt_secret_key'] ) && empty( $new_settings['jilt_secret_key'] ) ) ) {

			edd_jilt()->get_integration()->unlink_shop();           // this will mark the shop as uninstalled in Jilt
			edd_jilt()->get_integration()->revoke_authorization();  // this will revoke the oauth access token
			edd_jilt()->get_integration()->clear_connection_data(); // this will wipe Jilt tokens and keys in EDD

			edd_jilt()->get_message_handler()->add_message( __( 'Your shop is now disconnected from Jilt.', 'jilt-for-edd' ) );

			// remove secret key, if was used so far
			// TODO: remove the following block when dropping support for secret key authentication for good {IT 2018-01-26}
			if ( edd_jilt()->get_integration()->get_secret_key() ) {

				unset( $new_settings['jilt_secret_key'] );

				edd_jilt()->get_integration()->set_secret_key( null );
			}
		}

		// TODO: remove the following block when dropping support for secret key authentication for good {IT 2018-01-26}
		if ( ! isset( $_POST['edd_jilt_disconnect'] ) && ! empty( $new_settings['jilt_secret_key'] ) ) {

			$old_secret_key = edd_jilt()->get_integration()->get_secret_key();
			$new_secret_key = $new_settings['jilt_secret_key'];

			if ( $new_secret_key ) {

				// secret key has been changed or removed, so unlink remote shop
				if ( $new_secret_key !== $old_secret_key && edd_jilt()->get_integration()->is_linked() ) {

					edd_jilt()->get_integration()->unlink_shop();
				}

				if ( $new_secret_key !== $old_secret_key || ! edd_jilt()->get_integration()->has_connected() || ! edd_jilt()->get_integration()->is_linked() ) {

					$this->connect_to_jilt( $new_secret_key );

					// avoid an unnecessary additional API request
					$this->update_shop = false;

					// avoid an extra useless REST API request
					remove_action( 'update_option_edd_settings', array( 'EDD_Jilt_Admin', 'update_shop' ) );
				}
			}
		}

		if ( isset( $new_settings['edd_settings'] ) ) {
			EDD_Jilt_Settings::update_settings( $new_settings['edd_settings'] );
		}
	}


	/**
	 * Connects the shop to Jilt.
	 *
	 * If a $secret_key is provided, attempt to connect to the Jilt API to retrieve the corresponding Public Key, and link the shop to Jilt
	 *
	 * @since 1.4.0
	 *
	 * @param string $secret_key the secret key to use, or empty string
	 * @return bool connection success
	 */
	private function connect_to_jilt( $secret_key ) {

		$success = false;

		try {

			// remove the previous public key and linked shop id, if any, when the secret key is changed
			edd_jilt()->get_integration()->clear_connection_data();
			edd_jilt()->get_integration()->set_secret_key( $secret_key );
			edd_jilt()->get_integration()->refresh_public_key();

			if ( is_int( edd_jilt()->get_integration()->link_shop() ) ) {

				// dismiss the "welcome" message now that we've successfully linked
				edd_jilt()->get_admin_notice_handler()->dismiss_notice( 'get-started-notice' );
				edd_jilt()->get_admin_notice_handler()->add_admin_notice(
					__( 'Shop is now linked to Jilt!', 'jilt-for-edd' ),
					'shop-linked',
					array( 'add_settings_error' => true )
				);

				$success = true;

			} else {

				edd_jilt()->get_admin()->add_api_error_notice( array( 'error_message' => 'Unable to link shop' ) );
			}

		} catch ( EDD_Jilt_API_Exception $exception ) {

			$success          = false;
			$solution_message = null;

			// call to action based on error message
			if ( false !== strpos( $exception->getMessage(), 'Invalid API Key provided' ) ) {

				/* translators: Placeholders: %1$s - <a> tag, %2$s - </a> tag, %3$s - <a> tag, %4$s - </a> tag */
				$solution_message = sprintf( __( 'Please try re-connecting to Jilt or %1$sget in touch with Jilt Support%2$s to resolve this issue.', 'jilt-for-edd' ),
					'<a target="_blank" href="' . esc_url( edd_jilt()->get_support_url( array( 'message' => $exception->getMessage() ) ) ) . '">',
					'</a>'
				);
			}

			edd_jilt()->get_admin()->add_api_error_notice( array( 'error_message' => $exception->getMessage(), 'solution_message' => $solution_message ) );
			edd_jilt()->get_logger()->error( "Error communicating with Jilt: {$exception->getMessage()}" );
		}

		return $success;
	}


	/**
	 * Prompts a request for feedback / review in the settings page admin footer.
	 *
	 * @internal
	 *
	 * @since 1.4.0
	 *
	 * @param string $footer_text WordPress default text
	 * @return string may contain HTML
	 */
	public function add_settings_page_feedback_prompt( $footer_text ) {

		if ( current_user_can( 'manage_shop_settings' ) ) {

			$screen = get_current_screen();

			// check to make sure we're on a EDD admin page
			if ( $screen && 'toplevel_page_edd-jilt' === $screen->id ) {

				// adjust the footer text
				if ( ! get_option( 'edd_jilt_admin_footer_text_rated' ) ) {

					$review_url = 'https://wordpress.org/support/plugin/jilt-for-edd/reviews/#new-post';

					/* translators: %1$s - Jilt, %2$s - five stars */
					$footer_text = sprintf(
						esc_html__( 'If you like %1$s please leave us a %2$s rating. A huge thanks in advance!', 'jilt-for-edd' ),
						sprintf( '<strong>%s</strong>', esc_html__( 'Jilt', 'jilt-for-edd' ) ),
						'<a href="' . esc_url( $review_url ) . '" target="_blank" class="edd-jilt-rating-link" data-rated="' . esc_attr__( 'Thanks :)', 'jilt-for-edd' ) . '">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
					);

				} else {

					$footer_text = esc_html__( 'Thank you for recovering sales with Jilt.', 'jilt-for-edd' );
				}
			}
		}

		return $footer_text;
	}


	/**
	 * Outputs some inline script required by the admin settings page.
	 *
	 * @internal
	 *
	 * @since 1.4.0
	 */
	public function add_settings_page_scripts() {

		?>
		<script type="text/javascript">
			jQuery( document ).ready( function( $ ) {
				$( 'a.edd-jilt-rating-link' ).click( function () {
					$.post( edd_jilt.ajax_url, { action: 'edd_jilt_rated' } );
					$( this ).parent().text( $( this ).data( 'rated' ) );
				} );
			} );
		</script>
		<?php
	}


	/**
	 * Prevents the conversion of some HTML entities used in the plugin settings page into emojis.
	 *
	 * This bothers for example the check mark or the cross mark shown next to the API connection field.
	 *
	 * @internal
	 *
	 * @since 1.4.0
	 */
	public function disable_settings_wp_emoji() {

		if ( edd_jilt()->is_plugin_settings() ) {

			remove_action( 'admin_print_styles',  'print_emoji_styles' );
			remove_action( 'wp_head',             'print_emoji_detection_script', 7 );
			remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		}
	}


}
