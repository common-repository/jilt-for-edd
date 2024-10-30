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
 * Jilt Download Class helper methods
 *
 * @since 1.2.0
 */
class EDD_Jilt_Download {


	/**
	 * Return the image URL for a download
	 *
	 * @since 1.2.0
	 * @param \EDD_Download $download
	 * @return string|null
	 */
	public static function get_download_image_url( $download ) {

		$url = wp_get_attachment_url( get_post_thumbnail_id( $download->ID ) );

		return ! empty( $url ) ? $url : null;
	}


}
