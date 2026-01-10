<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class SM_Credit_Handler
 *
 * Manages credit checking and deduction by communicating with the
 * SoulMirror Account Service.
 */
class SM_Credit_Handler {
    /**
     * Singleton instance.
     *
     * @var SM_Credit_Handler|null
     */
    private static $instance = null;

    /**
     * Session key for cached credit data.
     */
    const SESSION_CREDIT_CACHE_KEY = 'sm_credit_cache';

    /**
     * Credit cache TTL in seconds.
     */
    const CREDIT_CACHE_TTL = 300;

    /**
     * Initializes the credit handler.
     */
    public function __construct() {
        // Initialization logic will be added in Phase 4
    }

    /**
     * Get singleton instance.
     *
     * @return SM_Credit_Handler
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Get current user's credit balance summary.
     *
     * @param bool $force_refresh Whether to bypass cache and fetch fresh data.
     * @return array
     */
    public function get_credit_balance( $force_refresh = false ) {
        $result = $this->check_user_credits( '', $force_refresh );

        if ( empty( $result['success'] ) ) {
            SM_Logger::log(
                'warning',
                'CREDIT_BALANCE',
                'Credit balance unavailable.',
                array(
                    'error' => isset( $result['error'] ) ? $result['error'] : 'unknown_error',
                )
            );
            return array(
                'service'   => 0,
                'universal' => 0,
            );
        }

        return array(
            'service'   => isset( $result['service_balance'] ) ? (int) $result['service_balance'] : 0,
            'universal' => isset( $result['universal_balance'] ) ? (int) $result['universal_balance'] : 0,
        );
    }
    /**
     * Checks if the current user has enough credits to perform an action.
     *
     * @param string $jwt_token Optional JWT token (uses stored token if empty).
     * @param bool   $force_refresh Whether to bypass cache and fetch fresh data.
     * @return array A result array, e.g., ['success' => true, 'has_credits' => true]
     */
    public function check_user_credits( $jwt_token = '', $force_refresh = false ) {
        if ( class_exists( 'SM_Auth_Handler' ) ) {
            SM_Auth_Handler::get_instance()->ensure_session();
        }

        $settings = SM_Settings::init()->get_settings();
        if ( empty( $settings['enable_account_integration'] ) ) {
            SM_Logger::log( 'warning', 'CREDIT_CHECK', 'Account integration disabled.' );
            return array(
                'success'     => false,
                'has_credits' => false,
                'error'       => 'integration_disabled',
            );
        }

        if ( empty( $jwt_token ) ) {
            $jwt_token = $this->get_token_from_storage();
        } else {
            $jwt_token = sanitize_text_field( $jwt_token );
        }

        if ( empty( $jwt_token ) ) {
            SM_Logger::log( 'warning', 'CREDIT_CHECK', 'Missing JWT token for credit check.' );
            return array(
                'success'     => false,
                'has_credits' => false,
                'error'       => 'missing_token',
            );
        }

        $service_slug = isset( $settings['service_slug'] ) ? sanitize_key( $settings['service_slug'] ) : 'aura_reading';

        SM_Logger::log(
            'info',
            'CREDIT_CHECK_START',
            'Starting credit check.',
            array(
                'service_slug'  => $service_slug,
                'has_token'     => ! empty( $jwt_token ),
                'force_refresh' => $force_refresh,
            )
        );

        // Skip cache if force_refresh is true
        if ( ! $force_refresh ) {
            $cached = $this->get_cached_credits( $jwt_token, $service_slug );
            if ( ! empty( $cached ) ) {
                SM_Logger::log(
                    'info',
                    'CREDIT_CHECK_CACHE_HIT',
                    'Using cached credit balances.',
                    array(
                        'service_slug'      => $service_slug,
                        'service_balance'   => isset( $cached['service_balance'] ) ? (int) $cached['service_balance'] : 0,
                        'universal_balance' => isset( $cached['universal_balance'] ) ? (int) $cached['universal_balance'] : 0,
                    )
                );
                return $cached;
            }
        }

        $account_service_url = isset( $settings['account_service_url'] ) ? rtrim( $settings['account_service_url'], '/' ) : '';
        $account_service_url = apply_filters( 'sm_account_service_url', $account_service_url );

        if ( empty( $account_service_url ) ) {
            SM_Logger::log( 'error', 'CREDIT_CHECK', 'Account Service URL missing.' );
            return array(
                'success'     => false,
                'has_credits' => false,
                'error'       => 'service_unconfigured',
            );
        }

        SM_Logger::log(
            'info',
            'CREDIT_CHECK_REQUEST',
            'Attempting to call Account Service for credit check.',
            array(
                'url' => $account_service_url . '/wp-json/soulmirror/v1/credits/check',
                'service_slug' => $service_slug,
            )
        );

        $sslverify = true;
        $parsed_url = wp_parse_url( $account_service_url );
        $host = isset( $parsed_url['host'] ) ? strtolower( $parsed_url['host'] ) : '';

        if ( $host === 'localhost' || substr( $host, -6 ) === '.local' ) {
            // Local dev hosts often use self-signed certs.
            $sslverify = false;
        }

        $response = wp_remote_post(
            $account_service_url . '/wp-json/soulmirror/v1/credits/check',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $jwt_token,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode(
                    array(
                        'service_slug' => $service_slug,
                    )
                ),
                'timeout'   => 30,
                'sslverify' => $sslverify,
            )
        );

