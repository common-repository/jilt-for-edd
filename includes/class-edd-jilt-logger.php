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

defined( 'ABSPATH' ) or exit;

/**
 * EDD Jilt Logger
 *
 * @since 1.0.0
 */
class EDD_Jilt_Logger {


	/** Information interesting for Developers, when trying to debug a problem */
	const DEBUG = 100;

	/** Information interesting for Support staff trying to figure out the context of a given error */
	const INFO = 200;

	/** Indicates potentially harmful events or states in the program */
	const WARNING = 400;

	/** Indicates non-fatal errors in the application */
	const ERROR = 500;

	/** Indicates the most severe of error conditions */
	const EMERGENCY = 800;

	/** Logging disabled */
	const OFF = 900;

	/** @var int the current log level */
	private $threshold;

	/** @var int the maximum log file size */
	private $max_file_size;

	/** @var array data from last request, if any. see EDD_Jilt_API_Base::broadcast_request() for format */
	private $last_api_request;

	/** @var array data from last API response, if any */
	private $last_api_response;

	/** @var string log file absolute path */
	private $log_file_path;

	/** @var string the log id */
	private $log_id;

	/** @var resource log file pointer */
	private $handle;

	 /** @var string the last logged API request ID, if any */
	private $last_logged_request_id;


	/**
	 * Construct the logger with a given threshold
	 *
	 * @since 1.1.0
	 *
	 * @param int $threshold one of OFF, DEBUG, INFO, WARNING, ERROR, EMERGENCY
	 * @param string $log_id the log id (plugin id)
	 * @param int|null $max_file_size maximum file size, in bytes, defaults to 5mb
	 */
	public function __construct( $threshold, $log_id, $max_file_size = null ) {

		if ( ! (int) $max_file_size ) {
			$max_file_size = 5 * 1024 * 1024;
		}

		$this->log_id        = $log_id;
		$this->max_file_size = $max_file_size;

		$this->set_threshold( $threshold );
	}


	/** Core methods ******************************************************/


	/**
	 * Saves errors or messages to EDD log when logging is enabled.
	 *
	 * @since 1.1.0
	 * @param int $level one of OFF, DEBUG, INFO, WARNING, ERROR, EMERGENCY
	 * @param string $message error or message to save to log
	 */
	public function log_with_level( $level, $message ) {

		// allow logging?
		if ( $this->logging_enabled( $level ) ) {

			$level_name = $this->get_log_level_name( $level );

			// if we're logging an error or fatal, and there is an unlogged API
			// request, log it as well
			if ( $this->last_api_request && $level >= self::ERROR ) {
				$this->log_api_request_helper( $level_name, $this->last_api_request, $this->last_api_response );

				$this->last_api_request = null;
				$this->last_api_response = null;
			}

			$this->add_log( "{$level_name} : {$message}" );
		}

	}


	/**
	 * Adds an emergency level message.
	 *
	 * System is unusable.
	 *
	 * @since 1.1.0
	 * @param string $message the message to log
	 */
	public function emergency( $message ) {
		$this->log_with_level( self::EMERGENCY, $message );
	}


	/**
	 * Adds an error level message.
	 *
	 * Runtime errors that do not require immediate action but should typically be logged
	 * and monitored.
	 *
	 * @since 1.1.0
	 * @param string $message the message to log
	 */
	public function error( $message ) {
		$this->log_with_level( self::ERROR, $message );
	}


	/**
	 * Adds a warning level message.
	 *
	 * Exceptional occurrences that are not errors.
	 *
	 * Example: Use of deprecated APIs, poor use of an API, undesirable things that are not
	 * necessarily wrong.
	 *
	 * @since 1.1.0
	 * @param string $message the message to log
	 */
	public function warning( $message ) {
		$this->log_with_level( self::WARNING, $message );
	}


	/**
	 * Adds a info level message.
	 *
	 * Interesting events.
	 * Example: User logs in, SQL logs.
	 *
	 * @since 1.1.0
	 * @param string $message the message to log
	 */
	public function info( $message ) {
		$this->log_with_level( self::INFO, $message );
	}


