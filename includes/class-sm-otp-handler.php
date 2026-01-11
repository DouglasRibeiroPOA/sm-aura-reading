<?php
/**
 * OTP handler for email verification.
 *
 * @package MysticPalmReadingAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages OTP generation, storage, and verification.
 */
class SM_OTP_Handler {

	/**
	 * Singleton instance.
	 *
	 * @var SM_OTP_Handler|null
	 */
	private static $instance = null;

	/**
	 * OTP table name.
	 *
	 * @var string
	 */
	private $table = '';

	/**
	 * Lead handler.
	 *
	 * @var SM_Lead_Handler|null
	 */
	private $lead_handler = null;

	/**
	 * Settings helper.
	 *
	 * @var SM_Settings|null
	 */
	private $settings = null;

	/**
	 * Initialize and return instance.
	 *
	 * @return SM_OTP_Handler
	 */
	public static function init() {
		return self::get_instance();
	}

	/**
	 * Get singleton instance.
	 *
	 * @return SM_OTP_Handler
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
	private function __construct() {
		if ( class_exists( 'SM_Database' ) ) {
			$db        = SM_Database::get_instance();
			$this->table = $db->get_table_name( 'otps' );
		}

		if ( class_exists( 'SM_Lead_Handler' ) ) {
			$this->lead_handler = SM_Lead_Handler::init();
		}

		if ( class_exists( 'SM_Settings' ) ) {
			$this->settings = SM_Settings::init();
		}
	}

	/**
	 * Generate and store an OTP for the given lead.
	 *
	 * @param string $lead_id Lead UUID.
	 * @param string $email   Lead email (optional; will use lead record if omitted).
	 * @return array|WP_Error {
	 *     otp_code: string (raw, for email),
	 *     expires_at: string (mysql datetime),
	 * }
	 */
	public function create_otp( $lead_id, $email = '' ) {
		global $wpdb;

		if ( '' === $this->table ) {
			return new WP_Error( 'db_not_ready', __( 'Database is not ready yet.', 'mystic-palm-reading' ) );
		}

		$lead_id = sanitize_text_field( (string) $lead_id );
		$email   = sanitize_email( (string) $email );
		$lead    = $this->get_lead( $lead_id );

		if ( '' === $lead_id || empty( $lead ) ) {
			return new WP_Error( 'invalid_lead', __( 'Invalid lead.', 'mystic-palm-reading' ) );
		}

		$target_email = ! empty( $email ) ? $email : ( isset( $lead['email'] ) ? $lead['email'] : '' );
		if ( empty( $target_email ) || ! is_email( $target_email ) ) {
			return new WP_Error( 'invalid_email', __( 'A valid email is required.', 'mystic-palm-reading' ) );
		}

		if ( $this->is_in_cooldown( $lead_id ) ) {
			$cooldown = $this->get_resend_cooldown_seconds( $lead_id );
			return new WP_Error(
				'otp_cooldown',
				sprintf(
					/* translators: %d: seconds until resend allowed */
					__( 'Please wait %d seconds before requesting a new code.', 'mystic-palm-reading' ),
					$cooldown
				)
			);
		}

		$rate_result = $this->check_send_rate_limit( $target_email );
		if ( is_wp_error( $rate_result ) ) {
			return $rate_result;
		}

		$otp_code    = $this->generate_code();
		$otp_hash    = $this->hash_code( $otp_code );
		$expires_at  = $this->expires_at();
		$cooldown_at = $this->resend_available_time();
		$otp_id      = $this->generate_uuid();
		$now         = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			$this->table,
			array(
				'id'               => $otp_id,
				'lead_id'          => $lead_id,
				'otp_hash'         => $otp_hash,
				'expires_at'       => $expires_at,
				'attempts'         => 0,
				'resend_available' => $cooldown_at,
				'created_at'       => $now,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			$this->log(
				'error',
				'OTP_CREATE_FAILED',
				'Failed to create OTP record',
				array(
					'lead_id' => $lead_id,
					'error'   => $wpdb->last_error,
				)
			);

			return new WP_Error( 'otp_create_failed', __( 'Could not generate an OTP right now.', 'mystic-palm-reading' ) );
		}

		$magic_token = $this->generate_magic_token( $otp_id, $lead_id, $expires_at );
		$magic_link  = $this->build_magic_link_url( $lead_id, $magic_token );

		$lead_name = '';
		$lead_handler = class_exists( 'SM_Lead_Handler' ) ? SM_Lead_Handler::get_instance() : null;
		if ( $lead_handler ) {
			$lead = $lead_handler->get_lead_by_id( $lead_id );
			if ( $lead && ! empty( $lead->name ) ) {
				$lead_name = sanitize_text_field( (string) $lead->name );
			}
		}

		$email_sent = $this->send_email( $target_email, $otp_code, $expires_at, $magic_link, $lead_name );

		if ( is_wp_error( $email_sent ) ) {
			return $email_sent;
		}

		$this->log(
			'info',
			'OTP_CREATED',
			'OTP created',
			array(
				'lead_id'    => $lead_id,
				'expires_at' => $expires_at,
				'email'      => $target_email,
			)
		);

		return array(
			'otp_code'   => $otp_code,
			'expires_at' => $expires_at,
		);
	}

