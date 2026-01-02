<?php
/**
 * Test Helper Endpoints
 *
 * Provides endpoints for automated testing to retrieve OTP codes, create test data, etc.
 * ONLY available when DevMode is enabled for security.
 *
 * @package MysticPalmReading
 * @since 1.4.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SM_Test_Helpers
 *
 * Manages test helper endpoints for E2E automated testing.
 */
class SM_Test_Helpers {

	/**
	 * Singleton instance
	 *
	 * @var SM_Test_Helpers|null
	 */
	private static $instance = null;

	/**
	 * Initialize the handler (singleton pattern)
	 *
	 * @return void
	 */
	public static function init() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
	}

	/**
	 * Get singleton instance
	 *
	 * @return SM_Test_Helpers
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::init();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation
	 */
	private function __construct() {
		// Register REST API endpoints for test helpers
		add_action( 'rest_api_init', array( $this, 'register_test_endpoints' ) );
	}

	/**
	 * Register test helper REST API endpoints
	 *
	 * @return void
	 */
	public function register_test_endpoints() {
		// Only register test endpoints if DevMode is enabled
		if ( ! SM_Dev_Mode::is_enabled() ) {
			return;
		}

		// Get latest OTP for an email
		register_rest_route(
			'soulmirror-test/v1',
			'/get-otp',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_otp_endpoint' ),
				'permission_callback' => '__return_true', // DevMode only, no auth required
				'args'                => array(
					'email' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
				),
			)
		);

		// Get lead ID by email
		register_rest_route(
			'soulmirror-test/v1',
			'/get-lead',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_lead_endpoint' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'email' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
				),
			)
		);

		// Clear all test data (emails starting with 'test-')
		register_rest_route(
			'soulmirror-test/v1',
			'/cleanup',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'cleanup_test_data_endpoint' ),
				'permission_callback' => '__return_true',
			)
		);

		// Seed a reading for testing (creates a complete reading with mock data)
		register_rest_route(
			'soulmirror-test/v1',
			'/seed-reading',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'seed_reading_endpoint' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'email' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
					'name'  => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'default'           => 'Test User',
					),
					'account_id' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'default'           => '',
					),
				),
			)
		);

		// Mock login for testing (DevMode only).
		register_rest_route(
			'soulmirror-test/v1',
			'/mock-login',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'mock_login_endpoint' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'email' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
					'name'  => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'default'           => 'Test User',
					),
					'account_id' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'default'           => '',
					),
				),
			)
		);
	}

	/**
	 * Get OTP code for an email address
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function get_otp_endpoint( $request ) {
		global $wpdb;

		$email = $request->get_param( 'email' );

		if ( empty( $email ) ) {
			return new WP_Error( 'missing_email', 'Email parameter is required', array( 'status' => 400 ) );
		}

		$db          = class_exists( 'SM_Database' ) ? SM_Database::get_instance() : null;
		$leads_table = $db ? $db->get_table_name( 'leads' ) : $wpdb->prefix . 'sm_leads';
		$otps_table  = $db ? $db->get_table_name( 'otps' ) : $wpdb->prefix . 'sm_otps';

		$lead_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$leads_table}
				 WHERE email = %s
				 ORDER BY created_at DESC
				 LIMIT 1",
				$email
			)
		);

		if ( empty( $lead_id ) ) {
			return new WP_Error( 'lead_not_found', 'No lead found for this email', array( 'status' => 404 ) );
		}

		$otp_code = str_pad( (string) wp_rand( 0, 9999 ), 4, '0', STR_PAD_LEFT );
		$otp_hash = function_exists( 'wp_hash_password' ) ? wp_hash_password( $otp_code ) : password_hash( $otp_code, PASSWORD_DEFAULT );
		$otp_id   = wp_generate_uuid4();
		$minutes  = class_exists( 'SM_Settings' ) ? SM_Settings::init()->get_otp_expiration_minutes() : 10;
		$expires  = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + ( $minutes * MINUTE_IN_SECONDS ) );
		$now      = current_time( 'mysql' );

		$wpdb->insert(
			$otps_table,
			array(
				'id'               => $otp_id,
				'lead_id'          => $lead_id,
				'otp_hash'         => $otp_hash,
				'expires_at'       => $expires,
				'attempts'         => 0,
				'resend_available' => $now,
				'created_at'       => $now,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( $wpdb->last_error ) {
			return new WP_Error( 'otp_create_failed', $wpdb->last_error, array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'email'   => $email,
				'otp'     => $otp_code,
				'lead_id' => $lead_id,
			)
		);
	}

	/**
	 * Get lead data by email address
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function get_lead_endpoint( $request ) {
		global $wpdb;

		$email = $request->get_param( 'email' );

		if ( empty( $email ) ) {
			return new WP_Error( 'missing_email', 'Email parameter is required', array( 'status' => 400 ) );
		}

		// Get lead data
		$lead = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}sm_leads
				 WHERE email = %s
				 ORDER BY created_at DESC
				 LIMIT 1",
				$email
			),
			ARRAY_A
		);

		if ( empty( $lead ) ) {
			return new WP_Error( 'lead_not_found', 'No lead found for this email', array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'lead'    => $lead,
			)
		);
	}

	/**
	 * Cleanup test data
	 *
	 * Deletes all leads and readings where email starts with 'test-'
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public function cleanup_test_data_endpoint( $request ) {
		global $wpdb;

		// Delete test leads (cascades to readings via foreign key)
		$deleted_leads = $wpdb->query(
			"DELETE FROM {$wpdb->prefix}sm_leads
			 WHERE email LIKE 'test-%@%'"
		);

		// Delete orphaned test readings
		$deleted_readings = $wpdb->query(
			"DELETE FROM {$wpdb->prefix}sm_readings
			 WHERE lead_id LIKE 'test-%'"
		);

		return rest_ensure_response(
			array(
				'success'          => true,
				'deleted_leads'    => $deleted_leads,
				'deleted_readings' => $deleted_readings,
			)
		);
	}

	/**
	 * Seed a complete reading for testing
	 *
	 * Creates a lead, OTP, quiz responses, and reading in one go.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function seed_reading_endpoint( $request ) {
		global $wpdb;

		$email = $request->get_param( 'email' );
		$name  = $request->get_param( 'name' );
		$account_id = $request->get_param( 'account_id' );

		if ( empty( $email ) ) {
			return new WP_Error( 'missing_email', 'Email parameter is required', array( 'status' => 400 ) );
		}

		$db          = class_exists( 'SM_Database' ) ? SM_Database::get_instance() : null;
		$leads_table = $db ? $db->get_table_name( 'leads' ) : $wpdb->prefix . 'sm_leads';
		$quiz_table  = $db ? $db->get_table_name( 'quiz' ) : $wpdb->prefix . 'sm_quiz';
		$read_table  = $db ? $db->get_table_name( 'readings' ) : $wpdb->prefix . 'sm_readings';

		// Generate lead ID
		$lead_id = wp_generate_uuid4();
		$now     = current_time( 'mysql' );

		// Insert lead
		$wpdb->insert(
			$leads_table,
			array(
				'id'              => $lead_id,
				'account_id'      => $account_id ? $account_id : null,
				'email'           => $email,
				'name'            => $name,
				'identity'        => 'prefer-not',
				'age'             => 28,
				'age_range'       => '25-34',
				'gdpr'            => 1,
				'gdpr_timestamp'  => $now,
				'email_confirmed' => 1,
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%s' )
		);

		if ( $wpdb->last_error ) {
			return new WP_Error( 'db_error', $wpdb->last_error, array( 'status' => 500 ) );
		}

		// Insert quiz responses
		$quiz_data = array(
			'love_question'    => 'guidance',
			'career_question'  => 'next-move',
			'life_question'    => 'balance',
			'current_feeling'  => 'hopeful',
			'primary_focus'    => 'relationships',
			'relationship_status' => 'single',
			'career_stage'     => 'exploring',
			'life_phase'       => 'transition',
		);

		$wpdb->insert(
			$quiz_table,
			array(
				'id'           => wp_generate_uuid4(),
				'lead_id'      => $lead_id,
				'answers_json' => wp_json_encode( $quiz_data ),
				'completed_at' => $now,
				'created_at'   => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		// Generate mock reading data
		$mock_reading = $this->generate_test_reading_data( $name );

		// Insert reading
		$reading_id = wp_generate_uuid4();
		$wpdb->insert(
			$read_table,
			array(
				'id'            => $reading_id,
				'lead_id'       => $lead_id,
				'account_id'    => $account_id ? $account_id : null,
				'reading_type'  => 'aura_teaser',
				'content_data'  => wp_json_encode( $mock_reading ),
				'unlocked_section' => '',
				'unlock_count'  => 0,
				'has_purchased' => 0,
				'created_at'    => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
		);

		if ( $wpdb->last_error ) {
			return new WP_Error( 'db_error', $wpdb->last_error, array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'success'    => true,
				'lead_id'    => $lead_id,
				'reading_id' => $reading_id,
				'email'      => $email,
				'account_id' => $account_id,
			)
		);
	}

	/**
	 * Mock login for automated tests (DevMode only).
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function mock_login_endpoint( $request ) {
		$email      = $request->get_param( 'email' );
		$name       = $request->get_param( 'name' );
		$account_id = $request->get_param( 'account_id' );

		if ( empty( $email ) ) {
			return new WP_Error( 'missing_email', 'Email parameter is required', array( 'status' => 400 ) );
		}

		if ( empty( $account_id ) ) {
			$account_id = 'test-account-' . wp_generate_uuid4();
		}

		$jwt_token = $this->build_test_jwt( $account_id, $email );
		$user_data = array(
			'account_id' => $account_id,
			'email'      => $email,
			'name'       => $name,
		);

		SM_Auth_Handler::get_instance()->store_jwt_token( $jwt_token, $user_data );

		return rest_ensure_response(
			array(
				'success'    => true,
				'account_id' => $account_id,
				'email'      => $email,
			)
		);
	}

	/**
	 * Generate test reading data
	 *
	 * @param string $name User name.
	 * @return array Reading data.
	 */
	private function generate_test_reading_data( $name ) {
		return array(
			'meta' => array(
				'user_name'    => $name,
				'generated_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
				'reading_type' => 'aura_teaser',
			),
			'opening' => array(
				'reflection_p1' => "Your palm holds patterns shaped by experience, {$name}. This is a test reading for automated testing purposes.",
				'reflection_p2' => 'This teaser captures what rises first: your strongest themes.',
			),
			'life_foundations' => array(
				'paragraph_1' => 'Test content for life foundations section.',
				'paragraph_2' => 'More test content with patterns.',
				'paragraph_3' => 'Test paragraph about intuition.',
				'core_theme' => 'Test theme for automated testing.',
			),
			'love_patterns' => array(
				'preview' => 'Test preview for love patterns section.',
				'locked_teaser' => 'Unlock to see your love pattern.',
			),
			'career_success' => array(
				'main_paragraph' => 'Test career success content.',
				'modal_love_patterns' => 'Test modal love content.',
				'modal_career_direction' => 'Test modal career content.',
				'modal_life_alignment' => 'Test modal life alignment.',
			),
			'personality_traits' => array(
				'intro' => 'Test personality traits intro.',
				'trait_1_name' => 'Intuition',
				'trait_1_score' => 88,
				'trait_2_name' => 'Resilience',
				'trait_2_score' => 92,
				'trait_3_name' => 'Independence',
				'trait_3_score' => 85,
			),
			'challenges_opportunities' => array(
				'preview' => 'Test challenges preview.',
				'locked_teaser' => 'Unlock to see challenges.',
			),
			'life_phase' => array(
				'preview' => 'Test life phase preview.',
				'locked_teaser' => 'Unlock to see life phase.',
			),
			'timeline_6_months' => array(
				'preview' => 'Test timeline preview.',
				'locked_teaser' => 'Unlock to see timeline.',
			),
			'guidance' => array(
				'preview' => 'Test guidance preview.',
				'locked_teaser' => 'Unlock to see guidance.',
			),
			'deep_relationship_analysis' => array(
				'placeholder_text' => 'Premium section placeholder.',
			),
			'extended_timeline_12_months' => array(
				'placeholder_text' => 'Premium section placeholder.',
			),
			'life_purpose_soul_mission' => array(
				'placeholder_text' => 'Premium section placeholder.',
			),
			'shadow_work_transformation' => array(
				'placeholder_text' => 'Premium section placeholder.',
			),
			'practical_guidance_action_plan' => array(
				'placeholder_text' => 'Premium section placeholder.',
			),
			'closing' => array(
				'paragraph_1' => 'Test closing paragraph 1.',
				'paragraph_2' => 'Test closing paragraph 2.',
			),
		);
	}

	/**
	 * Build a lightweight JWT for test sessions.
	 *
	 * @param string $account_id Account identifier.
	 * @param string $email User email.
	 * @return string JWT string.
	 */
	private function build_test_jwt( $account_id, $email ) {
		$header  = wp_json_encode( array( 'alg' => 'HS256', 'typ' => 'JWT' ) );
		$payload = wp_json_encode(
			array(
				'account_id' => $account_id,
				'email'      => $email,
				'exp'        => time() + DAY_IN_SECONDS,
			)
		);

		$segments = array(
			$this->base64url_encode( $header ),
			$this->base64url_encode( $payload ),
			'testsignature',
		);

		return implode( '.', $segments );
	}

	/**
	 * Encode data in base64url format.
	 *
	 * @param string $data Raw data.
	 * @return string Encoded data.
	 */
	private function base64url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}
}
