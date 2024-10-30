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
 * @package   EDD-Jilt/Integrations
 * @author    Jilt
 * @copyright Copyright (c) 2015-2020, SkyVerge, Inc.
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

/**
 * Manages Jilt integrations with 3rd party plugins.
 *
 * @since 1.2.0
 */
class EDD_Jilt_Integrations {


	private $integrations;

	public function __construct() {

		$load_integrations = [
			'EDD_Jilt_Free_Downloads_Integration',
			'EDD_Jilt_Software_Licensing_Integration',
			'EDD_Jilt_Simple_Shipping_Integration'
		];

		/**
		 * Filters the integrations to register with Jilt.
		 *
		 * Allows third party Jilt integrations to be registered.
		 *
		 * @since 1.2.0
		 *
		 * @param string[]|\EDD_Jilt_Integration[] $integrations array of string integration class names, or \EDD_Jilt_Integration integration instances
		 */
		$load_integrations = apply_filters( 'edd_jilt_integrations', $load_integrations );

		// Load gateways in order
		foreach ( $load_integrations as $integration ) {
			$load_integration = is_string( $integration ) ? new $integration() : $integration;
			$this->integrations[] = $load_integration;
		}
	}


}