	/**
	 * Adds a debug level message.
	 *
	 * Detailed debug information.
	 *
	 * @since 1.1.0
	 * @param string $message the message to log
	 */
	public function debug( $message ) {
		$this->log_with_level( self::DEBUG, $message );
	}


	/** Accessors/Mutators ******************************************************/


	/**
	 * Returns the current log level threshold
	 *
	 * @since 1.1.0
	 * @return int one of OFF, DEBUG, INFO, WARNING, ERROR, EMERGENCY
	 */
	public function get_threshold() {

		return $this->threshold;
	}


	/**
	 * Set the log level threshold
	 *
	 * @since 1.1.0
	 * @param int $threshold new log level one of OFF, DEBUG, INFO, WARNING, ERROR, EMERGENCY
	 */
	public function set_threshold( $threshold ) {

		$thresholds = [
			self::OFF,
			self::DEBUG,
			self::INFO,
			self::WARNING,
			self::ERROR,
			self::EMERGENCY
		];

		$this->threshold = in_array( $threshold, $thresholds, true ) ? $threshold : self::OFF;
	}


	/**
	 * Returns the current log level as a string name
	 *
	 * @since 1.1.0
	 * @param int $level optional level one of OFF, DEBUG, INFO, WARNING, ERROR, EMERGENCY
	 * @return string one of 'OFF', 'DEBUG', 'INFO', 'WARNING', 'ERROR', 'EMERGENCY'
	 */
	public function get_log_level_name( $level = null ) {

		if ( null === $level ) {
			$level = $this->get_threshold();
		}

		switch ( $level ) {
			case self::DEBUG:     return 'DEBUG';
			case self::INFO:      return 'INFO';
			case self::WARNING:   return 'WARNING';
			case self::ERROR:     return 'ERROR';
			case self::EMERGENCY: return 'EMERGENCY';
			case self::OFF:       return 'OFF';
		}
	}


	/**
	 * Returns the absolute file path
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_log_file_path() {

		if ( ! is_null( $this->log_file_path) ) {
			return $this->log_file_path;
		}

		/**
		 * Filters the log file path.
		 *
		 * @since 1.4.5
		 *
		 * @param string $path file path
		 */
		$log_file = apply_filters( 'edd_' . $this->log_id . '_log_file_location', $this->get_log_path() . $this->get_log_file_name() );

		$contents = @file_get_contents( $log_file );

		// create log if it doesn't exist
		if ( ! $contents ) {
			@file_put_contents( $log_file, '' );
		}

