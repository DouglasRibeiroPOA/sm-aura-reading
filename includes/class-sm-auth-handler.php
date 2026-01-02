<?php

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class SM_Auth_Handler
 *
 * Handles JWT authentication, session management, and user account interactions
 * with the SoulMirror Account Service.
 */
class SM_Auth_Handler {

	/**
	 * Session key for JWT token.
	 */
	const SESSION_TOKEN_KEY = 'sm_auth_jwt_token';

	/**
	 * Session key for user data.
	 */
	const SESSION_USER_KEY = 'sm_auth_user_data';

	/**
	 * Session key for post-auth redirect URL.
	 */
	const SESSION_REDIRECT_KEY = 'sm_auth_redirect_url';

	/**
	 * Cookie name for JWT token.
	 */
	const COOKIE_TOKEN_NAME = 'sm_auth_token';

	/**
	 * Singleton instance.
	 *
	 * @var SM_Auth_Handler|null
	 */
	private static $instance = null;

	/**
	 * Initialize the handler.
	 *
	 * @return SM_Auth_Handler
	 */
	public static function init() {
		return self::get_instance();
	}

	/**
	 * Get singleton instance.
	 *
	 * @return SM_Auth_Handler
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initializes the authentication handler, adding WordPress hooks.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'ensure_session' ), 1 );
		add_action( 'init', array( $this, 'register_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_handle_callback' ) );
	}

	/**
	 * Ensure PHP session is started for auth storage.
	 */
	public function ensure_session() {
		if ( headers_sent() ) {
			return;
		}

		if ( ! session_id() ) {
			session_start();
		}
	}

	/**
	 * Register rewrite rules for auth callback.
	 */
	public function register_rewrite_rules() {
		add_rewrite_tag( '%sm_auth_callback%', '1' );
		add_rewrite_rule( '^aura-reading/auth/callback/?$', 'index.php?sm_auth_callback=1', 'top' );
	}

	/**
	 * Register custom query vars.
	 *
	 * @param array $vars Existing query vars.
	 * @return array
	 */
	public function register_query_vars( $vars ) {
		$vars[] = 'sm_auth_callback';
		return $vars;
	}

	/**
	 * Handle callback requests routed via rewrite rules.
	 */
	public function maybe_handle_callback() {
		$path = '';
		if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$parsed = wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
			$path   = isset( $parsed['path'] ) ? rtrim( $parsed['path'], '/' ) : '';
		}

		$callback_path = rtrim( wp_parse_url( home_url( '/aura-reading/auth/callback' ), PHP_URL_PATH ), '/' );

