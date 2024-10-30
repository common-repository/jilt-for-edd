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
 * @package   EDD-Jilt/API
 * @author    Jilt
 * @copyright Copyright (c) 2015-2020, SkyVerge, Inc.
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

/**
 * Jilt API class - used for both REST API as well as OAuth handling.
 *
 * @since 1.0.0
 */
class EDD_Jilt_API extends EDD_Jilt_API_Base {


	/** Jilt REST API version */
	const API_VERSION = 2;

	/** @var string linked Shop ID */
	protected $shop_id;

	/** @var string linked shop ID */
	protected $linked_shop_id;

	/** @var \EDD_Jilt_OAuth_Access_Token|string Jilt OAuth access token or API secret key */
	protected $auth_token;

	/** @var string HTTP Authorization scheme */
	protected $auth_scheme;


	/**
	 * Sets up the API client.
	 *
	 * @since 1.0.0
	 *
	 * @param string $shop_id (optional) linked Shop ID
	 * @param \EDD_Jilt_OAuth_Access_Token|string $auth_token Jilt OAuth access token or secret api key for shops using legacy auth
	 */
	public function __construct( $shop_id = null, $auth_token = null ) {

		parent::__construct();

		$this->shop_id        = $shop_id;
		$this->linked_shop_id = $this->get_plugin()->get_integration()->get_linked_shop_id();

		$this->set_auth_token( $auth_token );

		// set up the request/response defaults
		$this->request_uri = $this->get_api_endpoint();
		$this->set_request_accept_header( 'application/json' );
		$this->set_request_content_type_header( 'application/json' );
		$this->set_request_header( 'x-jilt-shop-domain', edd_jilt()->get_shop_domain() );

		// pass through the client browser http referer
		if ( $referer = $this->get_client_request_url() ) {
			$this->set_request_header( 'referer', $referer );
		}

		if ( $user_id = get_current_user_id() ) {
			$this->set_request_header( 'x-jilt-remote-user-id', $user_id );
		}
	}


	/** API methods ****************************************************/


	/**
	 * Attempts to upgrade the shop from secret key to OAuth.
	 *
	 * @since 1.3.0
	 *
	 * @param int $shop_id the shop id
	 * @param string $domain the shop domain
	 * @param string $redirect_uri the redirect uri
	 * @param string $installation_id the installation-specific ID
	 * @throws EDD_Jilt_API_Exception on API error
	 * @return stdClass the response returned by Jilt
	 */
	public function update_auth( $shop_id, $domain, $redirect_uri, $installation_id ) {

		return $this->perform_request( 'PUT', "/shops/{$shop_id}/update_auth", array(
			'domain'          => $domain,
			'redirect_uri'    => $redirect_uri,
			'installation_id' => $installation_id,
		) );
	}


	/**
	 * Gets the current user public key
	 *
	 * @since 1.0.0
	 * @return string|bool public key for the current API user, false if not found
	 * @throws EDD_Jilt_API_Exception on API error
	 */
	public function get_public_key() {

		$response = $this->perform_request( 'GET', '/user' );

		return ! empty( $response->public_key ) ? $response->public_key : false;
	}


	/**
	 * Find a shop by domain
	 *
	 * @since 1.0.0
	 *
	 * @param array $args associative array of search parameters. Supports: 'domain'
	 * @return stdClass the shop record returned by the API, or null if none was found
	 * @throws EDD_Jilt_API_Exception on API error
	 */
	public function find_shop( $args = array() ) {

		$response = $this->perform_request( 'GET', '/shops', $args );

		if ( ! is_array( $response ) || 0 === count( $response ) ) {
			// null can also indicate a successful token refresh - see TODO {JS - 2018-07-31}
			return null;
		} else {
			// return the first found shop
			return $response[0];
		}
	}


	/**
	 * Gets a shop.
	 *
	 * @since 1.4.0
	 *
	 * @param null|string $shop_id the shop identifier
	 * @return stdClass the shop record returned by the API or false if a token
	 *          refresh happened (see TODO below {JS - 2018-07-31})
	 * @throws \EDD_Jilt_API_Exception on API error
	 */
	public function get_shop( $shop_id = null ) {

		$shop_id  = null === $shop_id ? (int) $this->shop_id : $shop_id;
		$response = $this->perform_request( 'GET', "/shops/{$shop_id}" );

		return isset( $response->access_token ) ? false : $response;
	}