		return $log_file;
	}


	/**
	 * Gets the path where logs are stored.
	 *
	 * @since 1.4.5
	 *
	 * @return string
	 */
	public function get_log_path() {

		return trailingslashit( edd_get_upload_dir() );
	}


	/**
	 * Gets the log file name.
	 *
	 * @since 1.4.5
	 *
	 * @param string $suffix suffix to append to the file name before .log
	 * @return string
	 */
	public function get_log_file_name( $suffix = '' ) {

		$date = date( 'Y-m-d', current_time( 'timestamp', true ) );
		$hash = wp_hash( $this->log_id );

		$file_name = "{$this->log_id}-{$date}-{$hash}{$suffix}.log";

		/**
		 * Filters the log file name.
		 *
		 * @since 1.4.5
		 *
		 * @param string $file_name log file name
		 */
		return (string) apply_filters( 'edd_' . $this->log_id . '_log_file_name', $file_name );
	}


	/**
	 * Get the relative log file path
	 *
	 * @since 1.1.0
	 * @return string relative log file path
	 */
	public function get_relative_log_file_path() {
		$wp_root_path = get_home_path();
		$log_file_path = $this->get_log_file_path();

		return str_replace( $wp_root_path, '', $log_file_path );
	}


	/**
	 * Get the last entry in the log
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_last_log_entry() {
		$last_log = $this->get_logs( 'DESC', 1 );
		return $last_log[0];
	}


	/**
	 * Get the log entries in ascending order
	 *
	 * @since 1.0.0
	 * @param string $order, default ASC
	 * @param int $count
	 * @return array
	 */
	public function get_logs( $order = 'ASC', $count = -1 ) {

		$logs = @file( $this->get_log_file_path() );

		if ( ! is_array( $logs ) ) {
			return array();
		}

		if ( 'DESC' === strtoupper( $order ) ) {
			array_reverse( $logs );
		}

		if ( $count > 0 ) {
			$logs = array_slice( $logs, 0, $count );
		}

		return $logs;
	}


	/**
	 * Returns true if the log file has entries.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function has_logs() {
		$logs = @file( $this->get_log_file_path() );
		return count( $logs ) > 0 ? true : false;
	}


	/** API logging methods ******************************************************/


	/**
	 * Log API requests/responses
	 *
	 * @since 1.1.0
	 * @param array $request request data, see EDD_Jilt_API_Base::broadcast_request() for format
	 * @param array $response response data
	 */
	public function log_api_request( $request, $response ) {

		// defaults to DEBUG level
		if ( $this->logging_enabled( self::DEBUG ) ) {
			$this->log_api_request_helper( 'DEBUG', $request, $response );

			$this->last_api_request = null;
			$this->last_api_response = null;
		} else {
			// save the request/response data in case our log level is higher than
			// DEBUG but there was an error
			$this->last_api_request  = $request;
			$this->last_api_response = $response;
		}
	}


	/**
	 * Log API requests/responses with a given log level
	 *
	 * @since 1.1.0
	 * @see self::log_api_request()
	 * @param string $level_name one of 'OFF', 'DEBUG', 'INFO', 'WARNING', 'ERROR', 'EMERGENCY'
	 * @param array $request request data, see EDD_Jilt_API_Base::broadcast_request() for format
	 * @param array $response response data
	 */
	protected function log_api_request_helper( $level_name, $request, $response ) {

		// use the x-request-id if present to avoid double-logging certain API
		// requests, e.g. 401 response to shop update that requires a token refresh
		// TODO: fix this properly by avoiding the double logging, probably requires some work in the framework API base class {justinstern - 2018-04-21}
		$x_request_id = null;

		if ( isset( $response['headers']['x-request-id'] ) ) {
			$x_request_id = $response['headers']['x-request-id'];

			if ( $x_request_id && $this->last_logged_request_id == $x_request_id ) {
				return;
			}
		}

		$this->add_log( "{$level_name} : Request\n" . $this->get_api_log_message( $request ));

		if ( ! empty( $response ) ) {
			$this->add_log( "{$level_name} : Response\n" . $this->get_api_log_message( $response ) );
		}

		$this->last_logged_request_id = $x_request_id;
	}


	/**
	 * Transform the API request/response data into a string suitable for logging
	 *
	 * @since 1.1.0
	 * @param array $data
	 * @return string
	 */
	public function get_api_log_message( $data ) {

		$messages = [];

		foreach ( (array) $data as $key => $value ) {
			$messages[] = trim( sprintf( '%s: %s', $key, is_array( $value ) || ( is_object( $value ) && 'stdClass' === get_class( $value ) ) ? print_r( (array) $value, true ) : $value ) );
		}

		return implode( "\n", $messages ) . "\n";
	}


	/** Helper methods ******************************************************/

	/**
	 * Open log file for writing.
	 *
	 * @since 1.1.0
	 *
	 * @param string $mode Optional. File mode. Default 'a'.
	 * @return bool Success.
	 */
	protected function open( $mode = 'a' ) {
		if ( isset( $this->handle ) ) {
			return true;
		}

		$file = $this->get_log_file_path();

		if ( $file ) {
			if ( ! file_exists( $file ) ) {
				$temphandle = @fopen( $file, 'w+' );
				@fclose( $temphandle );

				if ( defined( 'FS_CHMOD_FILE' ) ) {
					@chmod( $file, FS_CHMOD_FILE );
				}
			}

			if ( $this->handle = @fopen( $file, $mode ) ) {
				return true;
			}
		}

		return false;
	}


	/**
	 * Closes the log file.
	 *
	 * @since 1.4.5
	 *
	 * @return bool
	 */
	protected function close() {

		$result = false;

		if ( $this->is_open() ) {

			$result = @fclose( $this->handle );

			$this->handle = null;
		}

		return $result;
	}


	/**
	 * Determines whether the log file is currently open.
	 *
	 * @since 1.4.5
	 *
	 * @return bool
	 */
	protected function is_open() {

		return $this->handle && is_resource( $this->handle );
	}


	/**
	 * Is logging enabled for the given level?
	 *
	 * @since 1.1.0
	 * @param int $level one of OFF, DEBUG, INFO, WARNING, ERROR, EMERGENCY
	 * @return boolean true if logging is enabled for the given $level
	 */
	public function logging_enabled( $level ) {
		return $level >= $this->get_threshold();
	}


	/**
	 * Add an entry to the log file
	 *
	 * @since 1.0.0
	 * @param string $entry
	 * @return string entry with timestamp added
	 */
	protected function add_log( $entry ) {

		// if the log file is over size, rotate it out for a new file
		if ( $this->should_rotate() ) {
			$this->rotate_log();
		}

		$entry = current_time( 'mysql' ) . ' - ' . $entry;

		if ( $this->open() && is_resource( $this->handle ) ) {
			fwrite( $this->handle, $entry . PHP_EOL );
		}

		return $entry;
	}


	/**
	 * Determines whether the log file has reached its maximum size and needs to be rotated.
	 *
	 * @since 1.4.5
	 *
	 * @return bool
	 */
	public function should_rotate() {

		$file_size = 0;

		if ( $this->is_open() ) {

			$file_stat = fstat( $this->handle );

			$file_size = isset( $file_stat['size'] ) ? $file_stat['size'] : 0;

		} elseif ( file_exists( $this->get_log_file_path() ) ) {

			$file_size = filesize( $this->get_log_file_path() );
		}

		return $file_size > $this->get_max_file_size();
	}


	/**
	 * Rotates the log file by renaming existing log files with an incremented .n.log suffix.
	 *
	 * This method will close the current log file if open, then rename existing log files to maintain 10 historical
	 * logs. For example:
	 *     base.9.log -> [ REMOVED ]
	 *     base.8.log -> base.9.log
	 *     ...
	 *     base.0.log -> base.1.log
	 *     base.log   -> base.0.log
	 *
	 * @since 1.4.5
	 */
	public function rotate_log() {

		// always close the log file if it's open
		if ( $this->is_open() ) {
			$this->close();
		}

		for ( $i = 8; $i >= 0; $i-- ) {
			$this->increment_log_file_name( $i );
		}

		$this->increment_log_file_name();
	}


	/**
	 * Increment a log file suffix.
	 *
	 * @param int|null $number log suffix number to be incremented
	 * @return bool
	 */
	protected function increment_log_file_name( $number = null ) {

		if ( null === $number ) {
			$suffix      = '';
			$next_suffix = '.0';
		} else {
			$suffix      = '.' . $number;
			$next_suffix = '.' . ( $number + 1 );
		}

		$rename_from = $this->get_log_path() . $this->get_log_file_name( $suffix );
		$rename_to   = $this->get_log_path() . $this->get_log_file_name( $next_suffix );

		$result = false;

		if ( is_writable( $rename_from ) ) {
			$result = rename( $rename_from, $rename_to );
		}

		return $result;
	}


	/**
	 * Gets the maximum size for the log file.
	 *
	 * When this is reached, a new log file will be started.
	 *
	 * @since 1.4.5
	 *
	 * @return int maximum file size, in bytes
	 */
	protected function get_max_file_size() {

		/**
		 * Filters the maximum size for the log file.
		 *
		 * @since 1.4.5
		 *
		 * @param int $size maximum file size, in bytes
		 */
		return max( 100, (int) apply_filters( 'edd_' . $this->log_id . '_log_max_file_size', $this->max_file_size ) );
	}


}