        if ( is_wp_error( $response ) ) {
            SM_Logger::log(
                'error',
                'CREDIT_CHECK_RESPONSE_ERROR',
                'Credit check failed - cURL/WP_Error.',
                array(
                    'error_code' => $response->get_error_code(),
                    'error_message' => $response->get_error_message()
                )
            );
            return array(
                'success'     => false,
                'has_credits' => false,
                'error'       => 'network_error',
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = json_decode( wp_remote_retrieve_body( $response ), true );
        $raw_body    = wp_remote_retrieve_body( $response );

        if ( 200 !== $status_code ) {
            SM_Logger::log(
                'error',
                'CREDIT_CHECK_RESPONSE_FAILURE',
                'Credit check received non-200 response.',
                array(
                    'status_code' => $status_code,
                    'response_body_raw' => substr($raw_body, 0, 500), // Log first 500 chars of raw response
                    'error'       => isset( $body['error'] ) ? $body['error'] : 'unknown_error',
                )
            );

            $fallback = $this->get_user_credit_summary( $jwt_token, $service_slug );
            if ( ! empty( $fallback ) ) {
                SM_Logger::log(
                    'info',
                    'CREDIT_CHECK_FALLBACK_SUCCESS',
                    'Credit check fallback succeeded via user info.',
                    array(
                        'service_slug'      => $service_slug,
                        'service_balance'   => isset( $fallback['service_balance'] ) ? (int) $fallback['service_balance'] : 0,
                        'universal_balance' => isset( $fallback['universal_balance'] ) ? (int) $fallback['universal_balance'] : 0,
                    )
                );
                $this->store_cached_credits( $jwt_token, $fallback, $service_slug );
                return array_merge( array( 'success' => true, 'fallback' => 'user_info' ), $fallback );
            }

            return array(
                'success'     => false,
                'has_credits' => false,
                'error'       => isset( $body['error'] ) ? $body['error'] : 'check_failed',
            );
        }

        if ( empty( $body['data'] ) ) {
            SM_Logger::log(
                'error',
                'CREDIT_CHECK_RESPONSE_INVALID',
                'Credit check response was 200 but body is empty or invalid.',
                array(
                    'status_code' => $status_code,
                    'response_body_raw' => substr($raw_body, 0, 500),
                )
            );
            return array(
                'success'     => false,
                'has_credits' => false,
                'error'       => 'invalid_response_body',
            );
        }

        $service_balance   = isset( $body['data']['service_balance'] ) ? (int) $body['data']['service_balance'] : 0;
        $universal_balance = isset( $body['data']['universal_balance'] ) ? (int) $body['data']['universal_balance'] : 0;
        $total_available   = isset( $body['data']['total_available'] )
            ? (int) $body['data']['total_available']
            : ( $service_balance + $universal_balance );

        $data = array(
            'has_credits'       => $total_available > 0,
            'service_balance'   => $service_balance,
            'universal_balance' => $universal_balance,
            'total_available'   => $total_available,
        );

        SM_Logger::log(
            'info',
            'CREDIT_CHECK_SUCCESS',
            'Credit check succeeded.',
            array(
                'service_slug'      => $service_slug,
                'service_balance'   => $data['service_balance'],
                'universal_balance' => $data['universal_balance'],
                'total_available'   => $data['total_available'],
            )
        );

        if ( 0 === $total_available ) {
            $fallback = $this->get_user_credit_summary( $jwt_token, $service_slug );
            if ( ! empty( $fallback ) && ! empty( $fallback['total_available'] ) ) {
                SM_Logger::log(
                    'warning',
                    'CREDIT_CHECK_FALLBACK_USED',
                    'Credit check returned zero; user info fallback used.',
                    array(
                        'service_slug'      => $service_slug,
                        'service_balance'   => isset( $fallback['service_balance'] ) ? (int) $fallback['service_balance'] : 0,
                        'universal_balance' => isset( $fallback['universal_balance'] ) ? (int) $fallback['universal_balance'] : 0,
                    )
                );
                $fallback['success'] = true;
                $this->store_cached_credits( $jwt_token, $fallback, $service_slug );
                return $fallback;
            }
        }

        $this->store_cached_credits( $jwt_token, $data, $service_slug );

        return array_merge(
            array( 'success' => true ),
            $data
        );
    }

    /**
     * Deducts a credit from the user's account after a successful action.
     *
     * @param string $idempotency_key A unique key to prevent duplicate deductions.
     * @return array A result array, e.g., ['success' => true, 'transaction_id' => '...']
     */
    public function deduct_credit( $idempotency_key ) {
        if ( class_exists( 'SM_Auth_Handler' ) ) {
            SM_Auth_Handler::get_instance()->ensure_session();
        }

        $settings = SM_Settings::init()->get_settings();
        if ( empty( $settings['enable_account_integration'] ) ) {
            return array(
                'success' => false,
                'error'   => 'integration_disabled',
            );
        }

        $jwt_token = $this->get_token_from_storage();
        if ( empty( $jwt_token ) ) {
            return array(
                'success' => false,
                'error'   => 'missing_token',
            );
        }

        $service_slug = isset( $settings['service_slug'] ) ? sanitize_key( $settings['service_slug'] ) : 'aura_reading';
        $account_service_url = isset( $settings['account_service_url'] ) ? rtrim( $settings['account_service_url'], '/' ) : '';
        $account_service_url = apply_filters( 'sm_account_service_url', $account_service_url );

        if ( empty( $account_service_url ) ) {
            return array(
                'success' => false,
                'error'   => 'service_unconfigured',
            );
        }

        $idempotency_key = sanitize_text_field( (string) $idempotency_key );
        if ( '' === $idempotency_key ) {
            return array(
                'success' => false,
                'error'   => 'missing_idempotency_key',
            );
        }

        SM_Logger::log(
            'info',
            'CREDIT_DEDUCT_START',
            'Attempting credit deduction.',
            array(
                'service_slug'    => $service_slug,
                'idempotency_key' => $idempotency_key,
            )
        );

        $sslverify = true;
        $parsed_url = wp_parse_url( $account_service_url );
        $host = isset( $parsed_url['host'] ) ? strtolower( $parsed_url['host'] ) : '';

        if ( $host === 'localhost' || substr( $host, -6 ) === '.local' ) {
            $sslverify = false;
        }

        $response = wp_remote_post(
            $account_service_url . '/wp-json/soulmirror/v1/credits/deduct',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $jwt_token,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode(
                    array(
                        'service_slug'    => $service_slug,
                        'idempotency_key' => $idempotency_key,
                    )
                ),
                'timeout'   => 30,
                'sslverify' => $sslverify,
            )
        );

        if ( is_wp_error( $response ) ) {
            SM_Logger::log(
                'error',
                'CREDIT_DEDUCT',
                'Credit deduction failed - network error.',
                array(
                    'error' => $response->get_error_message(),
                )
            );
            return array(
                'success' => false,
                'error'   => 'network_error',
            );
        }

        $status = wp_remote_retrieve_response_code( $response );
        $raw_body = wp_remote_retrieve_body( $response );
        $body     = json_decode( $raw_body, true );

        if ( 409 === $status ) {
            SM_Logger::log(
                'warning',
                'CREDIT_DEDUCT_DUPLICATE',
                'Credit deduction already processed (idempotent retry).',
                array(
                    'service_slug'    => $service_slug,
                    'idempotency_key' => $idempotency_key,
                )
            );

            $this->clear_cached_credits( $jwt_token, $service_slug );

            return array(
                'success'        => true,
                'duplicate'      => true,
                'transaction_id' => isset( $body['data']['transaction_id'] ) ? sanitize_text_field( (string) $body['data']['transaction_id'] ) : '',
            );
        }

        if ( 200 !== $status || empty( $body['success'] ) ) {
            SM_Logger::log(
                'error',
                'CREDIT_DEDUCT',
                'Credit deduction failed.',
                array(
                    'status' => $status,
                    'body'   => isset( $body['error'] ) ? $body['error'] : $body,
                    'raw'    => substr( (string) $raw_body, 0, 500 ),
                )
            );
            return array(
                'success' => false,
                'error'   => isset( $body['error']['code'] ) ? $body['error']['code'] : 'deduct_failed',
            );
        }

        $result = array(
            'success'        => true,
            'transaction_id' => isset( $body['data']['transaction_id'] ) ? sanitize_text_field( (string) $body['data']['transaction_id'] ) : '',
        );

        if ( isset( $body['data']['service_balance'] ) || isset( $body['data']['universal_balance'] ) ) {
            $result['service_balance']   = isset( $body['data']['service_balance'] ) ? (int) $body['data']['service_balance'] : 0;
            $result['universal_balance'] = isset( $body['data']['universal_balance'] ) ? (int) $body['data']['universal_balance'] : 0;
            $result['total_available']   = isset( $body['data']['total_available'] ) ? (int) $body['data']['total_available'] : ( $result['service_balance'] + $result['universal_balance'] );

            $this->store_cached_credits(
                $jwt_token,
                array(
                    'has_credits'       => $result['total_available'] > 0,
                    'service_balance'   => $result['service_balance'],
                    'universal_balance' => $result['universal_balance'],
                    'total_available'   => $result['total_available'],
                ),
                $service_slug
            );
        } else {
            $this->clear_cached_credits( $jwt_token, $service_slug );
        }

        SM_Logger::log(
            'info',
            'CREDIT_DEDUCT',
            'Credit deducted successfully.',
            array(
                'service_slug' => $service_slug,
                'transaction'  => $result['transaction_id'],
            )
        );

        return $result;
    }

