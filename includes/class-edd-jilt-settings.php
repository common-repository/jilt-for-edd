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
 * Handler of Jilt for EDD Settings.
 *
 * @see \EDD_Jilt_Admin_Settings admin handler
 *
 * @since 1.4.0
 */
class EDD_Jilt_Settings {


	/**
	 * Returns an associative array of settings data.
	 *
	 * This method does not return the settings saved values.
	 * @see \EDD_Jilt_Settings::get_settings()
	 * @see \EDD_Jilt_Settings::get_setting()
	 *
	 * @since 1.4.0
	 *
	 * @return array output may vary according to integration's current status
	 */
	public static function get_setting_fields() {

		$integration = edd_jilt()->get_integration();
		$fields      = array(
			'display_jilt_status'        => self::get_connection_status_form_field(),
			'post_checkout_registration' => self::get_post_checkout_registration_form_field(),
			'jilt_log_threshold'         => self::get_log_threshold_form_field(),
		);

		if ( $integration->is_configured() && 'secret_key' === $integration->get_auth_method() ) {

			$fields['jilt_secret_key'] = array(
				'id'      => 'jilt_secret_key',
				'name'    => __( 'Secret Key', 'jilt-for-edd' ),
				/* translators: Placeholders: %1$s - opening HTML <a> link tag, %2$s - closing </a> HTML link tag */
				'desc'    => sprintf( esc_html__( 'Get this from your %1$sJilt account%2$s', 'jilt-for-edd' ), '<a href="' . esc_url( 'https://' . edd_jilt()->get_app_hostname() . '/shops/new/edd' ) . '" target="_blank">', '</a>' ),
				'type'    => 'password',
				'default' => '',
			);
		}

		if ( $integration->is_linked() ) {

			$help_links = array(
				/* translators: Placeholders: %1$s - opening HTML <a> link tag, %2$s - closing </a> HTML link tag */
				sprintf( esc_html__( '%1$sGo to Jilt dashboard%2$s', 'jilt-for-edd' ), '<a href="' . esc_url( $integration->get_jilt_app_url() ) . '">', '</a>' ),
				/* translators: Placeholders: %1$s - opening HTML <a> link tag, %2$s - closing </a> HTML link tag */
				sprintf( esc_html__( '%1$sGet Support!%2$s', 'jilt-for-edd' ),         '<a href="' . esc_url( edd_jilt()->get_support_url() ) . '">', '</a>' ),
			);

			$fields['jilt_help'] = array(
				'id'   => 'jilt_help',
				'desc' => '<div class="edd-jilt-help-links">' . implode( ' | ', $help_links ) . '</div>',
				'type' => 'descriptive_text',
			);
		}

		return $fields;
	}


	/**
	 * Returns the Jilt for EDD saved settings.
	 *
	 * @since 1.4.0
	 *
	 * @return array associative array of setting IDs and values
	 */
	public static function get_settings() {
		global $edd_options;

		$settings = array();

		if ( is_array( $edd_options ) && ! empty( $edd_options ) ) {

			foreach ( $edd_options as $key => $value ) {

				if ( 0 === strpos( $key, 'jilt_' ) ) {

					$settings[ substr( $key, 5 ) ] = $value;
				}
			}
		}

		return $settings;
	}


	/**
	 * Returns the settings without any unsafe or sensitive properties.
	 *
	 * @since 1.4.0
	 *
	 * @param array $settings settings to make safe, if unspecified will use all Jilt for EDD settings
	 * @return array associative array of setting IDs and their values
	 */
	public static function get_safe_settings( $settings = array() ) {

		$settings = empty( $settings ) ? self::get_settings() : $settings;

		unset( $settings['secret_key'], $settings['jilt_secret_key'] );

		return $settings;
	}


	/**
	 * Returns a Jilt for EDD setting's value.
	 *
	 * @since 1.4.0
	 *
	 * @param string $setting_id a setting ID
	 * @param mixed|false $default default value when setting is not found (default false)
	 * @return mixed
	 */
	public static function get_setting( $setting_id, $default = false ) {

		$setting_key = 0 !== strpos( $setting_id, 'jilt_' ) ? "jilt_{$setting_id}" : $setting_id;

		return edd_get_option( $setting_key, $default );
	}


