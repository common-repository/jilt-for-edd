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
 * @package   EDD-Jilt/Frontend
 * @author    Jilt
 * @copyright Copyright (c) 2015-2020, SkyVerge, Inc.
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

/**
 * Frontend Class
 *
 * Handles post-checkout registration process to show prompts to guest purchasers, and
 * create accounts for them with one click after purchasing.
 *
 * @since 1.2.0
 */
class EDD_Jilt_Frontend {


	/** @var array $messages messages to print */
	protected $messages = array();

	/**
	 * EDD_Jilt_Frontend constructor.
	 *
	 * @since 1.2.0
	 */
	public function __construct() {

		add_action( 'init', array( $this, 'init' ) );
	}


	/**
	 * Adds delayed hooks.
	 *
	 * @since 1.4.0
	 */
	public function init() {

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts_styles' ) );

		add_shortcode( 'jilt_subscribe', [ $this, 'add_shortcode' ] );

		add_filter( 'edd_template_paths', [ $this, 'add_edd_jilt_to_edd_template_paths' ] );

		// show a data collection notice when users log in
		if ( ! edd_jilt()->get_integration()->is_disabled() && edd_jilt()->get_integration()->show_email_usage_notice() ) {
			add_action( 'wp_footer', array( $this, 'output_logged_in_data_notice_html' ) );
		}

		if ( edd_jilt()->get_integration()->allow_post_checkout_registration() ) {

			// maybe render the prompt on the "purchase confirmation" page
			add_action( 'edd_payment_receipt_before', array( $this, 'maybe_render_registration_prompt' ), 1 );

			// register customers
			add_action( 'template_redirect', array( $this, 'maybe_register_new_customer' ) );

			// add params to identify when the user is verified to auto login
			add_filter( 'edd_user_account_verified_redirect', array( $this, 'add_user_verification_params' ) );

			// if a user is verified from our registration, log them in
			add_action( 'template_redirect', array( $this, 'maybe_login_verified_user' ) );

			// output our error and success messages
			add_action( 'get_template_part_history',   array( $this, 'print_messages' ), 1, 2 );
			add_action( 'get_template_part_shortcode', array( $this, 'print_messages' ), 1, 2 );
		}
	}


	/**
	 * Loads front end styles.
	 *
	 * @since 1.3.3
	 */
	private function load_styles() {

		if (    edd_jilt()->get_integration()->capture_email_on_add_to_cart( 'frontend' )
		     || edd_jilt()->get_integration()->show_email_usage_notice() ) {

			wp_enqueue_style( 'dashicons' );
			wp_enqueue_style( 'edd-jilt', edd_jilt()->get_plugin_url() . '/assets/css/frontend/edd-jilt-frontend.min.css', array(), edd_jilt()->get_version() );
		}
	}


	/**
	 * Returns the storefront JS script url
	 *
	 * @since 1.4.4
	 * @return string 'https://js.jilt.com/storefront/v1/jilt.js' by default
	 */
	public function get_storefront_js_url() {

		/**
		 * Filters the storefront js script url
		 *
		 * @since 1.4.4
		 *
		 * @param string storefront JS script url
		 * @param \EDD_Jilt plugin instance
		 */
		return apply_filters( 'edd_jilt_storefront_js_url', 'https://js.jilt.com/storefront/v1/jilt.js', $this );
	}


