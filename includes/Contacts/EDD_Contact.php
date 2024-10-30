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

namespace Jilt\EDD\Contacts;

defined( 'ABSPATH' ) or exit;

/**
 * EDD Jilt contact object.
 *
 * {BR 2019-09-28} This should likely implement an interface defining what a local
 *  Jilt contact looks like, and some of these methods could move to a trait.
 *
 * @since 1.5.0
 */
class EDD_Contact extends \EDD_Customer {


	/** @var string email address to subscribe with */
	protected $subscribe_email;

	/** @var string first name to subscribe with */
	protected $subscribe_first_name;

	/** @var string last name to subscribe with */
	protected $subscribe_last_name;


	/**
	 * Constructs the EDD Contact.
	 *
	 * @since 1.5.1
	 *
	 * @param int|string|bool $_id_or_email Contact ID, User ID, or email address
	 * @param bool $by_user_id whether to lookup by user ID or contact ID
	 */
	public function __construct( $_id_or_email = false, $by_user_id = false ) {

		parent::__construct( $_id_or_email, $by_user_id );

		$this->subscribe_email      = $this->email;
		$this->subscribe_first_name = $this->get_first_name();
		$this->subscribe_last_name  = $this->get_last_name();
	}


	/**
	 * Sets the email to use for subscription.
	 *
	 * @since 1.5.1
	 *
	 * @param string $email email address
	 */
	public function set_subscribe_email( $email ) {

		$this->subscribe_email = $email;
	}


	/**
	 * Sets the first name to use for subscription.
	 *
	 * @since 1.5.1
	 *
	 * @param string $first_name
	 */
	public function set_subscribe_first_name( $first_name ) {

		$this->subscribe_first_name = $first_name;
	}


	/**
	 * Sets the last name to use for subscription.
	 *
	 * @since 1.5.1
	 *
	 * @param string $last_name
	 */
	public function set_subscribe_last_name( $last_name ) {

		$this->subscribe_last_name = $last_name;
	}


	/** Jilt API interactions *************************************************/


	/**
	 * Opts the contact into marketing emails, and optionally assigns lists and / or tags.
	 *
	 * @since 1.5.0
	 *
	 * @param array $list_ids the Jilt list IDs the contact should be added to
	 * @param array $tags the tags that should be added to the contact
	 * @return array success result and message or object
	 */
	public function subscribe( array $list_ids = [], array $tags = [] ) {

		if ( empty( $this->subscribe_email ) ) {

			return [
				'result' => false,
				'message' => 'Must have an email address to subscribe'
			];
		}

		$api  = edd_jilt()->get_integration()->get_api();
		$data = [ 'accepts_marketing' => true, ];

		if ( ! $api ) {

			$success = [
				'result'  => false,
				'message' => 'API unavailable',
			];

		} else {

			if ( ! empty( $list_ids ) ) {
				$data['list_ids'] = $list_ids;
			}

			if ( ! empty( $tags ) ) {
				$data['tags'] = $tags;
			}

			// see if the contact exists in Jilt first
			if ( ( ! $this->is_guest() && $this->get_jilt_remote_id() && $this->email === $this->subscribe_email ) || $this->fetch_remote_contact() ) {

				try {

					$response = $api->update_customer( $this->subscribe_email, $data );
					$success  = [
						'result'  => true,
						'message' => $response,
					];

				} catch ( \EDD_Jilt_API_Exception $e ) {

					$success = [
						'result'  => false,
						'message' => $e->getMessage(),
					];
				}

			} else {

				$data = array_merge( $data, [
					'email'          => $this->subscribe_email,
					'first_name'     => $this->subscribe_first_name,
					'last_name'      => $this->subscribe_last_name,
					'contact_source' => 'jilt-for-edd',
				] );

				try {

					$response = $api->create_customer( $data );
					$success  = [
						'result'  => true,
						'message' => $response,
					];

				} catch ( \EDD_Jilt_API_Exception $e ) {

					$success = [
						'result'  => false,
						'message' => $e->getMessage(),
					];
				}
			}
		}

		return $success;
	}


	/** Handle contact details ************************************************/


	/**
	 * Gets the locally stored Jilt remote ID.
	 *
	 * @since 1.5.0
	 *
	 * @return int the Jilt contact ID
	 */
	public function get_jilt_remote_id() {

		return (int) $this->get_meta( '_edd_jilt_contact_id' );
	}


	/**
	 * Stores the Jilt remote ID.
	 *
	 * @since 1.5.0
	 *
	 * @param int $value the Jilt contact ID
	 */
	public function set_jilt_remote_id( $value ) {

		$this->update_meta( '_edd_jilt_contact_id', $value );
	}


