<?php
/**
 * Debug logger service for SoulMirror.
 *
 * @package MysticPalmReadingAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles log writing, rotation, and retrieval.
 */
class SM_Logger {

	/**
	 * Base log filename.
	 */
	const LOG_FILE = 'debug.log';

	/**
	 * Resolved log directory (with trailing slash).
	 *
	 * @var string
	 */
	private $log_dir = '';

	/**
	 * Maximum log size before rotation (10MB).
	 */
	const MAX_FILE_SIZE = 10485760;

	/**
	 * Number of rotated logs to keep.
	 */
	const MAX_ROTATED_FILES = 7;

	/**
	 * Retention window for rotated logs (30 days).
	 */
	const RETENTION_DAYS = 30;

	/**
	 * Level priority map for gating.
	 *
	 * @var array<string,int>
	 */
	private static $level_priority = array(
		'critical' => 1,
		'error'    => 2,
		'warning'  => 3,
		'info'     => 4,
		'debug'    => 5,
	);

	/**
	 * Singleton instance.
	 *
	 * @var SM_Logger|null
	 */
	private static $instance = null;

	/**
	 * Initialize logger and return instance.
	 *
	 * @return SM_Logger
	 */
	public static function init() {
		return self::get_instance();
	}

	/**
	 * Retrieve singleton instance.
	 *
	 * @return SM_Logger
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Write a log entry.
	 *
	 * @param string     $level      Log level (critical, error, warning, info, debug).
	 * @param string     $event_type Event type identifier.
	 * @param string     $message    Human-readable message.
	 * @param array|null $context    Optional contextual metadata.
	 * @param string     $status     Optional status label; defaults to level.
	 */
	public static function log( $level, $event_type, $message, $context = array(), $status = '' ) {
		$logger = self::get_instance();

		$normalized_level = strtolower( (string) $level );
		$force_log = is_array( $context ) && ! empty( $context['force_log'] );
		if ( ! $force_log && ! $logger->should_log( $normalized_level ) ) {
			return;
		}

		$event   = $logger->format_event_type( $event_type );
		$status  = $logger->format_status( $status, $normalized_level, $context );
		$meta    = $logger->prepare_meta( $context );
		$line    = sprintf(
			'[%s] [%s] %s: %s',
			$logger->timestamp(),
			$event,
			$status,
			$message
		);

		if ( '' !== $meta ) {
			$line .= ' | ' . $meta;
		}

		$logger->write_line( $line );
	}

	/**
	 * Log a critical event.
	 *
	 * @param string $event_type Event identifier.
	 * @param string $message    Message.
	 * @param array  $context    Context metadata.
	 */
	public static function critical( $event_type, $message, $context = array() ) {
		self::log( 'critical', $event_type, $message, $context );
	}

	/**
	 * Log an error event.
	 *
	 * @param string $event_type Event identifier.
	 * @param string $message    Message.
	 * @param array  $context    Context metadata.
	 */
	public static function error( $event_type, $message, $context = array() ) {
		self::log( 'error', $event_type, $message, $context );
	}

	/**
	 * Log a warning event.
	 *
	 * @param string $event_type Event identifier.
	 * @param string $message    Message.
	 * @param array  $context    Context metadata.
	 */
	public static function warning( $event_type, $message, $context = array() ) {
		self::log( 'warning', $event_type, $message, $context );
	}

	/**
	 * Log an informational event.
	 *
	 * @param string $event_type Event identifier.
	 * @param string $message    Message.
	 * @param array  $context    Context metadata.
	 */
	public static function info( $event_type, $message, $context = array() ) {
		self::log( 'info', $event_type, $message, $context );
	}

	/**
	 * Log a debug event.
	 *
	 * @param string $event_type Event identifier.
	 * @param string $message    Message.
	 * @param array  $context    Context metadata.
	 */
	public static function debug( $event_type, $message, $context = array() ) {
		self::log( 'debug', $event_type, $message, $context );
	}

