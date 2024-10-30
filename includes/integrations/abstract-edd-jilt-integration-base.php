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
 * Base Jilt for EDD integration class.
 *
 * @since 1.2.0
 */
abstract class EDD_Jilt_Integration_Base {


	/**
	 * Returns the title for this integration, e.g. 'Software Licensing'.
	 *
	 * @since 1.2.0
	 *
	 * @return string integration title
	 */
	abstract public function get_title();


	/**
	 * Checks whether this integration is active.
	 *
	 * Integrations are assumed to be active so long as they are instantiated,
	 * however certain integrations may need to be instantiated/operational,
	 * but in a more "passive" manner if the integration is decoupled from the
	 * integratee, and the integratee is not activated.
	 *
	 * E.g. the Software Licensing integration, which should continue to
	 * include the relevant Software Licensing custom post meta as order
	 * properties, even if the Software Licensing plugin is not active, but not
	 * necessarily be listed as an "active" integration.
	 *
	 * Returning false here will keep this integration from being listed as
	 * "active" (future work once UI support is added).
	 *
	 * @since 1.2.0
	 *
	 * @return boolean
	 */
	public function is_active() {
		return true;
	}


}