    /**
     * Get the current JWT token (if available).
     *
     * @return string
     */
    public function get_current_token() {
        return $this->get_token_from_storage();
    }

    /**
     * Retrieve JWT token from session or cookie.
     *
     * @return string
     */
    private function get_token_from_storage() {
        if ( ! empty( $_SESSION[ SM_Auth_Handler::SESSION_TOKEN_KEY ] ) ) {
            return sanitize_text_field( wp_unslash( $_SESSION[ SM_Auth_Handler::SESSION_TOKEN_KEY ] ) );
        }

        if ( ! empty( $_COOKIE[ SM_Auth_Handler::COOKIE_TOKEN_NAME ] ) ) {
            return sanitize_text_field( wp_unslash( $_COOKIE[ SM_Auth_Handler::COOKIE_TOKEN_NAME ] ) );
        }

        return '';
    }

    /**
     * Get cached credits for the current token if valid.
     *
     * @param string $jwt_token JWT token.
     * @return array|null
     */
    private function get_cached_credits( $jwt_token, $service_slug ) {
        if ( empty( $_SESSION[ self::SESSION_CREDIT_CACHE_KEY ] ) ) {
            return null;
        }

        $cache = $_SESSION[ self::SESSION_CREDIT_CACHE_KEY ];
        if ( ! is_array( $cache ) || empty( $cache['expires_at'] ) || empty( $cache['token_hash'] ) || empty( $cache['data'] ) ) {
            return null;
        }

        if ( time() >= (int) $cache['expires_at'] ) {
            unset( $_SESSION[ self::SESSION_CREDIT_CACHE_KEY ] );
            return null;
        }

        $token_hash = hash( 'sha256', $jwt_token . '|' . $service_slug );
        if ( ! hash_equals( $cache['token_hash'], $token_hash ) ) {
            return null;
        }

        return array_merge(
            array(
                'success' => true,
                'cached'  => true,
            ),
            $cache['data']
        );
    }