	/**
	 * Verify an OTP for the given lead.
	 *
	 * @param string $lead_id Lead UUID.
	 * @param string $code    Raw OTP code.
	 * @return true|WP_Error
	 */
	public function verify_otp( $lead_id, $code ) {
		global $wpdb;

		if ( '' === $this->table ) {
			return new WP_Error( 'db_not_ready', __( 'Database is not ready yet.', 'mystic-palm-reading' ) );
		}

		$lead_id = sanitize_text_field( (string) $lead_id );
		$code    = $this->sanitize_code( $code );

		if ( '' === $lead_id || '' === $code ) {
			return new WP_Error( 'invalid_input', __( 'Please check your information and try again.', 'mystic-palm-reading' ) );
		}

		if ( $this->is_ip_blocked() ) {
			return new WP_Error( 'otp_rate_limited', __( 'Too many attempts. Please try again later.', 'mystic-palm-reading' ) );
		}

		$otp = $this->get_latest_otp( $lead_id );
		if ( empty( $otp ) ) {
			return new WP_Error( 'otp_not_found', __( 'No OTP found. Please request a new code.', 'mystic-palm-reading' ) );
		}

		$max_attempts = $this->get_max_attempts();

		if ( (int) $otp['attempts'] >= $max_attempts ) {
			return new WP_Error( 'otp_max_attempts', __( 'Too many attempts. Please request a new code.', 'mystic-palm-reading' ) );
		}

		$now = current_time( 'timestamp' );
		if ( strtotime( $otp['expires_at'] ) < $now ) {
			$this->increment_attempts( $otp['id'], (int) $otp['attempts'] );
			$this->log(
				'warning',
				'OTP_EXPIRED',
				'OTP expired',
				array(
					'lead_id' => $lead_id,
				)
			);

			return new WP_Error( 'otp_expired', __( 'Your code has expired. Please request a new one.', 'mystic-palm-reading' ) );
		}

		if ( ! $this->check_code( $code, $otp['otp_hash'] ) ) {
			$this->increment_attempts( $otp['id'], (int) $otp['attempts'] );
			$this->log(
				'warning',
				'OTP_INVALID',
				'Invalid OTP entered',
				array(
					'lead_id'      => $lead_id,
					'attempt'      => (int) $otp['attempts'] + 1,
					'attempts_max' => $max_attempts,
				)
			);

			$this->bump_failure_count();

			return new WP_Error( 'otp_invalid', __( 'Invalid code. Please try again.', 'mystic-palm-reading' ) );
		}

		if ( ! $this->has_remaining_success_uses( $otp['id'] ) ) {
			return new WP_Error( 'otp_max_reuse', __( 'This code has been used too many times. Please request a new one.', 'mystic-palm-reading' ) );
		}

		return $this->complete_verification( $otp['id'], $lead_id, 'code', $otp );
	}

