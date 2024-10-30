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
 * @package   EDD-Jilt/Cron
 * @author    Jilt
 * @copyright Copyright (c) 2015-2020, SkyVerge, Inc.
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

/**
 * The Jilt cron class.
 *
 * @since 1.5.0
 */
class EDD_Jilt_Cron {


	/** @var EDD_Jilt_Admin_Tools instance */
	protected $admin_tools;


	/**
	 * Cron class constructor.
	 *
	 * @since 1.5.0
	 */
	public function __construct() {

		// schedule discount cleanup
		add_action( 'init', [ $this, 'schedule_discount_cleanup' ] );

		// clean up expired, unused discounts
		add_action( 'edd_jilt_discount_cleanup', [ $this, 'cleanup_discounts' ] );
	}


	/**
	 * Gets or creates the instance of EDD_Jilt_Admin_Tools.
	 *
	 * @since 1.5.0
	 *
	 * @return \EDD_Jilt_Admin_Tools
	 */
	protected function get_admin_tools() {

		if ( null === $this->admin_tools ) {

			require_once edd_jilt()->get_plugin_path() . '/includes/admin/class-edd-jilt-admin-tools.php';

			$this->admin_tools = new \EDD_Jilt_Admin_Tools();
		}

		return $this->admin_tools;
	}


	/**
	 * Add the scheduled discount cleanup event.
	 *
	 * @since 1.5.0
	 */
	public function schedule_discount_cleanup() {

		if ( ! wp_next_scheduled( 'edd_jilt_discount_cleanup' ) ) {

			// using a core schedule, don't forget to add a custom interval if we change this
			wp_schedule_event( strtotime( 'now +5 minutes' ), 'twicedaily', 'edd_jilt_discount_cleanup' );
		}
	}


	/**
	 * Runs the scheduled cleanup task.
	 *
	 * @since 1.5.0
	 */
	public function cleanup_discounts() {

		$admin_tools = $this->get_admin_tools();

		if ( $admin_tools ) {

			EDD_Jilt_Discount_Handler::delete_discounts();

			// schedule another run if we still have discounts to clear
			if ( EDD_Jilt_Discount_Handler::site_has_jilt_discounts() ) {
				wp_schedule_single_event( strtotime( 'now +5 minutes' ), 'edd_jilt_discount_cleanup' );
			}
		}
	}


}