	/**
	 * Loads front end scripts.
	 *
	 * @since 1.3.3
	 */
	private function load_scripts() {

		wp_enqueue_script( 'jquery-block-ui', edd_jilt()->get_plugin_url() . '/assets/js/lib/jquery-blockui/jquery.blockUI.min.js', [ 'jquery' ], edd_jilt()->get_version(), true );

		// add JS for subscribe form; used for widget and shortcode, so always load it
		wp_enqueue_script( 'edd-jilt-subscribe-form', edd_jilt()->get_plugin_url() . '/assets/js/frontend/edd-jilt-subscribe-form.min.js', [ 'jquery-block-ui' ], edd_jilt()->get_version(), true );

		wp_localize_script( 'edd-jilt-subscribe-form', 'edd_jilt_subscribe', [
			'nonce'  => wp_create_nonce( 'edd_jilt_subscribe' ),
			'loader' => edd_jilt()->get_plugin_url() . '/assets/img/ajax-loader.gif',
		] );

		// only load javascript once
		if ( ! wp_script_is( 'edd-jilt', 'enqueued' ) ) {

			wp_enqueue_script( 'edd-jilt', $this->get_storefront_js_url(), array( 'jquery' ), edd_jilt()->get_version(), true );

			$plugin      = edd_jilt();
			$integration = $plugin->get_integration();
			$params      = $integration->get_storefront_params();

			// convert yes/no values into booleans
			if ( ! empty( $params ) ) {
				foreach ( $params as $k => $v ) {
					if ( in_array( $v, array( 'yes', 'no' ), true ) ) {
						$params[ $k ] = 'yes' === $v;
					}
				}
			}

			try {
				$public_key = $integration->get_public_key();
			} catch ( EDD_Jilt_API_Exception $exception ) {
				$public_key = '';
			}

			// script data
			$params = array_merge( array(
				'public_key'               => $public_key,
				'payment_field_mapping'    => EDD_Jilt_Payment::get_jilt_payment_field_mapping(),
				'address_field_mapping'    => EDD_Jilt_Payment::get_address_field_mapping(),
				'cart_hash'                => $plugin->get_cart_handler()->get_cart_hash(),
				'cart_token'               => EDD_Jilt_Session::get_cart_token(),
				'ajax_url'                 => edd_get_ajax_url(),
				'nonce'                    => wp_create_nonce( 'jilt-for-edd' ),
				'log_threshold'            => $plugin->get_logger()->get_threshold(),
				'x_jilt_shop_domain'       => $plugin->get_shop_domain(),
				'shop_uuid'                => $integration->get_linked_shop_uuid(),
				'show_email_usage_notice'  => $integration->show_email_usage_notice(),
				'popover_dismiss_message'  => $this->get_popover_dismiss_message(),
				'platform'                 => 'edd',
				'api_url'                  => sprintf( 'https://%s', edd_jilt()->get_api_hostname() ),
			), $params );

			if ( null !== EDD_Jilt_Session::get_customer_email_collection_opt_out() ) {
				$params['email_collection_opt_out'] = (bool) EDD_Jilt_Session::get_customer_email_collection_opt_out();
			}

			if ( $integration->capture_email_on_add_to_cart( 'frontend' ) ) {

				/**
				 * Filters the popover title for the add to cart email capture form.
				 *
				 * @since 1.3.0
				 *
				 * @param string $title the popover title
				 */
				$params['add_to_cart_title'] = (string) apply_filters( 'edd_jilt_add_to_cart_popover_title', __( 'Reserve this item in your cart!', 'jilt-for-edd' ) );

				/**
				 * Filters the 'Continue' button label for the add to cart email capture form.
				 *
				 * @since 1.3.0
				 *
				 * @param string $label the button label
				 */
				$params['add_to_cart_continue'] = (string) apply_filters( 'edd_jilt_add_to_cart_continue_button_label', __( 'Continue', 'jilt-for-edd' ) );

			} else {

				$params['capture_email_on_add_to_cart'] = false;
			}

			wp_localize_script( 'edd-jilt', 'jiltStorefrontParams', $params );
		}
	}


	/**
	 * Enqueues frontend scripts and styles.
	 *
	 * @internal
	 *
	 * @since 1.3.3
	 */
	public function enqueue_scripts_styles() {

		if ( edd_jilt()->get_integration()->is_jilt_connected() ) {

			$this->load_styles();
			$this->load_scripts();
		}
	}

