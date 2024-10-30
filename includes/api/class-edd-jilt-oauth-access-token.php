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
 * @package   EDD-Jilt(API
 * @author    Jilt
 * @copyright Copyright (c) 2015-2020, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

/**
 * OAuth2 access token utility class
 *
 * @since 1.3.0
 */
class EDD_Jilt_OAuth_Access_Token {


	/** @var string the token */
	private $token;

	/** @var string the token type */
	private $token_type;

	/** @var int number of seconds the token expires in */
	private $expires_in;

	/** @var string the refresh token */
	private $refresh_token;

	/** @var int created at timestamp */
	private $created_at;

	/** @var string token scopes */
	private $scopes;


	/**
	 * Initializes the access toklen instance
	 *
	 * @param array $args access token args
	 */
	public function __construct( $args ) {

		$this->token         = $args['access_token'];
		$this->token_type    = $args['token_type'];
		$this->created_at    = $args['created_at'];
		$this->expires_in    = $args['expires_in'];
		$this->refresh_token = isset( $args['refresh_token'] ) ? $args['refresh_token'] : null;
		$this->scopes        = isset( $args['scope'] ) ? $args['scope'] : null;
	}


	/**
	 * Returns the token itself.
	 *
	 * @since 1.3.0
	 *
	 * @return string the access token
	 */
	public function get_token() {
		return $this->token;
	}


	/**
	 * Returns the refresh token.
	 *
	 * @since 1.3.0
	 *
	 * @return string|null the refresh token, if available
	 */
	public function get_refresh_token() {
		return $this->refresh_token;
	}


	/**
	 * Returns the token type.
	 *
	 * @since 1.3.0
	 *
	 * @return string the token type
	 */
	public function get_token_type() {
		return $this->token_type;
	}


	/**
	 * Checks whether the token is expired or not.
	 *
	 * @since 1.3.0
	 *
	 * @return bool
	 */
	public function is_expired() {
		return time() >= ( $this->created_at + $this->expires_in );
	}


}
