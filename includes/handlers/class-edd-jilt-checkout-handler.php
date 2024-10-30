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
 * @package   EDD-Jilt/Handlers
 * @author    Jilt
 * @copyright Copyright (c) 2015-2020, SkyVerge, Inc.
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

/**
 * Checkout Class
 *
 * Handles checkout page and orders that have been placed, but not yet paid for
 *
 * @since 1.0.0
 */
class EDD_Jilt_Checkout_Handler {


	/**
	 * Setup class
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		// handle pending payments
		add_action( 'edd_insert_payment', array( $this, 'handle_pending_payment' ) );

		// handle completed payments
		add_action( 'edd_complete_purchase', array( $this, 'handle_completed_payment' ), 99 );

		// handle updated orders
		add_action( 'edd_updated_edited_purchase', array( $this, 'handle_completed_payment' ), 99 );

		// maybe displays an email collection notice to customers, with possibility to opt out
		add_action( 'edd_purchase_form_after_email', array( $this, 'add_email_usage_notice' ) );

		// maybe display a marketing opt-in checkbox at checkout
		add_action( 'edd_purchase_form_before_submit', array( $this, 'output_marketing_consent_prompt' ), 5 );
	}


	/**
	 * Adds the email data processing notice to checkout if enabled.
	 *
	 * @internal
	 *
	 * @since 1.3.3
	 */
	public function add_email_usage_notice() {

		// add the notice if enabled and the opt out is not set
		if (    null === EDD_Jilt_Session::get_customer_email_collection_opt_out()
		     && edd_jilt()->get_integration()->show_email_usage_notice() ) :

			?>
			<p class="edd-jilt-email-usage-notice-wrap"><small><?php echo edd_jilt()->get_frontend()->get_email_usage_notice(); ?></small></p>
			<?php

		endif;
	}


	/**
	 * Shows the marketing consent opt-in checkbox.
	 *
	 * @internal
	 *
	 * @since 1.3.3
	 */
	public function output_marketing_consent_prompt() {

		if ( edd_jilt()->get_integration()->ask_consent_at_checkout() ) {

			$prompt = wp_kses_post( edd_jilt()->get_integration()->get_checkout_consent_prompt() );

			?>
			<div id="edd-jilt-marketing-consent-container">
				<label for="edd-jilt-marketing-consent">
					<input
						type="checkbox"
						id="edd-jilt-marketing-consent"
						name="edd_jilt_marketing_consent"
						value="yes"
						<?php checked( (bool) EDD_Jilt_Session::get_customer_marketing_consent() ); ?>
					/> <?php echo $prompt; ?>
				</label>
				<input
					type="hidden"
					name="edd_jilt_marketing_consent_prompt"
					value="<?php echo esc_attr( $prompt ); ?>"
				/>
			</div>
			<?php
		}
	}


	/**
	 * Returns the cart checkout URL for Jilt.
	 *
	 * Visiting this URL will load the associated cart from session/persistent cart
	 *
	 * @since 1.2.0
	 *
	 * @param string $jilt_cart_token Jilt cart token
	 * @return string the recovery URL
	 */
	public static function get_checkout_recovery_url( $jilt_cart_token ) {

		$data = array( 'cart_token' => $jilt_cart_token );

		// encode
		$data = base64_encode( wp_json_encode( $data ) );

		// add hash for easier verification that the checkout URL hasn't been tampered with
		$integration = edd_jilt()->get_integration();
		$secret      = 'secret_key' === $integration->get_auth_method() ? $integration->get_secret_key() : $integration->get_client_secret();
		$hash        = hash_hmac( 'sha256', $data, $secret );

		$params = array(
			'recover' => true,
			'token'   => rawurlencode( $data ),
			'hash'    => $hash,
		);

		// returns a URL like https://example.tld/checkout?recover=1&token=abc123&hash=xyz
		return edd_get_checkout_uri( $params );
	}


	/**
	 * Handle updating the Jilt order during checkout processing.
	 *
	 * Note that this method will be called once a checkout is processed and a
	 * pending payment is created. This *does not* mean the payment was completed.
	 *
	 * This also adds Jilt data to payment meta so that when/if a payment is actually
	 * completed, it can be marked as such in Jilt.
	 *
	 * @since 1.0.1
	 * @param int $payment_id EDD payment ID
	 */
	public function handle_pending_payment( $payment_id ) {

		if ( ! edd_jilt()->get_integration()->is_jilt_connected() ) {
			return;
		}

		$cart_token = EDD_Jilt_Session::get_cart_token();

		// bail out if this payment is not associated with a Jilt order
		if ( ! $cart_token ) {
			return;
		}

		// save Jilt order ID and cart token to order meta
		$payment = edd_get_payment( $payment_id );
		$payment->update_meta( '_edd_jilt_cart_token', $cart_token );

		// if consent was offered at checkout
		if ( edd_jilt()->get_integration()->ask_consent_at_checkout() ) {

			// note that we don't persist the consent timestamp since we use the placed_at as a proxy for that
			$payment->update_meta( '_edd_jilt_marketing_consent_accepted', isset( $_POST['edd_jilt_marketing_consent'] ) && 'yes' === $_POST['edd_jilt_marketing_consent'] ? 'yes' : 'no' );
			$payment->update_meta( '_edd_jilt_marketing_consent_notice', ! empty( $_POST['edd_jilt_marketing_consent_prompt'] ) ? sanitize_text_field( $_POST['edd_jilt_marketing_consent_prompt'] ) : edd_jilt()->get_integration()->get_checkout_consent_prompt() );
			$payment->update_meta( '_edd_jilt_marketing_consent_offered', 'yes' );

			$consented_to_marketing = isset( $_POST['edd_jilt_marketing_consent'] ) && 'yes' === $_POST['edd_jilt_marketing_consent'];
			EDD_Jilt_Session::set_customer_marketing_consent( $consented_to_marketing, $payment->user_id );

		} else {

			$payment->update_meta( '_edd_jilt_marketing_consent_offered', 'no' );
		}

		// mark as pending recovery
		if ( EDD_Jilt_Session::is_pending_recovery() ) {
			$this->mark_order_as_pending_recovery( $payment );
		}

		// update Jilt order details
		try {

			$payment = new EDD_Jilt_Payment( $payment_id );
			$this->get_api()->update_order( $cart_token, $this->get_jilt_order_data( $payment ) );

		} catch ( EDD_Jilt_API_Exception $e ) {

			edd_jilt()->get_logger()->error( "Error communicating with Jilt: {$e->getMessage()}" );
		}
	}