	/**
	 * Create a shop
	 *
	 * @since 1.0.0
	 * @param array $args associative array of shop parameters.
	 *        Required: 'profile_type', 'domain'
	 * @return stdClass the shop record returned by the API
	 * @throws EDD_Jilt_API_Exception on API error
	 */
	public function create_shop( $args = array() ) {

		$response = $this->perform_request( 'POST', '/shops', $args );

		// use the newly created shop id
		$this->shop_id = $response->id;

		return $response;
	}


	/**
	 * Updates a shop.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args associative array of shop parameters
	 * @param int $shop_id optional shop ID to update
	 * @return stdClass the shop record returned by the API
	 * @throws EDD_Jilt_API_Exception on API error
	 */
	public function update_shop( $args = array(), $shop_id = null ) {

		$shop_id = null === $shop_id ? $this->shop_id : $shop_id;

		$response = $this->perform_request( 'PUT', '/shops/' . $shop_id, $args );

		return $response;
	}


	/**
	 * Deletes the shop.
	 *
	 * @since 1.1.0
	 *
	 * @return stdClass the shop record returned by the API
	 * @throws EDD_Jilt_API_Exception on API error
	 */
	public function delete_shop() {

		$response = $this->perform_request( 'DELETE', "/shops/{$this->shop_id}" );

		return $response;
	}


	/**
	 * Returns an order.
	 *
	 * @since 1.0.0
	 *
	 * @param string $cart_token cart token
	 * @return stdClass the order record returned by the API
	 * @throws EDD_Jilt_API_Exception on API error
	 */
	public function get_order( $cart_token ) {

		$response = $this->perform_request( 'GET', "/shops/{$this->shop_id}/orders/{$cart_token}" );

		return $response;
	}


	/**
	 * Creates an order.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args associative array of order parameters
	 * @throws EDD_Jilt_API_Exception on API error
	 */
	public function create_order( $args = array() ) {

		$this->perform_request( 'POST', "/shops/{$this->shop_id}/orders", $args );
	}


	/**
	 * Updates an order.
	 *
	 * @since 1.0.0
	 *
	 * @param string $cart_token cart token
	 * @param array $args associative array of order parameters
	 * @throws EDD_Jilt_API_Exception on API error
	 */
	public function update_order( $cart_token, $args = array() ) {

		$this->perform_request( 'PUT', "/shops/{$this->shop_id}/orders/{$cart_token}", $args );
	}


	/**
	 * Deletes an order.
	 *
	 * @since 1.0.0
	 *
	 * @param string $cart_token cart token
	 * @throws EDD_Jilt_API_Exception on API error
	 * @return mixed
	 */
	public function delete_order( $cart_token ) {

		$this->perform_request( 'DELETE', "/shops/{$this->shop_id}/orders/{$cart_token}" );
	}


	/**
	 * Creates a Jilt customer.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args the data to use in creating the customer
	 * @return \stdClass the response data
	 * @throws EDD_Jilt_API_Exception on API error
	 */
	public function create_customer( $args ) {

		// use linked shop ID since this is a public REST API request
		$response = $this->perform_request( 'POST', "/shops/{$this->linked_shop_id}/customers/", $args );

		return $response;
	}


	/**
	 * Gets a Jilt customer.
	 *
	 * @since 1.5.0
	 *
	 * @param string $email the customer email
	 * @return \stdClass the customer response data
	 * @throws EDD_Jilt_API_Exception on API error
	 */
	public function get_customer( $email ) {

		// use linked shop ID since this is a public REST API request
		$response = $this->perform_request( 'GET', "/shops/{$this->linked_shop_id}/customers/{$email}" );

		return $response;
	}


	/**
	 * Updates a Jilt customer.
	 *
	 * @since 1.5.0
	 *
	 * @param string $email the customer email
	 * @param array $args the data to update
	 * @param bool $force true if tags and lists should be overridden
	 * @return \stdClass the response data
	 * @throws EDD_Jilt_API_Exception on API error
	 */
	public function update_customer( $email, $args, $force = false ) {

		$method = $force ? 'PUT' : 'PATCH';

		// use linked shop ID since this is a public REST API request
		$response = $this->perform_request( $method, "/shops/{$this->linked_shop_id}/customers/{$email}", $args );

		return $response;
	}