	/**
	 * Renders the Jilt subscribe form.
	 *
	 * @since 1.5.0-dev.2
	 *
	 * @param array $atts the shortcode attributes
	 * @return string buffered shortcode contents
	 */
	public function add_shortcode( $atts ) {

		$a = shortcode_atts( [
			'show_names'    => 'no',
			'require_names' => 'no',
			'button_text'   => __( 'Subscribe', 'jilt-for-edd' ),
			'list_ids'      => '',
			'tags'          => '',
		], $atts );

		ob_start();

		edd_jilt_subscribe_form( [
			'show_names'    => 'yes' === $a['show_names'],
			'require_names' => 'yes' === $a['require_names'],
			'button_text'   => $a['button_text'],
			'list_ids'      => array_map( 'trim', explode( ',', $a['list_ids'] ) ),
			'tags'          => array_map( 'trim', explode( ',', $a['tags'] ) ),
		] );

		return ob_get_clean();
	}


	/**
	 * Adds Jilt for EDD to the EDD template paths.
	 *
	 * @see edd_get_theme_template_paths()
	 *
	 * @since 1.5.0
	 *
	 * @param array $paths associative array of template paths
	 * @return array
	 */
	public function add_edd_jilt_to_edd_template_paths( $paths ) {

		$paths[90] = edd_jilt()->get_plugin_path() . '/templates';

		return $paths;
	}


	/**
	 * Renders the logged in user data collection notice one time, if enabled.
	 *
	 * @since 1.3.3
	 */
	public function output_logged_in_data_notice_html() {

		$user = wp_get_current_user();

		// check if we should be showing a logged in notice; don't show it to shop employees
		if ( edd_jilt()->get_integration()->show_email_usage_notice() && $user->ID > 0 && ! current_user_can( 'manage_options' ) ) {

			$opt_out = EDD_Jilt_Session::get_customer_email_collection_opt_out( $user->ID );

			// only render the notice if this meta has not been set previously
			if ( null === $opt_out ) {

				/* translators: Placeholders: %1$s, %3$s - opening HTML <a> link tag, %2$s - closing HTML </a> link tag */
				$message = sprintf( esc_html__( 'Your cart is saved while logged in so we can send you email reminders about it. %1$sGot it!%2$s %3$sNo thanks.%2$s', 'jilt-for-edd' ),
					'<a href="#" class="dismiss-link">','</a>', '<a href="#" class="logged-in-notice js-edd-jilt-email-collection-opt-out">'
				);

				echo '<div class="edd edd-jilt edd-jilt-email-usage-notice">' . $message . '</div>';

				// once we've shown this notice, set the meta to false (opted in)
				EDD_Jilt_Session::set_customer_email_collection_opt_out( false, $user->ID );
			}
		}
	}


	/**
	 * Gets the text for the add-to-cart popover dismiss message.
	 *
	 * @since 1.4.3
	 *
	 * @return string
	 */
	public function get_popover_dismiss_message() {

		if ( edd_jilt()->get_integration()->show_email_usage_notice() ) {

			$message = $this->get_email_usage_notice( array( 'js-jilt-popover-bypass' ) );

		} else {

			$message = edd_jilt()->get_integration()->get_storefront_param( 'popover_dismiss_message', esc_html__( "No thanks, I'll enter my email later.", 'jilt-for-edd' ) );

			/**
			 * Filters the "enter it later" link text for the add to cart email capture form.
			 *
			 * @since 1.3.0
			 *
			 * @param string $dismiss_text cart popover dismissal text
			 */
			$message = (string) apply_filters( 'edd_jilt_add_to_cart_popover_dismiss_text', $message );
		}

		return $message;
	}