	/**
	 * Fetches and saves the Jilt ID from the remote API.
	 *
	 * @since 1.5.0
	 *
	 * @param bool $refresh true if the ID should be overridden
	 */
	public function fetch_jilt_remote_id( $refresh = false ) {

		// proceed if we don't have a local ID or we're forcing refresh
		if ( $refresh || ! $this->get_jilt_remote_id() ) {

			$jilt_contact = $this->fetch_remote_contact();

			if ( $jilt_contact ) {
				$this->set_jilt_remote_id( $jilt_contact->id );
			}
		}
	}


	/**
	 * Gets contact's locally stored Jilt opt in data.
	 *
	 * @since 1.5.0
	 *
	 * @return bool whether the contact is opted in
	 */
	public function get_jilt_opt_in() {

		return 'yes' === $this->get_meta( '_edd_jilt_marketing_email_consent' );
	}


	/**
	 * Sets contact's locally stored Jilt opt in data.
	 *
	 * @since 1.5.0
	 *
	 * @param int $value the Jilt contact ID
	 */
	public function set_jilt_opt_in( $value ) {

		// we expect a "pretty" value...for now
		if ( is_bool( $value ) ) {
			$value = $value ? 'yes' : 'no';
		}

		$this->update_meta( '_edd_jilt_marketing_email_consent', $value );
	}


	/**
	 * Sets the Jilt opt in value from the API.
	 *
	 * @since 1.5.0
	 *
	 * @param bool $refresh true to force pulling the remote value
	 */
	public function fetch_jilt_opt_in( $refresh = false ) {

		// proceed if we haven't stored opt in data or we're forcing refresh
		if ( $refresh  || ! $this->get_jilt_opt_in() ) {

			$jilt_contact = $this->fetch_remote_contact();

			if ( $jilt_contact ) {
				$this->set_jilt_opt_in( $jilt_contact->accepts_marketing );
			}
		}
	}


	/**
	 * Gets the remote contact data from Jilt.
	 *
	 * @since 1.5.0
	 *
	 * @return bool|\stdClass the contact data
	 */
	public function fetch_remote_contact() {

		$api = edd_jilt()->get_integration()->get_api();

		if ( $api ) {

			try {

				return $api->get_customer( $this->subscribe_email );

			} catch ( \EDD_Jilt_API_Exception $e ) {

				return false;
			}
		}

		return false;
	}


	/** Set additional contact details ****************************************/


	/**
	 * Stores the GDPR consent data locally. This should be passed to Jilt when
	 * accepted by the API.
	 *
	 * @since 1.5.0
	 *
	 * @param string $context
	 * @param string $consent_text
	 * @param bool $ip_address
	 */
	public function store_opt_in_details( $context = '', $consent_text = 'Subscribe', $ip_address = false ) {

		$this->update_meta( '_edd_jilt_marketing_email_consent', 'yes' );
		$this->update_meta( '_edd_jilt_consent_context', $context );
		$this->update_meta( '_edd_jilt_consent_timestamp', date( 'Y-m-d\TH:i:s\Z' ) );
		$this->update_meta( '_edd_jilt_consent_notice', $consent_text );

		if ( $ip_address ) {
			$this->update_meta( '_edd_jilt_consent_ip_address', $ip_address );
		}
	}


	/** Contact details helpers ***********************************************/


	/**
	 * Updates the contact email address.
	 *
	 * @since 1.5.0
	 *
	 * @param string $email the email value
	 * @return bool|string success or error message
	 */
	public function set_email( $email ) {

		$result = $this->add_email( $email, true );

		return $result ? $result : 'Error setting contact email address';
	}


	/**
	 * Gets the contact last name.
	 *
	 * @since 1.5.0
	 *
	 * @return string
	 */
	public function get_first_name() {

		return $this->get_meta( '_edd_jilt_first_name' );
	}


	/**
	 * Updates the contact first name.
	 *
	 * @since 1.5.0
	 *
	 * @param string $name the name value
	 */
	public function set_first_name( $name ) {

		$this->update_meta( '_edd_jilt_first_name', $name );
	}


	/**
	 * Gets the contact last name.
	 *
	 * @since 1.5.0
	 *
	 * @return string
	 */
	public function get_last_name() {

		return $this->get_meta( '_edd_jilt_last_name' );
	}


	/**
	 * Updates the contact last name.
	 *
	 * @since 1.5.0
	 *
	 * @param string $name the name value
	 */
	public function set_last_name( $name ) {

		$this->update_meta( '_edd_jilt_last_name', $name );
	}


	/**
	 * Whether this is a guest customer or not.
	 *
	 * @since 1.5.0
	 *
	 * @return bool
	 */
	public function is_guest() {

		return 0 === $this->id;
	}

}