	/**
	 * Handle a completed payment. This method is called when a payment is marked
	 * as paid/completed. Note that the request context this method executes within
	 * may be different depending on the payment gateway used:
	 *
	 * 1) Onsite (Stripe, Braintree, etc) - executed in user context immediately after
	 * a payment is inserted, thus the user's EDD session is available
	 *
	 * 2) Offsite (PayPal standard, etc) - executed with no user context because
	 * offsite gateways typically use an IPN. This means there is no EDD session
	 * available and all data must come from the EDD payment object.
	 *
	 * @since 1.0.1
	 * @param int $payment_id Payment ID
	 */
	public function handle_completed_payment( $payment_id ) {

		if ( ! edd_jilt()->get_integration()->is_jilt_connected() ) {
			return;
		}

		$payment = new EDD_Jilt_Payment( $payment_id );

		if ( ! $cart_token = $payment->get_jilt_cart_token() ) {
			return;
		}

		// mark as recovered if pending recovery in case of resuming a pending order
		if ( EDD_Jilt_Session::is_pending_recovery() || $this->is_order_pending_recovery( $payment ) ) {
			$this->mark_order_as_recovered( $payment );
		}

		// update the Jilt order to indicate the order has been placed
		try {

			$this->get_api()->update_order( $cart_token, $this->get_jilt_order_data( $payment ) );

		} catch ( EDD_Jilt_API_Exception $e ) {

			edd_jilt()->get_logger()->error( "Error communicating with Jilt: {$e->getMessage()}" );
		}

		// remove Jilt order ID from session and user meta
		EDD_Jilt_Session::unset_jilt_order_data();
	}


	/**
	 * Checks whether an order is pending recovery.
	 *
	 * @since 1.4.0
	 *
	 * @param \EDD_Payment $payment the payment object
	 * @return bool
	 */
	public function is_order_pending_recovery( $payment ) {

		return (bool) $payment->get_meta( '_edd_jilt_pending_recovery', true );
	}


	/**
	 * Checks if an order is recovered.
	 *
	 * @since 1.4.0
	 *
	 * @param \EDD_Payment $payment the payment object
	 * @return bool
	 */
	public function is_order_recovered( $payment ) {

		return (bool) $payment->get_meta( '_edd_jilt_recovered', true );
	}


	/**
	 * Marks an order as pending recovery.
	 *
	 * @since 1.4.0
	 *
	 * @param \EDD_Payment $payment the payment object
	 */
	public function mark_order_as_pending_recovery( $payment ) {

		$payment->update_meta( '_edd_jilt_pending_recovery', true );
	}


	/**
	 * Marks an order as recovered by Jilt.
	 *
	 * @since 1.3.0
	 *
	 * @param \EDD_Payment $payment the payment object
	 */
	protected function mark_order_as_recovered( $payment ) {

		if ( $this->is_order_recovered( $payment ) ) {
			return;
		}

		delete_post_meta( $payment->ID, '_edd_jilt_pending_recovery' );
		$payment->update_meta( '_edd_jilt_recovered', true );

		$payment->add_note( __( 'Recovered by Jilt.', 'jilt-for-edd' ) );

		/**
		 * Fires when an order is recovered by Jilt.
		 *
		 * @since 1.3.0
		 *
		 * @param \EDD_Payment $payment the payment object
		 */
		do_action( 'edd_jilt_order_recovered', $payment );
	}


	/**
	 * Get the order data for updating a Jilt order via the API
	 *
	 * @since 1.2.0
	 *
	 * @param EDD_Jilt_Payment $payment
	 * @return array
	 */
	protected function get_jilt_order_data( $payment ) {

		$data = $payment->get_jilt_order_data();

		if ( isset( $_GET['edd-listener'] ) ) {
			// this indicates an IPN request
			return $data;
		}

		$data['client_session'] = EDD_Jilt_Session::get_client_session();

		if ( $browser_ip = edd_get_ip() ) {
			$data['client_details']['browser_ip'] = $browser_ip;
		}
		if ( ! empty( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
			$data['client_details']['accept_language'] = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
		}
		if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$data['client_details']['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
		}

		return $data;
	}


	/**
	 * Helper method to improve the readability of methods calling the API
	 *
	 * @since 1.0.0
	 * @return \EDD_Jilt_API instance
	 */
	protected function get_api() {
		return edd_jilt()->get_integration()->get_api();
	}


}