	/**
	 * Returns the email usage notice.
	 *
	 * @since 1.3.3
	 *
	 * array $link_classes classes to add to the usage notice link
	 * @return string HTML
	 */
	public function get_email_usage_notice( $link_classes = array() ) {

		$link_classes[] = 'js-edd-jilt-email-collection-opt-out';

		$notice = sprintf(
			/* translators: Placeholders: %1$s - opening HTML <a> link tag, %2$s - closing HTML </a> link tag */
			esc_html__( 'Your email and cart are saved so we can send you email reminders about this order. %1$sNo thanks%2$s.', 'jilt-for-edd' ),
			'<a href="#" class="' . esc_attr( implode( ' ', $link_classes ) ) . '">', '</a>'
		);

		/**
		 * Filters the email usage notice contents.
		 *
		 * @since 1.3.3
		 *
		 * @param string $notice notice text
		 * @param string $link_classes the CSS classes the opt out link should have
		 */
		$notice = (string) apply_filters( 'edd_jilt_email_usage_notice', $notice, $link_classes );

		return '<span class="edd-jilt-email-usage-notice">' . $notice . '</span>';
	}


	/**
	 * Checks the EDD payment confirmation page to render registration prompt immediately.
	 *
	 * @since 1.2.0
	 *
	 * @param \WP_Post $payment the payment post object
	 */
	public function maybe_render_registration_prompt( $payment ) {

		if ( $payment instanceof WP_Post ) {
			$payment = edd_get_payment( $payment->ID );
		}

		$billing_email = $payment->get_meta( '_edd_payment_user_email' );
		$existing_user = get_user_by( 'email', $billing_email );

		if ( ! is_user_logged_in() && ! $existing_user ) {

			// do not use a nonce, favoring order-specific validation
			// this way, a user can't just get a valid nonce, then change the payment ID in the registration link
			// we probably don't need this given EDD is going to verify the user, but it doesn't hurt anything
			if ( ! $token = $payment->get_meta( '_edd_jilt_post_checkout_registration' ) ) {
				$token = edd_jilt()->generate_random_token( 32, false );
				$payment->update_meta( '_edd_jilt_post_checkout_registration', $token );
			}

			$message = $this->render_registration_prompt( $payment->ID, $token );

			// don't use self::add_message because this callback fires after we've printed messages
			echo "<div class='edd-alert edd-alert-info'>{$message}</div>";
		}
	}


	/**
	 * Renders the registration prompt on the payment confirmation page.
	 *
	 * @since 1.2.0
	 *
	 * @param int $payment_id the ID currently placed order
	 * @param string $token the registration token for the order
	 * @return string the message to render
	 */
	protected function render_registration_prompt( $payment_id, $token ) {

		$url = add_query_arg(
			array(
				'registration_order_id' => $payment_id,
				'registration_token'    => $token,
			),
			trailingslashit( get_permalink( edd_get_option( 'purchase_history_page' ) ) )
		);

		$message  = __( 'Ensure checkout is fast and easy next time! Create an account and we\'ll save your details from this order.', 'jilt-for-edd' );
		$message .= ' <a class="edd-submit" href="' . esc_url( $url ) . '">' . esc_html__( 'Create Account', 'jilt-for-edd' ) . '&rarr;</a>';

		return $message;
	}


	/**
	 * Registers a new customer if "create" link is valid.
	 *
	 * @since 1.2.0
	 */
	public function maybe_register_new_customer() {

		if ( ! edd_is_purchase_history_page() || ! isset( $_GET['registration_order_id'] ) ) {
			return;
		}

		// now we have the order ID param, but not a token, boot this faker!
		if ( ! isset( $_GET['registration_token'] ) ) {
			$this->add_message( __( 'Whoops, looks like this registration link is not valid.', 'jilt-for-edd' ), 'error' );
			return;
		}

		$order_id = (int) $_GET['registration_order_id'];
		$token    = sanitize_text_field( $_GET['registration_token'] );

		try {

			$user = $this->process_post_checkout_registration( $order_id, $token );

			/* translators: Placeholder: %s - first name */
			$this->add_message( sprintf( __( 'Welcome, %s! Please check your email to verify your account.', 'jilt-for-edd' ), $user->first_name ), 'success' );
			return;

		} catch ( EDD_Jilt_Plugin_Exception $e ) {
			$this->add_message( $e->getMessage(), 'error' );
			return;
		}
	}


