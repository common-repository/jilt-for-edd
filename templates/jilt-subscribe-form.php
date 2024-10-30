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

/**
 * Renders the Jilt signup / subscribe form.
 *
 * @type int $count the index for the current form (to allow multiple to render on a single page)
 * @type bool $show_names whether first / last name fields should be shown
 * @type bool $require_names whether first / last name fields are required
 * @type string $button_text the subscribe button text
 * @type array $list_ids the Jilt list IDs the contact will be added to
 * @type array $tags the Jilt tags that will be added to the contact
 * @type array $contact_data contact properties {
 *   @type null|string $first_name the contact first name
 *   @type null|string $last_name the contact last name
 *   @type null|string $email the contact email
 * }
 *
 * @version 1.5.1
 * @since 1.5.0
 */

defined( 'ABSPATH' ) or exit;

do_action( 'edd_jilt_before_subscribe_form' ); ?>

<div class="jilt-for-edd subscribe-form">

	<?php do_action( 'edd_jilt_subscribe_form_start' ); ?>

	<form method="post" class="edd_jilt_subscribe_form" action="#edd_jilt_subscribe_form" <?php do_action( 'edd_jilt_subscribe_form_tag' ); ?>>

		<?php if ( $show_names ) : ?>

			<p class="jilt-form-row jilt-form-row--first">
				<label for="<?php echo esc_attr( "edd_jilt_fname__${count}" ); ?>"><?php esc_html_e( 'First name', 'jilt-for-edd' ); ?><?php echo $require_names ? ' <span class="required">*</span>' : ''; ?></label>
				<input id="<?php echo esc_attr( "edd_jilt_fname__${count}" ); ?>" type="text" class="input-text" name="edd_jilt_fname" value="<?php if ( $contact_data['first_name'] ) echo esc_attr( $contact_data['first_name'] ); ?>" <?php echo $require_names ? ' required' : ''; ?>/>
			</p>

			<p class="jilt-form-row jilt-form-row--last">
				<label for="<?php echo esc_attr( "edd_jilt_lname__${count}" ); ?>"><?php esc_html_e( 'Last name', 'jilt-for-edd' ); ?><?php echo $require_names ? ' <span class="required">*</span>' : ''; ?></label>
				<input id="<?php echo esc_attr( "edd_jilt_lname__${count}" ); ?>" type="text" class="input-text" name="edd_jilt_lname" value="<?php if ( $contact_data['last_name'] ) echo esc_attr( $contact_data['last_name'] ); ?>" <?php echo $require_names ? ' required' : ''; ?>/>
			</p>

		<?php endif; ?>

		<p class="jilt-form-row jilt-form-row--wide">
			<label for="<?php echo esc_attr( "edd_jilt_email__${count}" ); ?>"><?php esc_html_e( 'Email address', 'jilt-for-edd' ); ?>&nbsp;<span class="required">*</span></label>
			<input id="<?php echo esc_attr( "edd_jilt_email__${count}" ); ?>" type="email" class="input-text" name="edd_jilt_email" autocomplete="email" value="<?php if ( $contact_data['email'] ) echo esc_attr( $contact_data['email'] ); ?>" />
		</p>

		<?php // Spam Trap ?>
		<div style="<?php echo ( ( is_rtl() ) ? 'right' : 'left' ); ?>: -999em; position: absolute;">
			<label for="<?php echo esc_attr( "edd_jilt_secondary__${count}" ); ?>"><?php esc_html_e( 'Anti-spam', 'jilt-for-edd' ); ?></label>
			<input id="<?php echo esc_attr( "edd_jilt_secondary__${count}" ); ?>" type="text" name="secondary" tabindex="-1" autocomplete="off" />
		</div>

		<p class="jilt-form-row">
			<?php wp_nonce_field( 'edd-jilt-subscribe' ); ?>
			<input type="hidden" name="edd_jilt_subscribe_list_ids" value="<?php echo esc_attr( $list_ids ); ?>" />
			<input type="hidden" name="edd_jilt_subscribe_tags" value="<?php echo esc_attr( $tags ); ?>" />
			<button type="submit" class="button" name="edd_jilt_subscribe" value="<?php echo esc_attr( $button_text ); ?>"><?php echo esc_html( $button_text ); ?></button>
		</p>

	</form>

	<?php do_action( 'edd_jilt_subscribe_form_end' ); ?>

</div>

<?php do_action( 'edd_jilt_after_subscribe_form' );