	/**
	 * Verify OTP using a magic link token (no manual code entry).
	 *
	 * @param string $lead_id Lead UUID.
	 * @param string $token   Signed magic token.
	 * @return true|WP_Error
	 */
	public function verify_magic_token( $lead_id, $token ) {
		if ( '' === $this->table ) {
			return new WP_Error( 'db_not_ready', __( 'Database is not ready yet.', 'mystic-palm-reading' ) );
		}

		$lead_id = sanitize_text_field( (string) $lead_id );
		$token   = $this->sanitize_token( $token );

		if ( '' === $lead_id || '' === $token ) {
			return new WP_Error( 'invalid_input', __( 'Please check your information and try again.', 'mystic-palm-reading' ) );
		}

		if ( $this->is_ip_blocked() ) {
			return new WP_Error( 'otp_rate_limited', __( 'Too many attempts. Please try again later.', 'mystic-palm-reading' ) );
		}

		$parsed = $this->parse_magic_token( $token );
		if ( is_wp_error( $parsed ) ) {
			$this->log(
				'warning',
				'OTP_MAGIC_TOKEN_INVALID',
				'Magic link token failed validation',
				array(
					'lead_id' => $lead_id,
				)
			);

			return $parsed;
		}

		if ( $lead_id !== $parsed['lead_id'] ) {
			return new WP_Error( 'invalid_input', __( 'Please check your information and try again.', 'mystic-palm-reading' ) );
		}

		$otp = $this->get_otp_by_id( $parsed['otp_id'] );
		if ( empty( $otp ) ) {
			return new WP_Error( 'otp_not_found', __( 'No OTP found. Please request a new code.', 'mystic-palm-reading' ) );
		}

		if ( (int) $otp['attempts'] >= $this->get_max_attempts() ) {
			return new WP_Error( 'otp_max_attempts', __( 'Too many attempts. Please request a new code.', 'mystic-palm-reading' ) );
		}

		$now           = current_time( 'timestamp' );
		$expires_ts    = strtotime( $otp['expires_at'] );
		$token_expires = isset( $parsed['exp'] ) ? (int) $parsed['exp'] : 0;

		if ( $expires_ts < $now || $token_expires < $now ) {
			$this->increment_attempts( $otp['id'], (int) $otp['attempts'] );
			$this->log(
				'warning',
				'OTP_MAGIC_EXPIRED',
				'Magic link expired',
				array(
					'lead_id' => $lead_id,
				)
			);

			$this->bump_failure_count();

			return new WP_Error( 'otp_expired', __( 'Your link has expired. Please request a new one.', 'mystic-palm-reading' ) );
		}

		if ( ! $this->has_remaining_success_uses( $otp['id'] ) ) {
			return new WP_Error( 'otp_max_reuse', __( 'This link has been used too many times. Please request a new one.', 'mystic-palm-reading' ) );
		}

		return $this->complete_verification( $otp['id'], $lead_id, 'magic_link', $otp );
	}

	/**
	 * Check if resend is available for the lead.
	 *
	 * @param string $lead_id Lead UUID.
	 * @return bool
	 */
	public function can_resend( $lead_id ) {
		return ! $this->is_in_cooldown( $lead_id );
	}