	/**
	 * Retrieve the last N lines from the log file.
	 *
	 * @param int $lines Number of lines to retrieve.
	 * @return string
	 */
	public static function tail( $lines = 100 ) {
		$logger  = self::get_instance();
		$path    = $logger->get_log_path();

		if ( ! file_exists( $path ) ) {
			return __( 'Log file not found yet.', 'mystic-palm-reading' );
		}

		$buffer = array();
		$fp     = @fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( ! $fp ) {
			return __( 'Unable to read log file.', 'mystic-palm-reading' );
		}

		while ( ( $line = fgets( $fp ) ) !== false ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fgets
			$buffer[] = rtrim( $line, "\r\n" );
			if ( count( $buffer ) > $lines ) {
				array_shift( $buffer );
			}
		}

		fclose( $fp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return empty( $buffer ) ? __( 'Log file is empty.', 'mystic-palm-reading' ) : implode( "\n", $buffer );
	}

	/**
	 * Expose the resolved log file path.
	 *
	 * @return string
	 */
	public static function get_log_file_path() {
		$logger = self::get_instance();
		return $logger->get_log_path();
	}

	/**
	 * Clear the active log file.
	 *
	 * @return void
	 */
	public static function clear() {
		$logger = self::get_instance();
		$path   = $logger->get_log_path();

		if ( ! file_exists( $path ) ) {
			return;
		}

		@file_put_contents( $path, '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->log_dir = $this->resolve_log_dir();
		$this->ensure_log_file();
		$this->purge_rotated_logs();
	}

	/**
	 * Determine whether a level should be logged based on debug mode and priority.
	 *
	 * @param string $level Log level.
	 * @return bool
	 */
	private function should_log( $level ) {
		if ( ! isset( self::$level_priority[ $level ] ) ) {
			return false;
		}

		// Always log warnings and above.
		if ( self::$level_priority[ $level ] <= self::$level_priority['warning'] ) {
			return true;
		}

		// INFO/DEBUG are gated by debug toggle.
		return $this->is_debug_mode();
	}

	/**
	 * Check debug mode from settings; defaults to enabled if unavailable.
	 *
	 * @return bool
	 */
	private function is_debug_mode() {
		if ( class_exists( 'SM_Settings' ) ) {
			$settings = SM_Settings::init();
			if ( method_exists( $settings, 'is_debug_enabled' ) ) {
				return $settings->is_debug_enabled();
			}
		}

		return true;
	}

	/**
	 * Produce a timestamp in site-local time.
	 *
	 * @return string
	 */
	private function timestamp() {
		if ( function_exists( 'current_time' ) ) {
			return current_time( 'mysql' );
		}

		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Ensure the active log file exists.
	 */
	private function ensure_log_file() {
		$path = $this->get_log_path();

		if ( ! file_exists( $path ) ) {
			@file_put_contents( $path, '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}

	/**
	 * Get full log file path.
	 *
	 * @return string
	 */
	private function get_log_path() {
		return $this->get_log_dir() . self::LOG_FILE;
	}

	/**
	 * Get resolved log directory (creates if missing).
	 *
	 * @return string
	 */
	private function get_log_dir() {
		if ( '' === $this->log_dir ) {
			$this->log_dir = $this->resolve_log_dir();
		}

		return trailingslashit( $this->log_dir );
	}

	/**
	 * Determine a writable log directory (wp-content preferred, fallback to plugin dir).
	 *
	 * @return string
	 */
	private function resolve_log_dir() {
		if ( defined( 'WP_CONTENT_DIR' ) && WP_CONTENT_DIR && is_writable( WP_CONTENT_DIR ) ) {
			return trailingslashit( WP_CONTENT_DIR );
		}

		return trailingslashit( SM_AURA_PLUGIN_DIR );
	}

	/**
	 * Format event type.
	 *
	 * @param string $event_type Raw event type.
	 * @return string
	 */
	private function format_event_type( $event_type ) {
		$event = strtoupper( trim( (string) $event_type ) );
		return '' !== $event ? $event : 'GENERAL';
	}

	/**
	 * Format status label.
	 *
	 * @param string $status Requested status.
	 * @param string $level  Log level.
	 * @param array  $context Context array.
	 * @return string
	 */
	private function format_status( $status, $level, $context ) {
		if ( ! empty( $status ) ) {
			return strtoupper( (string) $status );
		}

		if ( is_array( $context ) && isset( $context['status'] ) ) {
			return strtoupper( (string) $context['status'] );
		}

		return strtoupper( $level );
	}

	/**
	 * Prepare and mask contextual metadata.
	 *
	 * @param array|null $context Context data.
	 * @return string
	 */
	private function prepare_meta( $context ) {
		if ( empty( $context ) || ! is_array( $context ) ) {
			return '';
		}

		$filtered = array();

		foreach ( $context as $key => $value ) {
			if ( 'status' === $key || 'force_log' === $key ) {
				continue;
			}

			$filtered[ $key ] = $this->mask_sensitive( $key, $value );
		}

		if ( empty( $filtered ) ) {
			return '';
		}

		$encoded = wp_json_encode( $filtered );

		return false === $encoded ? '' : $encoded;
	}

	/**
	 * Mask sensitive values by key or pattern.
	 *
	 * @param string $key   Context key.
	 * @param mixed  $value Value to mask.
	 * @return mixed
	 */
	private function mask_sensitive( $key, $value ) {
		if ( is_array( $value ) ) {
			$masked = array();
			foreach ( $value as $sub_key => $sub_value ) {
				$masked[ $sub_key ] = $this->mask_sensitive( $sub_key, $sub_value );
			}
			return $masked;
		}

		if ( is_object( $value ) ) {
			return '[object]';
		}

		$lower_key = strtolower( (string) $key );
		$needs_mask = preg_match( '/(key|secret|token|password|otp|code|api)/', $lower_key );

		if ( $needs_mask && is_string( $value ) ) {
			$length = strlen( $value );
			if ( $length <= 4 ) {
				return str_repeat( '*', $length );
			}

			$visible_prefix = substr( $value, 0, 2 );
			$visible_suffix = substr( $value, -2 );
			return $visible_prefix . str_repeat( '*', max( 2, $length - 4 ) ) . $visible_suffix;
		}

		return $value;
	}

	/**
	 * Write a line to the log file with locking and rotation.
	 *
	 * @param string $line Log entry.
	 */
	private function write_line( $line ) {
		$path = $this->get_log_path();

		$this->rotate_if_needed( $path );

		$handle = @fopen( $path, 'a' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( ! $handle ) {
			return;
		}

		if ( function_exists( 'flock' ) ) {
			@flock( $handle, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_flock
		}

		@fwrite( $handle, $line . PHP_EOL ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

		if ( function_exists( 'flock' ) ) {
			@flock( $handle, LOCK_UN ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_flock
		}

		@fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	}

	/**
	 * Rotate the log file if it exceeds the maximum size.
	 *
	 * @param string $path Active log file path.
	 */
	private function rotate_if_needed( $path ) {
		if ( file_exists( $path ) && filesize( $path ) < self::MAX_FILE_SIZE ) {
			return;
		}

		$rotation_base = 'debug-' . gmdate( 'Y-m-d' ) . '.log';
		$target        = $this->get_log_dir() . $rotation_base;
		$counter       = 1;

		while ( file_exists( $target ) ) {
			$counter++;
			$target = $this->get_log_dir() . 'debug-' . gmdate( 'Y-m-d' ) . '-' . $counter . '.log';
		}

		@rename( $path, $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rename

		$this->ensure_log_file();
		$this->purge_rotated_logs();
	}

	/**
	 * Delete rotated logs beyond retention and count limits.
	 */
	private function purge_rotated_logs() {
		$pattern = $this->get_log_dir() . 'debug-*.log';
		$files   = glob( $pattern );

		if ( false === $files || empty( $files ) ) {
			return;
		}

		usort(
			$files,
			function( $a, $b ) {
				return filemtime( $b ) <=> filemtime( $a );
			}
		);

		$now           = time();
		$day_in_second = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;
		$max_age_sec   = self::RETENTION_DAYS * $day_in_second;

		foreach ( $files as $index => $file ) {
			$age = $now - filemtime( $file );

			if ( $age > $max_age_sec || $index >= self::MAX_ROTATED_FILES ) {
				@unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink
			}
		}
	}
}
