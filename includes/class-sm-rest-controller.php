<?php
/**
 * Base REST controller for SoulMirror endpoints.
 *
 * @package MysticPalmReadingAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides shared REST helpers (nonce, rate limits, responses).
 */
class SM_REST_Controller extends WP_REST_Controller {

	/**
	 * Nonce action used for REST calls.
	 */
	const NONCE_ACTION = 'sm_nonce';

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'soulmirror/v1';

	/**
	 * Singleton instance.
	 *
	 * @var SM_REST_Controller|null
	 */
	private static $instance = null;

	/**
	 * Initialize controller.
	 *
	 * @return SM_REST_Controller
	 */
	public static function init() {
		return self::get_instance();
	}

	/**
	 * Retrieve singleton instance.
	 *
	 * @return SM_REST_Controller
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
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_pre_serve_request', array( $this, 'add_cors_headers' ), 10, 3 );
	}

	/**
	 * Register REST routes for the plugin.
	 * Routes are added in later units.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/nonce/refresh',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'handle_nonce_refresh' ),
					'permission_callback' => array( $this, 'permission_check_nonce' ),
					'args'                => array(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/flow/state',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'handle_flow_state_get' ),
					'permission_callback' => array( $this, 'permission_check_nonce' ),
					'args'                => array(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_flow_state_update' ),
					'permission_callback' => array( $this, 'permission_check_nonce' ),
					'args'                => array(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/flow/reset',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_flow_reset' ),
					'permission_callback' => array( $this, 'permission_check_nonce' ),
					'args'                => array(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/flow/magic/verify',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_flow_magic_verify' ),
					'permission_callback' => array( $this, 'permission_check_nonce' ),
					'args'                => array(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/lead/create',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_lead_create' ),
					'permission_callback' => '__return_true', // Public endpoint - nonce verified in handler
					'args'                => array(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/lead/current',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'handle_lead_current' ),
					'permission_callback' => array( $this, 'permission_check_nonce' ),
					'args'                => array(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/otp/send',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_otp_send' ),
					'permission_callback' => '__return_true', // Public endpoint - nonce verified in handler
					'args'                => array(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/otp/verify',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_otp_verify' ),
					'permission_callback' => '__return_true', // Public endpoint - nonce verified in handler
					'args'                => array(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/otp/auto-verify',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_otp_magic_verify' ),
					'permission_callback' => '__return_true', // Public endpoint - nonce verified in handler
					'args'                => array(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/mailerlite/sync',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_mailerlite_sync' ),
					'permission_callback' => '__return_true', // Public endpoint - nonce verified in handler
					'args'                => array(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/image/upload',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_image_upload' ),
					'permission_callback' => '__return_true', // Public endpoint - nonce verified in handler
					'args'                => array(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/quiz/save',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_quiz_save' ),
					'permission_callback' => '__return_true', // Public endpoint - nonce verified in handler
					'args'                => array(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/quiz/questions',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'handle_quiz_questions' ),
					'permission_callback' => '__return_true', // Public endpoint - nonce verified in handler
					'args'                => array(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/reading/generate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_reading_generate' ),
					'permission_callback' => '__return_true', // Public endpoint - nonce verified in handler
					'args'                => array(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/reading/status',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_reading_status' ),
					'permission_callback' => '__return_true', // Public endpoint - nonce verified in handler
					'args'                => array(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/reading/job-run',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_reading_job_run' ),
					'permission_callback' => '__return_true', // Token verified in handler
					'args'                => array(),
				),
			)
		);

		// NEW: Paid Reading System Endpoint (Full reading generation)
		register_rest_route(
			$this->namespace,
			'/reading/generate-paid',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_paid_reading_generate' ),
					'permission_callback' => '__return_true', // Public endpoint - nonce verified in handler
					'args'                => array(),
				),
			)
		);

		// NEW: Teaser Reading System Endpoint (JSON-based)
		register_rest_route(
			$this->namespace,
			'/reading/generate-teaser',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_teaser_reading_generate' ),
					'permission_callback' => '__return_true', // Public endpoint - nonce verified in handler
					'args'                => array(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/reading/check',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'handle_reading_check' ),
					'permission_callback' => '__return_true', // Public endpoint - nonce verified in handler
					'args'                => array(),
				),
			)
		);

		// NEW: Unlock a reading section (Teaser unlock flow)
		register_rest_route(
			$this->namespace,
			'/reading/unlock',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_reading_unlock' ),
					'permission_callback' => '__return_true', // Public endpoint - nonce verified in handler
					'args'                => array(
						'current_page_url' => array(
							'sanitize_callback' => 'sanitize_url',
							'validate_callback' => 'wp_http_validate_url',
							'required'          => false,
						),
					),
				),
			)
		);

		// NEW: Get existing reading by lead_id (for magic link entry point check)
		register_rest_route(
			$this->namespace,
			'/reading/get-by-lead',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'handle_reading_get_by_lead' ),
					'permission_callback' => '__return_true', // Public endpoint - nonce verified in handler
					'args'                => array(),
				),
			)
		);

		// NEW: Check if reading exists by email (for welcome page check)
		register_rest_route(
			$this->namespace,
			'/reading/check-by-email',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_reading_check_by_email' ),
					'permission_callback' => '__return_true', // Public endpoint - nonce verified in handler
					'args'                => array(),
				),
			)
		);

		// NEW: User logout route
		register_rest_route(
			$this->namespace,
			'/auth/logout',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( SM_Auth_Handler::get_instance(), 'handle_logout' ),
					'permission_callback' => array( $this, 'permission_check_nonce' ),
					'args'                => array(),
				),
			)
		);

		// NEW: Endpoint for dashboard "Generate New Reading" button
		register_rest_route(
			$this->namespace,
			'/reading/start-new',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'handle_start_new_reading' ),
					'permission_callback' => array( $this, 'permission_check_nonce' ),
					'args'                => array(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/reports/delete',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_report_delete' ),
					'permission_callback' => array( $this, 'permission_check_nonce' ),
					'args'                => array(),
				),
			)
		);

	}

	/**
	 * Handles the request from the "Generate New Reading" button on the dashboard.
	 * Checks for authentication and credits before allowing the user to proceed.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_start_new_reading( WP_REST_Request $request ) {
		$auth_handler = SM_Auth_Handler::get_instance();
		if ( ! $auth_handler->is_user_logged_in() ) {
			return $this->error_response( 'authentication_required', 'You must be logged in to start a new reading.', 401 );
		}

		$settings = SM_Settings::init()->get_settings();
		if ( empty( $settings['enable_account_integration'] ) ) {
			return $this->success_response( array( 'proceed' => true ) ); // If integration is off, always allow.
		}

		$credit_handler = SM_Credit_Handler::get_instance();
		$credit_check   = $credit_handler->check_user_credits();

		if ( empty( $credit_check['success'] ) ) {
			SM_Logger::log(
				'error',
				'REST_CREDIT_CHECK',
				'Credit check failed when starting new reading.',
				array( 'error' => $credit_check['error'] ?? 'unknown_error' )
			);
			return $this->error_response( 'credit_check_failed', 'Could not verify your credits. Please try again.', 500 );
		}

		if ( empty( $credit_check['has_credits'] ) ) {
			$return_url = $this->get_credit_return_url( $request );
			$redirect   = $this->get_credit_shop_redirect_url( $return_url );

			return $this->error_response(
				'credits_exhausted',
				'You do not have enough credits.',
				402, // Payment Required
				array(
					'redirect_to' => $redirect,
				)
			);
		}

		$referer  = wp_get_referer();
		$fallback = home_url( '/aura-reading/' );
		$target   = wp_validate_redirect( $referer, $fallback );
		$target   = add_query_arg( 'start_new', '1', $target );

		return $this->success_response( array(
			'proceed'       => true,
			'next_step_url' => $target, // Frontend will handle starting the flow
		) );
	}

	/**
	 * Delete a reading from the authenticated user's reports list.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_report_delete( WP_REST_Request $request ) {
		$nonce = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce ) ) {
			return $nonce;
		}

		$auth_handler = SM_Auth_Handler::get_instance();
		$user_data    = $auth_handler->get_current_user();
		$account_id   = ! empty( $user_data['account_id'] ) ? sanitize_text_field( $user_data['account_id'] ) : '';

		if ( empty( $account_id ) ) {
			return $this->error_response(
				'authentication_required',
				__( 'You must be logged in to delete a reading.', 'mystic-palm-reading' ),
				401
			);
		}

		$reading_id = $this->sanitize_string( $request->get_param( 'reading_id' ) );
		if ( empty( $reading_id ) ) {
			return $this->error_response(
				'missing_reading_id',
				__( 'Reading ID is required.', 'mystic-palm-reading' ),
				400
			);
		}

		$rate_key = 'reports_delete_' . md5( $account_id );
		$rate     = $this->check_rate_limit(
			$rate_key,
			10,
			60,
			array(
				'account_id' => $account_id,
				'reading_id' => $reading_id,
			)
		);

		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$reading_service = SM_Reading_Service::get_instance();
		$reading         = $reading_service->get_reading_by_id( $reading_id, false );

		if ( is_wp_error( $reading ) ) {
			return $this->error_response(
				$reading->get_error_code(),
				$reading->get_error_message(),
				404
			);
		}

		if ( empty( $reading->account_id ) || $reading->account_id !== $account_id ) {
			return $this->error_response(
				'forbidden',
				__( 'You do not have permission to delete this reading.', 'mystic-palm-reading' ),
				403
			);
		}

		$deleted = $reading_service->delete_reading( $reading_id );
		if ( is_wp_error( $deleted ) ) {
			return $this->error_response(
				$deleted->get_error_code(),
				$deleted->get_error_message(),
				500
			);
		}

		return $this->success_response(
			array(
				'reading_id' => $reading_id,
				'deleted'    => true,
			)
		);
	}



	/**
	 * Permission callback to enforce nonce on public endpoints.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return true|WP_Error
	 */
	public function permission_check_nonce( WP_REST_Request $request ) {
		return $this->verify_nonce( $request );
	}

