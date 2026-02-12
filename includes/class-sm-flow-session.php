<?php
/**
 * Flow session manager (DB-backed, cookie-identified).
 *
 * @package MysticPalmReadingAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SM_Flow_Session {

	/**
	 * Cookie name for the flow session.
	 */
	const COOKIE_NAME = 'sm_flow_id';

	/**
	 * Flow session TTL in seconds (default 24 hours).
	 */
	const FLOW_TTL = DAY_IN_SECONDS;

	/**
	 * Singleton instance.
	 *
	 * @var SM_Flow_Session|null
	 */
	private static $instance = null;

	/**
	 * Initialize the flow session manager.
	 *
	 * @return SM_Flow_Session
	 */
	public static function init() {
		return self::get_instance();
	}

	/**
	 * Retrieve singleton instance.
	 *
	 * @return SM_Flow_Session
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {}

	/**
	 * Get or create the current flow session.
	 *
	 * @return array<string,mixed>
	 */
	public function get_or_create_flow() {
		$flow_id = $this->get_flow_id_from_cookie();

		if ( $flow_id ) {
			$flow = $this->get_flow_by_id( $flow_id );
			if ( $flow && ! $this->is_flow_expired( $flow ) ) {
				$this->maybe_link_account( $flow_id );
				return $flow;
			}

			$this->delete_flow( $flow_id );
		}

		return $this->create_flow();
	}

	/**
	 * Update the flow session with new state values.
	 *
	 * @param string $flow_id Flow identifier.
	 * @param array  $updates Key/value pairs to update.
	 * @return array<string,mixed>|null
	 */
	public function update_flow( $flow_id, $updates ) {
		$flow_id = sanitize_text_field( (string) $flow_id );
		if ( '' === $flow_id ) {
			return null;
		}

		$allowed = array(
			'account_id'       => 'sanitize_text_field',
			'lead_id'          => 'sanitize_text_field',
			'reading_id'       => 'sanitize_text_field',
			'email'            => 'sanitize_email',
			'step_id'          => 'sanitize_text_field',
			'status'           => 'sanitize_text_field',
			'magic_token_hash' => 'sanitize_text_field',
			'magic_expires_at' => 'sanitize_text_field',
		);

		$data = array();
		foreach ( $allowed as $key => $sanitize_fn ) {
			if ( array_key_exists( $key, $updates ) ) {
				$value = call_user_func( $sanitize_fn, (string) $updates[ $key ] );
				if ( '' !== $value ) {
					$data[ $key ] = $value;
				}
			}
		}

		if ( empty( $data ) ) {
			return $this->get_flow_by_id( $flow_id );
		}

		$now        = $this->current_time_mysql();
		$expires_at = $this->calculate_expiration();
		$data['updated_at'] = $now;
		$data['expires_at'] = $expires_at;

		global $wpdb;
		$table = $this->get_table_name();
		if ( empty( $table ) ) {
			return null;
		}

		$wpdb->update(
			$table,
			$data,
			array( 'flow_id' => $flow_id ),
			null,
			array( '%s' )
		);

		return $this->get_flow_by_id( $flow_id );
	}

	/**
	 * Reset the flow session and clear the cookie.
	 *
	 * @return array<string,mixed>
	 */
	public function reset_flow() {
		$flow_id = $this->get_flow_id_from_cookie();
		if ( $flow_id ) {
			$this->delete_flow( $flow_id );
		}

		$this->clear_flow_cookie();

		return $this->create_flow();
	}

	/**
	 * Get a flow session by flow_id.
	 *
	 * @param string $flow_id Flow identifier.
	 * @return array<string,mixed>|null
	 */
	public function get_flow_by_id( $flow_id ) {
		$flow_id = sanitize_text_field( (string) $flow_id );
		if ( '' === $flow_id ) {
			return null;
		}

		global $wpdb;
		$table = $this->get_table_name();
		if ( empty( $table ) ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE flow_id = %s LIMIT 1",
				$flow_id
			),
			ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Check if a flow session is expired.
	 *
	 * @param array $flow Flow row data.
	 * @return bool
	 */
	public function is_flow_expired( $flow ) {
		if ( ! is_array( $flow ) || empty( $flow['expires_at'] ) ) {
			return true;
		}

		$expires_ts = strtotime( $flow['expires_at'] );
		if ( false === $expires_ts ) {
			return true;
		}

		return $expires_ts < current_time( 'timestamp' );
	}

	/**
	 * Create a new flow session row and set cookie.
	 *
	 * @return array<string,mixed>
	 */
	private function create_flow() {
		global $wpdb;
		$table = $this->get_table_name();
		if ( empty( $table ) ) {
			return array();
		}

		$flow_id    = wp_generate_uuid4();
		$now        = $this->current_time_mysql();
		$expires_at = $this->calculate_expiration();
		$account_id = $this->get_account_id();

		$wpdb->insert(
			$table,
			array(
				'flow_id'    => $flow_id,
				'account_id' => $account_id,
				'step_id'    => 'welcome',
				'status'     => 'in_progress',
				'expires_at' => $expires_at,
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		$this->set_flow_cookie( $flow_id );

		return $this->get_flow_by_id( $flow_id );
	}

	/**
	 * Link account_id to a flow session when available.
	 *
	 * @param string $flow_id Flow identifier.
	 * @return void
	 */
	private function maybe_link_account( $flow_id ) {
		$account_id = $this->get_account_id();
		if ( '' === $account_id ) {
			return;
		}

		$this->update_flow(
			$flow_id,
			array( 'account_id' => $account_id )
		);
	}

	/**
	 * Delete a flow session row.
	 *
	 * @param string $flow_id Flow identifier.
	 * @return void
	 */
	private function delete_flow( $flow_id ) {
		global $wpdb;
		$table = $this->get_table_name();
		if ( empty( $table ) ) {
			return;
		}

		$wpdb->delete(
			$table,
			array( 'flow_id' => sanitize_text_field( (string) $flow_id ) ),
			array( '%s' )
		);
	}

	/**
	 * Get the flow session table name.
	 *
	 * @return string
	 */
	private function get_table_name() {
		if ( ! class_exists( 'SM_Database' ) ) {
			return '';
		}

		return SM_Database::get_instance()->get_table_name( 'flow_sessions' );
	}

	/**
	 * Return the current flow_id cookie value.
	 *
	 * @return string
	 */
	private function get_flow_id_from_cookie() {
		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
	}

	/**
	 * Set the flow_id cookie.
	 *
	 * @param string $flow_id Flow identifier.
	 * @return void
	 */
	private function set_flow_cookie( $flow_id ) {
		if ( headers_sent() ) {
			return;
		}

		$expires = time() + self::FLOW_TTL;

		setcookie(
			self::COOKIE_NAME,
			sanitize_text_field( (string) $flow_id ),
			array(
				'expires'  => $expires,
				'path'     => defined( 'COOKIEPATH' ) ? COOKIEPATH : '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * Clear the flow_id cookie.
	 *
	 * @return void
	 */
	private function clear_flow_cookie() {
		if ( headers_sent() ) {
			return;
		}

		setcookie(
			self::COOKIE_NAME,
			'',
			array(
				'expires'  => time() - DAY_IN_SECONDS,
				'path'     => defined( 'COOKIEPATH' ) ? COOKIEPATH : '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * Get account_id from auth handler if logged in.
	 *
	 * @return string
	 */
	private function get_account_id() {
		if ( ! class_exists( 'SM_Auth_Handler' ) ) {
			return '';
		}

		$user = SM_Auth_Handler::get_instance()->get_current_user();
		if ( empty( $user['account_id'] ) ) {
			return '';
		}

		return sanitize_text_field( (string) $user['account_id'] );
	}

	/**
	 * Current time in MySQL format.
	 *
	 * @return string
	 */
	private function current_time_mysql() {
		return current_time( 'mysql' );
	}

	/**
	 * Calculate expiration time in MySQL format.
	 *
	 * @return string
	 */
	private function calculate_expiration() {
		$expires_ts = current_time( 'timestamp' ) + self::FLOW_TTL;
		return date( 'Y-m-d H:i:s', $expires_ts );
	}
}