	/**
	 * Send OTP email.
	 *
	 * @param string $email Recipient email.
	 * @param string $code  Raw OTP code.
	 * @param string $expires_at Expiration time.
	 * @param string $magic_link  Magic link URL.
	 * @return true|WP_Error
	 */
	private function send_email( $email, $code, $expires_at, $magic_link = '', $lead_name = '' ) {
		$subject = __( 'Your SoulMirror verification code', 'mystic-palm-reading' );
		$minutes = $this->get_expiration_minutes();

		$button_style = 'display:inline-block;padding:12px 18px;background:#4f46e5;color:#ffffff;font-weight:600;text-decoration:none;border-radius:8px;';
		$code_style   = 'font-size:22px;font-weight:700;letter-spacing:3px;color:#111827;';
		$text_style   = 'font-size:16px;color:#111827;line-height:1.6;margin:0 0 12px;';
		$muted_style  = 'font-size:13px;color:#6b7280;line-height:1.6;margin:0;';
		$greeting     = '' !== $lead_name ? sprintf( 'Hey %s,', $lead_name ) : __( 'Hey there,', 'mystic-palm-reading' );

		$button_html   = '';
		$fallback_html = '';

		if ( ! empty( $magic_link ) ) {
			$button_html = sprintf(
				'<p style="%1$s"><a href="%2$s" style="%3$s">%4$s</a></p>',
				'text-align:left;margin:16px 0;',
				esc_url( $magic_link ),
				$button_style,
				esc_html__( 'Verify my email', 'mystic-palm-reading' )
			);

			$fallback_html = sprintf(
				'<p style="%1$s">%2$s <a href="%3$s" style="color:#4f46e5;text-decoration:underline;">%4$s</a></p>',
				$text_style,
				esc_html__( 'If the button does not work, copy and paste this link:', 'mystic-palm-reading' ),
				esc_url( $magic_link ),
				esc_html__( 'Verify with this link', 'mystic-palm-reading' )
			);
		}

		$message  = '<!DOCTYPE html><html><body style="margin:0;padding:24px;background:#f9fafb;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,\'Helvetica Neue\',Arial,sans-serif;">';
		$message .= '<div style="max-width:520px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;padding:24px;">';
		$message .= '<h1 style="font-size:20px;margin:0 0 12px;color:#111827;">' . esc_html__( 'Verify your email to continue', 'mystic-palm-reading' ) . '</h1>';
		$message .= '<p style="' . $text_style . '">' . esc_html( $greeting ) . '</p>';
		$message .= '<p style="' . $text_style . '">' . esc_html__( 'Your verification code is below. Enter it to keep your reading moving.', 'mystic-palm-reading' ) . '</p>';
		$message .= '<p style="margin:16px 0 20px;"><span style="' . $code_style . '">' . esc_html( $code ) . '</span></p>';
		$message .= '<p style="' . $text_style . '">' . sprintf(
			/* translators: %d: minutes */
			esc_html__( 'This code expires in %d minutes.', 'mystic-palm-reading' ),
			$minutes
		) . '</p>';

		if ( ! empty( $button_html ) ) {
			$message .= '<p style="' . $text_style . '">' . esc_html__( 'Prefer one tap?', 'mystic-palm-reading' ) . '</p>';
			$message .= $button_html;
			$message .= $fallback_html;
		}

		$message .= '<p style="' . $muted_style . '">' . esc_html__( 'If you did not request this code, you can safely ignore this email.', 'mystic-palm-reading' ) . '</p>';
		$message .= '<p style="' . $muted_style . ';margin-top:16px;">SoulMirror</p>';
		$message .= '</div></body></html>';

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$sent = wp_mail( $email, $subject, $message, $headers );

		if ( ! $sent ) {
			$this->log(
				'error',
				'OTP_EMAIL_FAILED',
				'Failed to send OTP email',
				array(
					'email' => $email,
				)
			);

			return new WP_Error( 'otp_email_failed', __( 'Could not send the verification email. Please try again later.', 'mystic-palm-reading' ) );
		}

		$this->log(
			'info',
			'OTP_SENT',
			'OTP email sent',
			array(
				'email'      => $email,
				'expires_at' => $expires_at,
			)
		);

		return true;
	}

