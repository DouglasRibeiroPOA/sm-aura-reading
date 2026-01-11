<?php
/**
 * Teaser reading access token helper.
 *
 * @package MysticPalmReadingAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SM_Reading_Token {
	/**
	 * Default token TTL (7 days).
	 */
	const DEFAULT_TTL = WEEK_IN_SECONDS;

	/**
	 * Generate a signed access token for a teaser reading.
	 *
	 * @param string $lead_id Lead UUID.
	 * @param string $reading_id Reading UUID.
	 * @param string $reading_type Reading type.
	 * @param int|null $ttl Override TTL in seconds.
	 * @return string
	 */
	public static function generate( $lead_id, $reading_id, $reading_type = 'aura_teaser', $ttl = null ) {
		$lead_id    = sanitize_text_field( (string) $lead_id );
		$reading_id = sanitize_text_field( (string) $reading_id );
		$reading_type = sanitize_text_field( (string) $reading_type );

		if ( '' === $lead_id || '' === $reading_id ) {
			return '';
		}

		$ttl = is_int( $ttl ) ? $ttl : (int) apply_filters( 'sm_aura_teaser_token_ttl', self::DEFAULT_TTL );
		if ( $ttl <= 0 ) {
			$ttl = self::DEFAULT_TTL;
		}

		$now     = current_time( 'timestamp' );
		$payload = array(
			'lead_id'      => $lead_id,
			'reading_id'   => $reading_id,
			'reading_type' => $reading_type,
			'exp'          => $now + $ttl,
			'iat'          => $now,
			'nonce'        => wp_generate_uuid4(),
		);

		$payload_json = wp_json_encode( $payload );
		$payload_b64  = self::base64url_encode( $payload_json );
		$signature    = self::sign_payload( $payload_b64 );

		return $payload_b64 . '.' . $signature;
	}

	/**
	 * Validate a teaser reading access token.
	 *
	 * @param string $token Token string.
	 * @param string $expected_lead_id Optional lead ID to match.
	 * @param array $allowed_types Allowed reading types.
	 * @return array|WP_Error
	 */
	public static function validate( $token, $expected_lead_id = '', $allowed_types = array( 'aura_teaser' ) ) {
		$token = sanitize_text_field( (string) $token );
		if ( '' === $token || false === strpos( $token, '.' ) ) {
			return new WP_Error( 'token_invalid', __( 'Security check failed. Please request a new link.', 'mystic-aura-reading' ) );
		}

		$parts = explode( '.', $token );
		if ( 2 !== count( $parts ) ) {
			return new WP_Error( 'token_invalid', __( 'Security check failed. Please request a new link.', 'mystic-aura-reading' ) );
		}

		list( $payload_b64, $signature ) = $parts;

		$expected_signature = self::sign_payload( $payload_b64 );
		if ( ! hash_equals( $expected_signature, $signature ) ) {
			return new WP_Error( 'token_invalid', __( 'Security check failed. Please request a new link.', 'mystic-aura-reading' ) );
		}

		$payload_json = self::base64url_decode( $payload_b64 );
		$payload      = json_decode( $payload_json, true );

		if ( ! is_array( $payload ) || empty( $payload['lead_id'] ) || empty( $payload['exp'] ) ) {
			return new WP_Error( 'token_invalid', __( 'Security check failed. Please request a new link.', 'mystic-aura-reading' ) );
		}

		if ( empty( $payload['reading_id'] ) || empty( $payload['reading_type'] ) ) {
			return new WP_Error( 'token_not_reading', __( 'Security check failed. Please request a new link.', 'mystic-aura-reading' ) );
		}

		$now = current_time( 'timestamp' );
		if ( (int) $payload['exp'] < $now ) {
			return new WP_Error( 'token_expired', __( 'This link has expired. Please request a new one.', 'mystic-aura-reading' ) );
		}

		$expected_lead_id = sanitize_text_field( (string) $expected_lead_id );
		if ( '' !== $expected_lead_id && $expected_lead_id !== (string) $payload['lead_id'] ) {
			return new WP_Error( 'token_mismatch', __( 'Security check failed. Please request a new link.', 'mystic-aura-reading' ) );
		}

		$reading_type = (string) $payload['reading_type'];
		if ( ! in_array( $reading_type, $allowed_types, true ) ) {
			return new WP_Error( 'token_type_invalid', __( 'Security check failed. Please request a new link.', 'mystic-aura-reading' ) );
		}

		return $payload;
	}

	/**
	 * Sign payload for reading token.
	 *
	 * @param string $payload_b64 Base64url encoded payload.
	 * @return string
	 */
	private static function sign_payload( $payload_b64 ) {
		$secret = defined( 'AUTH_SALT' ) ? AUTH_SALT : ( defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : wp_generate_uuid4() );
		$hash   = hash_hmac( 'sha256', $payload_b64, $secret, true );

		return self::base64url_encode( $hash );
	}

	/**
	 * Base64url encode helper.
	 *
	 * @param string $data Raw data.
	 * @return string
	 */
	private static function base64url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Base64url decode helper.
	 *
	 * @param string $data Encoded data.
	 * @return string|false
	 */
	private static function base64url_decode( $data ) {
		$decoded = strtr( $data, '-_', '+/' );
		$padding = strlen( $decoded ) % 4;
		if ( $padding ) {
			$decoded .= str_repeat( '=', 4 - $padding );
		}
		return base64_decode( $decoded );
	}
}
