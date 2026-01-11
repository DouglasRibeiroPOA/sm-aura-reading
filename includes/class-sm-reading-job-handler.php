<?php
/**
 * Reading Job Handler
 *
 * Handles async reading generation via WP-Cron and lightweight job tracking.
 *
 * @package MysticPalmReading
 * @since 1.4.6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SM_Reading_Job_Handler {

	const OPTION_PREFIX = 'sm_reading_job_';
	const CRON_HOOK     = 'sm_reading_job_process';

	/**
	 * Singleton instance.
	 *
	 * @var SM_Reading_Job_Handler|null
	 */
	private static $instance = null;

	/**
	 * Initialize the handler.
	 *
	 * @return void
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
	}

	/**
	 * Get instance.
	 *
	 * @return SM_Reading_Job_Handler
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::init();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( self::CRON_HOOK, array( $this, 'process_job' ), 10, 3 );
		add_action( 'wp_mail_failed', array( $this, 'log_mail_failure' ) );
	}

	/**
	 * Log wp_mail failures for diagnostics.
	 *
	 * @param WP_Error $error The WP_Error object.
	 * @return void
	 */
	public function log_mail_failure( $error ) {
		if ( is_wp_error( $error ) ) {
			SM_Logger::log(
				'error',
				'READING_JOB',
				'wp_mail failed',
				array(
					'error_code'    => $error->get_error_code(),
					'error_message' => $error->get_error_message(),
					'error_data'    => $error->get_error_data(),
				)
			);
		}
	}

	/**
	 * Create or return a pending job for a lead.
	 *
	 * @param string $lead_id Lead UUID.
	 * @param string $reading_type Reading type.
	 * @param string $account_id Account ID.
	 * @return array Job payload.
	 */
	public function create_job( $lead_id, $reading_type, $account_id = '', $jwt_token = '' ) {
		$job = $this->get_job( $lead_id, $reading_type );
		if ( $job && in_array( $job['status'], array( 'queued', 'running' ), true ) ) {
			return $job;
		}

		$job_id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'sm-job-', true );
		$job_token = wp_generate_password( 32, false, false );
		$jwt_token = sanitize_text_field( (string) $jwt_token );
		$job    = array(
			'job_id'       => $job_id,
			'job_token'    => $job_token,
			'lead_id'      => $lead_id,
			'reading_type' => $reading_type,
			'account_id'   => $account_id,
			'jwt_token'    => $jwt_token,
			'status'       => 'queued',
			'attempts'     => 0,
			'error_code'   => '',
			'error_message'=> '',
			'error_data'   => array(),
			'reading_id'   => '',
			'created_at'   => current_time( 'mysql' ),
			'updated_at'   => current_time( 'mysql' ),
		);

		update_option( $this->get_job_key( $lead_id, $reading_type ), $job, false );
		// Only use immediate dispatch (no WP-Cron) to avoid duplicate job execution
		$this->dispatch_job_request( $lead_id, $reading_type, $job_id, $job_token, $account_id );

		SM_Logger::log(
			'info',
			'READING_JOB',
			'Reading job queued',
			array(
				'lead_id'      => $lead_id,
				'reading_type' => $reading_type,
				'job_id'       => $job_id,
			)
		);

		return $job;
	}

	/**
	 * Get a job for lead/type.
	 *
	 * @param string $lead_id Lead UUID.
	 * @param string $reading_type Reading type.
	 * @return array|null
	 */
	public function get_job( $lead_id, $reading_type ) {
		$key = $this->get_job_key( $lead_id, $reading_type );
		$job = get_option( $key );
		return is_array( $job ) ? $job : null;
	}

	/**
	 * Update job fields.
	 *
	 * @param string $lead_id Lead UUID.
	 * @param string $reading_type Reading type.
	 * @param array  $updates Fields to update.
	 * @return array|null
	 */
	public function update_job( $lead_id, $reading_type, $updates ) {
		$job = $this->get_job( $lead_id, $reading_type );
		if ( ! is_array( $job ) ) {
			return null;
		}

		$job = array_merge( $job, $updates );
		$job['updated_at'] = current_time( 'mysql' );
		update_option( $this->get_job_key( $lead_id, $reading_type ), $job, false );

		return $job;
	}

	/**
	 * Delete a job record.
	 *
	 * @param string $lead_id Lead UUID.
	 * @param string $reading_type Reading type.
	 * @return void
	 */
	public function delete_job( $lead_id, $reading_type ) {
		delete_option( $this->get_job_key( $lead_id, $reading_type ) );
	}

	/**
	 * Schedule job processing.
	 *
	 * @param string $lead_id Lead UUID.
	 * @param string $reading_type Reading type.
	 * @param string $account_id Account ID.
	 * @return void
	 */
	private function schedule_job( $lead_id, $reading_type, $account_id ) {
		if ( wp_next_scheduled( self::CRON_HOOK, array( $lead_id, $reading_type, $account_id ) ) ) {
			return;
		}

		wp_schedule_single_event( time() + 5, self::CRON_HOOK, array( $lead_id, $reading_type, $account_id ) );
		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
	}

	/**
	 * Dispatch a non-blocking job request.
	 *
	 * @param string $lead_id Lead UUID.
	 * @param string $reading_type Reading type.
	 * @param string $job_id Job UUID.
	 * @param string $job_token Job token.
	 * @param string $account_id Account ID.
	 * @return void
	 */
	private function dispatch_job_request( $lead_id, $reading_type, $job_id, $job_token, $account_id ) {
		$url = rest_url( 'soulmirror/v1/reading/job-run' );
		$args = array(
			'blocking'  => false,
			'timeout'   => 0.01,
			'sslverify' => false,
			'body'      => array(
				'lead_id'      => $lead_id,
				'reading_type' => $reading_type,
				'job_id'       => $job_id,
				'job_token'    => $job_token,
				'account_id'   => $account_id,
			),
		);

		$response = wp_remote_post( $url, $args );

		SM_Logger::log(
			'info',
			'READING_JOB',
			'Reading job dispatch requested',
			array(
				'lead_id'      => $lead_id,
				'reading_type' => $reading_type,
				'job_id'       => $job_id,
				'response'     => is_wp_error( $response ) ? $response->get_error_message() : 'sent',
			)
		);
	}

	/**
	 * Validate a job token against stored job data.
	 *
	 * @param string $lead_id Lead UUID.
	 * @param string $reading_type Reading type.
	 * @param string $job_id Job UUID.
	 * @param string $job_token Job token.
	 * @return bool
	 */
	public function validate_job_token( $lead_id, $reading_type, $job_id, $job_token ) {
		$job = $this->get_job( $lead_id, $reading_type );
		if ( ! $job ) {
			return false;
		}
		if ( empty( $job['job_id'] ) || empty( $job['job_token'] ) ) {
			return false;
		}
		return hash_equals( (string) $job['job_id'], (string) $job_id )
			&& hash_equals( (string) $job['job_token'], (string) $job_token );
	}

	/**
	 * Process a queued job.
	 *
	 * @param string $lead_id Lead UUID.
	 * @param string $reading_type Reading type.
	 * @param string $account_id Account ID.
	 * @return void
	 */
	public function process_job( $lead_id, $reading_type, $account_id = '' ) {
		$job = $this->get_job( $lead_id, $reading_type );
		if ( ! $job || in_array( $job['status'], array( 'completed', 'failed' ), true ) ) {
			return;
		}

		// Phase 4: Check for job timeout (5 minutes max)
		if ( 'running' === $job['status'] && ! empty( $job['updated_at'] ) ) {
			$updated_time = strtotime( $job['updated_at'] );
			$current_time = current_time( 'timestamp' );
			$elapsed      = $current_time - $updated_time;

			if ( $elapsed > 300 ) { // 5 minutes = 300 seconds
				$this->update_job(
					$lead_id,
					$reading_type,
					array(
						'status'        => 'failed',
						'error_code'    => 'job_timeout',
						'error_message' => __( 'Reading generation timed out. Please try again.', 'mystic-palm-reading' ),
					)
				);
				SM_Logger::log(
					'error',
					'READING_JOB',
					'Reading job timed out (running > 5 minutes)',
					array(
						'lead_id'      => $lead_id,
						'reading_type' => $reading_type,
						'elapsed_seconds' => $elapsed,
					)
				);
				return;
			}
		}

		$job_start_time = microtime( true );
		$attempts = isset( $job['attempts'] ) ? (int) $job['attempts'] + 1 : 1;
		$this->update_job(
			$lead_id,
			$reading_type,
			array(
				'status'   => 'running',
				'attempts' => $attempts,
			)
		);

		SM_Logger::log(
			'info',
			'READING_JOB',
			'Reading job started',
			array(
				'lead_id'      => $lead_id,
				'reading_type' => $reading_type,
				'attempt'      => $attempts,
			)
		);

		$ai_handler = SM_AI_Handler::get_instance();
		if ( 'aura_full' === $reading_type ) {
			$result = $ai_handler->generate_paid_reading( $lead_id, $account_id );
		} else {
			$result = $ai_handler->generate_teaser_reading( $lead_id );
		}

		if ( is_wp_error( $result ) ) {
			$job_duration = round( microtime( true ) - $job_start_time, 2 );
			$error_code   = $result->get_error_code();
			$this->update_job(
				$lead_id,
				$reading_type,
				array(
					'status'        => 'failed',
					'error_code'    => $error_code,
					'error_message' => $result->get_error_message(),
					'error_data'    => $result->get_error_data(),
				)
			);
			SM_Logger::log(
				'error',
				'READING_JOB',
				'Reading job failed',
				array(
					'lead_id'      => $lead_id,
					'reading_type' => $reading_type,
					'error_code'   => $error_code,
					'duration_seconds' => $job_duration,
				)
			);

			// Phase 4: Send vision failure email if image was rejected
			if ( 'palm_image_invalid' === $error_code ) {
				$this->send_vision_failure_email( $lead_id, $reading_type );
			}

			return;
		}

		$reading_id = isset( $result['reading_id'] ) ? $result['reading_id'] : '';
		if ( '' === $reading_id ) {
			$job_duration = round( microtime( true ) - $job_start_time, 2 );
			$this->update_job(
				$lead_id,
				$reading_type,
				array(
					'status'        => 'failed',
					'error_code'    => 'generation_error',
					'error_message' => __( 'Failed to generate reading.', 'mystic-palm-reading' ),
				)
			);
			SM_Logger::log(
				'error',
				'READING_JOB',
				'Reading job failed (missing reading_id)',
				array(
					'lead_id'      => $lead_id,
					'reading_type' => $reading_type,
					'duration_seconds' => $job_duration,
				)
			);
			return;
		}

		if ( 'aura_full' === $reading_type ) {
			if ( ! empty( $job['jwt_token'] ) && class_exists( 'SM_Auth_Handler' ) ) {
				SM_Auth_Handler::get_instance()->ensure_session();
				if ( empty( $_SESSION[ SM_Auth_Handler::SESSION_TOKEN_KEY ] ) ) {
					$_SESSION[ SM_Auth_Handler::SESSION_TOKEN_KEY ] = $job['jwt_token'];
				}
			}

			$credit_handler = SM_Credit_Handler::get_instance();
			$idempotency_key = 'reading_' . $reading_id;
			$deduction = $credit_handler->deduct_credit( $idempotency_key );
			if ( empty( $deduction['success'] ) ) {
				$job_duration = round( microtime( true ) - $job_start_time, 2 );
				SM_Reading_Service::get_instance()->delete_reading( $reading_id );
				$this->update_job(
					$lead_id,
					$reading_type,
					array(
						'status'        => 'failed',
						'error_code'    => 'credit_deduct_failed',
						'error_message' => __( 'We could not finalize your reading. Please try again in a moment.', 'mystic-palm-reading' ),
						'error_data'    => array(
							'credit_error' => isset( $deduction['error'] ) ? $deduction['error'] : 'unknown_error',
						),
					)
				);
				SM_Logger::log(
					'error',
					'READING_JOB',
					'Reading job failed (credit deduction)',
					array(
						'lead_id'      => $lead_id,
						'reading_type' => $reading_type,
						'reading_id'   => $reading_id,
						'credit_error' => isset( $deduction['error'] ) ? $deduction['error'] : 'unknown_error',
						'duration_seconds' => $job_duration,
					)
				);
				return;
			}

			SM_AI_Handler::get_instance()->cleanup_palm_images_for_lead( $lead_id );
		}

		$job_duration = round( microtime( true ) - $job_start_time, 2 );

		$this->update_job(
			$lead_id,
			$reading_type,
			array(
				'status'     => 'completed',
				'reading_id' => $reading_id,
			)
		);

		SM_Logger::log(
			'info',
			'READING_JOB',
			'Reading job completed',
			array(
				'lead_id'      => $lead_id,
				'reading_type' => $reading_type,
				'reading_id'   => $reading_id,
				'duration_seconds' => $job_duration,
			)
		);

		$this->send_completion_email( $lead_id, $reading_type, $reading_id );
		$this->delete_job( $lead_id, $reading_type );
	}

	/**
	 * Send a completion email after a reading is generated.
	 *
	 * @param string $lead_id Lead UUID.
	 * @param string $reading_type Reading type.
	 * @param string $reading_id Reading UUID.
	 * @return void
	 */
	private function send_completion_email( $lead_id, $reading_type, $reading_id ) {
		$lead_handler = SM_Lead_Handler::get_instance();
		$lead         = $lead_handler->get_lead_by_id( $lead_id );
		if ( ! $lead || empty( $lead->email ) || empty( $lead->email_confirmed ) ) {
			return;
		}

		$email = sanitize_email( (string) $lead->email );
		if ( ! is_email( $email ) ) {
			return;
		}

		$dedupe_key = 'sm_completion_email_' . $lead_id . '_' . $reading_type;
		$recent_send = get_transient( $dedupe_key );
		if ( is_array( $recent_send ) && ! empty( $recent_send['sent_at'] ) ) {
			$sent_at = (int) $recent_send['sent_at'];
			if ( time() - $sent_at < ( 10 * MINUTE_IN_SECONDS ) ) {
				SM_Logger::log(
					'warning',
					'READING_JOB',
					'Completion email suppressed (recent send)',
					array(
						'lead_id'      => $lead_id,
						'reading_type' => $reading_type,
						'reading_id'   => $reading_id,
						'email'        => $email,
					)
				);
				return;
			}
		}

		$subject = ( 'aura_full' === $reading_type )
			? __( 'Your Full Aura Reading Is Ready', 'mystic-aura-reading' )
			: __( 'Your Aura Reading Is Ready', 'mystic-aura-reading' );

		$report_args = array(
			'sm_report'    => 1,
			'lead_id'      => $lead_id,
			'reading_type' => $reading_type,
		);

		if ( 'aura_teaser' === $reading_type && class_exists( 'SM_Reading_Token' ) ) {
			$reading_token = SM_Reading_Token::generate( $lead_id, $reading_id, $reading_type );
			if ( '' !== $reading_token ) {
				$report_args['token'] = $reading_token;
			}
		}

		$report_url = add_query_arg(
			$report_args,
			home_url( '/' )
		);

		$greeting = isset( $lead->name ) && '' !== trim( (string) $lead->name )
			? 'Hey ' . trim( (string) $lead->name ) . ','
			: __( 'Hey there,', 'mystic-palm-reading' );

		$button_text = ( 'aura_full' === $reading_type )
			? __( 'View Full Reading', 'mystic-aura-reading' )
			: __( 'View My Reading', 'mystic-aura-reading' );

		$button_style = 'display:inline-block;padding:12px 18px;background:#4f46e5;color:#ffffff;font-weight:600;text-decoration:none;border-radius:8px;';
		$text_style   = 'font-size:16px;color:#111827;line-height:1.6;margin:0 0 12px;';
		$muted_style  = 'font-size:13px;color:#6b7280;line-height:1.6;margin:0;';

		$message  = '<!DOCTYPE html><html><body style="margin:0;padding:24px;background:#f9fafb;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,\'Helvetica Neue\',Arial,sans-serif;">';
		$message .= '<div style="max-width:520px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;padding:24px;">';
		$message .= '<h1 style="font-size:20px;margin:0 0 12px;color:#111827;">' . esc_html( $subject ) . '</h1>';
		$message .= '<p style="' . $text_style . '">' . esc_html( $greeting ) . '</p>';
		$message .= '<p style="' . $text_style . '">' . esc_html__( 'Your personalized palm reading is ready to view. Click below to see what the lines on your palm reveal.', 'mystic-palm-reading' ) . '</p>';
		$message .= '<p style="text-align:left;margin:16px 0;"><a href="' . esc_url( $report_url ) . '" style="' . $button_style . '">' . esc_html( $button_text ) . '</a></p>';
		$message .= '<p style="' . $text_style . '">' . esc_html__( 'If the button does not work, copy and paste this link:', 'mystic-palm-reading' ) . '</p>';
		$message .= '<p style="' . $muted_style . ';word-break:break-all;"><a href="' . esc_url( $report_url ) . '" style="color:#4f46e5;text-decoration:underline;">' . esc_url( $report_url ) . '</a></p>';
		$message .= '<p style="' . $muted_style . ';margin-top:16px;">SoulMirror</p>';
		$message .= '</div></body></html>';

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$sent = wp_mail( $email, $subject, $message, $headers );
		if ( ! $sent ) {
			SM_Logger::log(
				'error',
				'READING_JOB',
				'Completion email failed to send',
				array(
					'lead_id'      => $lead_id,
					'reading_type' => $reading_type,
					'reading_id'   => $reading_id,
					'email'        => $email,
				)
			);
			return;
		}

		set_transient(
			$dedupe_key,
			array(
				'reading_id' => $reading_id,
				'sent_at'    => time(),
			),
			10 * MINUTE_IN_SECONDS
		);

		SM_Logger::log(
			'info',
			'READING_JOB',
			'Completion email sent',
			array(
				'lead_id'      => $lead_id,
				'reading_type' => $reading_type,
				'reading_id'   => $reading_id,
				'email'        => $email,
			)
		);
	}

	/**
	 * Send a vision failure email when palm image is rejected (Phase 4).
	 *
	 * @param string $lead_id Lead UUID.
	 * @param string $reading_type Reading type.
	 * @return void
	 */
	private function send_vision_failure_email( $lead_id, $reading_type ) {
		$lead_handler = SM_Lead_Handler::get_instance();
		$lead         = $lead_handler->get_lead_by_id( $lead_id );
		if ( ! $lead || empty( $lead->email ) || empty( $lead->email_confirmed ) ) {
			return;
		}

		$email = sanitize_email( (string) $lead->email );
		if ( ! is_email( $email ) ) {
			return;
		}

		$subject = __( 'We Need a Clearer Aura Photo', 'mystic-aura-reading' );

		$resubmit_url = add_query_arg(
			array(
				'sm_resubmit' => 1,
				'lead_id'     => $lead_id,
				'step'        => 'palmPhoto',
			),
			home_url( '/' )
		);

		$greeting = isset( $lead->name ) && '' !== trim( (string) $lead->name )
			? 'Hey ' . trim( (string) $lead->name ) . ','
			: __( 'Hey there,', 'mystic-aura-reading' );

		$button_style = 'display:inline-block;padding:12px 18px;background:#4f46e5;color:#ffffff;font-weight:600;text-decoration:none;border-radius:8px;';
		$text_style   = 'font-size:16px;color:#111827;line-height:1.6;margin:0 0 12px;';
		$muted_style  = 'font-size:13px;color:#6b7280;line-height:1.6;margin:0;';
		$list_style   = 'font-size:15px;color:#111827;line-height:1.8;margin:0 0 12px;padding-left:20px;';

		$message  = '<!DOCTYPE html><html><body style="margin:0;padding:24px;background:#f9fafb;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,\'Helvetica Neue\',Arial,sans-serif;">';
		$message .= '<div style="max-width:520px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;padding:24px;">';
		$message .= '<h1 style="font-size:20px;margin:0 0 12px;color:#111827;">' . esc_html( $subject ) . '</h1>';
		$message .= '<p style="' . $text_style . '">' . esc_html( $greeting ) . '</p>';
		$message .= '<p style="' . $text_style . '">' . esc_html__( 'We couldn\'t clearly see your shoulders-up photo.', 'mystic-aura-reading' ) . '</p>';
		$message .= '<p style="' . $text_style . '">' . esc_html__( 'To get your reading, please upload a clearer photo:', 'mystic-aura-reading' ) . '</p>';
		$message .= '<p style="text-align:left;margin:16px 0;"><a href="' . esc_url( $resubmit_url ) . '" style="' . $button_style . '">' . esc_html__( 'Upload New Photo', 'mystic-aura-reading' ) . '</a></p>';
		$message .= '<p style="' . $text_style . ';font-weight:600;">' . esc_html__( 'Tips for a good aura photo:', 'mystic-aura-reading' ) . '</p>';
		$message .= '<ul style="' . $list_style . '">';
		$message .= '<li>' . esc_html__( 'Use good lighting', 'mystic-aura-reading' ) . '</li>';
		$message .= '<li>' . esc_html__( 'Keep your shoulders and upper torso in frame', 'mystic-aura-reading' ) . '</li>';
		$message .= '<li>' . esc_html__( 'Hold still to avoid blur', 'mystic-aura-reading' ) . '</li>';
		$message .= '<li>' . esc_html__( 'Avoid heavy shadows and harsh backlight', 'mystic-aura-reading' ) . '</li>';
		$message .= '</ul>';
		$message .= '<p style="' . $text_style . '">' . esc_html__( 'We\'re excited to reveal what your aura has to say!', 'mystic-aura-reading' ) . '</p>';
		$message .= '<p style="' . $text_style . '">' . esc_html__( 'If the button does not work, copy and paste this link:', 'mystic-aura-reading' ) . '</p>';
		$message .= '<p style="' . $muted_style . ';word-break:break-all;"><a href="' . esc_url( $resubmit_url ) . '" style="color:#4f46e5;text-decoration:underline;">' . esc_url( $resubmit_url ) . '</a></p>';
		$message .= '<p style="' . $muted_style . ';margin-top:16px;">SoulMirror</p>';
		$message .= '</div></body></html>';

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$sent = wp_mail( $email, $subject, $message, $headers );
		if ( ! $sent ) {
			SM_Logger::log(
				'error',
				'READING_JOB',
				'Vision failure email failed to send',
				array(
					'lead_id'      => $lead_id,
					'reading_type' => $reading_type,
					'email'        => $email,
				)
			);
			return;
		}

		SM_Logger::log(
			'info',
			'READING_JOB',
			'Vision failure email sent',
			array(
				'lead_id'      => $lead_id,
				'reading_type' => $reading_type,
				'email'        => $email,
			)
		);
	}

	/**
	 * Build the option key for a job.
	 *
	 * @param string $lead_id Lead UUID.
	 * @param string $reading_type Reading type.
	 * @return string
	 */
	private function get_job_key( $lead_id, $reading_type ) {
		$key = strtolower( $lead_id . '_' . $reading_type );
		$key = preg_replace( '/[^a-z0-9_\\-]/', '', $key );
		return self::OPTION_PREFIX . $key;
	}
}