    /**
     * Store credits in session cache.
     *
     * @param string $jwt_token JWT token.
     * @param array  $data      Credit data.
     * @return void
     */
    private function store_cached_credits( $jwt_token, $data, $service_slug ) {
        $_SESSION[ self::SESSION_CREDIT_CACHE_KEY ] = array(
            'token_hash' => hash( 'sha256', $jwt_token . '|' . $service_slug ),
            'expires_at' => time() + self::CREDIT_CACHE_TTL,
            'data'       => $data,
        );
    }

    /**
     * Clear cached credits for the current token.
     *
     * @param string $jwt_token JWT token.
     * @param string $service_slug Service slug.
     * @return void
     */
    private function clear_cached_credits( $jwt_token, $service_slug ) {
        if ( empty( $_SESSION[ self::SESSION_CREDIT_CACHE_KEY ] ) ) {
            return;
        }

        $cache = $_SESSION[ self::SESSION_CREDIT_CACHE_KEY ];
        if ( ! is_array( $cache ) || empty( $cache['token_hash'] ) ) {
            return;
        }

        $token_hash = hash( 'sha256', $jwt_token . '|' . $service_slug );
        if ( hash_equals( $cache['token_hash'], $token_hash ) ) {
            unset( $_SESSION[ self::SESSION_CREDIT_CACHE_KEY ] );
        }
    }