	/**
	 * Cleanup expired OTPs.
	 */
	public function cleanup_expired() {
		global $wpdb;

		if ( '' === $this->table ) {
			return;
		}

		$now = current_time( 'mysql' );
		$wpdb->query(
			$wpdb->prepare( "DELETE FROM {$this->table} WHERE expires_at < %s", $now ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Get lead by ID.
	 *
	 * @param string $lead_id Lead UUID.
	 * @return array|null
	 */
	private function get_lead( $lead_id ) {
		if ( $this->lead_handler instanceof SM_Lead_Handler ) {
			return $this->lead_handler->get_lead_by_id( $lead_id );
		}
		return null;
	}

	/**
	 * Generate a 4-digit numeric code.
	 *
	 * @return string
	 */
	private function generate_code() {
		return str_pad( (string) wp_rand( 0, 9999 ), 4, '0', STR_PAD_LEFT );
	}

	/**
	 * Hash the OTP code.
	 *
	 * @param string $code OTP code.
	 * @return string
	 */
	private function hash_code( $code ) {
		if ( function_exists( 'wp_hash_password' ) ) {
			return wp_hash_password( $code );
		}

		return password_hash( $code, PASSWORD_DEFAULT );
	}

	/**
	 * Verify the OTP code against a hash.
	 *
	 * @param string $code OTP code.
	 * @param string $hash Stored hash.
	 * @return bool
	 */
	private function check_code( $code, $hash ) {
		if ( function_exists( 'wp_check_password' ) ) {
			return wp_check_password( $code, $hash );
		}

		return password_verify( $code, $hash );
	}

	/**
	 * Generate a signed magic token for auto-verification.
	 *
	 * @param string $otp_id     OTP UUID.
	 * @param string $lead_id    Lead UUID.
	 * @param string $expires_at Expiration datetime (mysql).
	 * @return string
	 */
	private function generate_magic_token( $otp_id, $lead_id, $expires_at ) {
		$payload = array(
			'otp_id'  => $otp_id,
			'lead_id' => $lead_id,
			'exp'     => strtotime( $expires_at ),
			'iat'     => current_time( 'timestamp' ),
			'nonce'   => $this->generate_uuid(),
		);

		$payload_json = wp_json_encode( $payload );
		$payload_b64  = $this->base64url_encode( $payload_json );
		$signature    = $this->sign_magic_payload( $payload_b64 );

		return $payload_b64 . '.' . $signature;
	}

	/**
	 * Validate and parse a magic token.
	 *
	 * @param string $token Magic token string.
	 * @return array|WP_Error
	 */
	private function parse_magic_token( $token ) {
		if ( empty( $token ) || false === strpos( $token, '.' ) ) {
			return new WP_Error( 'invalid_token', __( 'Security check failed. Please request a new link.', 'mystic-palm-reading' ) );
		}

		$parts = explode( '.', $token );
		if ( count( $parts ) !== 2 ) {
			return new WP_Error( 'invalid_token', __( 'Security check failed. Please request a new link.', 'mystic-palm-reading' ) );
		}

		list( $payload_b64, $signature ) = $parts;

		$expected_signature = $this->sign_magic_payload( $payload_b64 );
		if ( ! hash_equals( $expected_signature, $signature ) ) {
			return new WP_Error( 'invalid_token', __( 'Security check failed. Please request a new link.', 'mystic-palm-reading' ) );
		}

		$payload_json = $this->base64url_decode( $payload_b64 );
		$payload      = json_decode( $payload_json, true );

		if ( ! is_array( $payload ) || empty( $payload['otp_id'] ) || empty( $payload['lead_id'] ) || empty( $payload['exp'] ) ) {
			return new WP_Error( 'invalid_token', __( 'Security check failed. Please request a new link.', 'mystic-palm-reading' ) );
		}

		return $payload;
	}

	/**
	 * Sign payload for magic link.
	 *
	 * @param string $payload_b64 Base64url encoded payload.
	 * @return string
	 */
	private function sign_magic_payload( $payload_b64 ) {
		$secret = defined( 'AUTH_SALT' ) ? AUTH_SALT : ( defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : wp_generate_uuid4() );
		$hash   = hash_hmac( 'sha256', $payload_b64, $secret, true );

		return $this->base64url_encode( $hash );
	}

	/**
	 * Base64url encode helper.
	 *
	 * @param string $data Raw data.
	 * @return string
	 */
	private function base64url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Base64url decode helper.
	 *
	 * @param string $data Encoded data.
	 * @return string|false
	 */
	private function base64url_decode( $data ) {
		$decoded = strtr( $data, '-_', '+/' );
		$padding = strlen( $decoded ) % 4;
		if ( $padding ) {
			$decoded .= str_repeat( '=', 4 - $padding );
		}
		return base64_decode( $decoded );
	}

	/**
	 * Build magic link URL.
	 *
	 * @param string $lead_id Lead UUID.
	 * @param string $token   Magic token.
	 * @return string
	 */
	private function build_magic_link_url( $lead_id, $token ) {
		$base_url = home_url( '/' );
		return add_query_arg(
			array(
				'sm_magic' => '1',
				'lead'     => $lead_id,
				'token'    => $token,
			),
			$base_url
		);
	}

	/**
	 * Enforce send rate limit: 3 per email per hour.
	 *
	 * @param string $email Email address.
	 * @return true|WP_Error
	 */
	private function check_send_rate_limit( $email ) {
		$key = SM_Rate_Limiter::build_key(
			'otp_send',
			array(
				strtolower( $email ),
				$this->get_client_ip(),
			)
		);

		$result = SM_Rate_Limiter::check(
			$key,
			4,
			2 * MINUTE_IN_SECONDS,
			array(
				'email' => $this->mask_email( $email ),
				'ip'    => $this->get_client_ip(),
			)
		);

		return $result;
	}

	/**
	 * IP-based lock after repeated failures.
	 *
	 * @return bool
	 */
	private function is_ip_blocked() {
		$key   = $this->ip_block_key();
		$count = (int) get_transient( $key );
		return $count >= 5;
	}

	/**
	 * Increment IP failure count.
	 */
	private function bump_failure_count() {
		$key   = $this->ip_block_key();
		$count = (int) get_transient( $key );
		$count++;
		set_transient( $key, $count, 10 * MINUTE_IN_SECONDS );
	}

	/**
	 * Reset IP failure count.
	 */
	private function reset_failure_count() {
		$key = $this->ip_block_key();
		delete_transient( $key );
	}

	/**
	 * Build transient key for IP throttling.
	 *
	 * @return string
	 */
	private function ip_block_key() {
		$ip = $this->get_client_ip();
		return 'sm_otp_fail_' . md5( $ip );
	}

	/**
	 * Calculate expiration datetime.
	 *
	 * @return string
	 */
	private function expires_at() {
		$minutes = $this->get_expiration_minutes();
		$expires = current_time( 'timestamp' ) + ( $minutes * MINUTE_IN_SECONDS );
		return gmdate( 'Y-m-d H:i:s', $expires + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
	}

	/**
	 * Calculate resend available datetime.
	 *
	 * @return string
	 */
	private function resend_available_time() {
		$seconds = $this->get_resend_cooldown_seconds_setting();
		$time    = current_time( 'timestamp' ) + $seconds;
		return gmdate( 'Y-m-d H:i:s', $time + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
	}

	/**
	 * Get latest OTP row for a lead.
	 *
	 * @param string $lead_id Lead UUID.
	 * @return array|null
	 */
	private function get_latest_otp( $lead_id ) {
		global $wpdb;

		if ( '' === $this->table ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE lead_id = %s ORDER BY created_at DESC LIMIT 1",
				$lead_id
			), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
	}

	/**
	 * Get OTP by its ID.
	 *
	 * @param string $otp_id OTP UUID.
	 * @return array|null
	 */
	private function get_otp_by_id( $otp_id ) {
		global $wpdb;

		if ( '' === $this->table ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %s LIMIT 1",
				$otp_id
			),
			ARRAY_A
		);
	}

	/**
	 * Determine if the lead is in resend cooldown.
	 *
	 * @param string $lead_id Lead UUID.
	 * @return bool
	 */
	private function is_in_cooldown( $lead_id ) {
		$otp = $this->get_latest_otp( $lead_id );

		if ( empty( $otp ) || empty( $otp['resend_available'] ) ) {
			return false;
		}

		$now = current_time( 'timestamp' );
		return strtotime( $otp['resend_available'] ) > $now;
	}

	/**
	 * Seconds remaining before resend is allowed.
	 *
	 * @param string $lead_id Lead UUID.
	 * @return int
	 */
	private function get_resend_cooldown_seconds( $lead_id ) {
		$otp = $this->get_latest_otp( $lead_id );
		if ( empty( $otp ) || empty( $otp['resend_available'] ) ) {
			return 0;
		}

		$remaining = strtotime( $otp['resend_available'] ) - current_time( 'timestamp' );
		return max( 0, (int) $remaining );
	}

	/**
	 * Increment attempts for an OTP.
	 *
	 * @param string $otp_id   OTP UUID.
	 * @param int    $attempts Current attempts.
	 */
	private function increment_attempts( $otp_id, $attempts ) {
		global $wpdb;

		$payload = array(
			'attempts' => $attempts + 1,
		);
		$formats = array( '%d' );

		static $has_updated_at = null;
		if ( null === $has_updated_at ) {
			$has_updated_at = (bool) $wpdb->get_var(
				$wpdb->prepare(
					"SHOW COLUMNS FROM {$this->table} LIKE %s",
					'updated_at'
				)
			);
		}

		if ( $has_updated_at ) {
			$payload['updated_at'] = current_time( 'mysql' );
			$formats[] = '%s';
		}

		$wpdb->update(
			$this->table,
			$payload,
			array( 'id' => $otp_id ),
			$formats,
			array( '%s' )
		);
	}

	/**
	 * Sanitize OTP code to digits.
	 *
	 * @param string $code Raw code.
	 * @return string
	 */
	private function sanitize_code( $code ) {
		$code = preg_replace( '/\D+/', '', (string) $code );
		return substr( $code, 0, 4 );
	}

	/**
	 * Sanitize token input.
	 *
	 * @param string $token Raw token string.
	 * @return string
	 */
	private function sanitize_token( $token ) {
		$token = (string) $token;
		return preg_replace( '/[^A-Za-z0-9\.\-\_\=]/', '', $token );
	}

	/**
	 * Get the maximum number of successful uses allowed per OTP (code or magic link).
	 *
	 * @return int
	 */
	public function get_success_reuse_limit() {
		return 20;
	}

	/**
	 * Determine if the OTP has remaining successful uses.
	 *
	 * @param string $otp_id OTP UUID.
	 * @return bool
	 */
	private function has_remaining_success_uses( $otp_id ) {
		$limit = $this->get_success_reuse_limit();
		$count = $this->get_success_use_count( $otp_id );

		return $count < $limit;
	}

	/**
	 * Increment successful use count and return the new total.
	 *
	 * @param string     $otp_id     OTP UUID.
	 * @param array|null $otp_record OTP record for TTL reference (optional).
	 * @return int
	 */
	private function increment_success_use( $otp_id, $otp_record = null ) {
		$key   = $this->get_success_use_key( $otp_id );
		$count = (int) get_transient( $key );
		$count++;

		$ttl = $this->get_success_use_ttl( $otp_record );
		set_transient( $key, $count, $ttl );

		return $count;
	}

	/**
	 * Get current successful use count for an OTP.
	 *
	 * @param string $otp_id OTP UUID.
	 * @return int
	 */
	private function get_success_use_count( $otp_id ) {
		$key   = $this->get_success_use_key( $otp_id );
		$count = get_transient( $key );

		return (int) $count;
	}

	/**
	 * Build success-use transient key.
	 *
	 * @param string $otp_id OTP UUID.
	 * @return string
	 */
	private function get_success_use_key( $otp_id ) {
		return 'sm_otp_success_' . md5( $otp_id );
	}

	/**
	 * Determine TTL for success-use counter based on OTP expiration.
	 *
	 * @param array|null $otp_record OTP record (expects expires_at).
	 * @return int
	 */
	private function get_success_use_ttl( $otp_record ) {
		$default_ttl = HOUR_IN_SECONDS;

		if ( ! is_array( $otp_record ) || empty( $otp_record['expires_at'] ) ) {
			return $default_ttl;
		}

		$expires_ts = strtotime( $otp_record['expires_at'] );
		$now        = current_time( 'timestamp' );
		$ttl        = $expires_ts - $now;

		return max( MINUTE_IN_SECONDS, (int) $ttl );
	}

	/**
	 * Reset attempts counter after a successful verification.
	 *
	 * @param string $otp_id OTP UUID.
	 * @return void
	 */
	private function reset_attempts( $otp_id ) {
		global $wpdb;

		$wpdb->update(
			$this->table,
			array( 'attempts' => 0 ),
			array( 'id' => $otp_id ),
			array( '%d' ),
			array( '%s' )
		);
	}

	/**
	 * Generate a UUID.
	 *
	 * @return string
	 */
	private function generate_uuid() {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}

		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0x0fff ) | 0x4000,
			wp_rand( 0, 0x3fff ) | 0x8000,
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff )
		);
	}