	/**
	 * Gets a set of Jilt customers.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args the search parameters {
	 *  @type string $email the email to search
	 *  @type mixed $unsubscribed 0 or 1 to search by subscription status
	 * }
	 * @return \stdClass[] the customers returned by the API
	 * @throws EDD_Jilt_API_Exception on API error
	 */
	public function get_customers( $args = [] ) {

		$search = http_build_query( $args );
		$search = ! empty( $search ) ? "?{$search}" : '';

		// use linked shop ID since this is a public REST API request
		$response = $this->perform_request( 'GET', "/shops/{$this->linked_shop_id}/customers/{$search}" );

		return $response    ;
	}


	/**
	 * Gets the Jilt lists for this store.
	 *
	 * @since 1.5.0
	 *
	 * @return \stdClass[] the lists returned by the API
	 * @throws EDD_Jilt_API_Exception on API error
	 */
	public function get_lists() {

		// use linked shop ID since this is a public REST API request
		$response = $this->perform_request( 'GET', "/shops/{$this->linked_shop_id}/lists" );

		return $response;
	}


	/** Validation methods ****************************************************/


	/**
	 * Check if the response has any status code errors
	 *
	 * @since 1.1.0
	 * @see \EDD_Jilt_API_Base::do_pre_parse_response_validation()
	 * @throws \EDD_Jilt_API_Exception non HTTP 200 status
	 */
	protected function do_pre_parse_response_validation() {

		switch ( $this->get_response_code() ) {

			// situation normal
			case 200:
			case 201:
			case 202:
			case 204:
				return;

			case 401:
				$headers = $this->get_response_headers();

				// expired token, try to refresh
				if ( $this->auth_token instanceof EDD_Jilt_OAuth_Access_Token && ! empty( $headers['www-authenticate'] ) && ( false !== strpos( $headers['www-authenticate'], 'The access token expired' ) ) ) {
					// first broadcast the request that resulted in this 401 response,
					// otherwise only the token refresh request will be logged
					$this->broadcast_request();

					if ( $this->maybe_refresh_access_token( true ) ) {
						// token refreshed: error averted!
						// TODO: This leaves the API class in a potentially bad state where the calling code is expecting the result of their original response, but instead will have the result of the token refresh. probably should throw an exception {JS - 2018-07-31}
						return;
					}
				}

				$this->handle_generic_api_error();
			break;

			// jilt account has been cancelled
			// TODO: this code has not yet been implemented see https://github.com/skyverge/jilt-app/issues/90
			case 410:
				$this->get_plugin()->handle_account_cancellation();
			break;

			default:
				$this->handle_generic_api_error();
		}
	}


	/**
	 * Handles generic API errors.
	 *
	 * @since 1.3.0
	 *
	 * @throws \EDD_Jilt_API_Exception
	 */
	private function handle_generic_api_error() {

		// default message to response code/message (e.g. HTTP Code 422 - Unprocessable Entity)
		$message = sprintf( 'HTTP code %s - %s', $this->get_response_code(), $this->get_response_message() );

		// if there's a more helpful Jilt API error message, use that instead
		if ( $this->get_raw_response_body() ) {
			$response = $this->get_parsed_response( $this->raw_response_body );

			if ( $response ) {
				$message = isset( $response->error_description ) ? $response->error_description : $response->error->message;
			}
		}

		throw new EDD_Jilt_API_Exception( $message, $this->get_response_code() );
	}


	/** Helper methods **********************************************/


	/**
	 * Sets the auth token
	 *
	 * @since 1.3.2
	 *
	 * @param \EDD_Jilt_OAuth_Access_Token|string $auth_token Jilt OAuth access
	 *        token or secret api key for shops using legacy auth
	 */
	public function set_auth_token( $auth_token ) {

		// set auth creds
		$this->auth_token = $auth_token;

		// OAuth uses Bearer, API secret key uses Token scheme
		$this->auth_scheme = $this->auth_token ? ( is_string( $this->auth_token ) ? 'Token' : 'Bearer' ) : null;

		$this->set_authorization_header();
	}