    /**
     * Fetch credit balances from the Account Service user info endpoint.
     *
     * @param string $jwt_token JWT token.
     * @param string $service_slug Service slug to match.
     * @return array|null
     */
    private function get_user_credit_summary( $jwt_token, $service_slug ) {
        $settings = SM_Settings::init()->get_settings();
        $account_service_url = isset( $settings['account_service_url'] ) ? rtrim( $settings['account_service_url'], '/' ) : '';
        $account_service_url = apply_filters( 'sm_account_service_url', $account_service_url );

        if ( empty( $account_service_url ) ) {
            return null;
        }

        $sslverify = true;
        $parsed_url = wp_parse_url( $account_service_url );
        $host = isset( $parsed_url['host'] ) ? strtolower( $parsed_url['host'] ) : '';

        if ( $host === 'localhost' || substr( $host, -6 ) === '.local' ) {
            // Local dev hosts often use self-signed certs.
            $sslverify = false;
        }

        $response = wp_remote_get(
            $account_service_url . '/wp-json/soulmirror/v1/user/info',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $jwt_token,
                    'Content-Type'  => 'application/json',
                ),
                'timeout'   => 30,
                'sslverify' => $sslverify,
            )
        );

        if ( is_wp_error( $response ) ) {
            SM_Logger::log(
                'error',
                'CREDIT_CHECK_USER_INFO_ERROR',
                'Credit summary fallback failed - network error.',
                array(
                    'error_code'    => $response->get_error_code(),
                    'error_message' => $response->get_error_message(),
                )
            );
            return null;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $status_code || empty( $body['success'] ) || empty( $body['data']['credits'] ) ) {
            SM_Logger::log(
                'warning',
                'CREDIT_CHECK_USER_INFO_INVALID',
                'Credit summary fallback returned invalid response.',
                array(
                    'status_code' => $status_code,
                    'body_raw'    => substr( wp_remote_retrieve_body( $response ), 0, 500 ),
                )
            );
            return null;
        }

        $credits = $body['data']['credits'];
        $service_balance = 0;
        $universal_balance = 0;
        $credit_slugs = array();

        foreach ( $credits as $credit ) {
            if ( empty( $credit['service_slug'] ) ) {
                continue;
            }
            $slug = sanitize_key( $credit['service_slug'] );
            $balance = isset( $credit['balance'] ) ? (int) $credit['balance'] : 0;
            $credit_slugs[] = $slug;

            if ( $slug === $service_slug ) {
                $service_balance = $balance;
            }

            if ( in_array( $slug, array( 'universal', 'universal-credits', 'universal-credit' ), true ) ) {
                $universal_balance = $balance;
            }
        }

        $total_available = $service_balance + $universal_balance;

        SM_Logger::log(
            'warning',
            'CREDIT_CHECK_USER_INFO_FALLBACK',
            'Credit check fallback used user info endpoint.',
            array(
                'service_slug'      => $service_slug,
                'service_balance'   => $service_balance,
                'universal_balance' => $universal_balance,
                'available_slugs'   => $credit_slugs,
            )
        );

        return array(
            'has_credits'       => $total_available > 0,
            'service_balance'   => $service_balance,
            'universal_balance' => $universal_balance,
            'total_available'   => $total_available,
        );
    }
}