	/**
	 * Validate the create account token for the order, and create a customer if valid.
	 *
	 * @since 1.2.0
	 *
	 * @param int $payment_id the ID of the payment that generated the registration prompt
	 * @param string $token the registration token to validate
	 * @throws \EDD_Jilt_Plugin_Exception
	 * @return WP_User the newly created user
	 */
	protected function process_post_checkout_registration( $payment_id, $token ) {

		$payment = edd_get_payment( $payment_id );

		if ( ! $payment instanceof EDD_Payment ) {
			throw new EDD_Jilt_Plugin_Exception( __( 'This order does not exist; it may have been deleted. Please register manually.', 'jilt-for-edd' ) );
		}

		$stored_token = $payment->get_meta( '_edd_jilt_post_checkout_registration' );

		// check the token in the URL with the order's stored token
		if ( ! $stored_token || $token !== $stored_token ) {
			throw new EDD_Jilt_Plugin_Exception( __( 'Invalid registration link. Please register manually.', 'jilt-for-edd' ) );
		}

		$email = $payment->get_meta( '_edd_payment_user_email' );

		// prep any consent or opt-out values that are in the session for transfer to the new user
		$email_collection_opt_out = EDD_Jilt_Session::get_customer_email_collection_opt_out();
		$marketing_consent        = EDD_Jilt_Session::get_customer_marketing_consent();

		/**
		 * Fires before creating a new customer via the payment confirmation page.
		 *
		 * @since 1.2.0
		 *
		 * @param int $payment_id the payment ID
		 * @param string $email the billing email for the new customer
		 */
		do_action( 'edd_jilt_before_post_checkout_registration', $payment->ID, $email );

		// EDD will link all historical purchases when we register the user
		$user_id = $this->create_user( $email );

		// we won't be sending an account creation email here since the "verify your account" email is sent
		// we'll prompt for a password change once that's done instead

		if ( is_wp_error( $user_id ) ) {
			throw new EDD_Jilt_Plugin_Exception( $user_id->get_error_message() );
		}

		// update some info for the newly registered user
		$user_id = wp_update_user( array(
			'ID'         => $user_id,
			'first_name' => $payment->first_name,
			'last_name'  => $payment->last_name,
			'role'       => 'subscriber',
		) );

		// don't need this any more
		$payment->delete_meta( '_edd_jilt_post_checkout_registration' );

		// multisite: ensure user exists on current site, if not, add them before allowing login
		if ( $user_id && is_multisite() && is_user_logged_in() && ! is_user_member_of_blog() ) {
			add_user_to_blog( get_current_blog_id(), $user_id, 'subscriber' );
		}

		// make sure we can identify this user later and log them in
		update_user_meta( $user_id, '_edd_jilt_registered_user_token', edd_jilt()->generate_random_token( 32, false ) );

		if ( ! is_null( $email_collection_opt_out ) ) {
			EDD_Jilt_Session::set_customer_email_collection_opt_out( $email_collection_opt_out, $user_id );
		}

		if ( ! is_null( $marketing_consent ) ) {
			EDD_Jilt_Session::set_customer_marketing_consent( $marketing_consent, $user_id );
		}

		$user = get_userdata( $user_id );

		/**
		 * Fires after creating a new customer via the payment confirmation page.
		 *
		 * @since 1.2.0
		 *
		 * @param int $payment_id the order ID
		 * @param \WP_User $user the newly created user
		 */
		do_action( 'edd_jilt_after_post_checkout_registration', $payment->ID, $user );

		return $user;
	}


