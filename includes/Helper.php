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
 * A class that provides general helper functions to Jilt for EDD.
 *
 * @since 1.5.0-dev.2
 */
class Helper {


	/**
	 * Cleans variables using sanitize_text_field. Arrays are cleaned recursively.
	 * Non-scalar values are ignored.
	 *
	 * @since 1.5.0
	 *
	 * @param string|array $var Data to sanitize.
	 * @return string|array
	 */
	public static function jilt_clean( $var ) {

		if ( is_array( $var ) ) {
			return array_map( '\\Jilt\\EDD\\Helper:jilt_clean', $var );
		}

		return is_scalar( $var ) ? sanitize_text_field( $var ) : $var;
	}


}

