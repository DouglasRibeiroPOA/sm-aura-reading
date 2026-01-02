<?php
/**
 * Centralized rate limiting helper.
 *
 * @package MysticPalmReadingAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides transient-based rate limiting with logging and cleanup.
 */
class SM_Rate_Limiter {

	/**
	 * Transient prefix.
	 */
	const PREFIX = 'sm_rl_';

	/**
	 * Cleanup hook name.
	 */
	const CLEANUP_HOOK = 'sm_rate_limit_cleanup';

	/**
	 * Singleton instance.
	 *
	 * @var SM_Rate_Limiter|null
	 */
	private static $instance = null;

	/**
	 * Initialize and return the instance.
	 *
	 * @return SM_Rate_Limiter
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'maybe_schedule_cleanup' ) );
		add_action( self::CLEANUP_HOOK, array( $this, 'cleanup_expired' ) );
	}

	/**
	 * Check and increment a rate limit bucket.
	 *
	 * @param string $key     Unique bucket key.
	 * @param int    $limit   Allowed attempts within the window.
	 * @param int    $window  Window in seconds.
	 * @param array  $context Optional context for logging.
	 * @return true|WP_Error
	 */
	public static function check( $key, $limit, $window, $context = array() ) {
		$key      = self::normalize_key( $key );
		$cache_key = self::PREFIX . md5( $key );
		$window   = max( 1, (int) $window );
		$limit    = max( 1, (int) $limit );
		$now      = time();
		$record   = get_transient( $cache_key );

		if ( ! is_array( $record ) || empty( $record['expires'] ) || $record['expires'] <= $now ) {
			$record = array(
				'count'   => 1,
				'expires' => $now + $window,
			);

			set_transient( $cache_key, $record, $window );
			return true;
		}

		if ( (int) $record['count'] >= $limit ) {
			$retry_after = max( 1, (int) $record['expires'] - $now );

			self::log_block( $key, $limit, $window, $retry_after, $context );

			return new WP_Error(
				'rate_limited',
				__( 'Too many requests. Please try again later.', 'mystic-palm-reading' ),
				array(
					'status'      => 429,
					'retry_after' => $retry_after,
				)
			);
		}

		$record['count'] = (int) $record['count'] + 1;
		$ttl             = max( 1, (int) $record['expires'] - $now );

		set_transient( $cache_key, $record, $ttl );

		return true;
	}

	/**
	 * Build a consistent key from bucket + identifiers.
	 *
	 * @param string       $bucket      Logical bucket name.
	 * @param string|array $identifiers Additional identifiers.
	 * @return string
	 */
	public static function build_key( $bucket, $identifiers = array() ) {
		$parts   = array( sanitize_key( (string) $bucket ) );
		$targets = is_array( $identifiers ) ? $identifiers : array( $identifiers );

		foreach ( $targets as $target ) {
			$parts[] = self::sanitize_identifier( $target );
		}

		return implode( '|', array_filter( $parts ) );
	}

	/**
	 * Schedule cleanup cron if not set.
	 *
	 * @return void
	 */
	public function maybe_schedule_cleanup() {
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	/**
	 * Cleanup expired rate limit transients to keep the table lean.
	 *
	 * @return void
	 */
	public function cleanup_expired() {
		global $wpdb;

		$timeout_like = $wpdb->esc_like( '_transient_timeout_' . self::PREFIX ) . '%';
		$expired      = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
				$timeout_like,
				time()
			)
		);

		if ( empty( $expired ) ) {
			return;
		}

		foreach ( $expired as $timeout_key ) {
			$data_key = str_replace( '_transient_timeout_', '_transient_', $timeout_key );
			delete_option( $timeout_key );
			delete_option( $data_key );
		}
	}

	/**
	 * Normalize a key for storage.
	 *
	 * @param string $key Raw key.
	 * @return string
	 */
	private static function normalize_key( $key ) {
		return trim( (string) $key );
	}

	/**
	 * Sanitize identifier fragments.
	 *
	 * @param mixed $value Identifier value.
	 * @return string
	 */
	private static function sanitize_identifier( $value ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			$value = wp_json_encode( $value );
		}

		return sanitize_text_field( wp_unslash( (string) $value ) );
	}

	/**
	 * Log a blocked attempt.
	 *
	 * @param string $key         Bucket key.
	 * @param int    $limit       Limit.
	 * @param int    $window      Window.
	 * @param int    $retry_after Retry seconds.
	 * @param array  $context     Extra context.
	 * @return void
	 */
	private static function log_block( $key, $limit, $window, $retry_after, $context = array() ) {
		if ( ! class_exists( 'SM_Logger' ) ) {
			return;
		}

		SM_Logger::warning(
			'RATE_LIMITED',
			'Rate limit exceeded',
			array_merge(
				array(
					'key'         => $key,
					'limit'       => (int) $limit,
					'window'      => (int) $window,
					'retry_after' => (int) $retry_after,
				),
				is_array( $context ) ? $context : array()
			)
		);
	}
}