	/**
	 * Adds additional user verification parameters to automatically log in users we've registered.
	 *
	 * @since 1.2.0
	 *
	 * @param string $url the verification URL
	 * @return string updated URL
	 */
	public function add_user_verification_params( $url ) {

		// we can directly use this, the filter we're hooked into is run after verifying the user
		$user_id = (int) $_GET['user_id'];

		if ( $login_token = get_user_meta( $user_id, '_edd_jilt_registered_user_token', true ) ) {

			$url = add_query_arg(
				array(
					'login_token'   => $login_token,
					'login_user_id' => $user_id,
				),
				$url
			);
		}

		return $url;
	}


	/**
	 * Automatically log in users when clicking the verification link if Jilt has registered them.
	 *
	 * @since 1.2.0
	 */
	public function maybe_login_verified_user() {

		// only attempt this if verification comes from a Jilt-registered user
		if ( edd_is_purchase_history_page() && isset( $_GET['edd-verify-success'], $_GET['login_user_id'], $_GET['login_token'] ) && '1' === $_GET['edd-verify-success'] ) {

			$user_id = (int) $_GET['login_user_id'];
			$token   = sanitize_text_field( $_GET['login_token'] );

			// if the URL token matches the user's token, log in automatically
			if ( $token && $token === get_user_meta( $user_id, '_edd_jilt_registered_user_token', true ) ) {

				wp_set_current_user( $user_id );
				wp_set_auth_cookie( $user_id, true );

				// don't need this flag any longer
				delete_user_meta( $user_id, '_edd_jilt_registered_user_token' );

				$user = get_userdata( $user_id );

				/** this hook is documented in wp-includes/user.php */
				do_action( 'wp_login', $user->user_login, $user );

				/* translators: Placeholders: %1$s - <a>, %2$s - </a> */
				$this->add_message( sprintf( __( 'Welcome! Please %1$sset your password%2$s, and thanks for registering!', 'jilt-for-edd' ), '<a href="' . esc_url( wp_lostpassword_url() ) . '" target="_blank">', '</a>' ), 'success' );

			} else {
				/* translators: Placeholders: %1$s - <a>, %2$s - </a> */
				$this->add_message( sprintf( __( 'Cannot log in automatically. Please %1$sre-set your password%2$s to log in manually.', 'jilt-for-edd' ), '<a href="' . esc_url( wp_lostpassword_url() ) . '" target="_blank">', '</a>' ), 'error' );
			}
		}
	}


	/** Helper methods **************************************************/


	/**
	 * Helper to create a new user.
	 *
	 * @since 1.2.0
	 *
	 * @param string $email user's billing email
	 * @return int|WP_Error user ID or error depending on success
	 */
	protected function create_user( $email ) {

		$username = sanitize_user( current( explode( '@', $email ) ), true );

		// ensure username is unique
		$append     = 1;
		$o_username = $username;

		while ( username_exists( $username ) ) {
			$username = $o_username . $append;
			$append++;
		}

		return wp_create_user( $username, edd_jilt()->generate_random_token(), $email );
	}


	/**
	 * Adds a message to be output.
	 *
	 * @since 1.2.0.1
	 *
	 * @param string $message the messsage text
	 * @param string $type message type: success, error, warn, info
	 * @return string formatted html
	 */
	protected function add_message( $message, $type = 'info' ) {

		$this->messages[] = array(
			'type'    => $type,
			'content' => $message,
		);
	}


	/**
	 * Helper to return a string formatted as a message div.
	 *
	 * @since 1.2.0
	 */
	public function print_messages( $slug = null, $name = null ) {

		// this ensures we print messages on the purchase history page OR purchase confirmation
		if ( ( 'shortcode' === $slug && 'receipt' === $name ) || ( 'history' === $slug && 'purchases' === $name ) ) {

			$messages = $this->messages;

			if ( ! empty( $messages ) ) {
				foreach( $messages as $message ) {
					echo "<div class='edd-alert edd-alert-{$message['type']}'>{$message['content']}</div>";
				}
			}
		}
	}


}