		if ( get_query_var( 'sm_auth_callback' ) || ( $path && $callback_path && $path === $callback_path ) ) {
			$this->handle_callback();
		}
	}

	/**
	 * Handles the JWT callback from the Account Service.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function handle_callback( $request = null ) {
		$this->ensure_session();

		$callback_params = array();
		$callback_values = array();
		foreach ( $_GET as $key => $value ) {
			$callback_params[] = sanitize_text_field( (string) $key );
			// Log first 100 chars of each value for debugging
			$callback_values[ $key ] = is_string( $value ) ? substr( $value, 0, 100 ) : $value;
		}
		SM_Logger::info(
			'AUTH_CALLBACK',
			'Auth callback received',
			array(
				'param_keys' => $callback_params,
				'param_values' => $callback_values,
				'raw_request_uri' => isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '',
			)
		);

		$settings = SM_Settings::init()->get_settings();
		if ( empty( $settings['enable_account_integration'] ) ) {
			SM_Logger::log( 'warning', 'AUTH_CALLBACK', 'Account integration disabled.' );
			wp_die( esc_html__( 'Account integration is currently disabled.', 'mystic-palm-reading' ) );
		}

		$jwt_token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		if ( empty( $jwt_token ) ) {
			SM_Logger::log( 'warning', 'AUTH_CALLBACK', 'Missing JWT token in callback.' );
			wp_die( esc_html__( 'Missing authentication token. Please try logging in again.', 'mystic-palm-reading' ) );
		}

		$validation = $this->validate_jwt_token( $jwt_token );
		if ( empty( $validation['success'] ) || empty( $validation['data'] ) ) {
			SM_Logger::log(
				'error',
				'AUTH_VALIDATE_FAILED',
				'JWT validation failed.',
				array(
					'error' => isset( $validation['message'] ) ? $validation['message'] : 'unknown_error',
				)
			);
			wp_die( esc_html__( 'Authentication failed. Please try logging in again.', 'mystic-palm-reading' ) );
		}

		$user_data = $validation['data'];
		$profile   = $this->get_account_profile( $jwt_token );
		$missing   = $this->get_missing_profile_fields( $user_data, $profile );

		if ( ! empty( $missing ) ) {
			$this->clear_auth_storage();
			wp_logout();

			SM_Logger::log(
				'warning',
				'AUTH_PROFILE_INVALID',
				'Account profile missing required fields.',
				array(
					'missing_fields' => $missing,
				)
			);

			$redirect_url = add_query_arg(
				array(
					'sm_auth_error'   => 'missing_profile',
					'sm_auth_missing' => implode( ',', $missing ),
				),
				home_url( '/' )
			);

			wp_safe_redirect( $redirect_url );
			exit;
		}

		$merged_user = $this->merge_user_profile( $user_data, $profile );
		$this->store_jwt_token( $jwt_token, $merged_user );
		$this->link_account_to_email( $merged_user );

		$redirect_url = $this->resolve_post_auth_redirect();
		SM_Logger::info(
			'AUTH_CALLBACK',
			'Auth callback redirect resolved',
			array(
				'redirect_url' => $redirect_url,
				'session_redirect' => isset( $_SESSION[ self::SESSION_REDIRECT_KEY ] ) ? $_SESSION[ self::SESSION_REDIRECT_KEY ] : 'not set',
				'get_redirect' => isset( $_GET['redirect'] ) ? $_GET['redirect'] : 'not set',
				'get_redirect_url' => isset( $_GET['redirect_url'] ) ? $_GET['redirect_url'] : 'not set',
			)
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Stores the JWT token in the user's session.
	 *
     * @param string $jwt_token The JWT token.
     * @param array $user_data The user data from the token.
     */
	public function store_jwt_token( $jwt_token, $user_data ) {
		$this->ensure_session();

		$_SESSION[ self::SESSION_TOKEN_KEY ] = $jwt_token;
		$_SESSION[ self::SESSION_USER_KEY ]  = $user_data;

		$expires_at = $this->get_token_expiration( $jwt_token );
		$this->set_auth_cookie( $jwt_token, $expires_at );

		SM_Logger::log(
			'info',
			'AUTH_SESSION',
			'Stored JWT token in session.',
			array(
				'account_id' => isset( $user_data['account_id'] ) ? $user_data['account_id'] : null,
			)
		);
	}

	/**
	 * Retrieves the current logged-in user's data.
     *
     * @return array|null The user data or null if not logged in.
     */
	public function get_current_user() {
		$this->ensure_session();

		$jwt_token = $this->get_token_from_storage();
		if ( empty( $jwt_token ) ) {
			return null;
		}

		$expires_at = $this->get_token_expiration( $jwt_token );
		if ( $expires_at && time() >= $expires_at ) {
			$this->clear_auth_storage();
			return null;
		}

		if ( ! empty( $_SESSION[ self::SESSION_USER_KEY ] ) ) {
			return $_SESSION[ self::SESSION_USER_KEY ];
		}

		$validation = $this->validate_jwt_token( $jwt_token );
		if ( empty( $validation['success'] ) || empty( $validation['data'] ) ) {
			$this->clear_auth_storage();
			return null;
		}

		$this->store_jwt_token( $jwt_token, $validation['data'] );
		return $validation['data'];

		return null;
	}

	/**
	 * Fetch the authenticated user's profile from the Account Service.
	 *
	 * @return array|null
	 */
	public function get_account_profile( $jwt_token = '' ) {
		$this->ensure_session();

		if ( empty( $jwt_token ) ) {
			$jwt_token = $this->get_token_from_storage();
		} else {
			$jwt_token = sanitize_text_field( $jwt_token );
		}
		if ( empty( $jwt_token ) ) {
			return null;
		}

		$settings = SM_Settings::init()->get_settings();
		if ( empty( $settings['enable_account_integration'] ) ) {
			return null;
		}

		$base_url = isset( $settings['account_service_url'] ) ? rtrim( $settings['account_service_url'], '/' ) : '';
		if ( empty( $base_url ) ) {
			return null;
		}

		$sslverify = true;
		$parsed_url = wp_parse_url( $base_url );
		$host = isset( $parsed_url['host'] ) ? strtolower( $parsed_url['host'] ) : '';

		if ( $host === 'localhost' || substr( $host, -6 ) === '.local' ) {
			$sslverify = false;
		}

		$response = wp_remote_get(
			$base_url . '/wp-json/soulmirror/v1/user/info',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $jwt_token,
					'Content-Type'  => 'application/json',
				),
				'timeout'   => 20,
				'sslverify' => $sslverify,
			)
		);

		if ( is_wp_error( $response ) ) {
			SM_Logger::log(
				'warning',
				'AUTH_USER_INFO',
				'Account Service user info request failed.',
				array(
					'error' => $response->get_error_message(),
				)
			);
			return null;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status || empty( $body['success'] ) || empty( $body['data'] ) ) {
			SM_Logger::log(
				'warning',
				'AUTH_USER_INFO',
				'Account Service user info response invalid.',
				array(
					'status' => $status,
				)
			);
			return null;
		}

		return $body['data'];
	}

	/**
	 * Checks if the user is currently logged in.
     *
     * @return bool True if logged in, false otherwise.
     */
	public function is_user_logged_in() {
		return null !== $this->get_current_user();
	}

    /**
     * Handles the user logout process.
     *
     * @param WP_REST_Request $request The REST request (optional).
     * @return WP_REST_Response A success response on successful logout.
     */
	public function handle_logout( WP_REST_Request $request = null ) {
		$this->clear_auth_storage();

		// Ensure WordPress session is also terminated
		wp_logout();

		SM_Logger::info(
			'AUTH_LOGOUT',
			'User logged out successfully.',
			array(
				'ip' => SM_REST_Controller::get_instance()->get_client_ip(),
			)
		);

		$redirect_url = home_url( '/' );
		$account_home = $this->get_account_service_home_url();
		$account_logout_url = $this->get_logout_url( $account_home ? $account_home : $redirect_url );
		if ( ! empty( $account_logout_url ) ) {
			$redirect_url = $account_logout_url;
		}

		// For REST API, return a success response with the redirect URL
		if ( $request instanceof WP_REST_Request ) {
			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => array(
						'logged_out'   => true,
						'redirect_url' => $redirect_url,
					),
				),
				200
			);
		}

		// Fallback for non-REST requests or direct calls.
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Get JWT token from session or cookie storage.
	 *
	 * @return string
	 */
	private function get_token_from_storage() {
		if ( ! empty( $_SESSION[ self::SESSION_TOKEN_KEY ] ) ) {
			return $_SESSION[ self::SESSION_TOKEN_KEY ];
		}

		if ( ! empty( $_COOKIE[ self::COOKIE_TOKEN_NAME ] ) ) {
			return sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_TOKEN_NAME ] ) );
		}

		return '';
	}

	/**
	 * Store JWT token in a secure cookie.
	 *
	 * @param string   $jwt_token JWT token.
	 * @param int|null $expires_at Expiration timestamp (optional).
	 */
	private function set_auth_cookie( $jwt_token, $expires_at = null ) {
		if ( headers_sent() ) {
			return;
		}

		$expires = $expires_at ? (int) $expires_at : ( time() + DAY_IN_SECONDS );

		setcookie(
			self::COOKIE_TOKEN_NAME,
			$jwt_token,
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
	 * Clear auth session and cookie.
	 */
	private function clear_auth_storage() {
		$this->ensure_session();

		unset( $_SESSION[ self::SESSION_TOKEN_KEY ], $_SESSION[ self::SESSION_USER_KEY ] );

		if ( headers_sent() ) {
			return;
		}

		setcookie(
			self::COOKIE_TOKEN_NAME,
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
	 * Extract expiration timestamp from JWT token.
	 *
	 * @param string $jwt_token JWT token.
	 * @return int|null
	 */
	private function get_token_expiration( $jwt_token ) {
		$parts = explode( '.', $jwt_token );
		if ( count( $parts ) < 2 ) {
			return null;
		}

		$payload = $this->base64url_decode( $parts[1] );
		if ( empty( $payload ) ) {
			return null;
		}

		$data = json_decode( $payload, true );
		if ( empty( $data['exp'] ) ) {
			return null;
		}

		return (int) $data['exp'];
	}

	/**
	 * Decode base64url data.
	 *
	 * @param string $data Encoded data.
	 * @return string
	 */
	private function base64url_decode( $data ) {
		$remainder = strlen( $data ) % 4;
		if ( $remainder ) {
			$data .= str_repeat( '=', 4 - $remainder );
		}

		$data = strtr( $data, '-_', '+/' );
		return base64_decode( $data );
	}

	/**
	 * Validate JWT token with Account Service API.
	 *
	 * @param string $jwt_token JWT token from callback.
	 * @return array
	 */
	private function validate_jwt_token( $jwt_token ) {
		$settings = SM_Settings::init()->get_settings();
		$base_url = isset( $settings['account_service_url'] ) ? rtrim( $settings['account_service_url'], '/' ) : '';

		if ( empty( $base_url ) ) {
			SM_Logger::log( 'error', 'AUTH_VALIDATE', 'Account Service URL missing.', array() );
			return array(
				'success' => false,
				'message' => 'missing_account_service_url',
			);
		}

		SM_Logger::log(
			'info',
			'AUTH_VALIDATE',
			'Validating JWT token with Account Service.',
			array(
				'url'          => $base_url . '/wp-json/soulmirror/v1/auth/validate',
				'token_length' => strlen( $jwt_token ),
				'token_prefix' => substr( $jwt_token, 0, 8 ),
			)
		);

		$sslverify = true;
		$parsed_url = wp_parse_url( $base_url );
		$host = isset( $parsed_url['host'] ) ? strtolower( $parsed_url['host'] ) : '';

		if ( $host === 'localhost' || substr( $host, -6 ) === '.local' ) {
			// Local dev hosts often use self-signed certs.
			$sslverify = false;
		}

		$response = wp_remote_post(
			$base_url . '/wp-json/soulmirror/v1/auth/validate',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $jwt_token,
					'Content-Type'  => 'application/json',
				),
				'timeout'   => 20,
				'sslverify' => $sslverify,
				'body'      => wp_json_encode( array() ),
			)
		);

		if ( is_wp_error( $response ) ) {
			SM_Logger::log(
				'error',
				'AUTH_VALIDATE',
				'Account Service validation request failed.',
				array(
					'error' => $response->get_error_message(),
				)
			);
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );
		$data   = json_decode( $body, true );

		if ( 200 !== $status || empty( $data['success'] ) ) {
			SM_Logger::log(
				'error',
				'AUTH_VALIDATE',
				'Account Service validation failed.',
				array(
					'status'    => $status,
					'body'      => $body,
					'raw_error' => isset( $data['error']['message'] ) ? $data['error']['message'] : 'invalid_token',
				)
			);
			return array(
				'success' => false,
				'message' => isset( $data['error']['message'] ) ? $data['error']['message'] : 'invalid_token',
			);
		}

		SM_Logger::log(
			'info',
			'AUTH_VALIDATE',
			'Account Service validation succeeded.',
			array(
				'status' => $status,
			)
		);

		return array(
			'success' => true,
			'data'    => isset( $data['data'] ) ? $data['data'] : array(),
		);
	}

	/**
	 * Link existing readings/leads to account_id by email.
	 *
	 * Automatically links free readings (account_id = NULL) to the user's account
	 * when they log in or create an account for the first time.
	 *
	 * @param array $user_data User data from token validation.
	 */
	private function link_account_to_email( $user_data ) {
		if ( empty( $user_data['account_id'] ) || empty( $user_data['email'] ) ) {
			SM_Logger::log(
				'warning',
				'AUTH_LINK_ACCOUNT',
				'Missing account_id or email in user data',
				array(
					'has_account_id' => ! empty( $user_data['account_id'] ),
					'has_email'      => ! empty( $user_data['email'] ),
				)
			);
			return;
		}

		if ( ! class_exists( 'SM_Database' ) ) {
			SM_Logger::log(
				'error',
				'AUTH_LINK_ACCOUNT',
				'SM_Database class not available',
				array()
			);
			return;
		}

		global $wpdb;
		$db             = SM_Database::get_instance();
		$account_id     = sanitize_text_field( $user_data['account_id'] );
		$email          = sanitize_email( $user_data['email'] );
		$readings_table = $db->get_table_name( 'readings' );
		$leads_table    = $db->get_table_name( 'leads' );

		// Update readings table using JOIN with leads table (readings table has no email column)
		// Case-insensitive email matching for better compatibility
		$readings_updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$readings_table} r
				INNER JOIN {$leads_table} l ON r.lead_id = l.id
				SET r.account_id = %s
				WHERE LOWER(l.email) = LOWER(%s)
				  AND (r.account_id IS NULL OR r.account_id = '')",
				$account_id,
				$email
			)
		);

		// Update leads table directly (leads table has email column)
		// Case-insensitive email matching
		$leads_updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$leads_table}
				SET account_id = %s
				WHERE LOWER(email) = LOWER(%s)
				  AND (account_id IS NULL OR account_id = '')",
				$account_id,
				$email
			)
		);

		SM_Logger::log(
			'info',
			'AUTH_LINK_ACCOUNT',
			'Linked existing free readings to account',
			array(
				'account_id'       => $account_id,
				'email'            => $email,
				'readings_updated' => $readings_updated !== false ? $readings_updated : 0,
				'leads_updated'    => $leads_updated !== false ? $leads_updated : 0,
			)
		);
	}

	/**
	 * Resolve post-auth redirect URL.
	 *
	 * @return string
	 */
	private function resolve_post_auth_redirect() {
		$redirect_url = '';

		// PRIORITY 1: Use session redirect if available (most reliable, has full URL)
		if ( ! empty( $_SESSION[ self::SESSION_REDIRECT_KEY ] ) ) {
			$session_redirect = wp_validate_redirect( $_SESSION[ self::SESSION_REDIRECT_KEY ], '' );
			if ( ! empty( $session_redirect ) ) {
				unset( $_SESSION[ self::SESSION_REDIRECT_KEY ] );
				return $session_redirect;
			}
		}

		// PRIORITY 2: Use GET redirect_url parameter
		if ( isset( $_GET['redirect_url'] ) ) {
			// Use esc_url_raw() for URLs to preserve query parameters
			$raw_redirect = esc_url_raw( wp_unslash( $_GET['redirect_url'] ) );
			$redirect_url = wp_validate_redirect( $raw_redirect, '' );
		}

		// PRIORITY 3: Use GET redirect parameter
		if ( empty( $redirect_url ) && isset( $_GET['redirect'] ) ) {
			// Use esc_url_raw() for URLs to preserve query parameters
			$raw_redirect = esc_url_raw( wp_unslash( $_GET['redirect'] ) );
			$redirect_url = wp_validate_redirect( $raw_redirect, '' );
		}

		// FALLBACK: Default to homepage
		if ( empty( $redirect_url ) ) {
			$redirect_url = home_url( '/' );
		}

		return $redirect_url;
	}

	/**
	 * Resolve required profile fields from validation payload and user profile.
	 *
	 * @param array      $user_data Token validation payload.
	 * @param array|null $profile   Account Service profile.
	 * @return array<string>
	 */
	private function get_missing_profile_fields( $user_data, $profile ) {
		$missing = array();

		$account_id = $this->resolve_profile_value( $user_data, $profile, array( 'account_id' ) );
		if ( empty( $account_id ) ) {
			$missing[] = 'account_id';
		}

		$email = $this->resolve_profile_value( $user_data, $profile, array( 'email' ) );
		if ( empty( $email ) || ! is_email( $email ) ) {
			$missing[] = 'email';
		}

		$name = $this->resolve_profile_value( $user_data, $profile, array( 'name', 'full_name', 'given_name', 'first_name', 'firstName', 'lastName' ) );
		if ( empty( $name ) ) {
			$missing[] = 'name';
		}

		$dob = $this->resolve_profile_value( $user_data, $profile, array( 'dob', 'date_of_birth', 'birthdate', 'birth_date' ) );
		if ( empty( $dob ) || ! $this->is_valid_dob( $dob ) ) {
			$missing[] = 'dob';
		}

		return $missing;
	}

	/**
	 * Merge user validation data with profile fields.
	 *
	 * @param array      $user_data Token validation payload.
	 * @param array|null $profile Account Service profile.
	 * @return array
	 */
	private function merge_user_profile( $user_data, $profile ) {
		if ( empty( $profile ) || ! is_array( $profile ) ) {
			return $user_data;
		}

		$fields = array(
			'account_id' => array( 'account_id' ),
			'email'      => array( 'email' ),
			'name'       => array( 'name', 'full_name', 'given_name', 'first_name', 'firstName', 'lastName' ),
			'dob'        => array( 'dob', 'date_of_birth', 'birthdate', 'birth_date' ),
		);

		foreach ( $fields as $target => $keys ) {
			if ( ! empty( $user_data[ $target ] ) ) {
				continue;
			}
			$value = $this->resolve_profile_value( $profile, null, $keys );
			if ( 'email' === $target ) {
				$value = sanitize_email( (string) $value );
			}
			if ( ! empty( $value ) ) {
				$user_data[ $target ] = $value;
			}
		}

		return $user_data;
	}

	/**
	 * Resolve a value from user data or profile using a list of keys.
	 *
	 * @param array      $primary Primary data array.
	 * @param array|null $secondary Secondary data array.
	 * @param array      $keys Keys to check.
	 * @return string
	 */
	private function resolve_profile_value( $primary, $secondary, $keys ) {
		foreach ( $keys as $key ) {
			if ( is_array( $primary ) && ! empty( $primary[ $key ] ) ) {
				return sanitize_text_field( (string) $primary[ $key ] );
			}
			if ( is_array( $secondary ) && ! empty( $secondary[ $key ] ) ) {
				return sanitize_text_field( (string) $secondary[ $key ] );
			}
		}

		return '';
	}

	/**
	 * Validate DOB string.
	 *
	 * @param string $dob_raw Raw DOB string.
	 * @return bool
	 */
	private function is_valid_dob( $dob_raw ) {
		$dob_raw = sanitize_text_field( (string) $dob_raw );
		if ( '' === $dob_raw ) {
			return false;
		}

		$timestamp = strtotime( $dob_raw );
		if ( false === $timestamp ) {
			return false;
		}

		$dob = new DateTime( '@' . $timestamp );
		$dob->setTimezone( wp_timezone() );
		$now = new DateTime( 'now', wp_timezone() );
		$age = (int) $dob->diff( $now )->y;

		return $age >= 0 && $age <= 120;
	}

	/**
	 * Generate login URL for Account Service with callback parameter.
	 *
	 * @param string $return_url Optional return URL after authentication (defaults to current page).
	 * @return string|null Login URL or null if Account Service not configured.
	 */
	public function get_login_url( $return_url = '' ) {
		$this->ensure_session();
		$settings = SM_Settings::init()->get_settings();

		// Check if Account Service integration is enabled
		if ( empty( $settings['enable_account_integration'] ) ) {
			return null;
		}

		$base_url = isset( $settings['account_service_url'] ) ? rtrim( $settings['account_service_url'], '/' ) : '';

		if ( empty( $base_url ) ) {
			return null;
		}

		// Determine return URL (used after callback).
		if ( empty( $return_url ) ) {
			// Default to current page or palm reading home
			$return_url = ! empty( $_SERVER['REQUEST_URI'] )
				? home_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) )
				: home_url( '/' );
		}

		$callback_url = home_url( '/aura-reading/auth/callback' );
		if ( ! empty( $return_url ) ) {
			$callback_url = add_query_arg(
				array(
					'redirect' => esc_url_raw( $return_url ),
				),
				$callback_url
			);
		}
		$_SESSION[ self::SESSION_REDIRECT_KEY ] = esc_url_raw( $return_url );

		// Build login URL with callback parameter.
		$login_url = add_query_arg(
			array(
				'redirect_url' => $callback_url,
			),
			$base_url . '/account/login'
		);

		return $login_url;
	}

	/**
	 * Generate logout URL for Account Service with redirect parameter.
	 *
	 * @param string $return_url Optional return URL after logout.
	 * @return string|null Logout URL or null if Account Service not configured.
	 */
	public function get_logout_url( $return_url = '' ) {
		$this->ensure_session();
		$settings = SM_Settings::init()->get_settings();

		if ( empty( $settings['enable_account_integration'] ) ) {
			return null;
		}

		$base_url = isset( $settings['account_service_url'] ) ? rtrim( $settings['account_service_url'], '/' ) : '';
		if ( empty( $base_url ) ) {
			return null;
		}

		if ( empty( $return_url ) ) {
			$return_url = home_url( '/' );
		}

		$logout_url = add_query_arg(
			array(
				'redirect_url' => esc_url_raw( $return_url ),
			),
			$base_url . '/account/logout'
		);

		return $logout_url;
	}

	/**
	 * Get the Account Service home URL when configured.
	 *
	 * @return string
	 */
	private function get_account_service_home_url() {
		$settings = SM_Settings::init()->get_settings();
		$base_url = isset( $settings['account_service_url'] ) ? rtrim( $settings['account_service_url'], '/' ) : '';

		if ( empty( $base_url ) ) {
			return '';
		}

		return $base_url;
	}
}