	/**
	 * Updates all Jilt for EDD settings with new data.
	 *
	 * @since 1.4.0
	 *
	 * @param array $new_settings associative array
	 * @return bool will return false if update or removal of a setting in array isn't successful
	 */
	public static function update_settings( array $new_settings ) {

		$old_settings = self::get_settings();
		$success      = array();

		// update existing/add new settings
		foreach ( $new_settings as $key => $value ) {

			if ( ! isset( $old_settings[ $key ] ) || $old_settings[ $key ] !== $value ) {

				$option_key = 0 === strpos( $key, 'jilt_' ) ? $key : "jilt_{$key}";
				$success[]  = edd_update_option( $option_key, $value );
			}
		}

		return ! in_array( false, $success, true );
	}


	/**
	 * Updates a setting's value.
	 *
	 * @since 1.4.0
	 *
	 * @param string $setting_id the ID of the setting to update
	 * @param mixed $new_data a new data value
	 * @return bool update success
	 */
	public static function update_setting( $setting_id, $new_data ) {

		$setting_key = 0 === strpos( $setting_id, 'jilt_' ) ? substr( $setting_id, 5 ) : $setting_id;

		return self::update_settings( array_merge( self::get_settings(), array( $setting_key => $new_data ) ) );
	}


	/**
	 * Deletes a setting (the setting will be restored to its default)
	 *
	 * @since 1.4.0
	 *
	 * @param string $setting_id the ID of the setting to delete
	 * @return bool deletion success
	 */
	public static function delete_setting( $setting_id ) {

		$setting_key = 0 !== strpos( $setting_id, 'jilt_' ) ? "jilt_{$setting_id}" : $setting_id;

		return edd_delete_option( $setting_key );
	}


	/** Form fields helper methods ******************************************************/


	/**
	 * Gets the form field options for the log threshold setting.
	 *
	 * @since 1.4.0
	 *
	 * @return array
	 */
	private static function get_log_threshold_form_field() {

		/* translators: Placeholders: %1$s - <code>path/to/log</code> */
		$description = sprintf(
			__( 'Save detailed error messages and API requests/responses to the debug log: %1$s', 'jilt-for-edd' ),
			'<code>' . edd_jilt()->get_logger()->get_relative_log_file_path() . '</code>'
		);

		return array(
			'id'          => 'jilt_log_threshold',
			'name'        => __( 'Logging', 'jilt-for-edd' ),
			'type'        => 'select',
			'field_class' => '',
			'multiple'    => false,
			'desc'        => $description,
			'default'     => EDD_Jilt_Logger::OFF,
			'options'     => array(
				EDD_Jilt_Logger::OFF       => _x( 'Off',   'Logging disabled', 'jilt-for-edd' ),
				EDD_Jilt_Logger::DEBUG     => _x( 'Debug', 'Log level debug',  'jilt-for-edd' ),
				EDD_Jilt_Logger::INFO      => __( 'Info',  'Log level info',   'jilt-for-edd' ),
				EDD_Jilt_Logger::WARNING   => __( 'Warning',  'Log level warn',   'jilt-for-edd' ),
				EDD_Jilt_Logger::ERROR     => __( 'Error', 'Log level error',  'jilt-for-edd' ),
				EDD_Jilt_Logger::EMERGENCY => __( 'Emergency', 'Log level emergency',  'jilt-for-edd' ),
			),
		);
	}


	/**
	 * Gets the form field options for the post checkout registration option.
	 *
	 * @since 1.4.0
	 *
	 * @return array
	 */
	private static function get_post_checkout_registration_form_field() {

		return array(
			'id'          => 'jilt_post_checkout_registration',
			'name'        => __( 'Post-checkout registration', 'jilt-for-edd' ),
			'type'        => 'checkbox',
			'field_class' => '',
			'desc'        => __( 'Prompt guest purchasers to register with one-click after placing an order.', 'jilt-for-edd' ),
			'default'     => 'no',
		);
	}


	/**
	 * Gets the form field options for the connection status action.
	 *
	 * @since 1.4.0
	 *
	 * @return array
	 */
	private static function get_connection_status_form_field() {

		return array(
			'id'   => 'display_jilt_status',
			'name' => __( 'Connection Status', 'jilt-for-edd' ),
			'type' => 'hook',
		);
	}


}