	/**
	 * Get expiration minutes from settings.
	 *
	 * @return int
	 */
	private function get_expiration_minutes() {
		if ( $this->settings instanceof SM_Settings && method_exists( $this->settings, 'get_otp_expiration_minutes' ) ) {
			return max( 1, (int) $this->settings->get_otp_expiration_minutes() );
		}

		return 10;
	}

	/**
	 * Get max attempts from settings.
	 *
	 * @return int
	 */
	private function get_max_attempts() {
		if ( $this->settings instanceof SM_Settings && method_exists( $this->settings, 'get_otp_max_attempts' ) ) {
			return max( 1, (int) $this->settings->get_otp_max_attempts() );
		}

		return 3;
	}

	/**
	 * Get resend cooldown minutes.
	 *
	 * @return int
	 */
	private function get_resend_cooldown_minutes() {
		if ( $this->settings instanceof SM_Settings && method_exists( $this->settings, 'get_otp_resend_cooldown_minutes' ) ) {
			return max( 1, (int) $this->settings->get_otp_resend_cooldown_minutes() );
		}

		return 10;
	}

	/**
	 * Get resend cooldown seconds.
	 *
	 * @return int
	 */
	private function get_resend_cooldown_seconds_setting() {
		$seconds = 30;
		if ( $this->settings instanceof SM_Settings && method_exists( $this->settings, 'get_otp_resend_cooldown_seconds' ) ) {
			$seconds = (int) $this->settings->get_otp_resend_cooldown_seconds();
		}

		$seconds = (int) apply_filters( 'sm_aura_otp_resend_cooldown_seconds', $seconds );
		return max( 30, $seconds );
	}

