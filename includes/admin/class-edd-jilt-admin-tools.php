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
 * Admin Tools class
 *
 * @since 1.3.0
 */
class EDD_Jilt_Admin_Tools {


	/**
	 * Initializes the system status tools.
	 *
	 * @since 1.3.0
	 */
	public function __construct() {

		add_filter( 'edd_sysinfo_after_session_config', array( $this, 'add_jilt_status' ) );
		add_action( 'edd_tools_tab_general',            array( $this, 'add_jilt_tools' ) );

		add_action( 'edd_clear_jilt_connection_data',   array( $this, 'run_clear_connection_data_tool' ) );
		add_action( 'edd_delete_jilt_discounts',        array( $this, 'run_delete_discounts_tool' ) );
	}


	/**
	 * Adds Jilt status to EDD system status output.
	 *
	 * @internal
	 *
	 * @since 1.3.0
	 *
	 * @param string $return system status
	 * @return string
	 */
	public function add_jilt_status( $return ) {

		$return .= "\n" . '-- Jilt Abandoned Cart Recovery ' . "\n\n";
		$return .= 'Plugin Version:                 ' . esc_html( edd_jilt()->get_version() ) . "\n";

		if ( edd_jilt()->is_plugin_update_available() ) {
			$return .= esc_html( sprintf( '%s is available', edd_jilt()->get_latest_plugin_version() ) ) . "\n";
		}

		$return .= 'Jilt API Version:               ' . esc_html( EDD_Jilt_API::get_api_version() ) . "\n";
		$return .= 'Jilt API Authentication Method: ' . ( ( 'secret_key' === edd_jilt()->get_integration()->get_auth_method() ) ? esc_html( 'Secret key' ) : esc_html( 'OAuth' ) ) . "\n";
		$return .= 'Jilt API Connected:             ' . ( edd_jilt()->get_integration()->has_connected() ? 'Yes' : 'No' ) . "\n";
		$return .= 'Linked to Jilt:                 ' . ( edd_jilt()->get_integration()->is_linked() ? 'Yes' : 'No' ) . "\n";
		$return .= 'Enabled:                        ' . ( $this->is_jilt_enabled() ? 'Yes' : 'No' ) . "\n";
		if ( edd_jilt()->get_integration()->is_duplicate_site() ) {
			$return .= "Enabled Reason:                 Duplicate site\n";
		}
		$return .= 'EDD API Available:              ' . $this->get_edd_api_available() . "\n";
		if ( edd_jilt()->get_integration()->is_jilt_connected() && ! edd_jilt()->get_edd_api_handler()->is_configured() ) {
			$return .= 'EDD API Available Reason:       ' . edd_jilt()->get_edd_api_handler()->get_api_configuration_error_short() . "\n";
		}

		return $return;
	}


	/**
	 * Is Jilt for EDD Enabled?
	 *
	 * @since 1.4.0
	 * @return boolean
	 */
	private function is_jilt_enabled() {

		return edd_jilt()->get_integration()->has_connected()
			&& edd_jilt()->get_integration()->is_linked()
			&& ! edd_jilt()->get_integration()->is_disabled();
	}


	/**
	 * Gets the status of the connection between Jilt and the EDD API.
	 *
	 * @since 1.4.0
	 *
	 * @return string EDD API status
	 */
	protected function get_edd_api_available() {

		if ( ! edd_jilt()->get_integration()->is_jilt_connected() ) {

			$availability = '-';

		} elseif ( ! edd_jilt()->get_edd_api_handler()->is_configured() ) {

			$availability = 'No';

		} else {

			$availability = 'Yes';
		}

		return $availability;
	}