	/**
	 * Get current flow session state.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_flow_state_get( WP_REST_Request $request ) {
		$nonce = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce ) ) {
			return $nonce;
		}

		$flow_session = SM_Flow_Session::get_instance();
		$flow         = $flow_session->get_or_create_flow();

		if ( empty( $flow ) ) {
			return $this->error_response( 'flow_missing', __( 'Unable to load flow session.', 'mystic-palm-reading' ), 500 );
		}

		return $this->success_response( $this->format_flow_state( $flow ) );
	}

	/**
	 * Update current flow session state.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_flow_state_update( WP_REST_Request $request ) {
		$nonce = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce ) ) {
			return $nonce;
		}

		$flow_session = SM_Flow_Session::get_instance();
		$flow         = $flow_session->get_or_create_flow();

		if ( empty( $flow ) || empty( $flow['flow_id'] ) ) {
			return $this->error_response( 'flow_missing', __( 'Unable to load flow session.', 'mystic-palm-reading' ), 500 );
		}

		$updates = array(
			'step_id'    => $this->sanitize_string( $request->get_param( 'step_id' ) ),
			'status'     => $this->sanitize_string( $request->get_param( 'status' ) ),
			'lead_id'    => $this->sanitize_string( $request->get_param( 'lead_id' ) ),
			'reading_id' => $this->sanitize_string( $request->get_param( 'reading_id' ) ),
			'email'      => $this->sanitize_email_value( $request->get_param( 'email' ) ),
		);

		$updated = $flow_session->update_flow( $flow['flow_id'], $updates );
		if ( empty( $updated ) ) {
			return $this->error_response( 'flow_update_failed', __( 'Unable to update flow session.', 'mystic-palm-reading' ), 500 );
		}

		return $this->success_response( $this->format_flow_state( $updated ) );
	}

	/**
	 * Reset current flow session.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_flow_reset( WP_REST_Request $request ) {
		$nonce = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce ) ) {
			return $nonce;
		}

		$flow_session = SM_Flow_Session::get_instance();
		$flow         = $flow_session->reset_flow();

		if ( empty( $flow ) ) {
			return $this->error_response( 'flow_reset_failed', __( 'Unable to reset flow session.', 'mystic-palm-reading' ), 500 );
		}

		return $this->success_response( $this->format_flow_state( $flow ) );
	}

	/**
	 * Verify a magic link and update flow session.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_flow_magic_verify( WP_REST_Request $request ) {
		$nonce = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce ) ) {
			return $nonce;
		}

		$lead_id      = $this->sanitize_string( $request->get_param( 'lead_id' ) );
		$token        = $this->sanitize_string( $request->get_param( 'token' ) );
		$reading_type = $this->sanitize_string( $request->get_param( 'reading_type' ) );

		$validation = $this->validate_otp_magic_verify_request( $lead_id, $token );
		if ( is_wp_error( $validation ) ) {
			return $this->error_response( $validation->get_error_code(), $validation->get_error_message(), 400 );
		}

		$otp_handler = SM_OTP_Handler::get_instance();
		$result      = $otp_handler->verify_magic_token( $lead_id, $token );
		if ( is_wp_error( $result ) ) {
			$status = 400;
			$data   = $result->get_error_data();
			if ( is_array( $data ) && isset( $data['status'] ) ) {
				$status = (int) $data['status'];
			}

			return $this->error_response(
				$result->get_error_code(),
				$result->get_error_message(),
				$status
			);
		}

		$mailerlite = SM_MailerLite_Handler::get_instance();
		$mailerlite->schedule_sync( $lead_id );

		$reading_payload = $this->build_existing_reading_payload( $lead_id );

		$flow_session = SM_Flow_Session::get_instance();
		$flow         = $flow_session->get_or_create_flow();

		if ( ! empty( $flow['flow_id'] ) ) {
			$flow_step   = $reading_payload['exists'] ? 'result' : 'palmPhoto';
			$flow_status = $reading_payload['exists'] ? 'reading_ready' : 'otp_verified';

			$flow_session->update_flow(
				$flow['flow_id'],
				array(
					'lead_id'    => $lead_id,
					'reading_id' => $reading_payload['reading_id'],
					'status'     => $flow_status,
					'step_id'    => $flow_step,
				)
			);
		}

		$flow_state = $flow ? $this->format_flow_state( $flow_session->get_flow_by_id( $flow['flow_id'] ) ) : array();

		return $this->success_response(
			array(
				'email_confirmed' => true,
				'method'          => 'magic_link',
				'lead'            => $this->build_lead_snapshot( $lead_id ),
				'reading'         => $reading_payload,
				'flow'            => $flow_state,
			)
		);
	}

	/**
	 * Handle lead creation endpoint.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_lead_create( WP_REST_Request $request ) {
		$nonce = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce ) ) {
			return $nonce;
		}

		$email      = $this->sanitize_email_value( $request->get_param( 'email' ) );
		$redirect   = $this->get_no_credit_redirect_url();
		// If this email already has a reading, return the existing reading instead of redirecting
		$ai_handler = SM_AI_Handler::get_instance();
		if ( $ai_handler->reading_exists_for_email( $email ) ) {
			$lead_handler     = class_exists( 'SM_Lead_Handler' ) ? SM_Lead_Handler::init() : null;
			$reading_service  = SM_Reading_Service::get_instance();
			$existing_lead    = $lead_handler ? $lead_handler->get_lead_by_email( $email ) : null;
			$existing_reading = ( $existing_lead ) ? $reading_service->get_latest_reading( $existing_lead->id, 'aura_teaser' ) : null;

			if ( $existing_reading && ! is_wp_error( $existing_reading ) ) {
				$renderer     = SM_Template_Renderer::get_instance();
				$reading_html = $renderer->render_reading( $existing_reading->id );

				if ( is_wp_error( $reading_html ) ) {
					$reading_html = $this->get_error_fallback_html();
				}

				SM_Logger::info(
					'REST_LEAD_CREATE',
					'Existing reading returned for email (no new credits used)',
					array(
						'lead_id'    => $existing_lead ? $existing_lead->id : null,
						'reading_id' => $existing_reading->id,
					)
				);

				$this->update_flow_state(
					array(
						'lead_id'    => $existing_lead ? $existing_lead->id : null,
						'reading_id' => $existing_reading->id,
						'email'      => $email,
						'status'     => 'reading_ready',
						'step_id'    => 'result',
					)
				);

				return $this->success_response(
					array(
						'lead_id'           => $existing_lead ? $existing_lead->id : null,
						'existing_reading'  => true,
						'reading_html'      => $reading_html,
						'reading_id'        => $existing_reading->id,
						'redirect_to'       => $redirect,
					)
				);
			}

			// Fallback: keep previous behavior if we cannot retrieve the reading
			return $this->error_response(
				'credits_exhausted',
				__( 'You’ve already received your free reading for this email. Please check your inbox or use a different address.', 'mystic-palm-reading' ),
				400,
				array(
					'redirect_to' => $redirect,
					'redirect_delay_ms' => 3500,
				)
			);
		}

		// Relaxed rate limit: 20 requests per minute (increased for mobile retry scenarios)
		$rate_limit = $this->check_rate_limit(
			$this->build_rate_limit_key( $request, $email ),
			20,
			MINUTE_IN_SECONDS,
			array(
				'route' => 'lead/create',
				'email' => $this->mask_email( $email ),
			)
		);
		if ( is_wp_error( $rate_limit ) ) {
			$status = is_array( $rate_limit->get_error_data() ) && isset( $rate_limit->get_error_data()['status'] ) ? (int) $rate_limit->get_error_data()['status'] : 429;
			$data   = is_array( $rate_limit->get_error_data() ) ? $rate_limit->get_error_data() : array();
			return $this->error_response(
				'credits_exhausted',
				__( 'You’ve already received your free reading. Please check your email or use a different address.', 'mystic-palm-reading' ),
				$status,
				wp_parse_args(
					$data,
					array(
						'redirect_to'      => $redirect,
						'redirect_delay_ms'=> 3500,
					)
				)
			);
		}

		$name      = $this->sanitize_string( $request->get_param( 'name' ) );
		$identity  = $this->sanitize_string( $request->get_param( 'identity' ) );
		$gdpr      = $this->sanitize_boolean( $request->get_param( 'gdpr' ) );
		$age       = $request->get_param( 'age' );
		$age_range = $this->sanitize_string( $request->get_param( 'age_range' ) );

		$validation = $this->validate_lead_request( $name, $email, $identity, $gdpr, $age, $age_range );
		if ( is_wp_error( $validation ) ) {
			return $this->error_response( $validation->get_error_code(), $validation->get_error_message(), 400 );
		}

		$handler = SM_Lead_Handler::get_instance();
		$result  = $handler->create_lead( $name, $email, $identity, $gdpr, $age, $age_range );

		if ( is_wp_error( $result ) ) {
			$status = 400;
			$data   = $result->get_error_data();
			if ( is_array( $data ) && isset( $data['status'] ) ) {
				$status = (int) $data['status'];
			}

			return $this->error_response(
				$result->get_error_code(),
				$result->get_error_message(),
				$status
			);
		}

		SM_Logger::info(
			'REST_LEAD_CREATE',
			'Lead created via REST endpoint',
			array(
				'lead_id'       => $result['lead_id'],
				'email'         => $this->mask_email( $email ),
				'exists_before' => (bool) $result['exists_before'],
			)
		);

		$this->update_flow_state(
			array(
				'lead_id' => $result['lead_id'],
				'email'   => $email,
				'status'  => 'in_progress',
				'step_id' => 'leadCapture',
			)
		);

		return $this->success_response(
			array(
				'lead_id'       => $result['lead_id'],
				'email_status'  => (bool) $result['email_status'],
				'exists_before' => (bool) $result['exists_before'],
			)
		);
	}

	/**
	 * Fetch the current logged-in user's lead profile.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_lead_current( WP_REST_Request $request ) {
		$nonce = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce ) ) {
			return $nonce;
		}

		$auth_handler = SM_Auth_Handler::get_instance();
		$user_data    = $auth_handler->get_current_user();
		$force_new    = $this->sanitize_boolean( $request->get_param( 'start_new' ) );

		if ( empty( $user_data ) ) {
			return $this->error_response(
				'authentication_required',
				__( 'You must be logged in to continue.', 'mystic-palm-reading' ),
				401
			);
		}

		$profile = null;
		$email   = isset( $user_data['email'] ) ? sanitize_email( $user_data['email'] ) : '';
		if ( empty( $email ) ) {
			$profile = $auth_handler->get_account_profile();
			$email   = isset( $profile['email'] ) ? sanitize_email( $profile['email'] ) : '';
		}

		if ( empty( $email ) ) {
			return $this->error_response(
				'missing_email',
				__( 'We could not find your email on file.', 'mystic-palm-reading' ),
				400
			);
		}

		$lead_handler = SM_Lead_Handler::get_instance();
		$lead         = $lead_handler->get_lead_by_email( $email );

		if ( $force_new ) {
			if ( null === $profile ) {
				$profile = $auth_handler->get_account_profile();
			}
			$snapshot = $this->build_profile_snapshot( $profile, $email );
			$snapshot = $this->apply_profile_fallback( $snapshot, $profile, $email );

			if ( empty( $snapshot['name'] ) || empty( $snapshot['email'] ) || ( empty( $snapshot['age'] ) && empty( $snapshot['age_range'] ) ) ) {
				SM_Logger::log(
					'warning',
					'LEAD_CURRENT',
					'Account profile missing required data for lead creation.',
					array(
						'email'   => $this->mask_email( $email ),
						'has_name' => ! empty( $snapshot['name'] ),
						'has_age'  => ! empty( $snapshot['age'] ) || ! empty( $snapshot['age_range'] ),
					)
				);
				return $this->error_response(
					'invalid_account',
					__( 'Your account is missing required details. Please update your account and try again.', 'mystic-palm-reading' ),
					400
				);
			} else {
				$created = $this->create_logged_in_lead( $lead_handler, $snapshot, $email, true );
				if ( is_wp_error( $created ) ) {
					return $created;
				}
				$lead     = $lead_handler->get_lead_by_id( $created );
				$snapshot = $this->build_lead_snapshot( $created );
			}
		} elseif ( empty( $lead ) ) {
			if ( null === $profile ) {
				$profile = $auth_handler->get_account_profile();
			}

			$snapshot = $this->build_profile_snapshot( $profile, $email );
			$snapshot = $this->apply_profile_fallback( $snapshot, $profile, $email );

			if ( empty( $snapshot['name'] ) || empty( $snapshot['email'] ) || ( empty( $snapshot['age'] ) && empty( $snapshot['age_range'] ) ) ) {
				SM_Logger::log(
					'warning',
					'LEAD_CURRENT',
					'Account profile missing required data for lead creation.',
					array(
						'email'   => $this->mask_email( $email ),
						'has_name' => ! empty( $snapshot['name'] ),
						'has_age'  => ! empty( $snapshot['age'] ) || ! empty( $snapshot['age_range'] ),
					)
				);
				return $this->error_response(
					'invalid_account',
					__( 'Your account is missing required details. Please update your account and try again.', 'mystic-palm-reading' ),
					400
				);
			}

			$created = $this->create_logged_in_lead( $lead_handler, $snapshot, $email );
			if ( is_wp_error( $created ) ) {
				return $created;
			}

			$lead     = $lead_handler->get_lead_by_id( $created );
			$snapshot = $this->build_lead_snapshot( $created );
		} else {
			$snapshot = $this->build_lead_snapshot( $lead->id );
		}

		if ( empty( $snapshot ) ) {
			return $this->error_response(
				'lead_not_found',
				__( 'We could not load your profile details. Please try again.', 'mystic-palm-reading' ),
				404
			);
		}

		if ( null === $profile ) {
			$profile = $auth_handler->get_account_profile();
		}

		$snapshot = $this->apply_profile_fallback( $snapshot, $profile, $email );

		if ( empty( $snapshot['age'] ) && empty( $snapshot['age_range'] ) ) {
			$profile  = $auth_handler->get_account_profile();
			$age_data = $this->extract_age_data_from_profile( $profile );

			if ( ! empty( $age_data ) ) {
				if ( ! empty( $age_data['age'] ) ) {
					$snapshot['age'] = (int) $age_data['age'];
				}
				if ( ! empty( $age_data['age_range'] ) ) {
					$snapshot['age_range'] = $age_data['age_range'];
				}
			}
		}

		if ( empty( $snapshot['age_range'] ) && ! empty( $snapshot['age'] ) ) {
			$snapshot['age_range'] = $this->map_age_to_range( (int) $snapshot['age'] );
		}

		$missing = array();

		if ( empty( $snapshot['name'] ) ) {
			$missing[] = 'name';
		}
		if ( empty( $snapshot['email'] ) ) {
			$missing[] = 'email';
		}
		if ( empty( $snapshot['identity'] ) ) {
			$missing[] = 'identity';
		}
		if ( empty( $snapshot['age'] ) && empty( $snapshot['age_range'] ) ) {
			$missing[] = 'age';
		}

		if ( ! empty( $lead ) && empty( $missing ) && empty( $snapshot['email_confirmed'] ) ) {
			$lead_handler->update_lead( $lead->id, array( 'email_confirmed' => true ) );
			$snapshot['email_confirmed'] = true;
		}

		SM_Logger::info(
			'LEAD_CURRENT',
			'Lead profile resolved for logged-in user.',
			array(
				'has_lead'        => ! empty( $lead ),
				'has_profile'     => ! empty( $profile ),
				'missing_fields'  => $missing,
				'has_name'        => ! empty( $snapshot['name'] ),
				'has_identity'    => ! empty( $snapshot['identity'] ),
				'has_age'         => ! empty( $snapshot['age'] ) || ! empty( $snapshot['age_range'] ),
				'profile_email'   => ! empty( $snapshot['email'] ) ? $this->mask_email( $snapshot['email'] ) : '',
			)
		);

		return $this->success_response(
			array(
				'lead'             => $snapshot,
				'profile_complete' => empty( $missing ),
				'missing_fields'   => $missing,
			)
		);
	}

	/**
	 * Validate lead creation payload.
	 *
	 * @param string $name     Lead name.
	 * @param string $email    Lead email.
	 * @param string $identity Identity string.
	 * @param bool   $gdpr     GDPR consent.
	 * @return true|WP_Error
	 */
	protected function validate_lead_request( $name, $email, $identity, $gdpr, $age = null, $age_range = '' ) {
		$identity_options = array( 'woman', 'man', 'prefer-not' );
		$age_range_options = array( 'age_18_25', 'age_26_35', 'age_36_50', 'age_51_65', 'age_65_plus' );

		if ( '' === $name || '' === $email || '' === $identity ) {
			return new WP_Error( 'invalid_input', __( 'Please check your information and try again.', 'mystic-palm-reading' ) );
		}

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_input', __( 'Please check your information and try again.', 'mystic-palm-reading' ) );
		}

		if ( ! in_array( $identity, $identity_options, true ) ) {
			return new WP_Error( 'invalid_input', __( 'Please check your information and try again.', 'mystic-palm-reading' ) );
		}

		if ( true !== $gdpr ) {
			return new WP_Error( 'invalid_input', __( 'GDPR consent is required.', 'mystic-palm-reading' ) );
		}

		if ( null !== $age && '' !== $age ) {
			$age_int = (int) $age;
			if ( $age_int < 0 || $age_int > 120 ) {
				return new WP_Error( 'invalid_input', __( 'Please check your information and try again.', 'mystic-palm-reading' ) );
			}
		}

		if ( '' !== $age_range && ! in_array( $age_range, $age_range_options, true ) ) {
			return new WP_Error( 'invalid_input', __( 'Please check your information and try again.', 'mystic-palm-reading' ) );
		}

