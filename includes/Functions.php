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

/**
 * Returns the One True Instance of Jilt for EDD
 *
 * @since 1.0.0
 *
 * @return \EDD_Jilt
 */
function edd_jilt() {
	return EDD_Jilt::instance();
}


/**
 * Renders the Jilt subscribe form.
 *
 * @since 1.5.0
 *
 * @param array $args the form arguments {
 *  @type bool $show_names whether first / last name fields should be shown
 *  @type bool $require_names whether first / last name fields are required
 *  @type string $button_text the subscribe button text
 *  @type array $list_ids the Jilt list IDs the contact will be added to
 *  @type array $tags the Jilt tags that will be added to the contact
 * }
 */
function edd_jilt_subscribe_form( $args ) {

	$form = wp_parse_args( $args, [
		'show_names'    => false,
		'require_names' => false,
		'button_text'   => __( 'Subscribe', 'jilt-for-edd' ),
		'list_ids'      => [],
		'tags'          => [],
	] );

	$fname = is_user_logged_in() ? wp_get_current_user()->user_firstname : null;
	$lname = is_user_logged_in() ? wp_get_current_user()->user_lastname  : null;
	$email = is_user_logged_in() ? wp_get_current_user()->user_email     : null;

	// favor data a contact may have submitted over stored data
	$form['contact_data'] = [
		'first_name' => ! empty( $_POST['edd_jilt_fname'] ) ? $_POST['edd_jilt_fname'] : $fname,
		'last_name'  => ! empty( $_POST['edd_jilt_lname'] ) ? $_POST['edd_jilt_lname'] : $lname,
		'email'      => ! empty( $_POST['edd_jilt_email'] ) ? $_POST['edd_jilt_email'] : $email,
	];

	$form['list_ids']   = is_array( $form['list_ids'] ) ? implode( ',', $form['list_ids'] ) : $form['list_ids'];
	$form['tags']       = is_array( $form['tags'] )     ? implode( ',', $form['tags'] )     : $form['tags'];
	$form['count']      = \Jilt\EDD\Widget::increment_count();

	extract( $form, EXTR_SKIP );

	$template = edd_locate_template( 'jilt-subscribe-form.php' );

	if ( is_readable( $template ) ) {
		require $template;
	}
}