	/**
	 * Perform a custom sanitization of the Authorization header, with a partial
	 * masking rather than the full mask of the base API class
	 *
	 * @since 1.1.0
	 * @see EDD_Jilt_API_Base::get_sanitized_request_headers()
	 * @return array of sanitized request headers
	 */
	protected function get_sanitized_request_headers() {

		$sanitized_headers = parent::get_sanitized_request_headers();

		$headers = $this->get_request_headers();

		if ( ! empty( $headers['Authorization'] ) ) {
			list( $_, $credential ) = explode( ' ', $headers['Authorization'] );
			if ( strlen( $credential ) > 7 ) {
				$sanitized_headers['Authorization'] = $this->auth_scheme . ' ' . substr( $credential, 0, 2 ) . str_repeat( '*', strlen( $credential ) - 7 ) . substr( $credential, -4 );
			} else {
				// invalid key, no masking required
				$sanitized_headers['Authorization'] = $headers['Authorization'];
			}
		}

		return $sanitized_headers;
	}


	/**
	 * Returns the main plugin class
	 *
	 * @since 1.1.0
	 * @see \EDD_Jilt_API_Base::get_plugin()
	 * @return \EDD_Jilt
	 */
	protected function get_plugin() {
		return edd_jilt();
	}


	/**
	 * Get the API endpoint URI
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_api_endpoint() {

		return sprintf( 'https://%s/%s', edd_jilt()->get_api_hostname(), self::get_api_version() );
	}


	/**
	 * Returns the Jilt OAuth endpoint.
	 *
	 * @since 1.3.0
	 *
	 * @return string
	 */
	public function get_oauth_endpoint() {

		return edd_jilt()->get_app_endpoint( 'oauth' );
	}


	/**
	 * Returns the Jilt Connect endpoint.
	 *
	 * @since 1.3.0
	 *
	 * @return string
	 */
	public function get_connect_endpoint() {

		return edd_jilt()->get_app_endpoint( 'connect/edd' );
	}


	/**
	 * Return a friendly representation of the API version in use
	 *
	 * @since 1.1.0
	 * @return string
	 */
	public static function get_api_version() {

		return 'v' . self::API_VERSION;
	}


	/**
	 * Returns the current shop id.
	 *
	 * @since 1.1.0
	 *
	 * @return int shop id
	 */
	public function get_shop_id() {
		return $this->shop_id;
	}


	/**
	 * Sets the current shop id.
	 *
	 * @since 1.1.0
	 *
	 * @param int $shop_id
	 */
	public function set_shop_id( $shop_id ) {
		$this->shop_id = $shop_id;
	}


	/**
	 * Get the current API key
	 *
	 * @since 1.1.0
	 * @deprecated since 1.3.0
	 * @return string current api key
	 */
	public function get_secret_key() {

		/* @deprecated since 1.3.0 */
		_deprecated_function( 'EDD_Jilt_API::get_secret_key()', '1.3.0', 'EDD_Jilt_API::get_auth_token()' );

		return $this->get_auth_token();
	}


	/**
	 * Returns the current auth token.
	 *
	 * @since 1.3.0
	 *
	 * @return string|\EDD_Jilt_OAuth_Access_Token current auth token
	 */
	public function get_auth_token() {
		return $this->auth_token;
	}


	/**
	 * Returns the current auth scheme.
	 *
	 * @since 1.3.0
	 *
	 * @return string current auth scheme
	 */
	public function get_auth_scheme() {
		return $this->auth_scheme;
	}


	/**
	 * Sets the authorization header for API requests.
	 *
	 * @since 1.3.0
	 */
	private function set_authorization_header() {

		if ( ! $this->auth_token ) {
			return;
		}

		$token = is_string( $this->auth_token ) ? $this->auth_token : $this->auth_token->get_token();

		$this->set_request_header( 'Authorization', $this->auth_scheme . ' ' . $token );
	}


	/** OAuth 2.0 Methods *****************************************************/