	/**
	 * Proxy logging.
	 *
	 * @param string $level      Log level.
	 * @param string $event_type Event type.
	 * @param string $message    Message.
	 * @param array  $context    Context data.
	 */
	private function log( $level, $event_type, $message, $context = array() ) {
		if ( class_exists( 'SM_Logger' ) ) {
			SM_Logger::log( $level, $event_type, $message, $context );
		}
	}

	/**
	 * Basic email masking for logs.
	 *
	 * @param string $email Email address.
	 * @return string
	 */
	private function mask_email( $email ) {
		if ( empty( $email ) || false === strpos( $email, '@' ) ) {
			return $email;
		}

		list( $user, $domain ) = explode( '@', $email, 2 );
		$masked_user           = strlen( $user ) > 2 ? substr( $user, 0, 2 ) . '***' : substr( $user, 0, 1 ) . '***';

		return $masked_user . '@' . $domain;
	}

	/**
	 * Retrieve client IP for rate limiting.
	 *
	 * @return string
	 */
	private function get_client_ip() {
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return 'unknown';
	}

	/**
	 * Finalize successful verification (common to code and magic link).
	 *
	 * @param string $otp_id  OTP UUID.
	 * @param string $lead_id Lead UUID.
	 * @param string $method  Verification method.
	 * @param array  $otp     OTP record (optional, for TTL).
	 * @return true|WP_Error
	 */
	private function complete_verification( $otp_id, $lead_id, $method = 'code', $otp = null ) {
		$success_count = $this->increment_success_use( $otp_id, $otp );
		$this->reset_attempts( $otp_id );

		if ( $this->lead_handler instanceof SM_Lead_Handler ) {
			$this->lead_handler->mark_email_confirmed( $lead_id );
		}

		$this->log(
			'info',
			'OTP_VERIFIED',
			'OTP verified successfully',
			array(
				'lead_id'       => $lead_id,
				'method'        => $method,
				'success_count' => $success_count,
			)
		);

		do_action( 'sm_otp_verified', $lead_id );

		$this->reset_failure_count();

		return true;
	}
}