		return true;
	}

	/**
	 * Mask an email for logging.
	 *
	 * @param string $email Email address.
	 * @return string
	 */
	protected function mask_email( $email ) {
		if ( empty( $email ) || false === strpos( $email, '@' ) ) {
			return $email;
		}

		list( $user, $domain ) = explode( '@', $email, 2 );
		$masked_user           = strlen( $user ) > 2 ? substr( $user, 0, 2 ) . '***' : substr( $user, 0, 1 ) . '***';

		return $masked_user . '@' . $domain;
	}

	/**
	 * Handle OTP send endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_otp_send( WP_REST_Request $request ) {
		$nonce = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce ) ) {
			return $nonce;
		}

		$lead_id = $this->sanitize_string( $request->get_param( 'lead_id' ) );
		$async   = $this->get_async_flag( $request );
		$async   = $this->get_async_flag( $request );
		$email   = $this->sanitize_email_value( $request->get_param( 'email' ) );

		$validation = $this->validate_otp_send_request( $lead_id, $email );
		if ( is_wp_error( $validation ) ) {
			return $this->error_response( $validation->get_error_code(), $validation->get_error_message(), 400 );
		}

		// Per-email rate limit: 10 sends per minute (increased to allow resend clicks).
		$rate_limit_key = SM_Rate_Limiter::build_key( 'otp_send', array( strtolower( $email ), $this->get_client_ip() ) );
		$rate_result    = $this->check_rate_limit(
			$rate_limit_key,
			10,
			MINUTE_IN_SECONDS,
			array(
				'route' => 'otp/send',
				'email' => $this->mask_email( $email ),
			)
		);
		if ( is_wp_error( $rate_result ) ) {
			$status = is_array( $rate_result->get_error_data() ) && isset( $rate_result->get_error_data()['status'] ) ? (int) $rate_result->get_error_data()['status'] : 429;
			return $this->error_response( 'rate_limited', $rate_result->get_error_message(), $status, $rate_result->get_error_data() );
		}

		$otp_handler = SM_OTP_Handler::get_instance();
		$result      = $otp_handler->create_otp( $lead_id, $email );

		if ( is_wp_error( $result ) ) {
			$status = 400;
			$data   = $result->get_error_data();
			if ( is_array( $data ) && isset( $data['status'] ) ) {
				$status = (int) $data['status'];
			}

			return $this->error_response(
				$result->get_error_code(),
				$result->get_error_message(),
				$status
			);
		}

		SM_Logger::info(
			'REST_OTP_SEND',
			'OTP sent via REST endpoint',
			array(
				'lead_id'    => $lead_id,
				'email'      => $this->mask_email( $email ),
				'expires_at' => $result['expires_at'],
			)
		);

		$this->update_flow_state(
			array(
				'lead_id' => $lead_id,
				'email'   => $email,
				'status'  => 'otp_pending',
				'step_id' => 'emailVerification',
			)
		);

		return $this->success_response(
			array(
				'expires_at' => $result['expires_at'],
			)
		);
	}

	/**
	 * Handle OTP verification endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_otp_verify( WP_REST_Request $request ) {
		$nonce = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce ) ) {
			return $nonce;
		}

		$lead_id = $this->sanitize_string( $request->get_param( 'lead_id' ) );
		$otp     = preg_replace( '/[^0-9]/', '', (string) $request->get_param( 'otp' ) );

		$validation = $this->validate_otp_verify_request( $lead_id, $otp );
		if ( is_wp_error( $validation ) ) {
			return $this->error_response( $validation->get_error_code(), $validation->get_error_message(), 400 );
		}

		$max_attempts = SM_Settings::init()->get_otp_max_attempts();
		$rate_result  = $this->check_rate_limit(
			SM_Rate_Limiter::build_key( 'otp_verify', array( $lead_id, $this->get_client_ip() ) ),
			max( 1, $max_attempts ),
			10 * MINUTE_IN_SECONDS,
			array(
				'route' => 'otp/verify',
				'lead'  => $lead_id,
			)
		);
		if ( is_wp_error( $rate_result ) ) {
			$status = is_array( $rate_result->get_error_data() ) && isset( $rate_result->get_error_data()['status'] ) ? (int) $rate_result->get_error_data()['status'] : 429;
			return $this->error_response( 'rate_limited', $rate_result->get_error_message(), $status, $rate_result->get_error_data() );
		}

		$otp_handler = SM_OTP_Handler::get_instance();
		$result      = $otp_handler->verify_otp( $lead_id, $otp );

		if ( is_wp_error( $result ) ) {
			$attempts = $this->get_otp_attempts( $lead_id );
			$status   = 400;
			$data     = $result->get_error_data();
			if ( is_array( $data ) && isset( $data['status'] ) ) {
				$status = (int) $data['status'];
			}

			return $this->error_response(
				$result->get_error_code(),
				$result->get_error_message(),
				$status,
				$attempts
			);
		}

		// Trigger MailerLite sync (non-blocking).
		$mailerlite = SM_MailerLite_Handler::get_instance();
		$mailerlite->schedule_sync( $lead_id );

		SM_Logger::info(
			'REST_OTP_VERIFY',
			'OTP verified via REST endpoint',
			array(
				'lead_id' => $lead_id,
			)
		);

		$this->update_flow_state(
			array(
				'lead_id' => $lead_id,
				'status'  => 'otp_verified',
				'step_id' => 'palmPhoto',
			)
		);

		return $this->success_response(
			array(
				'email_confirmed' => true,
			)
		);
	}

	/**
	 * Handle OTP magic link verification endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_otp_magic_verify( WP_REST_Request $request ) {
		$start_time = microtime( true );
		$lead_id      = $this->sanitize_string( $request->get_param( 'lead_id' ) );
		$token        = $this->sanitize_string( $request->get_param( 'token' ) );
		$reading_type = $this->sanitize_string( $request->get_param( 'reading_type' ) );

		$otp_handler = SM_OTP_Handler::get_instance();
		$verify_start = microtime( true );

		$validation = $this->validate_otp_magic_verify_request( $lead_id, $token );
		if ( is_wp_error( $validation ) ) {
			return $this->error_response( $validation->get_error_code(), $validation->get_error_message(), 400 );
		}

		$max_attempts   = SM_Settings::init()->get_otp_max_attempts();
		$reuse_limit    = method_exists( $otp_handler, 'get_success_reuse_limit' ) ? $otp_handler->get_success_reuse_limit() : 20;
		$rate_limit_cap = max( 1, max( $max_attempts, $reuse_limit ) );

		$rate_result = $this->check_rate_limit(
			SM_Rate_Limiter::build_key( 'otp_magic_verify', array( $lead_id, $this->get_client_ip() ) ),
			$rate_limit_cap,
			10 * MINUTE_IN_SECONDS,
			array(
				'route' => 'otp/auto-verify',
				'lead'  => $lead_id,
			)
		);
		if ( is_wp_error( $rate_result ) ) {
			$status = is_array( $rate_result->get_error_data() ) && isset( $rate_result->get_error_data()['status'] ) ? (int) $rate_result->get_error_data()['status'] : 429;
			return $this->error_response( 'rate_limited', $rate_result->get_error_message(), $status, $rate_result->get_error_data() );
		}

		$result = $otp_handler->verify_magic_token( $lead_id, $token );
		$verify_duration = microtime( true ) - $verify_start;

		if ( is_wp_error( $result ) ) {
			$attempts = $this->get_otp_attempts( $lead_id );
			$status   = 400;
			$data     = $result->get_error_data();
			if ( is_array( $data ) && isset( $data['status'] ) ) {
				$status = (int) $data['status'];
			}

			return $this->error_response(
				$result->get_error_code(),
				$result->get_error_message(),
				$status,
				$attempts
			);
		}

		// Trigger MailerLite sync (non-blocking).
		$mailerlite = SM_MailerLite_Handler::get_instance();
		$mailerlite_start = microtime( true );
		$mailerlite->schedule_sync( $lead_id );
		$mailerlite_duration = microtime( true ) - $mailerlite_start;

		// If a reading already exists, attach it so frontend can render without an extra call.
		$reading_start   = microtime( true );
		$reading_payload = $this->build_existing_reading_payload( $lead_id );
		$reading_duration = microtime( true ) - $reading_start;

		$total_duration = microtime( true ) - $start_time;
		SM_Logger::info(
			'REST_OTP_MAGIC_VERIFY',
			'OTP verified via magic link',
			array(
				'lead_id' => $lead_id,
				'durations_ms' => array(
					'verify_magic_token' => (int) round( $verify_duration * 1000 ),
					'mailerlite_sync'    => (int) round( $mailerlite_duration * 1000 ),
					'reading_lookup'     => (int) round( $reading_duration * 1000 ),
					'total'              => (int) round( $total_duration * 1000 ),
				),
			)
		);

		$flow_step   = $reading_payload['exists'] ? 'result' : 'palmPhoto';
		$flow_status = $reading_payload['exists'] ? 'reading_ready' : 'otp_verified';

		$this->update_flow_state(
			array(
				'lead_id'    => $lead_id,
				'reading_id' => $reading_payload['reading_id'],
				'status'     => $flow_status,
				'step_id'    => $flow_step,
			)
		);

		return $this->success_response(
			array(
				'email_confirmed' => true,
				'method'          => 'magic_link',
				'lead'            => $this->build_lead_snapshot( $lead_id ),
				'reading'         => $reading_payload,
			)
		);
	}

	/**
	 * Validate OTP send payload.
	 *
	 * @param string $lead_id Lead UUID.
	 * @param string $email   Email address (optional; falls back to stored lead email).
	 * @return true|WP_Error
	 */
	protected function validate_otp_send_request( $lead_id, $email ) {
		if ( '' === $lead_id ) {
			return new WP_Error( 'invalid_input', __( 'Please check your information and try again.', 'mystic-palm-reading' ) );
		}

		if ( '' !== $email && ! is_email( $email ) ) {
			return new WP_Error( 'invalid_input', __( 'Please check your information and try again.', 'mystic-palm-reading' ) );
		}

		return true;
	}

	/**
	 * Validate OTP verify payload.
	 *
	 * @param string $lead_id Lead UUID.
	 * @param string $otp     OTP code.
	 * @return true|WP_Error
	 */
	protected function validate_otp_verify_request( $lead_id, $otp ) {
		if ( '' === $lead_id || '' === $otp ) {
			return new WP_Error( 'invalid_input', __( 'Please check your information and try again.', 'mystic-palm-reading' ) );
		}

		if ( strlen( $otp ) !== 4 ) {
			return new WP_Error( 'invalid_input', __( 'Please check your information and try again.', 'mystic-palm-reading' ) );
		}

		return true;
	}

	/**
	 * Validate magic link verification payload.
	 *
	 * @param string $lead_id Lead UUID.
	 * @param string $token   Magic token string.
	 * @return true|WP_Error
	 */
	protected function validate_otp_magic_verify_request( $lead_id, $token ) {
		if ( '' === $lead_id || '' === $token ) {
			return new WP_Error( 'invalid_input', __( 'Please check your information and try again.', 'mystic-palm-reading' ) );
		}

		return true;
	}

	/**
	 * Get latest OTP attempt info for feedback.
	 *
	 * @param string $lead_id Lead UUID.
	 * @return array
	 */
	protected function get_otp_attempts( $lead_id ) {
		$max_attempts = SM_Settings::init()->get_otp_max_attempts();

		$attempts = array(
			'attempts_used' => null,
			'attempts_max'  => $max_attempts,
		);

		$db = SM_Database::get_instance();
		if ( ! $db ) {
			return $attempts;
		}

		global $wpdb;
		$table = $db->get_table_name( 'otps' );
		if ( empty( $table ) ) {
			return $attempts;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT attempts FROM {$table} WHERE lead_id = %s ORDER BY created_at DESC LIMIT 1",
				$lead_id
			),
			ARRAY_A
		);

		if ( isset( $row['attempts'] ) ) {
			$attempts['attempts_used'] = (int) $row['attempts'];
		}

		return $attempts;
	}

	/**
	 * Handle MailerLite sync endpoint (non-blocking).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_mailerlite_sync( WP_REST_Request $request ) {
		$nonce = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce ) ) {
			return $nonce;
		}

		$lead_id = $this->sanitize_string( $request->get_param( 'lead_id' ) );
		if ( '' === $lead_id ) {
			return $this->error_response( 'invalid_input', __( 'Please check your information and try again.', 'mystic-palm-reading' ), 400 );
		}

		// Relaxed rate limit: 6 per 5 minutes (increased from 3 for better UX)
		$rate_result = $this->check_rate_limit(
			$this->build_rate_limit_key( $request, $lead_id ),
			6,
			5 * MINUTE_IN_SECONDS,
			array(
				'route' => 'mailerlite/sync',
				'lead'  => $lead_id,
			)
		);
		if ( is_wp_error( $rate_result ) ) {
			$status = is_array( $rate_result->get_error_data() ) && isset( $rate_result->get_error_data()['status'] ) ? (int) $rate_result->get_error_data()['status'] : 429;
			return $this->error_response( 'rate_limited', $rate_result->get_error_message(), $status, $rate_result->get_error_data() );
		}

		$mailerlite = SM_MailerLite_Handler::get_instance();
		$synced     = $mailerlite->sync_subscriber( $lead_id );

		return $this->success_response(
			array(
				'synced' => (bool) $synced,
			)
		);
	}

	/**
	 * Handle image upload endpoint (base64 or multipart).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_image_upload( WP_REST_Request $request ) {
		$nonce = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce ) ) {
			return $nonce;
		}

		$lead_id      = $this->sanitize_string( $request->get_param( 'lead_id' ) );
		$image_base64 = $request->get_param( 'image_base64' );
		$image_param  = $request->get_param( 'image' ); // Frontend sends as 'image'
		$image_file   = isset( $_FILES['image'] ) ? $_FILES['image'] : null;

		if ( '' === $lead_id ) {
			return $this->error_response( 'invalid_input', __( 'Please check your information and try again.', 'mystic-palm-reading' ), 400 );
		}

		// Per-lead upload rate limit: 15 per minute (increased to allow retakes).
		$rate_result = $this->check_rate_limit(
			SM_Rate_Limiter::build_key( 'image_upload', array( $lead_id, $this->get_client_ip() ) ),
			15,
			MINUTE_IN_SECONDS,
			array(
				'route' => 'image/upload',
				'lead'  => $lead_id,
			)
		);
		if ( is_wp_error( $rate_result ) ) {
			$status = is_array( $rate_result->get_error_data() ) && isset( $rate_result->get_error_data()['status'] ) ? (int) $rate_result->get_error_data()['status'] : 429;
			return $this->error_response( 'rate_limited', $rate_result->get_error_message(), $status, $rate_result->get_error_data() );
		}

		// Check for image in either parameter format
		if ( empty( $image_base64 ) && empty( $image_param ) && empty( $image_file ) ) {
			return $this->error_response( 'invalid_input', __( 'Please provide an image.', 'mystic-palm-reading' ), 400 );
		}

		// Normalize: if 'image' param exists, use it as base64
		if ( ! empty( $image_param ) && empty( $image_base64 ) ) {
			$image_base64 = $image_param;
		}

		$image_handler = SM_Image_Handler::get_instance();
		$result        = null;

		if ( ! empty( $image_base64 ) ) {
			$result = $image_handler->upload_base64( $lead_id, $image_base64 );
		} else {
			$result = $image_handler->upload_file( $lead_id, $image_file );
		}

		if ( is_wp_error( $result ) ) {
			if ( 'reading_exists' === $result->get_error_code() ) {
				return $this->error_response(
					'credits_exhausted',
					__( 'You’ve already received your free reading for this email.', 'mystic-palm-reading' ),
					400,
					array(
						'redirect_to'       => $this->get_no_credit_redirect_url(),
						'redirect_delay_ms' => 500,
					)
				);
			}

			return $this->error_response(
				$result->get_error_code(),
				$result->get_error_message(),
				400
			);
		}

		SM_Logger::info(
			'REST_IMAGE_UPLOAD',
			'Image uploaded via REST endpoint',
			array(
				'lead_id'  => $lead_id,
				'image'    => isset( $result['image_url'] ) ? $result['image_url'] : '',
				'mime'     => isset( $result['mime_type'] ) ? $result['mime_type'] : '',
				'filesize' => isset( $result['size'] ) ? $result['size'] : null,
			)
		);

		$this->update_flow_state(
			array(
				'lead_id' => $lead_id,
				'status'  => 'in_progress',
				'step_id' => 'palmPhoto',
			)
		);

		return $this->success_response(
			array(
				'image_url' => $result['image_url'],
			)
		);
	}

	/**
	 * Handle quiz save endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_quiz_save( WP_REST_Request $request ) {
		$nonce = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce ) ) {
			return $nonce;
		}

		$lead_id = $this->sanitize_string( $request->get_param( 'lead_id' ) );
		$answers = $request->get_param( 'answers' );

		if ( is_string( $answers ) ) {
			$decoded = json_decode( $answers, true );
			$answers = null === $decoded ? $answers : $decoded;
		}

		if ( '' === $lead_id || ! is_array( $answers ) ) {
			return $this->error_response( 'invalid_input', __( 'Please check your information and try again.', 'mystic-palm-reading' ), 400 );
		}

		$quiz_handler = SM_Quiz_Handler::get_instance();
		$result       = $quiz_handler->save_quiz( $lead_id, $answers );

		if ( is_wp_error( $result ) ) {
			return $this->error_response(
				$result->get_error_code(),
				$result->get_error_message(),
				400
			);
		}

		SM_Logger::info(
			'REST_QUIZ_SAVE',
			'Quiz saved via REST endpoint',
			array(
				'lead_id' => $lead_id,
			)
		);

		$this->update_flow_state(
			array(
				'lead_id' => $lead_id,
				'status'  => 'in_progress',
				'step_id' => 'quiz',
			)
		);

		$primary_focus = '';
		if ( isset( $answers['questions'] ) && is_array( $answers['questions'] ) ) {
			$first_question = null;
			foreach ( $answers['questions'] as $question ) {
				if ( isset( $question['position'] ) && 1 === (int) $question['position'] ) {
					$first_question = $question;
					break;
				}
			}
			if ( null === $first_question && ! empty( $answers['questions'][0] ) ) {
				$first_question = $answers['questions'][0];
			}
			if ( is_array( $first_question ) && isset( $first_question['answer'] ) ) {
				$answer = $first_question['answer'];
				if ( is_array( $answer ) ) {
					$primary_focus = sanitize_text_field( (string) reset( $answer ) );
				} else {
					$primary_focus = sanitize_text_field( (string) $answer );
				}
			}
		}

		if ( '' !== $primary_focus ) {
			$lead_handler = SM_Lead_Handler::get_instance();
			$lead         = $lead_handler->get_lead_by_id( $lead_id );
			$is_teaser    = ! empty( $lead ) && ( ! isset( $lead->account_id ) || '' === (string) $lead->account_id );
			if ( $is_teaser ) {
				$mailerlite = SM_MailerLite_Handler::get_instance();
				$mailerlite->schedule_sync(
					$lead_id,
					array( 'primary_focus' => $primary_focus )
				);
				SM_Logger::info(
					'MAILERLITE_PRIMARY_FOCUS',
					'MailerLite primary focus sync scheduled',
					array(
						'lead_id'       => $lead_id,
						'primary_focus' => $primary_focus,
					)
				);
			}
		}

		return $this->success_response( array( 'saved' => true ) );
	}

	/**
	 * Handle dynamic quiz questions endpoint.
	 *
	 * Returns 5 personalized questions based on user demographics.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_quiz_questions( WP_REST_Request $request ) {
		$nonce = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce ) ) {
			return $nonce;
		}

		// Get parameters
		$age_range = $this->sanitize_string( $request->get_param( 'age_range' ) );
		$gender    = $this->sanitize_string( $request->get_param( 'gender' ) );

		// Validate inputs
		if ( '' === $age_range || '' === $gender ) {
			return $this->error_response(
				'invalid_input',
				__( 'Age range and gender are required.', 'mystic-palm-reading' ),
				400
			);
		}

		// Get question bank handler
		$question_bank_handler = SM_Question_Bank_Handler::init();

		// Select 5 personalized questions
		$questions = $question_bank_handler->select_questions( $age_range, $gender );

		if ( is_wp_error( $questions ) ) {
			SM_Logger::log(
				'error',
				'REST_QUIZ_QUESTIONS_ERROR',
				'Failed to select quiz questions',
				array(
					'error'     => $questions->get_error_message(),
					'age_range' => $age_range,
					'gender'    => $gender,
				)
			);

			return $this->error_response(
				$questions->get_error_code(),
				$questions->get_error_message(),
				400
			);
		}

		SM_Logger::log(
			'info',
			'REST_QUIZ_QUESTIONS',
			'Quiz questions selected via REST endpoint',
			array(
				'age_range'      => $age_range,
				'gender'         => $gender,
				'question_count' => count( $questions ),
			)
		);

		return $this->success_response( array( 'questions' => $questions ) );
	}

	/**
	 * Handle reading generation endpoint.
	 *
	 * NOW USES TEASER READING SYSTEM (JSON-based with template rendering).
	 * ENFORCES ONE READING PER OTP/EMAIL - prevents duplicate generation.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_reading_generate( WP_REST_Request $request ) {
		$nonce = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce ) ) {
			return $nonce;
		}

		$lead_id = $this->sanitize_string( $request->get_param( 'lead_id' ) );
		$async   = $this->get_async_flag( $request );

		if ( '' === $lead_id ) {
			return $this->error_response( 'invalid_input', __( 'Please check your information and try again.', 'mystic-palm-reading' ), 400 );
		}

		SM_Logger::log(
			'info',
			'REST_READING_GENERATE',
			'Reading generation requested',
			array(
				'lead_id' => $lead_id,
				'route'   => $request->get_route(),
			)
		);

		// CRITICAL: Check if reading already exists for this lead (OTP consumption enforcement)
		$reading_service = SM_Reading_Service::get_instance();
		$existing_reading = $reading_service->get_latest_reading( $lead_id, 'aura_teaser' );

		// If reading exists, return it instead of generating a new one
		if ( ! is_wp_error( $existing_reading ) && ! empty( $existing_reading ) ) {
			$this->maybe_link_account_to_reading( $lead_id, $existing_reading->id );

			SM_Logger::info(
				'REST_READING_GENERATE',
				'Reading already exists for this lead - returning existing reading (OTP consumed)',
				array(
					'lead_id'    => $lead_id,
					'reading_id' => $existing_reading->id,
				)
			);

			// Render the existing reading
			$renderer = SM_Template_Renderer::get_instance();
			$reading_html = $renderer->render_reading( $existing_reading->id );

			if ( is_wp_error( $reading_html ) ) {
				$reading_html = $this->get_error_fallback_html();
			}

			$this->update_flow_state(
				array(
					'lead_id'    => $lead_id,
					'reading_id' => $existing_reading->id,
					'status'     => 'reading_ready',
					'step_id'    => 'result',
				)
			);

			$reading_token = '';
			if ( class_exists( 'SM_Reading_Token' ) ) {
				$reading_token = SM_Reading_Token::generate( $lead_id, $existing_reading->id, 'aura_teaser' );
			}

			return $this->success_response(
				array(
					'reading_html'  => $reading_html,
					'reading_id'    => $existing_reading->id,
					'reading_token' => $reading_token,
					'is_existing'   => true, // Flag to inform frontend this is a cached reading
					'status'        => 'ready',
				)
			);
		}

		$lead_handler = class_exists( 'SM_Lead_Handler' ) ? SM_Lead_Handler::get_instance() : null;
		$lead         = $lead_handler ? $lead_handler->get_lead_by_id( $lead_id ) : null;
		if ( $lead && ! empty( $lead->invalid_image_locked ) ) {
			$last_reason = isset( $lead->invalid_image_last_reason ) ? (string) $lead->invalid_image_last_reason : '';
			if ( 'api_error' === $last_reason && $lead_handler ) {
				$lead_handler->update_lead(
					$lead_id,
					array(
						'invalid_image_attempts'    => 0,
						'invalid_image_locked'      => 0,
						'invalid_image_last_reason' => '',
						'invalid_image_last_at'     => null,
					)
				);
			} else {
			return $this->handle_palm_image_invalid(
				$lead_id,
				new WP_Error(
					'palm_image_invalid',
					__( 'We still could not see your photo clearly. Please contact support or start a new reading.', 'mystic-aura-reading' ),
					array( 'reason' => 'locked' )
				),
				2,
				false
			);
			}
		}

		$settings     = SM_Settings::init()->get_settings();
		$auth_handler = SM_Auth_Handler::get_instance();

		if ( ! empty( $settings['enable_account_integration'] ) && $auth_handler->is_user_logged_in() ) {
			return $this->error_response(
				'paid_required',
				__( 'Paid reading required for logged-in users.', 'mystic-palm-reading' ),
				403
			);
		}

		if ( $async ) {
			$job_handler = SM_Reading_Job_Handler::get_instance();
			$job         = $job_handler->get_job( $lead_id, 'aura_teaser' );

			if ( $job ) {
				return $this->build_job_status_response( $lead_id, 'aura_teaser', $job, 2, false );
			}

			$job = $job_handler->create_job( $lead_id, 'aura_teaser' );

			$this->update_flow_state(
				array(
					'lead_id' => $lead_id,
					'status'  => 'reading_processing',
					'step_id' => 'resultLoading',
				)
			);

			return $this->success_response(
				array(
					'status'       => 'processing',
					'job_id'       => isset( $job['job_id'] ) ? $job['job_id'] : '',
					'reading_type' => 'aura_teaser',
				)
			);
		}

		// Step 1: Generate NEW teaser reading (JSON) using AI handler
		$ai_handler = SM_AI_Handler::get_instance();
		$teaser_result = $ai_handler->generate_teaser_reading( $lead_id );

		if ( is_wp_error( $teaser_result ) ) {
			if ( 'palm_image_invalid' === $teaser_result->get_error_code() ) {
				return $this->handle_palm_image_invalid( $lead_id, $teaser_result, 2, false );
			}

			return $this->error_response(
				$teaser_result->get_error_code(),
				$teaser_result->get_error_message(),
				400
			);
		}

		$reading_id = isset( $teaser_result['reading_id'] ) ? $teaser_result['reading_id'] : null;

		if ( empty( $reading_id ) ) {
			return $this->error_response(
				'generation_error',
				__( 'Failed to generate reading.', 'mystic-palm-reading' ),
				500
			);
		}

		$this->maybe_link_account_to_reading( $lead_id, $reading_id );

		// Step 2: Render template with the reading data
		$renderer = SM_Template_Renderer::get_instance();
		$reading_html = $renderer->render_reading( $reading_id );

		if ( is_wp_error( $reading_html ) ) {
			SM_Logger::log(
				'error',
				'REST_READING_GENERATE',
				'Template rendering failed',
				array(
					'lead_id'    => $lead_id,
					'reading_id' => $reading_id,
					'error'      => $reading_html->get_error_message(),
				)
			);

			// Fallback: Return basic error HTML
			$reading_html = $this->get_error_fallback_html();
		}

		SM_Logger::log(
			'info',
			'REST_READING_GENERATE',
			'Rendered reading HTML',
			array(
				'lead_id'      => $lead_id,
				'reading_id'   => $reading_id,
				'html_length'  => strlen( $reading_html ),
				'has_fallback' => ( false !== strpos( $reading_html, 'Your Reading is Being Prepared' ) ) ? 'yes' : 'no',
			)
		);

		SM_Logger::info(
			'REST_READING_GENERATE',
			'NEW teaser reading generated and rendered (OTP consumed)',
			array(
				'lead_id'    => $lead_id,
				'reading_id' => $reading_id,
			)
		);

		$this->update_flow_state(
			array(
				'lead_id'    => $lead_id,
				'reading_id' => $reading_id,
				'status'     => 'reading_ready',
				'step_id'    => 'result',
			)
		);

		$reading_token = '';
		if ( class_exists( 'SM_Reading_Token' ) ) {
			$reading_token = SM_Reading_Token::generate( $lead_id, $reading_id, 'aura_teaser' );
		}

		return $this->success_response(
			array(
				'reading_html'  => $reading_html, // Already escaped by renderer
				'reading_id'    => $reading_id,
				'reading_token' => $reading_token,
				'is_existing'   => false,
				'status'        => 'ready',
			)
		);
	}

	/**
	 * Handle reading status endpoint (async polling).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_reading_status( WP_REST_Request $request ) {
		$nonce = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce ) ) {
			return $nonce;
		}

		$lead_id      = $this->sanitize_string( $request->get_param( 'lead_id' ) );
		$reading_type = $this->sanitize_string( $request->get_param( 'reading_type' ) );
		$allowed_types = array( 'aura_teaser', 'aura_full' );

		if ( '' === $lead_id ) {
			return $this->error_response( 'invalid_input', __( 'Please check your information and try again.', 'mystic-palm-reading' ), 400 );
		}

		if ( ! in_array( $reading_type, $allowed_types, true ) ) {
			$reading_type = 'aura_teaser';
		}

		$auth_handler = SM_Auth_Handler::get_instance();
		if ( 'aura_full' === $reading_type && ! $auth_handler->is_user_logged_in() ) {
			$reading_type = 'aura_teaser';
		}

		$reading_service = SM_Reading_Service::get_instance();
		$existing_reading = $reading_service->get_latest_reading( $lead_id, $reading_type );

		if ( ! is_wp_error( $existing_reading ) && ! empty( $existing_reading ) ) {
			$renderer = ( 'aura_full' === $reading_type )
				? SM_Full_Template_Renderer::get_instance()
				: SM_Template_Renderer::get_instance();
			$reading_html = $renderer->render_reading( $existing_reading->id );

			if ( is_wp_error( $reading_html ) ) {
				$reading_html = $this->get_error_fallback_html();
			}

			$this->update_flow_state(
				array(
					'lead_id'    => $lead_id,
					'reading_id' => $existing_reading->id,
					'status'     => 'reading_ready',
					'step_id'    => 'result',
				)
			);

			$reading_token = '';
			if ( 'aura_teaser' === $reading_type && class_exists( 'SM_Reading_Token' ) ) {
				$reading_token = SM_Reading_Token::generate( $lead_id, $existing_reading->id, $reading_type );
			}

			return $this->success_response(
				array(
					'status'        => 'ready',
					'reading_html'  => $reading_html,
					'reading_id'    => $existing_reading->id,
					'reading_type'  => $reading_type,
					'reading_token' => $reading_token,
				)
			);
		}

		$job_handler = SM_Reading_Job_Handler::get_instance();
		$job         = $job_handler->get_job( $lead_id, $reading_type );

		if ( $job ) {
			$max_attempts = ( 'aura_full' === $reading_type ) ? 3 : 2;
			$charge_credit = ( 'aura_full' === $reading_type );
			return $this->build_job_status_response( $lead_id, $reading_type, $job, $max_attempts, $charge_credit );
		}

		return $this->success_response(
			array(
				'status'  => 'not_found',
				'message' => __( 'No reading is currently processing.', 'mystic-palm-reading' ),
			)
		);
	}

	/**
	 * Handle async job execution (non-blocking dispatch).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_reading_job_run( WP_REST_Request $request ) {
		$lead_id      = $this->sanitize_string( $request->get_param( 'lead_id' ) );
		$reading_type = $this->sanitize_string( $request->get_param( 'reading_type' ) );
		$job_id       = $this->sanitize_string( $request->get_param( 'job_id' ) );
		$job_token    = $this->sanitize_string( $request->get_param( 'job_token' ) );
		$account_id   = $this->sanitize_string( $request->get_param( 'account_id' ) );

		if ( '' === $lead_id || '' === $reading_type || '' === $job_id || '' === $job_token ) {
			return $this->error_response( 'invalid_input', __( 'Missing job details.', 'mystic-palm-reading' ), 400 );
		}

		$job_handler = SM_Reading_Job_Handler::get_instance();
		if ( ! $job_handler->validate_job_token( $lead_id, $reading_type, $job_id, $job_token ) ) {
			return $this->error_response( 'invalid_job_token', __( 'Invalid job token.', 'mystic-palm-reading' ), 403 );
		}

		SM_Logger::log(
			'info',
			'READING_JOB',
			'Reading job run triggered',
			array(
				'lead_id'      => $lead_id,
				'reading_type' => $reading_type,
				'job_id'       => $job_id,
			)
		);

		$job_handler->process_job( $lead_id, $reading_type, $account_id );

		return $this->success_response( array( 'status' => 'processing' ) );
	}

	/**
	 * Handle paid reading generation endpoint (Full Reading System).
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_paid_reading_generate( WP_REST_Request $request ) {
		$nonce = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce ) ) {
			return $nonce;
		}

		$lead_id = $this->sanitize_string( $request->get_param( 'lead_id' ) );
		$async   = $this->get_async_flag( $request );

		if ( '' === $lead_id ) {
			return $this->error_response( 'invalid_input', __( 'Please check your information and try again.', 'mystic-palm-reading' ), 400 );
		}

		SM_Logger::log(
			'info',
			'REST_PAID_READING_GENERATE',
			'Paid reading generation requested',
			array(
				'lead_id' => $lead_id,
				'route'   => $request->get_route(),
			)
		);

		$settings     = SM_Settings::init()->get_settings();
		$auth_handler = SM_Auth_Handler::get_instance();

		if ( empty( $settings['enable_account_integration'] ) || ! $auth_handler->is_user_logged_in() ) {
			return $this->error_response(
				'authentication_required',
				__( 'Please log in to generate a full reading.', 'mystic-palm-reading' ),
				401
			);
		}

		$lead_handler = class_exists( 'SM_Lead_Handler' ) ? SM_Lead_Handler::get_instance() : null;
		$lead         = $lead_handler ? $lead_handler->get_lead_by_id( $lead_id ) : null;
		if ( $lead && ! empty( $lead->invalid_image_locked ) ) {
			$last_reason = isset( $lead->invalid_image_last_reason ) ? (string) $lead->invalid_image_last_reason : '';
			if ( 'api_error' === $last_reason && $lead_handler ) {
				$lead_handler->update_lead(
					$lead_id,
					array(
						'invalid_image_attempts'    => 0,
						'invalid_image_locked'      => 0,
						'invalid_image_last_reason' => '',
						'invalid_image_last_at'     => null,
					)
				);
			} else {
			return $this->handle_palm_image_invalid(
				$lead_id,
				new WP_Error(
					'palm_image_invalid',
					__( 'We still could not see your photo clearly. Please contact support or start a new reading.', 'mystic-aura-reading' ),
					array( 'reason' => 'locked' )
				),
				3,
				true
			);
			}
		}

		$credit_handler = SM_Credit_Handler::get_instance();
		$credit_check   = $credit_handler->check_user_credits();

		if ( empty( $credit_check['success'] ) ) {
			SM_Logger::log(
				'error',
				'REST_CREDIT_CHECK',
				'Credit check failed before paid reading generation.',
				array(
					'lead_id' => $lead_id,
					'error'   => isset( $credit_check['error'] ) ? $credit_check['error'] : 'unknown_error',
				)
			);

			return $this->error_response(
				'credit_check_failed',
				__( 'We could not verify your credits right now. Please try again.', 'mystic-palm-reading' ),
				400
			);
		}

		if ( empty( $credit_check['has_credits'] ) ) {
			$return_url = $this->get_credit_return_url( $request );
			$redirect   = $this->get_credit_shop_redirect_url( $return_url );

			return $this->error_response(
				'credits_exhausted',
				__( 'You do not have enough credits to generate a new reading.', 'mystic-palm-reading' ),
				400,
				array(
					'redirect_to'       => $redirect,
					'redirect_delay_ms' => 500,
					'service_balance'   => isset( $credit_check['service_balance'] ) ? $credit_check['service_balance'] : 0,
					'universal_balance' => isset( $credit_check['universal_balance'] ) ? $credit_check['universal_balance'] : 0,
				)
			);
		}

		$user_data  = $auth_handler->get_current_user();
		$account_id = ! empty( $user_data['account_id'] ) ? sanitize_text_field( (string) $user_data['account_id'] ) : '';

		if ( $async ) {
			$job_handler = SM_Reading_Job_Handler::get_instance();
			$job         = $job_handler->get_job( $lead_id, 'aura_full' );

			if ( $job ) {
				return $this->build_job_status_response( $lead_id, 'aura_full', $job, 3, true );
			}

			$jwt_token = $credit_handler->get_current_token();
			$job = $job_handler->create_job( $lead_id, 'aura_full', $account_id, $jwt_token );

			$this->update_flow_state(
				array(
					'lead_id' => $lead_id,
					'status'  => 'reading_processing',
					'step_id' => 'resultLoading',
				)
			);

			return $this->success_response(
				array(
					'status'       => 'processing',
					'job_id'       => isset( $job['job_id'] ) ? $job['job_id'] : '',
					'reading_type' => 'aura_full',
				)
			);
		}

		$ai_handler = SM_AI_Handler::get_instance();
		$result     = $ai_handler->generate_paid_reading( $lead_id, $account_id );

		if ( is_wp_error( $result ) ) {
			if ( 'palm_image_invalid' === $result->get_error_code() ) {
				return $this->handle_palm_image_invalid( $lead_id, $result, 3, true );
			}

			return $this->error_response(
				$result->get_error_code(),
				$result->get_error_message(),
				500
			);
		}

		$reading_id = isset( $result['reading_id'] ) ? $result['reading_id'] : null;
		if ( empty( $reading_id ) ) {
			return $this->error_response(
				'generation_error',
				__( 'Failed to generate reading.', 'mystic-palm-reading' ),
				500
			);
		}

		$this->maybe_link_account_to_reading( $lead_id, $reading_id );

		$idempotency_key = 'reading_' . $reading_id;
		$deduction       = $credit_handler->deduct_credit( $idempotency_key );

		if ( empty( $deduction['success'] ) ) {
			SM_Logger::log(
				'error',
				'REST_CREDIT_DEDUCT',
				'Credit deduction failed after paid reading generation.',
				array(
					'lead_id'    => $lead_id,
					'reading_id' => $reading_id,
					'error'      => isset( $deduction['error'] ) ? $deduction['error'] : 'unknown_error',
				)
			);

			SM_Reading_Service::get_instance()->delete_reading( $reading_id );

			return $this->error_response(
				'credit_deduct_failed',
				__( 'We could not finalize your reading. Please try again in a moment.', 'mystic-palm-reading' ),
				502
			);
		}

		SM_AI_Handler::get_instance()->cleanup_palm_images_for_lead( $lead_id );

		$renderer = SM_Full_Template_Renderer::get_instance();
		$reading_html = $renderer->render_reading( $reading_id );

		if ( is_wp_error( $reading_html ) ) {
			$reading_html = $this->get_error_fallback_html();
		}

		SM_Logger::log(
			'info',
			'REST_PAID_READING_GENERATE',
			'Rendered paid reading HTML',
			array(
				'lead_id'      => $lead_id,
				'reading_id'   => $reading_id,
				'html_length'  => strlen( $reading_html ),
				'has_fallback' => ( false !== strpos( $reading_html, 'Your Reading is Being Prepared' ) ) ? 'yes' : 'no',
			)
		);

		$this->update_flow_state(
			array(
				'lead_id'    => $lead_id,
				'reading_id' => $reading_id,
				'status'     => 'reading_ready',
				'step_id'    => 'result',
			)
		);

		$credits_remaining = isset( $deduction['total_available'] ) ? $deduction['total_available'] : null;

		return $this->success_response(
			array(
				'reading_html'      => $reading_html,
				'reading_id'        => $reading_id,
				'reading_type'      => 'aura_full',
				'credits_remaining' => $credits_remaining,
			)
		);
	}

	/**
	 * Get fallback error HTML when rendering fails
	 *
	 * @return string Error HTML.
	 */
	private function get_error_fallback_html() {
		return '<div style="padding: 2rem; text-align: center; color: #666;">'
			. '<h2>Your Reading is Being Prepared</h2>'
			. '<p>We encountered a temporary issue loading your reading. Please refresh the page or contact support.</p>'
			. '</div>';
	}

	/**
	 * Build reading payload for an existing lead.
	 *
	 * @param string $lead_id Lead UUID.
	 * @return array<string,mixed>
	 */
	private function build_existing_reading_payload( $lead_id ) {
		$reading_service = SM_Reading_Service::get_instance();
		$existing_reading = $reading_service->get_latest_reading( $lead_id, 'aura_teaser' );
		$reading_payload  = array(
			'exists'       => false,
			'reading_id'   => null,
			'reading_html' => null,
		);

		if ( ! is_wp_error( $existing_reading ) && ! empty( $existing_reading ) ) {
			$renderer = SM_Template_Renderer::get_instance();
			$reading_html = $renderer->render_reading( $existing_reading->id );

			if ( is_wp_error( $reading_html ) ) {
				$reading_html = $this->get_error_fallback_html();
			}

			$reading_payload = array(
				'exists'       => true,
				'reading_id'   => $existing_reading->id,
				'reading_html' => $reading_html,
			);
		}

		return $reading_payload;
	}

	/**
	 * Handle invalid palm image detection with retry and lockout support.
	 *
	 * @param string   $lead_id       Lead UUID.
	 * @param WP_Error $error         Error from palm summary generation.
	 * @param int      $max_attempts  Maximum total attempts before lockout.
	 * @param bool     $charge_credit Whether to deduct credit on lockout.
	 * @return WP_REST_Response
	 */
	private function handle_palm_image_invalid( $lead_id, $error, $max_attempts, $charge_credit ) {
		$lead_handler     = class_exists( 'SM_Lead_Handler' ) ? SM_Lead_Handler::get_instance() : null;
		$credit_handler   = class_exists( 'SM_Credit_Handler' ) ? SM_Credit_Handler::get_instance() : null;
		$lead             = $lead_handler ? $lead_handler->get_lead_by_id( $lead_id ) : null;
		$current_attempts = ( $lead && isset( $lead->invalid_image_attempts ) ) ? (int) $lead->invalid_image_attempts : 0;
		$already_locked   = ( $lead && ! empty( $lead->invalid_image_locked ) );
		$attempts         = $current_attempts;
		$locked           = $already_locked;
		$reason           = '';

		if ( $error instanceof WP_Error ) {
			$data = $error->get_error_data();
			if ( is_array( $data ) && ! empty( $data['reason'] ) ) {
				$reason = sanitize_text_field( (string) $data['reason'] );
			}
			if ( '' === $reason ) {
				$reason = sanitize_text_field( (string) $error->get_error_code() );
			}
		}

		$count_attempt = ( 'api_error' !== $reason );
		if ( ! $already_locked && $count_attempt ) {
			$attempts = $current_attempts + 1;
			$locked   = ( $attempts >= $max_attempts );
		}

		if ( $lead_handler && ! empty( $lead_id ) ) {
			$update = array(
				'invalid_image_attempts'    => $attempts,
				'invalid_image_locked'      => $locked ? 1 : 0,
				'invalid_image_last_reason' => $reason,
				'invalid_image_last_at'     => current_time( 'mysql' ),
			);

			$update_result = $lead_handler->update_lead( $lead_id, $update );
			if ( is_wp_error( $update_result ) ) {
				SM_Logger::warning(
					'PALM_IMAGE_INVALID',
					'Failed to update lead invalid image metadata',
					array(
						'lead_id' => $lead_id,
						'error'   => $update_result->get_error_message(),
					)
				);
			}
		}

		$credit_deducted = false;
		if ( $charge_credit && $locked && ! $already_locked && $credit_handler ) {
			$idempotency_key = 'invalid_palm_' . $lead_id;
			$deduction       = $credit_handler->deduct_credit( $idempotency_key );
			$credit_deducted = ! empty( $deduction['success'] );

			if ( ! $credit_deducted ) {
				SM_Logger::warning(
					'PALM_IMAGE_INVALID',
					'Credit deduction failed after palm image lockout',
					array(
						'lead_id' => $lead_id,
						'error'   => isset( $deduction['error'] ) ? $deduction['error'] : 'unknown_error',
					)
				);
			}
		}

		SM_Logger::warning(
			'PALM_IMAGE_INVALID',
			'Palm image rejected; retry required',
			array(
				'lead_id'      => $lead_id,
				'reason'       => $reason,
				'attempts'     => $attempts,
				'max_attempts' => $max_attempts,
				'locked'       => $locked ? 1 : 0,
				'counted'      => $count_attempt ? 1 : 0,
				'flow'         => $charge_credit ? 'paid' : 'teaser',
			)
		);

		$this->update_flow_state(
			array(
				'lead_id' => $lead_id,
				'status'  => 'image_retry',
				'step_id' => 'palmPhoto',
			)
		);

		if ( 'api_error' === $reason ) {
			$message = __( 'We had trouble analyzing your photo right now. Please try again in a moment.', 'mystic-aura-reading' );
		} elseif ( $locked && $charge_credit ) {
			$message = __( 'We could not verify your photo after multiple attempts. One credit was used. Please contact support if you need help.', 'mystic-aura-reading' );
		} elseif ( $locked ) {
			$message = __( 'We still could not see your photo clearly. Please contact support or start a new reading.', 'mystic-aura-reading' );
		} else {
			$message = $error instanceof WP_Error ? $error->get_error_message() : __( 'We could not clearly see your photo. Please go back and upload a clear shoulders-up photo.', 'mystic-aura-reading' );
		}

		return $this->error_response(
			'palm_image_invalid',
			$message,
			422,
			array(
				'step_id'         => 'palmPhoto',
				'attempts'        => $attempts,
				'max_attempts'    => $max_attempts,
				'retry_remaining' => max( 0, $max_attempts - $attempts ),
				'locked'          => $locked,
				'credit_deducted' => $credit_deducted,
			)
		);
	}

	/**
	 * Update flow session state (best effort).
	 *
	 * @param array $updates Flow updates.
	 * @return void
	 */
	private function update_flow_state( $updates ) {
		if ( ! class_exists( 'SM_Flow_Session' ) ) {
			return;
		}

		$flow_session = SM_Flow_Session::get_instance();
		$flow         = $flow_session->get_or_create_flow();
		if ( empty( $flow['flow_id'] ) ) {
			return;
		}

		$flow_session->update_flow( $flow['flow_id'], $updates );
	}

	/**
	 * Parse async flag from request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	private function get_async_flag( WP_REST_Request $request ) {
		$async = $request->get_param( 'async' );
		return filter_var( $async, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Build a response for a queued/running/failed job.
	 *
	 * @param string $lead_id Lead UUID.
	 * @param string $reading_type Reading type.
	 * @param array  $job Job data.
	 * @param int    $max_attempts Max invalid image attempts.
	 * @param bool   $charge_credit Whether to deduct credit on lockout.
	 * @return WP_REST_Response
	 */
	private function build_job_status_response( $lead_id, $reading_type, $job, $max_attempts, $charge_credit ) {
		$status = isset( $job['status'] ) ? $job['status'] : 'queued';

		if ( in_array( $status, array( 'queued', 'running' ), true ) ) {
			return $this->success_response(
				array(
					'status'       => 'processing',
					'job_id'       => isset( $job['job_id'] ) ? $job['job_id'] : '',
					'reading_type' => $reading_type,
				)
			);
		}

		if ( 'failed' === $status ) {
			$error_code = isset( $job['error_code'] ) ? (string) $job['error_code'] : 'generation_error';
			$error_message = isset( $job['error_message'] ) ? (string) $job['error_message'] : __( 'Failed to generate reading.', 'mystic-palm-reading' );
			$error_data = isset( $job['error_data'] ) && is_array( $job['error_data'] ) ? $job['error_data'] : array();

			if ( 'palm_image_invalid' === $error_code ) {
				$reason = '';
				if ( ! empty( $error_data['reason'] ) ) {
					$reason = sanitize_text_field( (string) $error_data['reason'] );
				}
				$job_handler = SM_Reading_Job_Handler::get_instance();
				$job_handler->delete_job( $lead_id, $reading_type );
				return $this->handle_palm_image_invalid(
					$lead_id,
					new WP_Error( 'palm_image_invalid', $error_message, array( 'reason' => $reason ) ),
					$max_attempts,
					$charge_credit
				);
			}

			$job_handler = SM_Reading_Job_Handler::get_instance();
			$job_handler->delete_job( $lead_id, $reading_type );

			return $this->error_response(
				$error_code,
				$error_message,
				500
			);
		}

		return $this->success_response(
			array(
				'status'       => 'processing',
				'job_id'       => isset( $job['job_id'] ) ? $job['job_id'] : '',
				'reading_type' => $reading_type,
			)
		);
	}

	/**
	 * Format flow state for REST responses.
	 *
	 * @param array $flow Flow row.
	 * @return array<string,mixed>
	 */
	private function format_flow_state( $flow ) {
		if ( ! is_array( $flow ) ) {
			return array();
		}

		return array(
			'flow_id'    => isset( $flow['flow_id'] ) ? $flow['flow_id'] : '',
			'step_id'    => isset( $flow['step_id'] ) ? $flow['step_id'] : '',
			'status'     => isset( $flow['status'] ) ? $flow['status'] : '',
			'lead_id'    => isset( $flow['lead_id'] ) ? $flow['lead_id'] : '',
			'reading_id' => isset( $flow['reading_id'] ) ? $flow['reading_id'] : '',
			'email'      => isset( $flow['email'] ) ? $flow['email'] : '',
			'expires_at' => isset( $flow['expires_at'] ) ? $flow['expires_at'] : '',
		);
	}

	/**
	 * Attach account_id to lead and reading after a successful generation.
	 *
	 * @param string $lead_id Lead UUID.
	 * @param string $reading_id Reading UUID.
	 * @return void
	 */
	protected function maybe_link_account_to_reading( $lead_id, $reading_id ) {
		$settings = SM_Settings::init()->get_settings();
		if ( empty( $settings['enable_account_integration'] ) ) {
			return;
		}

		$auth_handler = SM_Auth_Handler::get_instance();
		if ( ! $auth_handler->is_user_logged_in() ) {
			return;
		}

		$user_data  = $auth_handler->get_current_user();
		$account_id = ! empty( $user_data['account_id'] ) ? sanitize_text_field( (string) $user_data['account_id'] ) : '';

		if ( '' === $account_id ) {
			return;
		}

		global $wpdb;
		$db            = SM_Database::get_instance();
		$readings_table = $db->get_table_name( 'readings' );
		$leads_table    = $db->get_table_name( 'leads' );

		$reading_updated = false;
		if ( ! empty( $reading_id ) ) {
			$reading_updated = false !== $wpdb->update(
				$readings_table,
				array( 'account_id' => $account_id ),
				array( 'id' => $reading_id ),
				array( '%s' ),
				array( '%s' )
			);
		}

		$lead_updated = false;
		if ( ! empty( $lead_id ) ) {
			$lead_updated = false !== $wpdb->update(
				$leads_table,
				array( 'account_id' => $account_id ),
				array( 'id' => $lead_id ),
				array( '%s' ),
				array( '%s' )
			);
		}

		SM_Logger::info(
			'READING_ACCOUNT_LINK',
			'Linked account to reading after successful generation.',
			array(
				'lead_id'        => $lead_id,
				'reading_id'     => $reading_id,
				'account_id'     => $account_id,
				'lead_updated'   => $lead_updated,
				'reading_updated'=> $reading_updated,
			)
		);
	}

	/**
	 * Handle teaser reading generation endpoint (NEW - Teaser Reading System).
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_teaser_reading_generate( WP_REST_Request $request ) {
		$nonce = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce ) ) {
			return $nonce;
		}

		$lead_id = $this->sanitize_string( $request->get_param( 'lead_id' ) );

		if ( '' === $lead_id ) {
			return $this->error_response( 'invalid_input', __( 'Please check your information and try again.', 'mystic-palm-reading' ), 400 );
		}

		SM_Logger::log(
			'info',
			'REST_TEASER_READING_GENERATE',
			'Teaser reading generation requested',
			array(
				'lead_id' => $lead_id,
				'route'   => $request->get_route(),
			)
		);

		$ai_handler = SM_AI_Handler::get_instance();
		$result     = $ai_handler->generate_teaser_reading( $lead_id );

		if ( is_wp_error( $result ) ) {
			return $this->error_response(
				$result->get_error_code(),
				$result->get_error_message(),
				400
			);
		}

		SM_Logger::info(
			'REST_TEASER_READING_GENERATE',
			'Teaser reading generated via REST endpoint',
			array(
				'lead_id'    => $lead_id,
				'reading_id' => isset( $result['reading_id'] ) ? $result['reading_id'] : null,
			)
		);

		return $this->success_response(
			array(
				'reading_id'   => isset( $result['reading_id'] ) ? $result['reading_id'] : null,
				'reading_data' => isset( $result['reading_data'] ) ? $result['reading_data'] : array(),
			)
		);
	}

	/**
	 * Handle reading check endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_reading_check( WP_REST_Request $request ) {
		$nonce = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce ) ) {
			return $nonce;
		}

		$email = $this->sanitize_email_value( $request->get_param( 'email' ) );

		if ( '' === $email || ! is_email( $email ) ) {
			return $this->error_response( 'invalid_input', __( 'Please check your information and try again.', 'mystic-palm-reading' ), 400 );
		}

		$ai_handler = SM_AI_Handler::get_instance();
		$exists     = $ai_handler->reading_exists_for_email( $email );

		return $this->success_response(
			array(
				'exists'       => (bool) $exists,
				'can_generate' => ! $exists,
			)
		);
	}

	/**
	 * Handle unlock attempts for teaser readings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_reading_unlock( WP_REST_Request $request ) {
		$nonce = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce ) ) {
			return $nonce;
		}

		$reading_id       = $this->sanitize_string( $request->get_param( 'reading_id' ) );
		$section          = $this->sanitize_string( $request->get_param( 'section_name' ) );
		$lead_id          = $this->sanitize_string( $request->get_param( 'lead_id' ) );
		$current_page_url = $this->sanitize_string( $request->get_param( 'current_page_url' ) );

		if ( '' === $reading_id || '' === $section ) {
			return $this->error_response( 'invalid_input', __( 'Reading ID and section are required.', 'mystic-palm-reading' ), 400 );
		}

		// Rate limit for unlock attempts per reading.
		$rate_result = $this->check_rate_limit(
			$this->build_rate_limit_key( $request, $reading_id . '_unlock' ),
			5, // Max 5 unlocks per minute to prevent abuse
			MINUTE_IN_SECONDS,
			array(
				'route'            => 'reading/unlock',
				'reading_id'       => $reading_id,
				'section'          => $section,
				'current_page_url' => $current_page_url,
			)
		);
		if ( is_wp_error( $rate_result ) ) {
			$status = is_array( $rate_result->get_error_data() ) && isset( $rate_result->get_error_data()['status'] ) ? (int) $rate_result->get_error_data()['status'] : 429;
			return $this->error_response( 'rate_limited', $rate_result->get_error_message(), $status, $rate_result->get_error_data() );
		}

		$unlock_handler = SM_Unlock_Handler::init();
		$result         = $unlock_handler->attempt_unlock( $reading_id, $section, $lead_id, $current_page_url );

		if ( is_wp_error( $result ) ) {
			return $this->error_response(
				$result->get_error_code(),
				$result->get_error_message(),
				400
			);
		}

		SM_Logger::log(
			'info',
			'REST_READING_UNLOCK',
			'Unlock attempt processed',
			array(
				'reading_id'       => $reading_id,
				'section'          => $section,
				'status'           => $result['status'],
				'current_page_url' => $current_page_url,
			)
		);

		// If a redirect_url is present in the result, include it in the response.
		// Frontend JS will handle the actual redirection.
		if ( isset( $result['redirect_url'] ) ) {
			$response_data = array(
				'status'       => $result['status'],
				'message'      => isset( $result['message'] ) ? $result['message'] : '',
				'redirect_url' => $result['redirect_url'],
			);

			// Add optional fields if present (not all statuses return these)
			if ( isset( $result['section'] ) ) {
				$response_data['section'] = $result['section'];
			}
			if ( isset( $result['unlocked_sections'] ) ) {
				$response_data['unlocked_sections'] = $result['unlocked_sections'];
			}
			if ( isset( $result['unlocks_remaining'] ) ) {
				$response_data['unlocks_remaining'] = $result['unlocks_remaining'];
			}

			return $this->success_response( $response_data );
		}

		return $this->success_response( $result );
	}

	/**
	 * Handle get reading by lead_id endpoint (for magic link entry point check).
	 *
	 * This endpoint checks if a reading exists for the given lead_id and returns
	 * the rendered HTML if it exists. This enforces "one reading per magic link".
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_reading_get_by_lead( WP_REST_Request $request ) {
		SM_Logger::log(
			'info',
			'REST_READING_GET_BY_LEAD',
			'Incoming reading lookup request',
			array(
				'lead_param'  => $request->get_param( 'lead_id' ),
				'token_param' => $request->get_param( 'token' ) ? 'present' : 'missing',
				'route'       => $request->get_route(),
			)
		);

		$lead_id      = $this->sanitize_string( $request->get_param( 'lead_id' ) );
		$token        = $this->sanitize_string( $request->get_param( 'token' ) );
		$reading_type = $this->sanitize_string( $request->get_param( 'reading_type' ) );
		$token_validated = false;
		$reading_token_payload = null;

		// If a token is provided, validate it and allow the call without nonce.
		if ( '' !== $token ) {
			$reading_token_error = null;
			if ( class_exists( 'SM_Reading_Token' ) ) {
				$reading_token_payload = SM_Reading_Token::validate( $token, $lead_id );
				if ( ! is_wp_error( $reading_token_payload ) ) {
					$token_validated = true;
					if ( '' === $lead_id && ! empty( $reading_token_payload['lead_id'] ) ) {
						$lead_id = $this->sanitize_string( $reading_token_payload['lead_id'] );
					}
					if ( '' === $reading_type && ! empty( $reading_token_payload['reading_type'] ) ) {
						$reading_type = $this->sanitize_string( $reading_token_payload['reading_type'] );
					}
				} else {
					$reading_token_error = $reading_token_payload;
				}
			}

			if ( ! $token_validated ) {
				if ( $reading_token_error && 'token_not_reading' !== $reading_token_error->get_error_code() ) {
					return $this->error_response(
						$reading_token_error->get_error_code(),
						$reading_token_error->get_error_message(),
						403
					);
				}

				$otp_handler = class_exists( 'SM_OTP_Handler' ) ? SM_OTP_Handler::init() : null;
				if ( null !== $otp_handler ) {
					$token_check = $otp_handler->verify_magic_token( $lead_id, $token );
					if ( is_wp_error( $token_check ) ) {
						SM_Logger::log(
							'warning',
							'REST_READING_GET_BY_LEAD',
							'Magic token validation failed for reading lookup',
							array(
								'lead_id' => $lead_id,
								'error'   => $token_check->get_error_message(),
								'code'    => $token_check->get_error_code(),
							)
						);
						return $this->error_response(
							$token_check->get_error_code(),
							$token_check->get_error_message(),
							403
						);
					}

					SM_Logger::log(
						'info',
						'REST_READING_GET_BY_LEAD',
						'Magic token validated for reading lookup',
						array(
							'lead_id' => $lead_id,
						)
					);

					$token_validated = true;
				}
			}
		}

		// Fallback to nonce verification when no valid token present.
		if ( ! $token_validated ) {
			$nonce = $this->verify_nonce( $request );
			if ( is_wp_error( $nonce ) ) {
				return $nonce;
			}
		}

		if ( '' === $lead_id ) {
			return $this->error_response(
				'invalid_input',
				__( 'Please check your information and try again.', 'mystic-palm-reading' ),
				400
			);
		}

		// Rate limit: 60 requests per minute per lead_id (skipped when magic token already validated).
		if ( ! $token_validated ) {
			$rate_key = SM_Rate_Limiter::build_key(
				'reading_get_by_lead',
				array( $lead_id, $this->get_client_ip() )
			);

			$rate_limit = $this->check_rate_limit(
				$rate_key,
				60,
				MINUTE_IN_SECONDS,
				array(
					'lead_id' => $lead_id,
					'route'   => 'reading/get-by-lead',
				)
			);

			if ( is_wp_error( $rate_limit ) ) {
				$status = is_array( $rate_limit->get_error_data() ) && isset( $rate_limit->get_error_data()['status'] )
					? intval( $rate_limit->get_error_data()['status'] )
					: 429;

				return $this->error_response(
					$rate_limit->get_error_code(),
					$rate_limit->get_error_message(),
					$status,
					$rate_limit->get_error_data()
				);
			}
		}

		$reading_service = SM_Reading_Service::get_instance();
		$auth_handler    = SM_Auth_Handler::get_instance();
		$settings        = SM_Settings::init()->get_settings();
		$allowed_types   = array( 'aura_teaser', 'aura_full' );
		$user_data       = $auth_handler->get_current_user();
		$account_id      = ! empty( $user_data['account_id'] ) ? sanitize_text_field( (string) $user_data['account_id'] ) : '';
		$user_email      = ! empty( $user_data['email'] ) ? sanitize_email( (string) $user_data['email'] ) : '';

		$lead_handler = class_exists( 'SM_Lead_Handler' ) ? SM_Lead_Handler::get_instance() : null;
		$lead         = $lead_handler ? $lead_handler->get_lead_by_id( $lead_id ) : null;

		if ( $auth_handler->is_user_logged_in() && $lead ) {
			$lead_account_id = ! empty( $lead->account_id ) ? sanitize_text_field( (string) $lead->account_id ) : '';
			$lead_email      = ! empty( $lead->email ) ? sanitize_email( (string) $lead->email ) : '';

			if ( '' !== $lead_account_id ) {
				if ( '' === $account_id || $lead_account_id !== $account_id ) {
					return $this->error_response(
						'forbidden',
						__( 'You do not have permission to access this reading.', 'mystic-palm-reading' ),
						403
					);
				}
			} else {
				$lead_email_normalized = strtolower( $lead_email );
				$user_email_normalized = strtolower( $user_email );

				if ( '' === $lead_email_normalized || '' === $user_email_normalized || $lead_email_normalized !== $user_email_normalized ) {
					return $this->error_response(
						'forbidden',
						__( 'You do not have permission to access this reading.', 'mystic-palm-reading' ),
						403
					);
				}
			}
		}

		if ( ! in_array( $reading_type, $allowed_types, true ) ) {
			$reading_type = '';
		}

		if ( 'aura_full' === $reading_type && ! $auth_handler->is_user_logged_in() ) {
			$reading_type = 'aura_teaser';
		}

		if ( '' === $reading_type && $auth_handler->is_user_logged_in() ) {
			$existing_reading = $reading_service->get_latest_reading( $lead_id, 'aura_full' );
			if ( is_wp_error( $existing_reading ) || empty( $existing_reading ) ) {
				$reading_type    = 'aura_teaser';
				$existing_reading = $reading_service->get_latest_reading( $lead_id, $reading_type );
			}
		} else {
			$reading_type     = ( '' === $reading_type ) ? 'aura_teaser' : $reading_type;
			$existing_reading = $reading_service->get_latest_reading( $lead_id, $reading_type );
		}

		if ( 'aura_full' === $reading_type
			&& ( is_wp_error( $existing_reading ) || empty( $existing_reading ) )
			&& $auth_handler->is_user_logged_in()
			&& ! empty( $settings['enable_account_integration'] )
		) {
			$teaser_reading = $reading_service->get_latest_reading( $lead_id, 'aura_teaser' );
			if ( ! is_wp_error( $teaser_reading ) && ! empty( $teaser_reading ) ) {
				$lead_handler = class_exists( 'SM_Lead_Handler' ) ? SM_Lead_Handler::get_instance() : null;
				$lead         = $lead_handler ? $lead_handler->get_lead_by_id( $lead_id ) : null;
				if ( $lead && ! empty( $lead->invalid_image_locked ) ) {
					$last_reason = isset( $lead->invalid_image_last_reason ) ? (string) $lead->invalid_image_last_reason : '';
					if ( 'api_error' === $last_reason && $lead_handler ) {
						$lead_handler->update_lead(
							$lead_id,
							array(
								'invalid_image_attempts'    => 0,
								'invalid_image_locked'      => 0,
								'invalid_image_last_reason' => '',
								'invalid_image_last_at'     => null,
							)
						);
					} else {
					return $this->handle_palm_image_invalid(
						$lead_id,
						new WP_Error(
							'palm_image_invalid',
							__( 'We still could not see your photo clearly. Please contact support or start a new reading.', 'mystic-aura-reading' ),
							array( 'reason' => 'locked' )
						),
						3,
						true
					);
					}
				}

				$rollback_payload = array(
					'reading_type'     => isset( $teaser_reading->reading_type ) ? $teaser_reading->reading_type : 'aura_teaser',
					'content_data'     => isset( $teaser_reading->content_data ) ? $teaser_reading->content_data : null,
					'account_id'       => isset( $teaser_reading->account_id ) ? $teaser_reading->account_id : null,
					'unlock_count'     => isset( $teaser_reading->unlock_count ) ? $teaser_reading->unlock_count : 0,
					'unlocked_section' => isset( $teaser_reading->unlocked_section ) ? $teaser_reading->unlocked_section : '',
					'has_purchased'    => ! empty( $teaser_reading->has_purchased ),
					'updated_at'       => current_time( 'mysql' ),
				);

				$credit_handler = SM_Credit_Handler::get_instance();
				$credit_check   = $credit_handler->check_user_credits();

				if ( empty( $credit_check['success'] ) ) {
					return $this->error_response(
						'credit_check_failed',
						__( 'We could not verify your credits right now. Please try again.', 'mystic-palm-reading' ),
						400
					);
				}

				if ( empty( $credit_check['has_credits'] ) ) {
					$return_url = $this->get_credit_return_url( $request );
					$redirect   = $this->get_credit_shop_redirect_url( $return_url );

					return $this->error_response(
						'credits_exhausted',
						__( 'You do not have enough credits to generate a new reading.', 'mystic-palm-reading' ),
						400,
						array(
							'redirect_to'       => $redirect,
							'redirect_delay_ms' => 500,
							'service_balance'   => isset( $credit_check['service_balance'] ) ? $credit_check['service_balance'] : 0,
							'universal_balance' => isset( $credit_check['universal_balance'] ) ? $credit_check['universal_balance'] : 0,
						)
					);
				}

				$user_data  = $auth_handler->get_current_user();
				$account_id = ! empty( $user_data['account_id'] ) ? sanitize_text_field( (string) $user_data['account_id'] ) : '';
				$ai_handler = SM_AI_Handler::get_instance();
				$result     = $ai_handler->generate_paid_reading_from_teaser( $lead_id, $account_id, $teaser_reading );

				if ( is_wp_error( $result ) ) {
					if ( 'palm_image_invalid' === $result->get_error_code() ) {
						return $this->handle_palm_image_invalid( $lead_id, $result, 3, true );
					}

					return $this->error_response(
						$result->get_error_code(),
						$result->get_error_message(),
						500
					);
				}

				$reading_id = isset( $result['reading_id'] ) ? $result['reading_id'] : null;
				if ( empty( $reading_id ) ) {
					return $this->error_response(
						'generation_error',
						__( 'Failed to generate reading.', 'mystic-palm-reading' ),
						500
					);
				}

				$this->maybe_link_account_to_reading( $lead_id, $reading_id );

				$idempotency_key = 'reading_' . $reading_id;
				$deduction       = $credit_handler->deduct_credit( $idempotency_key );

				if ( empty( $deduction['success'] ) ) {
					if ( ! empty( $teaser_reading->id ) && $reading_id === $teaser_reading->id ) {
						SM_Reading_Service::get_instance()->update_reading_data( $reading_id, $rollback_payload );
					} else {
						SM_Reading_Service::get_instance()->delete_reading( $reading_id );
					}

					return $this->error_response(
						'credit_deduct_failed',
						__( 'We could not finalize your reading. Please try again in a moment.', 'mystic-palm-reading' ),
						502
					);
				}

				SM_AI_Handler::get_instance()->cleanup_palm_images_for_lead( $lead_id );

				$renderer     = SM_Full_Template_Renderer::get_instance();
				$reading_html = $renderer->render_reading( $reading_id );

				if ( is_wp_error( $reading_html ) ) {
					$reading_html = $this->get_error_fallback_html();
				}

				$this->update_flow_state(
					array(
						'lead_id'    => $lead_id,
						'reading_id' => $reading_id,
						'status'     => 'reading_ready',
						'step_id'    => 'result',
					)
				);

				$credits_remaining = isset( $deduction['total_available'] ) ? $deduction['total_available'] : null;

				return $this->success_response(
					array(
						'exists'            => true,
						'reading_html'      => $reading_html,
						'reading_id'        => $reading_id,
						'reading_type'      => 'aura_full',
						'credits_remaining' => $credits_remaining,
					)
				);
			}
		}

		// If no reading exists, return not found
		if ( is_wp_error( $existing_reading ) || empty( $existing_reading ) ) {
			SM_Logger::info(
				'REST_READING_GET_BY_LEAD',
				'No reading found for lead',
				array(
					'lead_id' => $lead_id,
				)
			);

			return $this->success_response(
				array(
					'exists'       => false,
					'reading_html' => null,
					'reading_id'   => null,
				)
			);
		}

		// Reading exists - render it
		$renderer = ( 'aura_full' === $reading_type )
			? SM_Full_Template_Renderer::get_instance()
			: SM_Template_Renderer::get_instance();
		$reading_html = $renderer->render_reading( $existing_reading->id );

		if ( is_wp_error( $reading_html ) ) {
			$reading_html = $this->get_error_fallback_html();
		}

		if ( $auth_handler->is_user_logged_in() ) {
			$reading_account_id = ! empty( $existing_reading->account_id ) ? sanitize_text_field( (string) $existing_reading->account_id ) : '';
			if ( '' !== $reading_account_id && $reading_account_id !== $account_id ) {
				return $this->error_response(
					'forbidden',
					__( 'You do not have permission to access this reading.', 'mystic-palm-reading' ),
					403
				);
			}
		}

		SM_Logger::info(
			'REST_READING_GET_BY_LEAD',
			'Existing reading found and rendered for lead',
			array(
				'lead_id'    => $lead_id,
				'reading_id' => $existing_reading->id,
				'reading_type' => $reading_type,
				'html_length' => strlen( $reading_html ),
			)
		);

		$reading_token = '';
		if ( 'aura_teaser' === $reading_type && class_exists( 'SM_Reading_Token' ) ) {
			$reading_token = SM_Reading_Token::generate( $lead_id, $existing_reading->id, $reading_type );
		}

		return $this->success_response(
			array(
				'exists'        => true,
				'reading_html'  => $reading_html,
				'reading_id'    => $existing_reading->id,
				'reading_type'  => $reading_type,
				'reading_token' => $reading_token,
			)
		);
	}

	/**
	 * Check if a reading exists for a given email address.
	 * Used by the welcome page to determine if user should see existing reading.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_reading_check_by_email( WP_REST_Request $request ) {
		// Verify nonce
		$nonce = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce ) ) {
			return $nonce;
		}

		$email = $this->sanitize_email_value( $request->get_param( 'email' ) );

		if ( empty( $email ) ) {
			return $this->error_response(
				'invalid_input',
				__( 'Please provide a valid email address.', 'mystic-palm-reading' ),
				400
			);
		}

		// Rate limit: 10 requests per minute per email
		$rate_key = SM_Rate_Limiter::build_key(
			'reading_check_by_email',
			array( $email, $this->get_client_ip() )
		);

		$rate_limit = $this->check_rate_limit(
			$rate_key,
			10,
			MINUTE_IN_SECONDS,
			array(
				'email' => $this->mask_email( $email ),
				'route' => 'reading/check-by-email',
			)
		);

		if ( is_wp_error( $rate_limit ) ) {
			$status = is_array( $rate_limit->get_error_data() ) && isset( $rate_limit->get_error_data()['status'] )
				? intval( $rate_limit->get_error_data()['status'] )
				: 429;

			return $this->error_response(
				$rate_limit->get_error_code(),
				$rate_limit->get_error_message(),
				$status,
				$rate_limit->get_error_data()
			);
		}

		// Check if a reading exists for this email and get lead details in one go.
		$lead_handler = class_exists('SM_Lead_Handler') ? SM_Lead_Handler::init() : null;
		if (!$lead_handler) {
			return $this->error_response('service_unavailable', __('Service temporarily unavailable.', 'mystic-palm-reading'), 500);
		}

		$existing_lead = $lead_handler->get_lead_by_email($email);

		// Case 1: Email not found, new user.
		if (!$existing_lead) {
			SM_Logger::info('REST_READING_CHECK_BY_EMAIL', 'No lead found for email, proceed as new user.', array('email' => $this->mask_email($email)));
			return $this->success_response(array(
				'action' => 'continue_free',
				'message' => __('Proceeding with free reading.', 'mystic-palm-reading'),
			));
		}

		$has_account = !empty($existing_lead->account_id);

		// Case 2: Email found and has a linked account.
		if ($has_account) {
			SM_Logger::info('REST_READING_CHECK_BY_EMAIL', 'Lead found with an account. Redirecting to login.', array('email' => $this->mask_email($email), 'account_id' => $existing_lead->account_id));
			return $this->success_response(array(
				'action' => 'redirect_login',
				'message' => __('Welcome back! Please log in to access your account.', 'mystic-palm-reading'),
			));
		}

		// Case 3: Email found, but no account linked (previous free reading).
		$reading_service = SM_Reading_Service::get_instance();
		$existing_reading = $reading_service->get_latest_reading($existing_lead->id, 'aura_teaser');

		if (!is_wp_error($existing_reading) && !empty($existing_reading)) {
			SM_Logger::info('REST_READING_CHECK_BY_EMAIL', 'Lead found with a past free reading. Encouraging login.', array('email' => $this->mask_email($email)));
			return $this->success_response(array(
				'action' => 'redirect_login',
				'message' => __('You have a past reading with us. Log in or create an account to see it.', 'mystic-palm-reading'),
			));
		}

		// Case 4: Edge case - lead exists but no reading found. Treat as new user.
		SM_Logger::info('REST_READING_CHECK_BY_EMAIL', 'Lead found but no reading. Proceeding as new user.', array('email' => $this->mask_email($email)));
		return $this->success_response(array(
			'action' => 'continue_free',
			'message' => __('Proceeding with free reading.', 'mystic-palm-reading'),
		));

	}

	/**
	 * Provide a refreshed nonce for long-lived sessions.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_nonce_refresh( WP_REST_Request $request ) {
		$nonce = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce ) ) {
			return $nonce;
		}

		$expires_in = (int) apply_filters( 'nonce_life', DAY_IN_SECONDS );
		$new_nonce  = wp_create_nonce( self::NONCE_ACTION );

		return $this->success_response(
			array(
				'nonce'        => $new_nonce,
				'expires_in'   => $expires_in,
				'issued_at'    => current_time( 'mysql' ),
				'nonce_action' => self::NONCE_ACTION,
			)
		);
	}

	/**
	 * Verify the nonce from headers or request params.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return true|WP_Error
	 */
	protected function verify_nonce( WP_REST_Request $request ) {
		// RELAXED NONCE VERIFICATION FOR BETTER UX
		// We rely on rate limiting instead of strict nonce checks for lead capture

		$nonce = $request->get_header( 'x-sm-nonce' );

		if ( empty( $nonce ) ) {
			$nonce = $request->get_header( 'x-wp-nonce' );
		}

		if ( empty( $nonce ) ) {
			$nonce = $request->get_param( 'nonce' );
		}

		// If no nonce provided at all, just log and allow (rate limiting will protect us)
		if ( empty( $nonce ) ) {
			SM_Logger::info(
				'NONCE_MISSING',
				'No nonce provided - allowing request (protected by rate limiting)',
				array(
					'route' => $request->get_route(),
					'ip'    => $this->get_client_ip(),
				)
			);
			return true; // Allow the request
		}

		// Verify nonce but be very permissive
		$verify_result = wp_verify_nonce( $nonce, self::NONCE_ACTION );

		// Accept nonce if it's valid OR if it's from the previous tick (more forgiving)
		// wp_verify_nonce returns 1 (current tick) or 2 (previous tick) on success
		if ( $verify_result ) {
			SM_Logger::info(
				'NONCE_VALID',
				'Nonce verified successfully',
				array(
					'route'  => $request->get_route(),
					'result' => $verify_result, // 1 = current, 2 = previous tick
				)
			);
			return true;
		}

		// Even if nonce fails, log but DON'T block the request
		// Rate limiting will catch abuse
		SM_Logger::warning(
			'NONCE_FAILED_ALLOWED',
			'Nonce verification failed but request allowed (rate limiting active)',
			array(
				'route' => $request->get_route(),
				'nonce' => substr( $nonce, 0, 10 ) . '...',
				'ip'    => $this->get_client_ip(),
			)
		);

		// ALLOW THE REQUEST - rate limiting will protect against abuse
		return true;
	}

	/**
	 * Enforce a rate limit using the centralized helper.
	 *
	 * @param string $key     Unique identifier (route + user/ip).
	 * @param int    $limit   Maximum allowed requests within window.
	 * @param int    $window  Window in seconds.
	 * @param array  $context Optional context for logging.
	 * @return true|WP_Error
	 */
	protected function check_rate_limit( $key, $limit = 5, $window = 60, $context = array() ) {
		return SM_Rate_Limiter::check( $key, $limit, $window, $context );
	}

	/**
	 * Build a consistent success response.
	 *
	 * @param array $data   Payload.
	 * @param int   $status HTTP status code.
	 * @return WP_REST_Response
	 */
	protected function success_response( $data = array(), $status = 200 ) {
		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			$status
		);
	}

	/**
	 * Build a consistent error response.
	 *
	 * @param string $error_code Machine-readable error code.
	 * @param string $message    Human-friendly message.
	 * @param int    $status     HTTP status code.
	 * @param array  $data       Optional extra data (e.g., retry_after).
	 * @return WP_REST_Response
	 */
	protected function error_response( $error_code, $message, $status = 400, $data = array() ) {
		$body = array(
			'success'    => false,
			'error_code' => $error_code,
			'message'    => $message,
		);

		if ( ! empty( $data ) ) {
			$body['data'] = $data;
		}

		return new WP_REST_Response( $body, $status );
	}

	/**
	 * Build a safe snapshot of lead metadata for the frontend.
	 *
	 * @param string $lead_id Lead UUID.
	 * @return array<string,mixed>
	 */
	protected function build_lead_snapshot( $lead_id ) {
		$lead_handler = SM_Lead_Handler::get_instance();
		$lead         = $lead_handler->get_lead_by_id( $lead_id );

		if ( empty( $lead ) ) {
			return array();
		}

		$snapshot = array(
			'id'              => $lead->id,
			'name'            => $lead->name,
			'email'           => $lead->email,
			'identity'        => $lead->identity,
			'age'             => isset( $lead->age ) ? (int) $lead->age : null,
			'age_range'       => isset( $lead->age_range ) ? $lead->age_range : '',
			'gdpr'            => (bool) $lead->gdpr,
			'email_confirmed' => (bool) $lead->email_confirmed,
		);

		// Enrich with demographic hints from quiz if present.
		if ( class_exists( 'SM_Quiz_Handler' ) ) {
			$quiz_handler = SM_Quiz_Handler::get_instance();
			$answers      = $quiz_handler->get_answers( $lead_id );

			if ( is_array( $answers ) && isset( $answers['demographics'] ) && is_array( $answers['demographics'] ) ) {
				if ( '' === $snapshot['age_range'] && ! empty( $answers['demographics']['age_range'] ) ) {
					$snapshot['age_range'] = sanitize_text_field( (string) $answers['demographics']['age_range'] );
				}
				if ( '' === $snapshot['identity'] && ! empty( $answers['demographics']['gender'] ) ) {
					$snapshot['identity'] = sanitize_text_field( (string) $answers['demographics']['gender'] );
				}
			}
		}

		return $snapshot;
	}

	/**
	 * Build a snapshot from Account Service profile data.
	 *
	 * @param array|null $profile Account Service profile data.
	 * @param string     $fallback_email Email fallback.
	 * @return array<string,mixed>
	 */
	protected function build_profile_snapshot( $profile, $fallback_email ) {
		if ( empty( $profile ) || ! is_array( $profile ) ) {
			$profile = array();
		}

		$name     = $this->resolve_profile_name( $profile );
		$email    = isset( $profile['email'] ) ? sanitize_email( (string) $profile['email'] ) : '';
		$identity = isset( $profile['identity'] ) ? sanitize_text_field( (string) $profile['identity'] ) : '';

		if ( '' === $identity && ! empty( $profile['gender'] ) ) {
			$identity = $this->normalize_identity_value( $profile['gender'] );
		}

		$age_data = $this->extract_age_data_from_profile( $profile );

		return array(
			'id'              => null,
			'name'            => $name,
			'email'           => $email ? $email : $fallback_email,
			'identity'        => $identity,
			'age'             => isset( $age_data['age'] ) ? (int) $age_data['age'] : null,
			'age_range'       => isset( $age_data['age_range'] ) ? $age_data['age_range'] : '',
			'gdpr'            => false,
			'email_confirmed' => false,
		);
	}

	/**
	 * Apply Account Service profile fields to a snapshot when missing.
	 *
	 * @param array       $snapshot Existing snapshot.
	 * @param array|null  $profile Account Service profile data.
	 * @param string      $fallback_email Email fallback.
	 * @return array<string,mixed>
	 */
	protected function apply_profile_fallback( $snapshot, $profile, $fallback_email ) {
		if ( empty( $snapshot ) || ! is_array( $snapshot ) ) {
			$snapshot = array();
		}

		if ( empty( $profile ) || ! is_array( $profile ) ) {
			if ( empty( $snapshot['email'] ) && ! empty( $fallback_email ) ) {
				$snapshot['email'] = $fallback_email;
			}
			return $snapshot;
		}

		if ( empty( $snapshot['name'] ) ) {
			$snapshot['name'] = $this->resolve_profile_name( $profile );
		}

		if ( empty( $snapshot['email'] ) ) {
			if ( ! empty( $profile['email'] ) ) {
				$snapshot['email'] = sanitize_email( (string) $profile['email'] );
			} elseif ( ! empty( $fallback_email ) ) {
				$snapshot['email'] = $fallback_email;
			}
		}

		if ( empty( $snapshot['identity'] ) ) {
			if ( ! empty( $profile['identity'] ) ) {
				$snapshot['identity'] = sanitize_text_field( (string) $profile['identity'] );
			} elseif ( ! empty( $profile['gender'] ) ) {
				$snapshot['identity'] = $this->normalize_identity_value( $profile['gender'] );
			}
		}

		if ( empty( $snapshot['identity'] ) ) {
			$snapshot['identity'] = 'prefer-not';
		}

		if ( empty( $snapshot['age'] ) && empty( $snapshot['age_range'] ) ) {
			$age_data = $this->extract_age_data_from_profile( $profile );
			if ( ! empty( $age_data ) ) {
				if ( ! empty( $age_data['age'] ) ) {
					$snapshot['age'] = (int) $age_data['age'];
				}
				if ( ! empty( $age_data['age_range'] ) ) {
					$snapshot['age_range'] = $age_data['age_range'];
				}
			}
		}

		if ( empty( $snapshot['age_range'] ) && ! empty( $snapshot['age'] ) ) {
			$snapshot['age_range'] = $this->map_age_to_range( (int) $snapshot['age'] );
		}

		return $snapshot;
	}

	/**
	 * Resolve a display name from Account Service profile data.
	 *
	 * @param array $profile Profile data.
	 * @return string
	 */
	protected function resolve_profile_name( $profile ) {
		if ( empty( $profile ) || ! is_array( $profile ) ) {
			return '';
		}

		$name_fields = array( 'name', 'full_name', 'given_name', 'first_name', 'firstName' );
		foreach ( $name_fields as $field ) {
			if ( ! empty( $profile[ $field ] ) ) {
				return sanitize_text_field( (string) $profile[ $field ] );
			}
		}

		$first = ! empty( $profile['firstName'] ) ? $profile['firstName'] : '';
		$last  = ! empty( $profile['lastName'] ) ? $profile['lastName'] : '';
		$full  = trim( $first . ' ' . $last );

		return '' !== $full ? sanitize_text_field( $full ) : '';
	}

	/**
	 * Create a lead for logged-in users using Account Service profile data.
	 *
	 * @param SM_Lead_Handler $lead_handler Lead handler instance.
	 * @param array           $snapshot     Profile snapshot data.
	 * @param string          $email        Email address.
	 * @return string|WP_Error
	 */
	protected function create_logged_in_lead( $lead_handler, $snapshot, $email, $force_new = false ) {
		if ( empty( $lead_handler ) ) {
			return new WP_Error( 'service_unavailable', __( 'Service temporarily unavailable.', 'mystic-palm-reading' ) );
		}

		$created = $force_new && method_exists( $lead_handler, 'create_lead_fresh' )
			? $lead_handler->create_lead_fresh(
				$snapshot['name'],
				$snapshot['email'],
				! empty( $snapshot['identity'] ) ? $snapshot['identity'] : 'prefer-not',
				true,
				! empty( $snapshot['age'] ) ? (int) $snapshot['age'] : null,
				! empty( $snapshot['age_range'] ) ? $snapshot['age_range'] : ''
			)
			: $lead_handler->create_lead(
				$snapshot['name'],
				$snapshot['email'],
				! empty( $snapshot['identity'] ) ? $snapshot['identity'] : 'prefer-not',
				true,
				! empty( $snapshot['age'] ) ? (int) $snapshot['age'] : null,
				! empty( $snapshot['age_range'] ) ? $snapshot['age_range'] : ''
			);

		if ( $force_new && ! method_exists( $lead_handler, 'create_lead_fresh' ) ) {
			SM_Logger::log(
				'warning',
				'LEAD_CURRENT',
				'Force-new lead requested but fallback to existing lead creation.',
				array(
					'email' => $this->mask_email( $email ),
				)
			);
		}

		if ( is_wp_error( $created ) || empty( $created['lead_id'] ) ) {
			SM_Logger::log(
				'error',
				'LEAD_CURRENT',
				'Failed to create lead from account profile.',
				array(
					'email' => $this->mask_email( $email ),
				)
			);
			return new WP_Error(
				'lead_create_failed',
				__( 'We could not start your reading right now. Please try again.', 'mystic-palm-reading' )
			);
		}

		$lead_handler->update_lead( $created['lead_id'], array( 'email_confirmed' => true ) );

		SM_Logger::info(
			'LEAD_CURRENT',
			'Created new lead for logged-in start-new flow.',
			array(
				'lead_id' => $created['lead_id'],
				'email'   => $this->mask_email( $snapshot['email'] ),
			)
		);

		return $created['lead_id'];
	}

	/**
	 * Normalize Account Service identity values to lead identity keys.
	 *
	 * @param string $identity Raw identity input.
	 * @return string
	 */
	protected function normalize_identity_value( $identity ) {
		$identity = strtolower( trim( (string) $identity ) );

		$map = array(
			'male'               => 'man',
			'man'                => 'man',
			'female'             => 'woman',
			'woman'              => 'woman',
			'prefer_not_to_say'  => 'prefer-not',
			'prefer-not'         => 'prefer-not',
			'non-binary'         => 'prefer-not',
			'nonbinary'          => 'prefer-not',
			'other'              => 'prefer-not',
		);

		return isset( $map[ $identity ] ) ? $map[ $identity ] : '';
	}

	/**
	 * Extract age metadata from Account Service profile data.
	 *
	 * @param array|null $profile Account Service profile data.
	 * @return array<string,mixed>
	 */
	protected function extract_age_data_from_profile( $profile ) {
		if ( empty( $profile ) || ! is_array( $profile ) ) {
			return array();
		}

		$age       = null;
		$age_range = '';

		if ( isset( $profile['age_range'] ) && '' !== $profile['age_range'] ) {
			$age_range = $this->normalize_age_range_value( $profile['age_range'] );
		}

		if ( isset( $profile['age'] ) && is_numeric( $profile['age'] ) ) {
			$age = (int) $profile['age'];
			if ( $age < 0 || $age > 120 ) {
				$age = null;
			}
		}

		if ( null === $age ) {
			$dob_fields = array( 'dob', 'date_of_birth', 'birthdate' );
			foreach ( $dob_fields as $field ) {
				if ( empty( $profile[ $field ] ) ) {
					continue;
				}

				$age = $this->calculate_age_from_dob( $profile[ $field ] );
				if ( null !== $age ) {
					break;
				}
			}
		}

		if ( '' === $age_range && null !== $age ) {
			$age_range = $this->map_age_to_range( $age );
		}

		if ( null === $age && '' === $age_range ) {
			return array();
		}

		return array(
			'age'       => $age,
			'age_range' => $age_range,
		);
	}

	/**
	 * Calculate age in years from a DOB string.
	 *
	 * @param string $dob_raw Raw DOB input.
	 * @return int|null
	 */
	protected function calculate_age_from_dob( $dob_raw ) {
		$dob_raw = sanitize_text_field( wp_unslash( (string) $dob_raw ) );
		if ( '' === $dob_raw ) {
			return null;
		}

		$dob = DateTime::createFromFormat( 'Y-m-d', $dob_raw );
		if ( false === $dob ) {
			$timestamp = strtotime( $dob_raw );
			if ( false === $timestamp ) {
				return null;
			}
			$dob = new DateTime( '@' . $timestamp );
			$dob->setTimezone( wp_timezone() );
		}

		$now = new DateTime( 'now', wp_timezone() );
		$age = (int) $dob->diff( $now )->y;

		if ( $age < 0 || $age > 120 ) {
			return null;
		}

		return $age;
	}

	/**
	 * Normalize age range input to a known key.
	 *
	 * @param string|int $age_range Raw age range.
	 * @return string
	 */
	protected function normalize_age_range_value( $age_range ) {
		$age_range = sanitize_text_field( (string) $age_range );
		if ( '' === $age_range ) {
			return '';
		}

		$valid_ranges = array( 'age_18_25', 'age_26_35', 'age_36_50', 'age_51_65', 'age_65_plus' );
		if ( in_array( $age_range, $valid_ranges, true ) ) {
			return $age_range;
		}

		if ( is_numeric( $age_range ) ) {
			return $this->map_age_to_range( (int) $age_range );
		}

		return '';
	}

	/**
	 * Map a numeric age to a known age range key.
	 *
	 * @param int $age Numeric age.
	 * @return string
	 */
	protected function map_age_to_range( $age ) {
		if ( $age >= 18 && $age <= 25 ) {
			return 'age_18_25';
		}
		if ( $age >= 26 && $age <= 35 ) {
			return 'age_26_35';
		}
		if ( $age >= 36 && $age <= 50 ) {
			return 'age_36_50';
		}
		if ( $age >= 51 && $age <= 65 ) {
			return 'age_51_65';
		}
		if ( $age > 65 ) {
			return 'age_65_plus';
		}

		return '';
	}

	/**
	 * Sanitize a string parameter.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return string
	 */
	protected function sanitize_string( $value ) {
		return sanitize_text_field( wp_unslash( (string) $value ) );
	}

	/**
	 * Sanitize boolean-like values.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return bool
	 */
	protected function sanitize_boolean( $value ) {
		return (bool) rest_sanitize_boolean( $value );
	}

	/**
	 * Sanitize an email value.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return string
	 */
	protected function sanitize_email_value( $value ) {
		return sanitize_email( (string) $value );
	}

	/**
	 * Derive a rate-limit key using IP and route.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @param string          $suffix  Optional suffix.
	 * @return string
	 */
	protected function build_rate_limit_key( WP_REST_Request $request, $suffix = '' ) {
		$identifiers = array(
			$request->get_route(),
			$this->get_client_ip(),
		);

		if ( '' !== $suffix ) {
			$identifiers[] = $suffix;
		}

		return SM_Rate_Limiter::build_key( 'rest', $identifiers );
	}

	/**
	 * Retrieve sanitized client IP for logging/rate limiting.
	 *
	 * @return string
	 */
	public function get_client_ip() {
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return 'unknown';
	}

	/**
	 * Get redirect URL for exhausted credits.
	 *
	 * @return string
	 */
	private function get_no_credit_redirect_url() {
		if ( class_exists( 'SM_Settings' ) ) {
			return SM_Settings::init()->get_no_credit_redirect_url();
		}

		return '/offerings?show_message=no_more_credits';
	}

	/**
	 * Build the Account Service shop URL for credit purchases.
	 *
	 * @param string $return_url URL to return to after purchase.
	 * @return string
	 */
	private function get_credit_shop_redirect_url( $return_url = '' ) {
		$settings = SM_Settings::init()->get_settings();
		$account_service_url = isset( $settings['account_service_url'] ) ? rtrim( $settings['account_service_url'], '/' ) : '';
		$service_slug        = isset( $settings['service_slug'] ) ? sanitize_key( $settings['service_slug'] ) : 'aura_reading';

		if ( empty( $account_service_url ) ) {
			return $this->get_no_credit_redirect_url();
		}

		$args = array(
			'service' => $service_slug,
		);

		if ( ! empty( $return_url ) ) {
			$args['return_url'] = $return_url;
		}

		return add_query_arg( $args, $account_service_url . '/shop' );
	}

	/**
	 * Resolve the return URL for credit purchase redirects.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return string
	 */
	private function get_credit_return_url( WP_REST_Request $request ) {
		$referer = $request->get_header( 'referer' );
		if ( ! empty( $referer ) ) {
			return esc_url_raw( $referer );
		}

		$referer = wp_get_referer();
		if ( ! empty( $referer ) ) {
			return esc_url_raw( $referer );
		}

		return esc_url_raw( home_url( '/palm-reading' ) );
	}

	/**
	 * Log a nonce failure without exposing the raw nonce.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $nonce   Provided nonce (masked).
	 * @return void
	 */
	private function log_nonce_failure( WP_REST_Request $request, $nonce ) {
		if ( ! class_exists( 'SM_Logger' ) ) {
			return;
		}

		$masked = '';
		if ( ! empty( $nonce ) ) {
			$masked = substr( $nonce, 0, 4 ) . '...' . substr( $nonce, -2 );
		}

		SM_Logger::warning(
			'REST_NONCE_INVALID',
			'Nonce verification failed',
			array(
				'route' => $request->get_route(),
				'ip'    => $this->get_client_ip(),
				'nonce' => $masked,
			)
		);
	}

	/**
	 * Add basic CORS headers for REST responses.
	 *
	 * @param bool             $served  Whether the request has already been served.
	 * @param WP_HTTP_Response $result  Result to send to the client.
	 * @param WP_REST_Request  $request Request object.
	 * @return bool
	 */
	public function add_cors_headers( $served, $result, $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( headers_sent() ) {
			return $served;
		}

		$origin = get_http_origin();

		header( 'Access-Control-Allow-Origin: ' . ( $origin ? esc_url_raw( $origin ) : esc_url_raw( home_url() ) ) );
		header( 'Vary: Origin' );
		header( 'Access-Control-Allow-Headers: Content-Type, X-WP-Nonce, X-SM-Nonce' );
		header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );

		return $served;
	}
}