	/**
	 * Adds Jilt tools to EDD Tools screen.
	 *
	 * @internal
	 *
	 * @since 1.3.0
	 */
	public function add_jilt_tools() {

		if( ! current_user_can( 'manage_shop_settings' ) ) {
			return;
		}

		/**
		 * Fires before the Clear Jilt Connection Data tool.
		 *
		 * @since 1.3.0
		 */
		do_action( 'edd_tools_clear_jilt_connection_data_before' );
		?>
		<div class="postbox">
			<h3><span><?php esc_html_e( 'Clear Jilt connection data', 'jilt-for-edd' ); ?></span></h3>
			<div class="inside">
				<p><?php esc_html_e( 'This tool will clear all Jilt connection data from the database, including OAuth client credentials.', 'jilt-for-edd' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'edit.php?post_type=download&page=edd-tools&tab=general' ) ); ?>">
					<p>
						<input type="hidden" name="edd_action" value="clear_jilt_connection_data" />
						<?php wp_nonce_field( 'edd_clear_jilt_connection_data_nonce', 'edd_clear_jilt_connection_data_nonce' ); ?>
						<?php submit_button( esc_html__( 'Clear', 'jilt-for-edd' ), 'secondary', 'submit', false ); ?>
					</p>
				</form>
			</div><!-- .inside -->
		</div><!-- .postbox -->
		<?php
		/**
		 * Fires after the Clear Jilt Connection Data tool.
		 *
		 * @since 1.3.0
		 */
		do_action( 'edd_tools_clear_jilt_connection_data_after' );

		/**
		 * Fires before the Delete Jilt Discounts tool is added to the page.
		 *
		 * @since 1.4.0
		 */
		do_action( 'edd_tools_delete_jilt_discounts_before' );

		$delete_tool_description = sprintf(
			/** translators: Placeholders: %s - number of coupons to be deleted every time the tool is run */
			__( 'This tool will delete unused, expired discounts that were created by Jilt (up to %s at a time)', 'jilt-for-edd' ),
			EDD_Jilt_Discount_Handler::get_delete_discounts_tool_per_run_limit()
		);

		?>
		<div class="postbox">
			<h3><span><?php esc_html_e( 'Delete Jilt Discounts', 'jilt-for-edd' ); ?></span></h3>
			<div class="inside">
				<p><?php echo esc_html( $delete_tool_description ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'edit.php?post_type=download&page=edd-tools&tab=general' ) ); ?>">
					<p>
						<input type="hidden" name="edd_action" value="delete_jilt_discounts" />
						<?php wp_nonce_field( 'edd_delete_jilt_discounts_nonce', 'edd_delete_jilt_discounts_nonce' ); ?>
						<?php submit_button( esc_html__( 'Delete', 'jilt-for-edd' ), 'secondary', 'submit', false ); ?>
					</p>
				</form>
			</div><!-- .inside -->
		</div><!-- .postbox -->
		<?php
		/**
		 * Fires after the Delete Jilt Discounts tool is added to the page.
		 *
		 * @since 1.4.0
		 */
		do_action( 'edd_tools_delete_jilt_discounts_after' );
	}


	/**
	 * Runs the the clear connection data tool.
	 *
	 * @internal
	 *
	 * @since 1.3.0
	 */
	public function run_clear_connection_data_tool() {

		if( ! wp_verify_nonce( $_POST['edd_clear_jilt_connection_data_nonce'], 'edd_clear_jilt_connection_data_nonce' ) ) {
			return;
		}

		if( ! current_user_can( 'manage_shop_settings' ) ) {
			return;
		}

		$integration = edd_jilt()->get_integration();

		$integration->unlink_shop(); // this will mark the shop as uninstalled in Jilt

		if ( 'access_token' === $integration->get_auth_method() ) {
			$integration->revoke_authorization(); // this will revoke the oauth access token
		}

		$integration->clear_connection_data( true ); // force clear client credentials

		edd_jilt()->get_message_handler()->add_message( __( 'Jilt connection data has been cleared.', 'jilt-for-edd' ) );
	}


	/**
	 * Runs the delete discounts tool.
	 *
	 * Deletes any discounts that were created by Jilt, have not been used, and are expired.
	 *
	 * @internal
	 *
	 * @since 1.4.0
	 */
	public function run_delete_discounts_tool() {

		if ( ! wp_verify_nonce( $_POST['edd_delete_jilt_discounts_nonce'], 'edd_delete_jilt_discounts_nonce' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_shop_settings' ) ) {
			return;
		}

		$deleted = EDD_Jilt_Discount_Handler::delete_discounts();

		if ( ! empty( $deleted ) ) {
			$message = sprintf( _n( 'Success! %d Jilt discount deleted.', 'Success! %d Jilt discounts deleted.', $deleted, 'jilt-for-edd' ), $deleted );
		} else {
			$message = __( 'Success! All unused, expired Jilt discounts have been deleted.', 'jilt-for-edd' );
		}

		edd_jilt()->get_message_handler()->add_message( $message );
	}


}
