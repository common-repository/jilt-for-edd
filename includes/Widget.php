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
 * @package   EDD-Jilt
 * @author    Jilt
 * @copyright Copyright (c) 2015-2020, SkyVerge, Inc.
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace Jilt\EDD;

defined( 'ABSPATH' ) or exit;

/**
 * A simple widget for displaying a subscribe form for Jilt.
 *
 * @since 1.5.0-dev.2
 */
class Widget extends \WP_Widget {

	/** @var int number of times the form has been rendered */
	public static $count = 0;

	/** @var bool tracks whether styles have been printed to the page or not */
	protected static $printed_styles = false;

	/** @var bool tracks whether scripts have been printed on the page or not */
	protected static $printed_scripts = false;


	/**
	 * Setup the widget options
	 *
	 * @since 1.5.0
	 */
	public function __construct() {

		// set widget options
		$options = [
			'classname'   => 'widget_edd_jilt',
			'description' => __( 'Allow your customers to opt into Jilt emails.', 'jilt-for-edd' ),
		];

		// instantiate the widget
		parent::__construct( 'edd_jilt', __( 'Jilt', 'jilt-for-edd' ), $options );

		// reload on widget save
		add_action( 'admin_print_footer_scripts-widgets.php', [ $this, 'maybe_print_scripts' ] );
	}


	/**
	 * Increments the form count and returns the new count.
	 *
	 * Used to keep track of how many times the form has been rendered, to increment IDs
	 *
	 * @since 1.5.0
	 *
	 * @return int
	 */
	public static function increment_count() {
		return ++self::$count;
	}


	/**
	 * Renders the widget.
	 *
	 * @see \WP_Widget::widget()
	 *
	 * @since 1.5.0
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {

		if ( ! edd_jilt()->get_integration()->get_api() ) {
			return;
		}

		$this->maybe_print_styles();

		$title = ! empty( $instance['title'] ) ? $instance['title'] : '';
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

		echo $args['before_widget'];

		if ( $title ) {
			echo $args['before_title'] . wp_kses_post( $title ) . $args['after_title'];
		}

		$form = [
			'show_names'    => isset( $instance['use_names'] ) && 'no' !== $instance['use_names'],
			'require_names' => isset( $instance['use_names'] ) && 'require' === $instance['use_names'],
			'button_text'   => isset( $instance['button_text'] ) ? $instance['button_text'] : '',
			'list_ids'      => isset( $instance['list_ids'] ) ? $instance['list_ids'] : '',
			'tags'          => isset( $instance['tags'] ) ? $instance['tags'] : '',
		];

		edd_jilt_subscribe_form( $form );

		echo $args['after_widget'];
	}


	/**
	 * Prints styles to the page one time regardless of the number of rendered widgets.
	 *
	 * @since 1.5.0
	 */
	protected function maybe_print_styles() {

		if ( ! self::$printed_styles ) : self::$printed_styles = true; ?>

			<style type="text/css">
				.jilt-form-row {
					zoom: 1;
				}
				.jilt-form-row::before, .jilt-form-row::after {
					content: " ";
					display: table;
				}
				.jilt-form-row::after {
					clear: both;
				}
				.jilt-form-row label {
					display: block;
				}
				.jilt-form-row label.checkbox {
					display: inline;
				}
				.jilt-form-row select {
					width: 100%;
				}
				.jilt-form-row .input-text {
					box-sizing: border-box;
					width: 100%;
				}
				.jilt-form-row .required {
					color: #b94a48;
				}

				.jilt-form-row--first,
				.jilt-form-row--last {
					width: 47%;
					overflow: visible;
				}

				.jilt-form-row--first {
					float: left;
				}

				.jilt-form-row--last {
					float: right;
				}

				.jilt-form-row--wide {
					clear: both;
				}
			</style>

		<?php endif;
	}


	/**
	 * Updates the widget title & selected options.
	 *
	 * @see \WP_Widget::update()
	 *
	 * @since 1.5.0
	 *
	 * @param array $new_instance
	 * @param array $old_instance
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {

		$instance = [
			'title'       => isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '',
			'use_names'   => isset( $new_instance['use_names'] ) ? sanitize_text_field( $new_instance['use_names'] ) : '',
			'button_text' => isset( $new_instance['button_text'] ) ? sanitize_text_field( $new_instance['button_text'] ) : '',
			'list_ids'    => isset( $new_instance['list_ids'] ) ? array_map( 'intval', $new_instance['list_ids'] ) : '',
			'tags'        => isset( $new_instance['tags'] ) ? sanitize_text_field( $new_instance['tags'] ) : '',
		];

		return $instance;
	}


	/**
	 * Renders the admin form for the widget.
	 *
	 * @see \WP_Widget::form()
	 *
	 * @since 1.5.0
	 *
	 * @param array $instance
	 */
	public function form( $instance ) {

		$api = edd_jilt()->get_integration()->get_api();

		if ( ! $api ) {
			?><p><?php printf( esc_html__( 'You must %1$sconnect to Jilt%2$s before using this widget.', 'jilt-for-edd' ), '<a href="' . esc_url( edd_jilt()->get_settings_url() ) . '">', '</a>' ); ?></p><?php
			return;
		}

		try {

			$lists = wp_list_pluck( $api->get_lists(), 'name', 'id' );

		} catch ( \EDD_Jilt_API_Exception $e ) {

			$lists = [ '' => sprintf( __( 'Oops, something went wrong: %s', 'jilt-for-edd' ), $e->getMessage() ) ];
		}

		include( 'admin/views/html-admin-widget-settings.php' );
	}


	/**
	 * Updates the widget's list enhanced select after adding / saving.
	 *
	 * @since 1.5.0
	 */
	public function maybe_print_scripts() {

		if ( ! self::$printed_scripts ) : self::$printed_scripts = true; ?>

			<script type="text/javascript">
	            jQuery( function( $ ) {

	                function edd_jilt_reload_enhanced_select() {
	                    $( '#widgets-right .edd-jilt-enhanced-select' ).chosen( {
		                    width: '100%'
	                    } );
	                }

	                // re-initialize on widgets in the main area
	                $( document ).on( 'widget-updated widget-added', edd_jilt_reload_enhanced_select );

	                edd_jilt_reload_enhanced_select();
	            } );
			</script>

		<?php endif;
	}


}