	/**
	 * Requests installation-specific OAuth client credentials from Jilt
	 *
	 * @since 1.3.0
	 *
	 * @param string $domain the shop domain
	 * @param string $redirect_uri the redirect uri
	 * @param string $installation_id the installation-specific ID
	 * @return stdClass the client credentials returned by Jilt
	 * @throws EDD_Jilt_API_Exception on API error
	 */
	public function get_client_credentials( $domain, $redirect_uri, $installation_id ) {

		$this->request_uri = $this->get_connect_endpoint();

		return $this->perform_request( 'POST', '/client', array(
			'installation_id' => $installation_id,
			'domain'          => $domain,
			'redirect_uri'    => $redirect_uri,
		) );
	}


	/**
	 * Exchanges the authorization code for an access token & refresh token.
	 *
	 * @since 1.3.0
	 *
	 * @param string $code authorization code, returned after the user authorizes the plugin
	 * @param string $redirect_uri the redirect uri
	 * @param string $client_id the OAuth client id
	 * @param string $client_secret the OAuth client secret
	 * @throws \EDD_Jilt_API_Exception
	 * @return stdClass
	 */
	public function get_oauth_tokens( $code, $redirect_uri, $client_id, $client_secret ) {

		$this->request_uri = $this->get_oauth_endpoint();

		return $this->perform_request( 'POST', '/token', array(
			'grant_type'    => 'authorization_code',
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
			'code'          => $code,
			'redirect_uri'  => $redirect_uri, // TODO: urlencode once this is resolved: https://github.com/doorkeeper-gem/doorkeeper/issues/1013
		) );
	}


	/**
	 * Refreshes the OAuth2 access token.
	 *
	 * @since 1.3.0
	 *
	 * @throws \EDD_Jilt_API_Exception
	 * @return stdClass
	 */
	public function refresh_oauth_token() {

		$this->request_uri = $this->get_oauth_endpoint();

		return $this->perform_request( 'POST', '/token', array(
			'refresh_token' => $this->auth_token->get_refresh_token(),
			'grant_type'    => 'refresh_token',
		) );
	}


	/**
	 * Revokes the OAuth2 access token.
	 *
	 * @since 1.3.0
	 *
	 * @param string $client_id the OAuth client id
	 * @param string $client_secret the OAuth client secret
	 * @throws \EDD_Jilt_API_Exception
	 * @return stdClass
	 */
	public function revoke_oauth_token( $client_id, $client_secret ) {

		$this->request_uri = $this->get_oauth_endpoint();

		return $this->perform_request( 'POST', '/revoke', array(
			'token'         => $this->get_auth_token()->get_token(),
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
		) );
	}


	/**
	 * Refreshes the OAuth 2 access token if it's expired.
	 *
	 * @since 1.3.0
	 *
	 * @param bool $force (optional) whether to force refreshing the access token or not, defaults to false
	 * @return bool true if the token was successfully refreshed, false otherwise
	 */
	protected function maybe_refresh_access_token( $force = false ) {

		$refreshed = false;
		$request_uri = $this->request_uri;

		if ( $force || $this->auth_token->is_expired() ) {

			try {

				$response = $this->refresh_oauth_token();

				if ( $response ) {

					$access_token = json_decode( json_encode( $response ), true ); // convert stdClass to array

					$this->get_plugin()->get_integration()->set_access_token( $access_token );

					// set the auth token on api client and update the auth header
					$this->auth_token = $this->get_plugin()->get_integration()->get_access_token();

					$this->set_authorization_header();
				}

				// success!
				$refreshed = true;

			} catch ( EDD_Jilt_API_Exception $e ) {

				edd_jilt()->get_logger()->error( 'Could not refresh access token. ' . $e->getMessage(), $this->get_plugin()->get_id() );
			}
		}

		$this->request_uri = $request_uri;

		return $refreshed;
	}


	/**
	 * Gets the full client request URL.
	 *
	 * @since 1.3.0
	 *
	 * @return string the request URL e.g. https://example.com/checkout/
	 */
	private function get_client_request_url() {

		if ( isset( $_SERVER['REQUEST_SCHEME'] ) ) {
			$scheme = $_SERVER['REQUEST_SCHEME'];
		} elseif ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ) {
			$scheme = 'https';
		} else {
			$scheme = 'http';
		}

		return isset( $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] ) ? "{$scheme}://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}" : '';
	}


}
