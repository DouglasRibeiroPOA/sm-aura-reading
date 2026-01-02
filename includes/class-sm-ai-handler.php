<?php
/**
 * AI Handler Class
 *
 * Handles OpenAI GPT-4o Vision + Text API integration for generating
 * personalized palm readings based on palm images and quiz responses.
 *
 * @package MysticPalmReading
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SM_AI_Handler
 *
 * Manages AI-powered palm reading generation using OpenAI GPT-4o.
 */
class SM_AI_Handler {

	/**
	 * Singleton instance
	 *
	 * @var SM_AI_Handler|null
	 */
	private static $instance = null;

	/**
	 * OpenAI API endpoint
	 *
	 * @var string
	 */
	private $api_endpoint = 'https://api.openai.com/v1/chat/completions';

	/**
	 * OpenAI model to use
	 *
	 * @var string
	 */
	private $model = 'gpt-4o';

	/**
	 * Maximum tokens for response
	 *
	 * @var int
	 */
	private $max_tokens = 3200;

	/**
	 * Temperature for creativity
	 *
	 * @var float
	 */
	private $temperature = 0.7;

	/**
	 * Allowed HTML tags for reading sanitization
	 *
	 * @var array
	 */
	private $allowed_html = array(
		'h2'     => array(),
		'h3'     => array(),
		'h4'     => array(),
		'p'      => array(),
		'strong' => array(),
		'em'     => array(),
		'ul'     => array(),
		'ol'     => array(),
		'li'     => array(),
		'br'     => array(),
	);

	/**
	 * OpenAI API call counter for current reading generation.
	 *
	 * @var int
	 */
	private $openai_call_count = 0;

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
	 * @return SM_AI_Handler
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::init();
		}
		return self::$instance;
	}

	/**
	 * Generate a personalized teaser reading (JSON-based - NEW).
	 *
	 * @param string $lead_id Lead ID.
	 * @return array|WP_Error Array with reading data on success, WP_Error on failure.
	 */
	public function generate_teaser_reading( $lead_id ) {
		$context = $this->generate_teaser_context( $lead_id );
		if ( is_wp_error( $context ) ) {
			return $context;
		}

		$reading_data  = $context['reading_data'];
		$image_path    = $context['image_path'];
		$attempt_count = isset( $context['attempt_count'] ) ? intval( $context['attempt_count'] ) : 0;

		// Store reading in database
		$reading_id = $this->save_teaser_reading( $lead_id, $reading_data );
		if ( is_wp_error( $reading_id ) ) {
			return $reading_id;
		}

		$this->log_qa_metrics( $reading_data, $reading_id, $lead_id, 'aura_teaser', $attempt_count );

		// Delete palm image after successful generation
		$this->cleanup_palm_image( $image_path );

		// Fire custom hook
		do_action( 'sm_teaser_reading_generated', $lead_id, $reading_id, $reading_data );

		return array(
			'success'      => true,
			'reading_id'   => $reading_id,
			'reading_data' => $reading_data,
		);
	}

	/**
	 * Generate teaser context without saving (used by paid two-phase flow).
	 *
	 * @param string $lead_id Lead ID.
	 * @return array|WP_Error Array with reading_data, lead, quiz, image_path on success.
	 */
	public function generate_teaser_context( $lead_id ) {
		// Reset OpenAI call counter for this generation
		$this->openai_call_count = 0;

		// Validate lead_id
		if ( empty( $lead_id ) ) {
			SM_Logger::log( 'error', 'AI_READING', 'Missing lead_id', array() );
			return new WP_Error(
				'missing_lead_id',
				__( 'Lead ID is required.', 'mystic-aura-reading' )
			);
		}

		// Get lead data
		$lead = $this->get_lead_data( $lead_id );
		if ( is_wp_error( $lead ) ) {
			return $lead;
		}

		// Check email is verified
		if ( ! $lead['email_confirmed'] ) {
			SM_Logger::log( 'error', 'AI_READING', 'Email not verified', array(
				'lead_id' => $lead_id,
			) );
			return new WP_Error(
				'email_not_verified',
				__( 'Please verify your email first.', 'mystic-aura-reading' )
			);
		}

		// Get quiz responses
		$quiz = $this->get_quiz_data( $lead_id );
		if ( is_wp_error( $quiz ) ) {
			return $quiz;
		}

		// Get palm image
		$image_path = $this->get_palm_image( $lead_id );
		if ( is_wp_error( $image_path ) ) {
			return $image_path;
		}

		// PHASE 2: Generate palm summary + foundation content (Call 1 - Vision API)
		// This now returns palm_snapshot + opening + life_foundations in addition to palm analysis
		$palm_summary = $this->generate_palm_summary( $lead, $quiz, $image_path );
		if ( is_wp_error( $palm_summary ) ) {
			return $palm_summary;
		}

		$attempts = array();
		$last_response = null;

		// Extract palm_snapshot and content sections from Vision call
		$palm_snapshot = isset( $palm_summary['data']['palm_snapshot'] ) ? $palm_summary['data']['palm_snapshot'] : '';
		$vision_content = array();

		// Extract opening section (your_hand_as_mirror)
		if ( isset( $palm_summary['data']['your_hand_as_mirror'] ) && is_array( $palm_summary['data']['your_hand_as_mirror'] ) ) {
			$vision_content['opening'] = $palm_summary['data']['your_hand_as_mirror'];
		}

		// Extract foundations section (foundations_of_path) → maps to life_foundations
		if ( isset( $palm_summary['data']['foundations_of_path'] ) && is_array( $palm_summary['data']['foundations_of_path'] ) ) {
			$vision_content['life_foundations'] = $palm_summary['data']['foundations_of_path'];
		}

		// PHASE 2: Generate all remaining sections in one call (Call 2 - Completion API)
		$completion_prompt = $this->build_teaser_completion_prompt( $lead, $quiz, $palm_snapshot, $palm_summary['data'] );
		$completion_data   = $this->generate_teaser_part( $completion_prompt, $lead_id, 'completion', $attempts, $last_response );
		if ( is_wp_error( $completion_data ) ) {
			return $completion_data;
		}

		// Merge Vision content sections with Completion sections
		$reading_data = array_merge( $vision_content, $completion_data );
		$reading_data = $this->ensure_teaser_payload_sections( $reading_data, $lead );

		// PHASE 2: Skip expansion retries - accept best-effort output
		// The missing-field tolerance check below will catch if too many sections are missing

		$validated = SM_Teaser_Reading_Schema::validate_response( $reading_data );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}
		$reading_data = $validated;

		// Phase 1: Missing-field tolerance (max 2 sections)
		// Count missing required sections in the final report
		$missing_sections = $this->count_missing_required_sections( $reading_data );
		if ( count( $missing_sections ) > 2 ) {
			SM_Logger::log(
				'error',
				'AI_READING',
				'Teaser reading has too many missing sections',
				array(
					'lead_id'          => $lead_id,
					'missing_sections' => $missing_sections,
					'missing_count'    => count( $missing_sections ),
					'tolerance'        => 2,
				)
			);
			return new WP_Error(
				'teaser_missing_sections',
				sprintf( __( 'Reading generation incomplete: %d required sections missing (max 2 allowed).', 'mystic-aura-reading' ), count( $missing_sections ) ),
				array(
					'missing_sections' => $missing_sections,
				)
			);
		} elseif ( count( $missing_sections ) > 0 ) {
			SM_Logger::log(
				'warning',
				'AI_READING',
				'Teaser reading has missing sections within tolerance',
				array(
					'lead_id'          => $lead_id,
					'missing_sections' => $missing_sections,
					'missing_count'    => count( $missing_sections ),
				)
			);
			$attempts[] = sprintf( 'accepted_with_%d_missing_sections', count( $missing_sections ) );
		}

		$short_fields = $this->get_teaser_short_fields_by_schema( $reading_data );
		if ( ! empty( $short_fields ) ) {
			SM_Logger::log(
				'warning',
				'AI_READING',
				'Teaser payload contains short fields; accepting to avoid hard failure',
				array(
					'lead_id'      => $lead_id,
					'short_fields' => $short_fields,
					'summary'      => $this->summarize_teaser_payload( $reading_data ),
				)
			);
			$attempts[] = 'accept_short_payload';
		}

		$summary_text = isset( $palm_summary['summary_text'] ) ? $this->normalize_palm_summary_text( $palm_summary['summary_text'] ) : '';
		if ( ! isset( $reading_data['meta'] ) || ! is_array( $reading_data['meta'] ) ) {
			$reading_data['meta'] = array();
		}
		$reading_data['meta']['palm_summary_text'] = $summary_text;
		$reading_data['palm_summary'] = $this->normalize_palm_summary_data(
			isset( $palm_summary['data'] ) ? $palm_summary['data'] : array()
		);

		SM_Logger::log(
			'info',
			'AI_READING',
			'Teaser reading generated successfully',
			array(
				'lead_id'           => $lead_id,
				'tokens'            => isset( $last_response['usage']['total_tokens'] ) ? $last_response['usage']['total_tokens'] : 0,
				'attempts'          => $attempts,
				'openai_call_count' => $this->openai_call_count,
				'force_log'         => true,
			)
		);

		SM_Logger::log(
			'info',
			'AI_READING',
			'Teaser payload summary',
			array(
				'lead_id'   => $lead_id,
				'summary'   => $this->summarize_teaser_payload( $reading_data ),
				'force_log' => true,
			)
		);

		return array(
			'success'      => true,
			'reading_data' => $reading_data,
			'lead'         => $lead,
			'quiz'         => $quiz,
			'image_path'   => $image_path,
			'palm_summary' => $palm_summary,
			'attempt_count' => count( $attempts ),
		);
	}

	/**
	 * Generate a fully unlocked paid reading (two-phase JSON generation).
	 *
	 * @param string $lead_id Lead ID.
	 * @param string $account_id Account ID (optional override).
	 * @return array|WP_Error Array with reading data on success, WP_Error on failure.
	 */
	public function generate_paid_reading( $lead_id, $account_id = '' ) {
		$context = $this->generate_teaser_context( $lead_id );
		if ( is_wp_error( $context ) ) {
			return $context;
		}

		$phase_1_data  = $context['reading_data'];
		$lead          = $context['lead'];
		$quiz          = $context['quiz'];
		$image_path    = $context['image_path'];
		$palm_summary  = isset( $context['palm_summary'] ) ? $context['palm_summary'] : array(
			'summary_text' => '',
			'data'         => array(),
		);

		$phase_2_data = $this->generate_paid_completion( $lead, $quiz, $phase_1_data, $palm_summary );
		if ( is_wp_error( $phase_2_data ) ) {
			return $phase_2_data;
		}

		$combined_data = $this->merge_paid_reading_data( $phase_1_data, $phase_2_data );
		$account_id    = ! empty( $account_id ) ? $account_id : ( isset( $lead['account_id'] ) ? $lead['account_id'] : '' );

		if ( empty( $account_id ) ) {
			return new WP_Error(
				'missing_account_id',
				__( 'Account required for paid reading.', 'mystic-aura-reading' )
			);
		}

		$reading_id = $this->save_paid_reading( $lead_id, $combined_data, $account_id );
		if ( is_wp_error( $reading_id ) ) {
			return $reading_id;
		}

		$this->log_qa_metrics( $combined_data, $reading_id, $lead_id, 'aura_full', 0 );

		SM_Logger::log(
			'info',
			'AI_READING',
			'Paid payload summary',
			array(
				'lead_id'           => $lead_id,
				'summary'           => $this->summarize_paid_payload( $combined_data ),
				'openai_call_count' => $this->openai_call_count,
				'force_log'         => true,
			)
		);

		return array(
			'success'      => true,
			'reading_id'   => $reading_id,
			'reading_data' => $combined_data,
		);
	}

	/**
	 * Generate a paid reading using an existing teaser payload (one extra API call).
	 *
	 * @param string $lead_id Lead ID.
	 * @param string $account_id Account ID (optional override).
	 * @param object $teaser_reading Existing teaser reading row (optional).
	 * @return array|WP_Error Array with reading data on success, WP_Error on failure.
	 */
	public function generate_paid_reading_from_teaser( $lead_id, $account_id = '', $teaser_reading = null ) {
		if ( empty( $lead_id ) ) {
			return new WP_Error(
				'missing_lead_id',
				__( 'Lead ID is required.', 'mystic-aura-reading' )
			);
		}

		$lead = $this->get_lead_data( $lead_id );
		if ( is_wp_error( $lead ) ) {
			return $lead;
		}

		$quiz = $this->get_quiz_data( $lead_id );
		if ( is_wp_error( $quiz ) ) {
			return $quiz;
		}

		$phase_1_data = null;
		if ( is_object( $teaser_reading ) ) {
			if ( isset( $teaser_reading->content_data_parsed ) && is_array( $teaser_reading->content_data_parsed ) ) {
				$phase_1_data = $teaser_reading->content_data_parsed;
			} elseif ( ! empty( $teaser_reading->content_data ) ) {
				$decoded = json_decode( $teaser_reading->content_data, true );
				if ( is_array( $decoded ) ) {
					$phase_1_data = $decoded;
				}
			}
		}

		$image_path = '';
		$palm_summary_text = '';
		if ( empty( $phase_1_data ) ) {
			$context = $this->generate_teaser_context( $lead_id );
			if ( is_wp_error( $context ) ) {
				return $context;
			}

			$phase_1_data = $context['reading_data'];
			$image_path   = $context['image_path'];
			$palm_summary_text = isset( $context['palm_summary']['summary_text'] ) ? $context['palm_summary']['summary_text'] : '';
		} elseif ( isset( $phase_1_data['meta']['palm_summary_text'] ) ) {
			$palm_summary_text = $phase_1_data['meta']['palm_summary_text'];
		}

		$palm_summary = array(
			'summary_text' => $palm_summary_text,
			'data'         => array(),
		);

		$phase_2_data = $this->generate_paid_completion( $lead, $quiz, $phase_1_data, $palm_summary );
		if ( is_wp_error( $phase_2_data ) ) {
			return $phase_2_data;
		}

		$combined_data = $this->merge_paid_reading_data( $phase_1_data, $phase_2_data );
		$account_id    = ! empty( $account_id ) ? $account_id : ( isset( $lead['account_id'] ) ? $lead['account_id'] : '' );

		if ( empty( $account_id ) ) {
			return new WP_Error(
				'missing_account_id',
				__( 'Account required for paid reading.', 'mystic-aura-reading' )
			);
		}

		$reading_id      = '';
		$reading_service = SM_Reading_Service::get_instance();

		if ( is_object( $teaser_reading ) && ! empty( $teaser_reading->id ) ) {
			$update = $reading_service->update_reading_data(
				$teaser_reading->id,
				array(
					'reading_type'     => 'aura_full',
					'content_data'     => $combined_data,
					'account_id'       => $account_id,
					'unlock_count'     => 0,
					'unlocked_section' => '',
					'has_purchased'    => true,
					'updated_at'       => current_time( 'mysql' ),
				)
			);

			if ( is_wp_error( $update ) ) {
				return $update;
			}

			$reading_id = $teaser_reading->id;
		} else {
			$reading_id = $this->save_paid_reading( $lead_id, $combined_data, $account_id );
			if ( is_wp_error( $reading_id ) ) {
				return $reading_id;
			}
		}

		$this->log_qa_metrics( $combined_data, $reading_id, $lead_id, 'aura_full', 0 );

		SM_Logger::log(
			'info',
			'AI_READING',
			'Paid payload summary (teaser upgrade)',
			array(
				'lead_id'           => $lead_id,
				'summary'           => $this->summarize_paid_payload( $combined_data ),
				'openai_call_count' => $this->openai_call_count,
				'force_log'         => true,
			)
		);

		return array(
			'success'      => true,
			'reading_id'   => $reading_id,
			'reading_data' => $combined_data,
		);
	}

	/**
	 * Clean up palm images for a lead after successful paid fulfillment.
	 *
	 * @param string $lead_id Lead ID.
	 * @return void
	 */
	public function cleanup_palm_images_for_lead( $lead_id ) {
		if ( empty( $lead_id ) ) {
			return;
		}

		$upload_dir  = wp_upload_dir();
		$private_dir = $upload_dir['basedir'] . '/sm-palm-private';
		$lead_dir    = $private_dir . '/' . $lead_id;
		$pattern     = $lead_dir . '/' . $lead_id . '-*';
		$files       = glob( $pattern );

		if ( empty( $files ) ) {
			return;
		}

		foreach ( $files as $file_path ) {
			if ( file_exists( $file_path ) ) {
				$deleted = unlink( $file_path );
				if ( $deleted ) {
					SM_Logger::log( 'info', 'AI_READING', 'Palm image deleted after paid fulfillment', array(
						'path' => basename( $file_path ),
					) );
				} else {
					SM_Logger::log( 'warning', 'AI_READING', 'Failed to delete palm image after paid fulfillment', array(
						'path' => $file_path,
					) );
				}
			}
		}
	}

	/**
	 * Generate a personalized palm reading
	 *
	 * @param string $lead_id Lead ID.
	 * @return array|WP_Error Array with reading HTML on success, WP_Error on failure.
	 */
	public function generate_reading( $lead_id ) {
		global $wpdb;

		// Validate lead_id
		if ( empty( $lead_id ) ) {
			SM_Logger::log( 'error', 'AI_READING', 'Missing lead_id', array() );
			return new WP_Error(
				'missing_lead_id',
				__( 'Lead ID is required.', 'mystic-aura-reading' )
			);
		}

		// Check if reading already exists (one per email enforcement)
		$existing_reading = $this->get_reading_by_lead( $lead_id );
		if ( ! is_wp_error( $existing_reading ) && $existing_reading ) {
			SM_Logger::log( 'warning', 'AI_READING', 'Reading already exists for lead', array(
				'lead_id' => $lead_id,
			) );
			return new WP_Error(
				'reading_exists',
				__( 'You have already received your free aura reading.', 'mystic-aura-reading' )
			);
		}

		// Get lead data
		$lead = $this->get_lead_data( $lead_id );
		if ( is_wp_error( $lead ) ) {
			return $lead;
		}

		// Check email is verified
		if ( ! $lead['email_confirmed'] ) {
			SM_Logger::log( 'error', 'AI_READING', 'Email not verified', array(
				'lead_id' => $lead_id,
			) );
			return new WP_Error(
				'email_not_verified',
				__( 'Please verify your email first.', 'mystic-aura-reading' )
			);
		}

		// Get quiz responses
		$quiz = $this->get_quiz_data( $lead_id );
		if ( is_wp_error( $quiz ) ) {
			return $quiz;
		}

		// Get palm image
		$image_path = $this->get_palm_image( $lead_id );
		if ( is_wp_error( $image_path ) ) {
			return $image_path;
		}

		// Build the prompt (check for new dynamic quiz format vs old static format)
		$is_dynamic_quiz = isset( $quiz['questions'] ) && isset( $quiz['demographics'] );
		$template_id     = null;

		if ( $is_dynamic_quiz ) {
			// Use prompt template system for dynamic quiz format
			$template_handler = SM_Prompt_Template_Handler::init();
			$template         = $template_handler->select_random_template();

			if ( is_wp_error( $template ) ) {
				SM_Logger::log(
					'error',
					'AI_READING',
					'Failed to select prompt template',
					array(
						'lead_id' => $lead_id,
						'error'   => $template->get_error_message(),
					)
				);
				// Fallback to old build_prompt method
				$prompt = $this->build_prompt( $lead, $quiz );
			} else {
				$template_id = isset( $template['id'] ) ? $template['id'] : null;

				// Prepare user data for template
				$user_data = array(
					'name'       => $lead['name'],
					'age_range'  => isset( $quiz['demographics']['age_range'] ) ? $quiz['demographics']['age_range'] : '',
					'gender'     => isset( $quiz['demographics']['gender'] ) ? $quiz['demographics']['gender'] : '',
				);

				// Render prompt with placeholders replaced
				$prompt = $template_handler->replace_placeholders( $template, $user_data, $quiz );

				if ( is_wp_error( $prompt ) ) {
					SM_Logger::log(
						'error',
						'AI_READING',
						'Failed to render prompt template',
						array(
							'lead_id'     => $lead_id,
							'template_id' => $template_id,
							'error'       => $prompt->get_error_message(),
						)
					);
					// Fallback to old build_prompt method
					$prompt = $this->build_prompt( $lead, $quiz );
				} else {
					SM_Logger::log(
						'info',
						'AI_READING',
						'Using dynamic prompt template',
						array(
							'lead_id'     => $lead_id,
							'template_id' => $template_id,
						)
					);
				}
			}
		} else {
			// Use old static build_prompt method for backward compatibility
			$prompt = $this->build_prompt( $lead, $quiz );
			SM_Logger::log(
				'info',
				'AI_READING',
				'Using static prompt (old quiz format)',
				array( 'lead_id' => $lead_id )
			);
		}

		// Encode image for Vision API
		$image_data = $this->encode_image( $image_path );
		if ( is_wp_error( $image_data ) ) {
			return $image_data;
		}

		// Call OpenAI API (primary with image, fallback without image or with stronger prompt)
		SM_Logger::log(
			'info',
			'AI_READING',
			'Calling OpenAI API',
			array(
				'lead_id' => $lead_id,
				'model'   => $this->model,
			)
		);

		$response = $this->call_openai_api( $prompt, $image_data );
		if ( is_wp_error( $response ) ) {
			// If the structure is invalid, retry without image and with fallback prompt.
			if ( 'invalid_response' === $response->get_error_code() ) {
				SM_Logger::log(
					'warning',
					'AI_READING',
					'Invalid response structure on first attempt, retrying without image',
					array(
						'lead_id' => $lead_id,
					)
				);
				$response = $this->call_openai_api( $prompt, '', $this->get_fallback_system_prompt(), false );
			}

			if ( is_wp_error( $response ) ) {
				return $response;
			}
		}

		// Extract and sanitize HTML
		$reading_html = $this->extract_and_sanitize_html( $response );
		if ( is_wp_error( $reading_html ) ) {
			// Retry once text-only if structure/sanitization failed
			if ( 'invalid_response' === $reading_html->get_error_code() ) {
				$response = $this->call_openai_api( $prompt, '', $this->get_fallback_system_prompt(), false );
				if ( is_wp_error( $response ) ) {
					return $response;
				}
				$reading_html = $this->extract_and_sanitize_html( $response );
			}

			if ( is_wp_error( $reading_html ) ) {
				return $reading_html;
			}
		}

		// Validate word count (target 750-1000 words; allow fallback path if short)
		$word_count          = $this->count_words( $reading_html );
		$looks_like_refusal  = $this->is_refusal_response( $reading_html );
		$invalid_first_reply = $looks_like_refusal || $word_count < 500 || $word_count > 1500;

		if ( $invalid_first_reply ) {
			SM_Logger::log(
				'warning',
				'AI_READING',
				'Invalid AI response on first attempt (refusal or wrong length)',
				array(
					'lead_id'         => $lead_id,
					'word_count'      => $word_count,
					'is_refusal'      => $looks_like_refusal,
					'tokens'          => isset( $response['usage']['total_tokens'] ) ? $response['usage']['total_tokens'] : 0,
					'content_preview' => mb_substr( wp_strip_all_tags( $reading_html ), 0, 120 ),
				)
			);

			$fallback_response = $this->call_openai_api( $prompt, $image_data, $this->get_fallback_system_prompt() );
			if ( is_wp_error( $fallback_response ) ) {
				return $fallback_response;
			}

			$reading_html = $this->extract_and_sanitize_html( $fallback_response );
			if ( is_wp_error( $reading_html ) ) {
				return $reading_html;
			}

			$word_count         = $this->count_words( $reading_html );
			$looks_like_refusal = $this->is_refusal_response( $reading_html );

			if ( $looks_like_refusal ) {
				SM_Logger::log(
					'warning',
					'AI_READING',
					'Invalid AI response after retry (refusal) - attempting rescue prompt',
					array(
						'lead_id'         => $lead_id,
						'word_count'      => $word_count,
						'is_refusal'      => $looks_like_refusal,
						'content_preview' => mb_substr( wp_strip_all_tags( $reading_html ), 0, 120 ),
					)
				);

				// Final rescue attempt with stricter anti-refusal prompt (text-only)
				$rescue_response = $this->call_openai_api( $prompt, '', $this->get_rescue_system_prompt(), false );
				if ( ! is_wp_error( $rescue_response ) ) {
					$reading_html = $this->extract_and_sanitize_html( $rescue_response );
					if ( ! is_wp_error( $reading_html ) ) {
						$word_count         = $this->count_words( $reading_html );
						$looks_like_refusal = $this->is_refusal_response( $reading_html );
					}
				}

				if ( $looks_like_refusal || is_wp_error( $reading_html ) || $word_count < 400 ) {
					$reading_html = $this->build_safe_fallback_reading( $lead, $quiz );
					$word_count   = $this->count_words( $reading_html );
					$template_id  = $template_id ?: 'template_rescue_fallback';
					SM_Logger::log(
						'info',
						'AI_READING',
						'Used static fallback reading to avoid refusal',
						array(
							'lead_id'    => $lead_id,
							'word_count' => $word_count,
						)
					);
				}
			}

			// If still too short but not a refusal, attempt an extension pass.
			if ( $word_count < 700 ) {
				$reading_html = $this->extend_reading( $reading_html );
				if ( is_wp_error( $reading_html ) ) {
					return $reading_html;
				}
				$word_count         = $this->count_words( $reading_html );
				$looks_like_refusal = $this->is_refusal_response( $reading_html );
			}

			if ( $looks_like_refusal || $word_count < 650 || $word_count > 1600 ) {
				SM_Logger::log(
					'warning',
					'AI_READING',
					'Final AI output invalid after rescue, using fallback content',
					array(
						'lead_id'         => $lead_id,
						'word_count'      => $word_count,
						'is_refusal'      => $looks_like_refusal,
						'content_preview' => mb_substr( wp_strip_all_tags( $reading_html ), 0, 120 ),
					)
				);
				$reading_html = $this->build_safe_fallback_reading( $lead, $quiz );
				$word_count   = $this->count_words( $reading_html );
				$template_id  = $template_id ?: 'template_rescue_fallback';
			}
		}

		SM_Logger::log(
			'info',
			'AI_READING',
			'Reading generated',
			array(
				'lead_id'     => $lead_id,
				'word_count'  => $word_count,
				'tokens'      => isset( $response['usage']['total_tokens'] ) ? $response['usage']['total_tokens'] : 0,
				'template_id' => $template_id,
			)
		);

		// Store reading in database
		$saved = $this->save_reading( $lead_id, $reading_html, $template_id );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		// Delete palm image after successful generation
		$this->cleanup_palm_image( $image_path );

		// Fire custom hook
		do_action( 'sm_reading_generated', $lead_id, $reading_html );

		return array(
			'success'      => true,
			'reading_html' => $reading_html,
			'word_count'   => $word_count,
		);
	}

	/**
	 * Get lead data from database
	 *
	 * @param string $lead_id Lead ID.
	 * @return array|WP_Error Lead data or error.
	 */
	private function get_lead_data( $lead_id ) {
		global $wpdb;
		$table = SM_Database::get_instance()->get_table_name( 'leads' );

		$lead = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, name, email, identity, email_confirmed, account_id FROM $table WHERE id = %s",
				$lead_id
			),
			ARRAY_A
		);

		if ( ! $lead ) {
			SM_Logger::log( 'error', 'AI_READING', 'Lead not found', array(
				'lead_id' => $lead_id,
			) );
			return new WP_Error(
				'lead_not_found',
				__( 'Lead not found.', 'mystic-aura-reading' )
			);
		}

		return $lead;
	}

	/**
	 * Get quiz data from database
	 *
	 * @param string $lead_id Lead ID.
	 * @return array|WP_Error Quiz answers or error.
	 */
	private function get_quiz_data( $lead_id ) {
		global $wpdb;
		$table = SM_Database::get_instance()->get_table_name( 'quiz' );

		$quiz = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT answers_json FROM $table WHERE lead_id = %s",
				$lead_id
			)
		);

		if ( ! $quiz ) {
			SM_Logger::log( 'error', 'AI_READING', 'Quiz not found', array(
				'lead_id' => $lead_id,
			) );
			return new WP_Error(
				'quiz_not_found',
				__( 'Please complete the quiz first.', 'mystic-aura-reading' )
			);
		}

		$answers = json_decode( $quiz->answers_json, true );
		if ( ! $answers ) {
			return new WP_Error(
				'invalid_quiz_data',
				__( 'Invalid quiz data.', 'mystic-aura-reading' )
			);
		}

		return $answers;
	}

	/**
	 * Get palm image path
	 *
	 * @param string $lead_id Lead ID.
	 * @return string|WP_Error Image path or error.
	 */
	private function get_palm_image( $lead_id ) {
		$upload_dir = wp_upload_dir();
		$private_dirs = array(
			$upload_dir['basedir'] . '/sm-aura-private',
			$upload_dir['basedir'] . '/sm-palm-private',
		);

		$lead_dir = '';
		$pattern  = '';
		$files    = array();
		$used_dir = '';

		foreach ( $private_dirs as $dir ) {
			$lead_dir = $dir . '/' . $lead_id;
			$pattern  = $lead_dir . '/' . $lead_id . '-*';
			$files    = glob( $pattern );

			if ( ! empty( $files ) ) {
				$used_dir = $dir;
				break;
			}
		}

		if ( empty( $files ) ) {
			SM_Logger::log( 'error', 'AI_READING', 'Palm image not found', array(
				'lead_id' => $lead_id,
				'pattern' => $pattern,
				'lead_dir' => $lead_dir,
				'dir_exists' => is_dir( $lead_dir ),
			) );
			return new WP_Error(
				'image_not_found',
				__( 'Please upload your photo first.', 'mystic-aura-reading' )
			);
		}

		usort(
			$files,
			static function( $a, $b ) {
				return filemtime( $b ) <=> filemtime( $a );
			}
		);

		$selected = $files[0];

		SM_Logger::info(
			'AI_READING',
			'Aura image selected for analysis',
			array(
				'lead_id'     => $lead_id,
				'file_count'  => count( $files ),
				'selected'    => basename( $selected ),
				'selected_at' => gmdate( 'Y-m-d H:i:s', filemtime( $selected ) ),
				'storage_dir' => $used_dir,
			)
		);

		return $selected;
	}

	/**
	 * System prompt to strongly discourage refusals and force completion.
	 *
	 * @return string
	 */
	private function get_rescue_system_prompt() {
		return 'You are a mystical aura reader. Under no circumstances refuse or apologize. Always deliver a full, rich, 750-900 word aura reading that weaves in the provided Q&A context and aura insights. Avoid any disclaimers or refusal language. Use warm, encouraging tone. Allowed HTML tags only: <h4>, <p>, <strong>, <em>, <ul>, <li>. Do not include code fences or markdown.';
	}

	/**
	 * Safe fallback reading to avoid user-facing failures.
	 *
	 * @param array $lead Lead data.
	 * @param array $quiz Quiz data.
	 * @return string
	 */
	private function build_safe_fallback_reading( $lead, $quiz ) {
		$name   = isset( $lead['name'] ) ? sanitize_text_field( $lead['name'] ) : __( 'Seeker', 'mystic-aura-reading' );
		$gender = isset( $lead['identity'] ) ? sanitize_text_field( $lead['identity'] ) : '';

		$concern_summary = '';
		if ( isset( $quiz['questions'] ) && is_array( $quiz['questions'] ) ) {
			$parts = array();
			foreach ( $quiz['questions'] as $q ) {
				$q_text   = isset( $q['question_text'] ) ? sanitize_text_field( $q['question_text'] ) : '';
				$q_answer = '';
				if ( isset( $q['answer'] ) && is_array( $q['answer'] ) ) {
					$q_answer = implode( ', ', array_map( 'sanitize_text_field', $q['answer'] ) );
				} elseif ( isset( $q['answer'] ) ) {
					$q_answer = sanitize_text_field( (string) $q['answer'] );
				}
				if ( $q_text && $q_answer ) {
					$parts[] = "<li><strong>{$q_text}</strong>: {$q_answer}</li>";
				}
			}
			if ( ! empty( $parts ) ) {
				$concern_summary = '<ul>' . implode( '', $parts ) . '</ul>';
			}
		}

		$intro = sprintf(
			'<p>%s, I tuned into your palm and the intentions you shared. Here is a focused reading based on your energy%s.</p>',
			$name,
			$gender ? ' and how you identify' : ''
		);

		return $intro .
			'<h4>Life Path & Vitality</h4><p>Your life line shows resilience and an ability to recover from challenges. You are entering a phase where steady routines and mindful rest strengthen your vitality.</p>' .
			'<h4>Heart & Connections</h4><p>Your heart line suggests deep capacity for connection. Expect warmer, more authentic relationships as you lead with honesty and small daily gestures.</p>' .
			'<h4>Mind & Direction</h4><p>The head line indicates clarity returning. Decisions made in the next few weeks can set a stable course—trust thoughtful planning over impulse.</p>' .
			'<h4>Purpose & Opportunity</h4><p>Mounts around Jupiter and Apollo point to purpose through contribution and creativity. Lean into a project that feels meaningful; momentum builds when you share your gifts.</p>' .
			'<h4>Next Steps</h4><p>Anchor a simple ritual: morning breathwork, evening reflection, and one concrete action toward your intention. Small, consistent steps reshape your path.</p>' .
			( $concern_summary ? '<h4>Your Focus Points</h4>' . $concern_summary : '' );
	}

	/**
	 * Check if a column exists on a table.
	 *
	 * @param string $table_name Table name.
	 * @param string $column_name Column to check.
	 * @return bool
	 */
	private function column_exists( $table_name, $column_name ) {
		global $wpdb;

		$column = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW COLUMNS FROM {$table_name} LIKE %s",
				$column_name
			)
		);

		return ! empty( $column );
	}

	/**
	 * Build the teaser reading prompt for OpenAI (JSON output).
	 *
	 * @param array $lead Lead data.
	 * @param array $quiz Quiz answers.
	 * @return string Formatted JSON prompt.
	 */
	private function build_teaser_prompt( $lead, $quiz, $palm_summary_text ) {
		$name = sanitize_text_field( $lead['name'] );
		$gender = isset( $lead['identity'] ) ? sanitize_text_field( $lead['identity'] ) : 'not specified';

		// Build quiz answers block (short summary)
		$quiz_block = $this->format_quiz_answers_summary( $quiz );

		// Trait master list
		$traits = implode( ', ', SM_Teaser_Reading_Schema_V2::TRAIT_MASTER_LIST );

		// Get current timestamp in ISO format
		$timestamp = gmdate( 'Y-m-d\TH:i:s\Z' );
		$palm_summary_text = $this->normalize_palm_summary_text( $palm_summary_text );

		$template = <<<EOT
Generate a personalized aura reading teaser for:

USER DATA:
- Name: {$name}
- Gender: {$gender}
- Aura Field: Derived from the aura summary

QUIZ ANSWER SUMMARY:
{$quiz_block}

AURA SUMMARY (from image analysis):
{$palm_summary_text}

TRAIT MASTER LIST (select 3 most aligned):
{$traits}

CRITICAL INSTRUCTIONS:
- Analyze the aura photo and reference specific visible cues
- If aura cues are unclear, provide plausible interpretations without mentioning limitations
- Integrate quiz answers into relevant sections with explicit callbacks (e.g., "You mentioned..." or "When you shared...")
- Explicitly reference at least 3 specific quiz answers across the preview sections
- Use {$name}'s name naturally 2-3 times throughout
- Select 3 traits that best match this user's profile
- Generate ONLY the fields defined in the JSON schema below (no extra fields)
- Keep tone warm, mystical, insightful
- Enriched sections (opening, life_foundations, career_success, personality_traits, closing) should feel fuller and more insightful than previews
- Avoid vague generalities; anchor insights in aura cues and user context
- Follow the word count targets. Do not under-deliver.
- Treat these as the teaser "topic" sections to be most compelling: life_foundations, career_success, love_patterns, challenges_opportunities, life_phase, timeline_6_months, guidance.

⚠️ WORD COUNT GUIDANCE: Aim for the target ranges below. If slightly short, still provide a complete, coherent paragraph.

REQUIRED JSON STRUCTURE:
{
  "meta": {
    "user_name": "{$name}",
    "generated_at": "{$timestamp}",
    "reading_type": "aura_teaser"
  },
  "opening": {
    "reflection_p1": "Target 40-60 words - paragraph introducing aura reading",
    "reflection_p2": "Target 40-60 words - paragraph explaining teaser vs full reading"
  },
  "life_foundations": {
    "paragraph_1": "Target 60-75 words - analysis of primary palm lines and resilience",
    "paragraph_2": "Target 60-75 words - analysis of emotional nature and growth patterns",
    "paragraph_3": "Target 40-60 words - additional insight that completes the section",
    "core_theme": "Target 20-35 words - core insight"
  },
  "love_patterns": {
    "preview": "Target 40-60 words - preview of heart line analysis and connection patterns",
    "locked_teaser": "Target 12-20 words - enticing teaser"
  },
  "career_success": {
    "main_paragraph": "Target 80-120 words - paragraph on career alignment",
    "modal_love_patterns": "Target 35-55 words - quick insight about what they crave & avoid in love, based on palm and quiz answers",
    "modal_career_direction": "Target 35-55 words - quick insight about where they naturally thrive in career, based on palm features",
    "modal_life_alignment": "Target 35-55 words - quick insight about their balance signals and alignment indicators"
  },
  "personality_traits": {
    "intro": "Target 70-100 words - introduction to personality analysis",
    "trait_1_name": "Select from master list",
    "trait_1_score": 0-100 (integer),
    "trait_2_name": "Select from master list",
    "trait_2_score": 0-100 (integer),
    "trait_3_name": "Select from master list",
    "trait_3_score": 0-100 (integer)
  },
  "challenges_opportunities": {
    "preview": "Target 40-60 words - preview of current challenges and hidden opportunities",
    "locked_teaser": "Target 12-20 words - enticing teaser"
  },
  "life_phase": {
    "preview": "Target 40-60 words - preview of transition period and what's ending/beginning",
    "locked_teaser": "Target 12-20 words - enticing teaser"
  },
  "timeline_6_months": {
    "preview": "Target 40-60 words - preview of forward-looking timeline and upcoming themes",
    "locked_teaser": "Target 12-20 words - enticing teaser"
  },
  "guidance": {
    "preview": "Target 40-60 words - preview of actionable advice",
    "locked_teaser": "Target 12-20 words - enticing teaser"
  },
  "deep_relationship_analysis": {
    "placeholder_text": "Use a short placeholder indicating this section unlocks in the full reading."
  },
  "extended_timeline_12_months": {
    "placeholder_text": "Use a short placeholder indicating this section unlocks in the full reading."
  },
  "life_purpose_soul_mission": {
    "placeholder_text": "Use a short placeholder indicating this section unlocks in the full reading."
  },
  "shadow_work_transformation": {
    "placeholder_text": "Use a short placeholder indicating this section unlocks in the full reading."
  },
  "practical_guidance_action_plan": {
    "placeholder_text": "Use a short placeholder indicating this section unlocks in the full reading."
  },
  "closing": {
    "paragraph_1": "Target 40-60 words - summary of teaser",
    "paragraph_2": "Target 40-60 words - invitation to reveal full reading"
  }
}

Return ONLY valid JSON matching the schema above. No markdown, no code fences, no explanations - just the raw JSON object.
EOT;

		return $template;
	}

	/**
	 * Build the teaser core prompt (opening + foundations + traits + love + career).
	 *
	 * @param array  $lead Lead data.
	 * @param array  $quiz Quiz answers.
	 * @param string $palm_summary_text Palm summary text.
	 * @return string
	 */
	private function build_teaser_core_prompt( $lead, $quiz, $palm_summary_text ) {
		$schema = <<<JSON
{
  "meta": {
    "user_name": "__USER_NAME__",
    "generated_at": "TIMESTAMP",
    "reading_type": "aura_teaser"
  },
  "opening": {
    "reflection_p1": "Target 40-60 words - paragraph introducing aura reading",
    "reflection_p2": "Target 40-60 words - paragraph explaining teaser vs full reading"
  },
  "life_foundations": {
    "paragraph_1": "Target 60-75 words - analysis of primary palm lines and resilience",
    "paragraph_2": "Target 60-75 words - analysis of emotional nature and growth patterns",
    "paragraph_3": "Target 40-60 words - additional insight that completes the section",
    "core_theme": "Target 20-35 words - core insight"
  },
  "love_patterns": {
    "preview": "Target 40-60 words - preview of heart line analysis and connection patterns",
    "locked_teaser": "Target 12-20 words - enticing teaser"
  },
  "career_success": {
    "main_paragraph": "Target 80-120 words - paragraph on career alignment",
    "modal_love_patterns": "Target 50-60 words - quick insight about what they crave & avoid in love, based on palm and quiz answers",
    "modal_career_direction": "Target 50-60 words - quick insight about where they naturally thrive in career, based on palm features",
    "modal_life_alignment": "Target 50-60 words - quick insight about their balance signals and alignment indicators"
  },
  "personality_traits": {
    "intro": "Target 70-100 words - introduction to personality analysis",
    "trait_1_name": "Select from master list",
    "trait_1_score": 0-100 (integer),
    "trait_2_name": "Select from master list",
    "trait_2_score": 0-100 (integer),
    "trait_3_name": "Select from master list",
    "trait_3_score": 0-100 (integer)
  }
}
JSON;

		return $this->build_teaser_prompt_with_schema( $lead, $quiz, $palm_summary_text, $schema );
	}

	/**
	 * Build teaser core prompt part A (opening + foundations + traits).
	 *
	 * @param array  $lead Lead data.
	 * @param array  $quiz Quiz answers.
	 * @param string $palm_summary_text Palm summary text.
	 * @return string
	 */
	private function build_teaser_core_prompt_part_a( $lead, $quiz, $palm_summary_text ) {
		$schema = <<<JSON
{
  "meta": {
    "user_name": "__USER_NAME__",
    "generated_at": "TIMESTAMP",
    "reading_type": "aura_teaser"
  },
  "opening": {
    "reflection_p1": "Target 40-60 words - paragraph introducing aura reading",
    "reflection_p2": "Target 40-60 words - paragraph explaining teaser vs full reading"
  },
  "life_foundations": {
    "paragraph_1": "Target 60-75 words - analysis of primary palm lines and resilience",
    "paragraph_2": "Target 60-75 words - analysis of emotional nature and growth patterns",
    "paragraph_3": "Target 40-60 words - additional insight that completes the section",
    "core_theme": "Target 20-35 words - core insight"
  },
  "personality_traits": {
    "intro": "Target 70-100 words - introduction to personality analysis",
    "trait_1_name": "Select from master list",
    "trait_1_score": 0-100 (integer),
    "trait_2_name": "Select from master list",
    "trait_2_score": 0-100 (integer),
    "trait_3_name": "Select from master list",
    "trait_3_score": 0-100 (integer)
  }
}
JSON;

		return $this->build_teaser_prompt_with_schema( $lead, $quiz, $palm_summary_text, $schema );
	}

	/**
	 * Build teaser core prompt part B (love + career).
	 *
	 * @param array  $lead Lead data.
	 * @param array  $quiz Quiz answers.
	 * @param string $palm_summary_text Palm summary text.
	 * @return string
	 */
	private function build_teaser_core_prompt_part_b( $lead, $quiz, $palm_summary_text ) {
		$schema = <<<JSON
{
  "love_patterns": {
    "preview": "Target 40-60 words - preview of heart line analysis and connection patterns",
    "locked_teaser": "Target 12-20 words - enticing teaser"
  },
  "career_success": {
    "main_paragraph": "Target 80-120 words - paragraph on career alignment",
    "modal_love_patterns": "Target 35-55 words - quick insight about what they crave & avoid in love, based on palm and quiz answers",
    "modal_career_direction": "Target 35-55 words - quick insight about where they naturally thrive in career, based on palm features",
    "modal_life_alignment": "Target 35-55 words - quick insight about their balance signals and alignment indicators"
  }
}
JSON;

		return $this->build_teaser_prompt_with_schema( $lead, $quiz, $palm_summary_text, $schema );
	}

	/**
	 * Build the teaser secondary prompt (challenges + phase + timeline + guidance + closing).
	 *
	 * @param array  $lead Lead data.
	 * @param array  $quiz Quiz answers.
	 * @param string $palm_summary_text Palm summary text.
	 * @return string
	 */
	private function build_teaser_secondary_prompt( $lead, $quiz, $palm_summary_text ) {
		$schema = <<<JSON
{
  "challenges_opportunities": {
    "preview": "Target 40-60 words - preview of current challenges and hidden opportunities",
    "locked_teaser": "Target 12-20 words - enticing teaser"
  },
  "life_phase": {
    "preview": "Target 40-60 words - preview of transition period and what's ending/beginning",
    "locked_teaser": "Target 12-20 words - enticing teaser"
  },
  "timeline_6_months": {
    "preview": "Target 40-60 words - preview of forward-looking timeline and upcoming themes",
    "locked_teaser": "Target 12-20 words - enticing teaser"
  },
  "guidance": {
    "preview": "Target 40-60 words - preview of actionable advice",
    "locked_teaser": "Target 12-20 words - enticing teaser"
  },
  "closing": {
    "paragraph_1": "Target 40-60 words - summary of teaser",
    "paragraph_2": "Target 40-60 words - invitation to reveal full reading"
  }
}
JSON;

		return $this->build_teaser_prompt_with_schema( $lead, $quiz, $palm_summary_text, $schema );
	}

	/**
	 * Build a teaser prompt with a custom schema block.
	 *
	 * @param array  $lead Lead data.
	 * @param array  $quiz Quiz answers.
	 * @param string $palm_summary_text Palm summary text.
	 * @param string $schema_block JSON schema block.
	 * @return string
	 */
	private function build_teaser_prompt_with_schema( $lead, $quiz, $palm_summary_text, $schema_block ) {
		$name = sanitize_text_field( $lead['name'] );
		$gender = isset( $lead['identity'] ) ? sanitize_text_field( $lead['identity'] ) : 'not specified';
		$quiz_block = $this->format_quiz_answers_summary( $quiz );
		$traits = implode( ', ', SM_Teaser_Reading_Schema_V2::TRAIT_MASTER_LIST );
		$timestamp = gmdate( 'Y-m-d\TH:i:s\Z' );
		$palm_summary_text = $this->normalize_palm_summary_text( $palm_summary_text );
		$schema_block = str_replace( array( 'TIMESTAMP', '__USER_NAME__' ), array( $timestamp, $name ), $schema_block );
		$image_note_instruction = '';
		if ( false !== strpos( $palm_summary_text, 'Image clarity:' ) ) {
			$image_note_instruction = '- Include one gentle sentence in the opening that the aura photo was a bit unclear, so you blended visual cues with what they shared.';
		}

		$template = <<<EOT
Generate a personalized aura reading teaser for:

USER DATA:
- Name: {$name}
- Gender: {$gender}
- Aura Field: Derived from the aura summary

QUIZ ANSWER SUMMARY:
{$quiz_block}

AURA SUMMARY (from image analysis):
{$palm_summary_text}

TRAIT MASTER LIST (select 3 most aligned):
{$traits}

CRITICAL INSTRUCTIONS:
- Reference aura cues and quiz answers directly.
- Use {$name}'s name naturally 1-2 times.
- Generate ONLY the fields in the schema (no extra fields).
- Keep tone warm, mystical, and specific.
- Meet minimum word counts for every field.
{$image_note_instruction}

REQUIRED JSON STRUCTURE:
{$schema_block}

Return ONLY valid JSON matching the schema above. No markdown, no code fences, no explanations - just the raw JSON object.
EOT;

		return $template;
	}

	/**
	 * Build the unified teaser completion prompt (Phase 2).
	 *
	 * Generates all remaining teaser sections in a single call after Vision call.
	 *
	 * @param array  $lead Lead data.
	 * @param array  $quiz Quiz answers.
	 * @param string $palm_snapshot Palm snapshot from Vision call (Call 1).
	 * @param array  $palm_summary_data Normalized palm summary data from Call 1.
	 * @return string
	 */
	private function build_teaser_completion_prompt( $lead, $quiz, $palm_snapshot, $palm_summary_data ) {
		$name      = sanitize_text_field( $lead['name'] );
		$gender    = isset( $lead['identity'] ) ? sanitize_text_field( $lead['identity'] ) : 'not specified';
		$timestamp = gmdate( 'Y-m-d\TH:i:s\Z' );
		$traits    = implode( ', ', SM_Teaser_Reading_Schema_V2::TRAIT_MASTER_LIST );
		$quiz_block = $this->format_quiz_answers_summary( $quiz );

		// Extract palm features for context
		$hand_type = isset( $palm_summary_data['hand_type'] ) ? $palm_summary_data['hand_type'] : 'unknown';

		$schema = <<<JSON
{
  "meta": {
    "user_name": "{$name}",
    "generated_at": "{$timestamp}",
    "reading_type": "aura_teaser"
  },
  "personality_traits": {
    "intro": "Target 70-100 words - introduction to personality analysis",
    "trait_1_name": "Select from master list",
    "trait_1_score": 0-100 (integer),
    "trait_2_name": "Select from master list",
    "trait_2_score": 0-100 (integer),
    "trait_3_name": "Select from master list",
    "trait_3_score": 0-100 (integer)
  },
  "love_patterns": {
    "preview": "Target 120-160 words (~10 sentences) - substantial preview of heart line analysis, connection patterns, and relationship insights"
  },
  "career_success": {
    "main_paragraph": "Target 80-120 words - paragraph on career alignment and strengths"
  },
  "challenges_opportunities": {
    "preview": "Target 120-160 words (~10 sentences) - substantive preview of current challenges, hidden opportunities, and transformation potential"
  },
  "life_phase": {
    "preview": "Target 120-160 words (~10 sentences) - comprehensive preview of transition period, what's ending/beginning, and next chapter themes"
  },
  "timeline_6_months": {
    "preview": "Target 120-160 words (~10 sentences) - detailed preview of forward-looking timeline, upcoming themes, and key timing windows"
  },
  "guidance": {
    "preview": "Target 120-160 words (~10 sentences) - substantive preview of actionable advice, specific guidance, and recommended focus areas"
  },
  "closing": {
    "paragraph_1": "Target 40-60 words - summary of teaser insights",
    "paragraph_2": "Target 40-60 words - warm invitation to reveal full reading"
  }
}
JSON;

		$template = <<<EOT
Continue generating the personalized aura reading teaser for {$name}.

CONTEXT FROM VISION CALL (Call 1):
- Opening and foundations sections have already been generated
- Use the aura snapshot below as the factual basis for all remaining sections

USER DATA:
- Name: {$name}
- Gender: {$gender}
- Aura Field: {$hand_type}

QUIZ ANSWERS:
{$quiz_block}

AURA SNAPSHOT (from Vision call):
{$palm_snapshot}

TRAIT MASTER LIST (select 3 most aligned):
{$traits}

CRITICAL INSTRUCTIONS:
- Reference aura cues from the snapshot and quiz answers directly
- Use {$name}'s name naturally 1-2 times across all sections
- Generate ONLY the fields in the schema (no extra fields)
- Keep tone warm, mystical, and specific to {$name}
- Meet minimum word counts for every field
- Preview sections should provide valuable insights while leaving depth for the paid reading

REQUIRED JSON STRUCTURE:
{$schema}

Return ONLY valid JSON matching the schema above. No markdown, no code fences, no explanations - just the raw JSON object.
EOT;

		return $template;
	}

	/**
	 * Build a prompt to expand short teaser sections using existing content.
	 *
	 * @param array  $lead Lead data.
	 * @param array  $quiz Quiz answers.
	 * @param string $palm_summary_text Palm summary text.
	 * @param array  $payload Sections to expand.
	 * @return string
	 */
	private function build_teaser_expand_prompt( $lead, $quiz, $palm_summary_text, $payload ) {
		$name = sanitize_text_field( $lead['name'] );
		$gender = isset( $lead['identity'] ) ? sanitize_text_field( $lead['identity'] ) : 'not specified';
		$quiz_block = $this->format_quiz_answers_summary( $quiz );
		$palm_summary_text = $this->normalize_palm_summary_text( $palm_summary_text );
		$payload_json = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		$targets = <<<TEXT
TARGETS:
- opening.reflection_p1: 40-60 words
- opening.reflection_p2: 40-60 words
- life_foundations.paragraph_1: 100-130 words
- life_foundations.paragraph_2: 100-130 words
- life_foundations.paragraph_3: 80-100 words
- life_foundations.core_theme: 50-70 words
- love_patterns.preview: 120-160 words (~10 sentences)
- career_success.main_paragraph: 80-120 words
- personality_traits.intro: 70-100 words
- challenges_opportunities.preview: 120-160 words (~10 sentences)
- life_phase.preview: 120-160 words (~10 sentences)
- timeline_6_months.preview: 120-160 words (~10 sentences)
- guidance.preview: 120-160 words (~10 sentences)
- closing.paragraph_1: 40-60 words
- closing.paragraph_2: 40-60 words
TEXT;

		$template = <<<EOT
You are expanding short sections in an aura reading teaser. Keep the JSON structure exactly the same, but rewrite and expand the provided fields to meet the word targets.

USER DATA:
- Name: {$name}
- Gender: {$gender}

QUIZ ANSWER SUMMARY:
{$quiz_block}

PALM SUMMARY (from image analysis):
{$palm_summary_text}

{$targets}

SECTIONS TO EXPAND (current content):
{$payload_json}

INSTRUCTIONS:
- Keep all keys and structure identical.
- Expand content to hit targets while staying grounded in aura cues and quiz answers.
- Keep trait names and scores unchanged if present.
- Minimum word counts are required for every field. Do not stop short.

Return ONLY valid JSON for the sections above.
EOT;

		return $template;
	}

	/**
	 * Build the paid completion prompt (Phase 2).
	 *
	 * @param array $lead Lead data.
	 * @param array $quiz Quiz answers.
	 * @param array $phase_1_data Phase 1 JSON data.
	 * @return string Prompt text.
	 */
	private function build_paid_completion_prompt( $lead, $quiz, $phase_1_data, $palm_summary_text ) {
		$name = sanitize_text_field( $lead['name'] );
		$gender = isset( $lead['identity'] ) ? sanitize_text_field( $lead['identity'] ) : 'not specified';
		$quiz_block = $this->format_quiz_answers_summary( $quiz );
		$phase_1_json = wp_json_encode( $phase_1_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		$timestamp = gmdate( 'Y-m-d\TH:i:s\Z' );

		$template = <<<EOT
You are an expert aura reader providing the paid completion of a reading.

USER DATA:
- Name: {$name}
- Gender: {$gender}

QUIZ ANSWER SUMMARY:
{$quiz_block}

AURA SUMMARY (from image analysis):
{$palm_summary_text}

PHASE 1 CONTEXT (JSON):
{$phase_1_json}

CRITICAL INSTRUCTIONS:
- Build on Phase 1 insights. Do NOT repeat verbatim.
- Every insight must connect to the user's quiz answers or Phase 1 aura analysis.
- Write in a warm, mystical, grounded tone.
- Explicitly reference at least 5 specific quiz answers across the sections.
- Avoid vague generalities; anchor insights in the aura analysis and user context.
- Return ONLY valid JSON. No markdown, no code fences, no extra text.

WORD COUNT TARGET: 1300-1600 words total across all sections.

REQUIRED JSON STRUCTURE:
{
  "meta": {
    "user_name": "{$name}",
    "generated_at": "{$timestamp}",
    "reading_type": "aura_full"
  },
  "career_success": {
    "modal_love_patterns": "Target 45-60 words - quick insight about relationship patterns and what they seek in love",
    "modal_career_direction": "Target 45-60 words - quick insight about where they thrive with money and success",
    "modal_life_alignment": "Target 45-60 words - quick insight about balance, pacing, and sustainable growth"
  },
  "love_patterns": { "locked_full": "160-220 words" },
  "challenges_opportunities": { "locked_full": "160-220 words" },
  "life_phase": { "locked_full": "160-220 words" },
  "timeline_6_months": { "locked_full": "160-220 words" },
  "guidance": { "locked_full": "140-180 words" },
  "deep_relationship_analysis": { "full_content": "160-220 words" },
  "extended_timeline_12_months": { "full_content": "160-220 words" },
  "life_purpose_soul_mission": { "full_content": "220-280 words" },
  "shadow_work_transformation": { "full_content": "160-220 words" },
  "practical_guidance_action_plan": { "full_content": "140-180 words" }
}
EOT;

		return $template;
	}

	/**
	 * Generate paid completion data (Phase 2).
	 *
	 * @param array $lead Lead data.
	 * @param array $quiz Quiz data.
	 * @param array $phase_1_data Phase 1 JSON data.
	 * @return array|WP_Error
	 */
	private function generate_paid_completion( $lead, $quiz, $phase_1_data, $palm_summary ) {
		$summary_text = is_array( $palm_summary ) && isset( $palm_summary['summary_text'] ) ? $palm_summary['summary_text'] : '';
		$summary_text = $this->normalize_palm_summary_text( $summary_text );
		$prompt       = $this->build_paid_completion_prompt( $lead, $quiz, $phase_1_data, $summary_text );

		SM_Logger::log(
			'info',
			'AI_READING',
			'Calling OpenAI API for paid completion (Phase 3: single-call best-effort)',
			array(
				'lead_id' => isset( $lead['id'] ) ? $lead['id'] : 'unknown',
				'model'   => $this->model,
			)
		);

		$response = $this->call_openai_api(
			$prompt,
			'',
			$this->get_teaser_system_prompt(),
			false,
			array( 'type' => 'json_object' )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$completion_data = $this->extract_and_validate_paid_completion_json( $response );
		if ( is_wp_error( $completion_data ) ) {
			// Phase 3: No retries - fail fast if JSON is fundamentally invalid
			SM_Logger::log(
				'error',
				'AI_READING',
				'Paid completion JSON validation failed (Phase 3: no retry)',
				array(
					'lead_id' => isset( $lead['id'] ) ? $lead['id'] : 'unknown',
					'error'   => $completion_data->get_error_message(),
				)
			);
			return $completion_data;
		}

		// Phase 3: Check for short sections but log warning only - accept best-effort output
		$paid_short_sections = $this->is_paid_payload_short( $completion_data );
		if ( ! empty( $paid_short_sections ) ) {
			SM_Logger::log(
				'warning',
				'AI_READING',
				'Paid payload has short sections (Phase 3: accepting best-effort)',
				array(
					'lead_id'        => isset( $lead['id'] ) ? $lead['id'] : 'unknown',
					'short_sections' => $paid_short_sections,
					'summary'        => $this->summarize_paid_payload( $completion_data ),
				)
			);
			// Accept the output anyway - no retry
		}

		SM_Logger::log(
			'info',
			'AI_READING',
			'Paid completion generated (Phase 3: 1 call)',
			array(
				'lead_id' => isset( $lead['id'] ) ? $lead['id'] : 'unknown',
			)
		);

		return $completion_data;
	}

	/**
	 * Merge Phase 1 and Phase 2 data into a full paid reading.
	 *
	 * @param array $phase_1_data Phase 1 JSON data.
	 * @param array $phase_2_data Phase 2 JSON data.
	 * @return array
	 */
	private function merge_paid_reading_data( $phase_1_data, $phase_2_data ) {
		$merged = $phase_1_data;

		$merged['meta']['reading_type'] = 'aura_full';
		$merged['meta']['generated_at'] = isset( $phase_2_data['meta']['generated_at'] )
			? $phase_2_data['meta']['generated_at']
			: $merged['meta']['generated_at'];

		if ( isset( $merged['opening'] ) && is_array( $merged['opening'] ) ) {
			$merged['opening']['reflection_p2'] = 'This full reading expands the patterns your palm reveals and ties them to the choices you are making now. It offers grounded guidance, deeper context, and a clear sense of momentum so you can move forward with confidence and clarity.';
		}

		$locked_sections = array(
			'love_patterns',
			'challenges_opportunities',
			'life_phase',
			'timeline_6_months',
			'guidance',
		);

		foreach ( $locked_sections as $section ) {
			if ( isset( $phase_2_data[ $section ]['locked_full'] ) ) {
				$merged[ $section ]['locked_full'] = $phase_2_data[ $section ]['locked_full'];
			}
		}

		if ( isset( $phase_2_data['career_success'] ) && is_array( $phase_2_data['career_success'] ) ) {
			if ( ! isset( $merged['career_success'] ) || ! is_array( $merged['career_success'] ) ) {
				$merged['career_success'] = array();
			}
			$modal_fields = array( 'modal_love_patterns', 'modal_career_direction', 'modal_life_alignment' );
			foreach ( $modal_fields as $field ) {
				if ( ! empty( $phase_2_data['career_success'][ $field ] ) ) {
					$merged['career_success'][ $field ] = $phase_2_data['career_success'][ $field ];
				}
			}
		}

		$premium_sections = array(
			'deep_relationship_analysis',
			'extended_timeline_12_months',
			'life_purpose_soul_mission',
			'shadow_work_transformation',
			'practical_guidance_action_plan',
		);

		foreach ( $premium_sections as $section ) {
			if ( isset( $phase_2_data[ $section ] ) ) {
				$merged[ $section ] = $phase_2_data[ $section ];
			}
		}

		if ( isset( $merged['closing'] ) && is_array( $merged['closing'] ) ) {
			$merged['closing']['paragraph_1'] = 'This is your full reading — the complete reflection of your palm patterns, choices, and next steps.';
			$merged['closing']['paragraph_2'] = 'Let the themes settle and return to any section whenever you need clarity.';
		}

		return $merged;
	}

	/**
	 * Format quiz answers for prompt inclusion.
	 *
	 * @param array $quiz Quiz data.
	 * @return string Formatted quiz answers.
	 */
	private function format_quiz_answers_for_prompt( $quiz ) {
		$lines = array();

		// Handle dynamic quiz format (new)
		if ( isset( $quiz['questions'] ) && is_array( $quiz['questions'] ) ) {
			$counter = 1;
			foreach ( $quiz['questions'] as $q ) {
				$q_text = isset( $q['question_text'] ) ? sanitize_text_field( $q['question_text'] ) : '';
				$q_answer = '';
				if ( isset( $q['answer'] ) && is_array( $q['answer'] ) ) {
					$q_answer = implode( ', ', array_map( 'sanitize_text_field', $q['answer'] ) );
				} elseif ( isset( $q['answer'] ) ) {
					$q_answer = sanitize_text_field( (string) $q['answer'] );
				}
				if ( $q_text && $q_answer ) {
					$lines[] = "{$counter}. {$q_text}: {$q_answer}";
					$counter++;
				}
			}
		}

		// Fallback to static quiz format (old)
		if ( empty( $lines ) ) {
			$energy = isset( $quiz['energy'] ) ? sanitize_text_field( $quiz['energy'] ) : 'balanced';
			$focus = isset( $quiz['focus'] ) ? sanitize_text_field( $quiz['focus'] ) : 'general';
			$element = isset( $quiz['element'] ) ? sanitize_text_field( $quiz['element'] ) : 'balanced';
			$intentions = isset( $quiz['intentions'] ) && is_array( $quiz['intentions'] )
				? implode( ', ', array_map( 'sanitize_text_field', $quiz['intentions'] ) )
				: 'spiritual growth';
			$goals = isset( $quiz['goals'] ) ? sanitize_text_field( $quiz['goals'] ) : 'personal development';

			$lines[] = "1. Energy Level: {$energy}";
			$lines[] = "2. Life Focus: {$focus}";
			$lines[] = "3. Element Resonance: {$element}";
			$lines[] = "4. Spiritual Intentions: {$intentions}";
			$lines[] = "5. Future Goals: {$goals}";
		}

		return implode( "\n", $lines );
	}

	/**
	 * Produce a compact quiz summary to reduce prompt size.
	 *
	 * @param array $quiz Quiz data.
	 * @return string
	 */
	private function format_quiz_answers_summary( $quiz ) {
		$full  = $this->format_quiz_answers_for_prompt( $quiz );
		$lines = preg_split( '/\r?\n/', trim( $full ) );
		if ( empty( $lines ) ) {
			return '';
		}

		$summary = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			if ( strlen( $line ) > 180 ) {
				$line = substr( $line, 0, 177 ) . '...';
			}
			$summary[] = $line;
			if ( count( $summary ) >= 8 ) {
				break;
			}
		}

		return implode( "\n", $summary );
	}

	/**
	 * Build the prompt for OpenAI
	 *
	 * @param array $lead Lead data.
	 * @param array $quiz Quiz answers.
	 * @return string Formatted prompt.
	 */
	private function build_prompt( $lead, $quiz ) {
		$name = sanitize_text_field( $lead['name'] );
		$identity = sanitize_text_field( $lead['identity'] );

		// Extract quiz answers with defaults
		$energy = isset( $quiz['energy'] ) ? sanitize_text_field( $quiz['energy'] ) : 'balanced';
		$focus = isset( $quiz['focus'] ) ? sanitize_text_field( $quiz['focus'] ) : 'general';
		$element = isset( $quiz['element'] ) ? sanitize_text_field( $quiz['element'] ) : 'balanced';
		$intentions = isset( $quiz['intentions'] ) && is_array( $quiz['intentions'] )
			? implode( ', ', array_map( 'sanitize_text_field', $quiz['intentions'] ) )
			: 'spiritual growth';
		$goals = isset( $quiz['goals'] ) ? sanitize_text_field( $quiz['goals'] ) : 'personal development';

		$insights_block = implode(
			"\n",
			array(
				"- Name: {$name}",
				"- Identity: {$identity}",
				"- Energy: {$energy}",
				"- Focus: {$focus}",
				"- Element: {$element}",
				"- Intentions: {$intentions}",
				"- Goals: {$goals}",
			)
		);

$template = <<<EOT
You are a mystical aura reader. Create a deeply personalized aura reading for {$name} that weaves together the aura photo insights with their personal energy profile:

{$insights_block}

CRITICAL: Connect your aura observations to their Energy level ({$energy}), Life Focus ({$focus}), Elemental resonance ({$element}), Spiritual Intentions ({$intentions}), and Future Goals ({$goals}). Reference these throughout the reading to make it feel uniquely tailored to them.

<h4>Welcome</h4>
<p>In 3-4 sentences, offer a warm, mystical welcome that acknowledges their current energy state and spiritual path. Set a calming tone that prepares them to discover their aura signature.</p>

<h4>Aura Field & Presence</h4>
<p>In 3-4 sentences, describe the overall aura field and what it suggests about their core energy, emotional nature, and approach to life. Connect this to their stated energy level and element resonance.</p>

<h4>Emotional Climate</h4>
<p>In 4-5 sentences, interpret the emotional tone you sense (warmth, openness, guardedness, softness). Reveal what this suggests about their capacity for love, empathy, emotional expression, and relationships. Connect to their spiritual intentions.</p>

<h4>Mental Focus</h4>
<p>In 4-5 sentences, describe their mental focus, clarity, and decision style as reflected in posture and expression. Explain how they process challenges and make choices. Link to their life focus and goals.</p>

<h4>Vital Flow</h4>
<p>In 4-5 sentences, examine vitality, resilience, and momentum. Discuss where energy feels steady, where it feels stretched, and how it recovers. Relate to their energy level and future aspirations.</p>

<h4>Centers & Balance</h4>
<p>In 3-4 sentences, explore energetic centers or focal points that appear stronger or quieter. Describe where balance is present and where renewal is needed. Connect to their spiritual path.</p>

<h4>Shadow & Growth</h4>
<p>In 3-4 sentences, identify a subtle pattern or growth edge that invites transformation. Make this feel gentle, empowering, and invitational.</p>

<h4>Your Path Forward</h4>
<p>In 3-4 sentences, close with an uplifting, personalized message about their unique journey of growth and healing. Acknowledge their stated goals and intentions. End with a gentle invitation: "Discover deeper insights into your destiny."</p>

Instructions:
- Fill EVERY section above completely; never skip or leave placeholders.
- Reference the aura photo directly; describe visible cues such as posture, openness, expression, and light.
- WEAVE IN their personal details (energy, focus, element, intentions, goals) throughout the reading—make it feel custom-made for them.
- Word count target: 750-1000 words total; expand each section to meet this.
- Output clean HTML using ONLY <h4>, <p>, <strong>, <em>; absolutely NO <ul> or <li> tags.
- Never apologize, refuse, or mention limitations; provide your most insightful mystical reading.
EOT;

		$prompt = $template;

		return $prompt;
	}

	/**
	 * Encode image to base64 for Vision API
	 *
	 * @param string $image_path Path to image file.
	 * @return string|WP_Error Base64 encoded image or error.
	 */
	private function encode_image( $image_path ) {
		if ( ! file_exists( $image_path ) ) {
			return new WP_Error(
				'file_not_found',
				__( 'Image file not found.', 'mystic-aura-reading' )
			);
		}

		$image_data = file_get_contents( $image_path );
		if ( $image_data === false ) {
			return new WP_Error(
				'file_read_error',
				__( 'Could not read image file.', 'mystic-aura-reading' )
			);
		}

		$base64 = base64_encode( $image_data );
		$mime_type = mime_content_type( $image_path );

		return 'data:' . $mime_type . ';base64,' . $base64;
	}

	/**
	 * Generate a compact palm summary from the image for downstream prompts.
	 *
	 * @param array  $lead Lead data.
	 * @param array  $quiz Quiz data.
	 * @param string $image_path Path to palm image.
	 * @return array|WP_Error
	 */
	private function generate_palm_summary( $lead, $quiz, $image_path ) {
		$image_data = $this->encode_image( $image_path );
		if ( is_wp_error( $image_data ) ) {
			return $image_data;
		}

		$prompt   = $this->build_palm_summary_prompt( $lead, $quiz );
		$attempts = array( 'primary_with_image' );
		$started  = microtime( true );

		SM_Logger::log(
			'info',
			'AURA_VISION',
			'Starting vision analysis',
			array(
				'lead_id'    => isset( $lead['id'] ) ? $lead['id'] : 'unknown',
				'image_file' => basename( $image_path ),
			)
		);

		$response = $this->call_openai_api(
			$prompt,
			$image_data,
			$this->get_palm_summary_system_prompt(),
			true,
			array( 'type' => 'json_object' )
		);

		if ( is_wp_error( $response ) ) {
			SM_Logger::warning(
				'AURA_VISION',
				'Vision analysis failed (primary)',
				array(
					'lead_id'  => isset( $lead['id'] ) ? $lead['id'] : 'unknown',
					'error'    => $response->get_error_message(),
					'duration' => round( microtime( true ) - $started, 3 ),
				)
			);

			$retry = $this->call_openai_api(
				$prompt,
				$image_data,
				$this->get_palm_summary_rescue_system_prompt(),
				true,
				array( 'type' => 'json_object' )
			);

			if ( is_wp_error( $retry ) ) {
				SM_Logger::warning(
					'AURA_VISION',
					'Vision analysis failed (rescue)',
					array(
						'lead_id'  => isset( $lead['id'] ) ? $lead['id'] : 'unknown',
						'error'    => $retry->get_error_message(),
						'duration' => round( microtime( true ) - $started, 3 ),
					)
				);

				return new WP_Error(
					'palm_image_invalid',
					__( 'We could not clearly see your photo. Please go back and upload a clear shoulders-up photo.', 'mystic-aura-reading' ),
					array(
						'reason' => 'api_error',
					)
				);
			}

			$response = $retry;
		}

		SM_Logger::log(
			'info',
			'AURA_VISION',
			'Vision analysis completed',
			array(
				'lead_id'  => isset( $lead['id'] ) ? $lead['id'] : 'unknown',
				'duration' => round( microtime( true ) - $started, 3 ),
			)
		);

		$summary_data = $this->extract_palm_summary_json( $response );
		if ( is_wp_error( $summary_data ) ) {
			return $summary_data;
		}

		$invalid_reason = $this->get_invalid_palm_summary_reason( $summary_data );
		if ( '' !== $invalid_reason ) {
			$info = $this->get_palm_image_info( $image_path );
			SM_Logger::warning(
				'PALM_IMAGE_INVALID',
				'Palm summary rejected after image analysis',
				array(
					'lead_id'    => isset( $lead['id'] ) ? $lead['id'] : 'unknown',
					'reason'     => $invalid_reason,
					'image_file' => $info['file'],
					'image_size' => $info['size'],
					'width'      => $info['width'],
					'height'     => $info['height'],
				)
			);

			return new WP_Error(
				'palm_image_invalid',
				__( 'We could not clearly see your photo. Please go back and upload a clear shoulders-up photo.', 'mystic-aura-reading' ),
				array(
					'reason' => $invalid_reason,
				)
			);
		}

		if ( class_exists( 'SM_Lead_Handler' ) && ! empty( $lead['id'] ) ) {
			SM_Lead_Handler::get_instance()->update_lead(
				$lead['id'],
				array(
					'invalid_image_attempts' => 0,
					'invalid_image_locked'   => 0,
					'invalid_image_last_reason' => '',
					'invalid_image_last_at'  => null,
				)
			);
		}

		$summary_text = $this->format_palm_summary_text( $summary_data );

		SM_Logger::log(
			'info',
			'AI_READING',
			'Palm summary generated',
			array(
				'lead_id'       => isset( $lead['id'] ) ? $lead['id'] : 'unknown',
				'attempts'      => $attempts,
				'summary_words' => $this->count_words( $summary_text ),
			)
		);
		if ( $this->should_capture_openai_trace() ) {
			SM_Logger::log(
				'info',
				'OPENAI_TRACE',
				'Palm summary details',
				array(
					'lead_id'      => isset( $lead['id'] ) ? $lead['id'] : 'unknown',
					'summary_text' => $summary_text,
					'summary_data' => $summary_data,
				)
			);
		}

		return array(
			'summary_text' => $summary_text,
			'data'         => $summary_data,
		);
	}

	/**
	 * Fetch basic image metadata for logging.
	 *
	 * @param string $image_path Image path.
	 * @return array{file:string,size:int,width:int,height:int}
	 */
	private function get_palm_image_info( $image_path ) {
		$info = array(
			'file'   => '',
			'size'   => 0,
			'width'  => 0,
			'height' => 0,
		);

		if ( empty( $image_path ) ) {
			return $info;
		}

		$info['file'] = basename( $image_path );
		$info['size'] = file_exists( $image_path ) ? (int) filesize( $image_path ) : 0;

		$dimensions = @getimagesize( $image_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( is_array( $dimensions ) && isset( $dimensions[0], $dimensions[1] ) ) {
			$info['width']  = (int) $dimensions[0];
			$info['height'] = (int) $dimensions[1];
		}

		return $info;
	}

	/**
	 * Build the palm summary prompt (compact image analysis).
	 *
	 * Phase 2: Expanded to include palm_snapshot + opening + life_foundations content.
	 *
	 * @param array $lead Lead data.
	 * @param array $quiz Quiz data.
	 * @return string
	 */
	private function build_palm_summary_prompt( $lead, $quiz ) {
		$name       = sanitize_text_field( $lead['name'] );
		$gender     = isset( $lead['identity'] ) ? sanitize_text_field( $lead['identity'] ) : 'not specified';
		$timestamp  = gmdate( 'Y-m-d\TH:i:s\Z' );
		$quiz_block = $this->format_quiz_answers_summary( $quiz );

$template = <<<EOT
You are analyzing an aura photo (upper body or shoulders) and generating the foundation content for a personalized aura reading.

PHASE 2: This Vision call now generates aura analysis + first content sections (aura_snapshot + opening + life_foundations).

USER DATA:
- Name: {$name}
- Gender: {$gender}

QUIZ ANSWERS:
{$quiz_block}

IMAGE VALIDATION RULES:
1. First, assess whether the image shows a person (upper body, shoulders, or face).
2. If the image is clearly NOT a person (e.g., food, landscape, object), set hand_present="no" and action="proceed".
3. If the image shows a person but details are unclear, blurry, or partially visible, set hand_present="uncertain" and action="proceed".
4. If the image shows a clear person/upper body, set hand_present="yes" and action="proceed".
5. For uncertain images: provide observations based on what IS visible, avoid fabricating specifics, use gentle language like "Based on what is visible..."
6. For clear non-person images: keep observations minimal but still provide a best-effort aura snapshot.

AURA ANALYSIS INSTRUCTIONS:
- Focus on observable cues (posture, openness, facial expression, shoulder/neck tension, framing, light, and any obvious color impression).
- Keep analysis fields factual and specific; avoid medical or diagnostic claims.
- Provide best-effort interpretations grounded in visible cues.
- If details are faint, make a best-effort inference based on visible features only.
- Never respond with "None clearly visible" for signals; always provide 1-2 plausible signals.

CONTENT GENERATION INSTRUCTIONS:
- palm_snapshot: Friendly, user-facing aura snapshot (80-120 words). Reference presence, posture, and energy cues in natural sentences.
- your_hand_as_mirror (opening): Warm, mystical introduction that connects aura reading to {$name}'s journey. First paragraph introduces the reading; second explains this is a teaser.
- foundations_of_path (life_foundations): Deep analysis of energy patterns, emotional tone, and growth themes. Must be comprehensive and specific.
- Use {$name}'s name naturally 1-2 times across all content sections.
- Reference aura cues AND quiz answers directly in content sections.
- Keep tone warm, mystical, and specific to {$name}.
- Meet minimum word counts for every field.

REQUIRED JSON:
{
  "meta": {
    "user_name": "{$name}",
    "generated_at": "{$timestamp}"
  },
  "image_assessment": {
    "hand_present": "yes|no|uncertain",
    "confidence": 0.0-1.0,
    "reason": "Brief honest description of image quality or why it's not a person",
    "action": "proceed"
  },
  "hand_type": "Aura field shape or presence (1-2 sentences). If uncertain, describe visible posture/structure only.",
  "line_observations": {
    "life_line": "Vital flow - 1-2 sentences based on visible openness, steadiness, or tension.",
    "head_line": "Mind flow - 1-2 sentences based on focus, expression, or composure.",
    "heart_line": "Heart flow - 1-2 sentences based on warmth, softness, or guardedness.",
    "fate_line": "Purpose flow - 1-2 sentences based on directionality or groundedness."
  },
  "mounts": ["Energy center or focal point with brief descriptor", "Energy center or focal point with brief descriptor"],
  "markings": ["Distinct signal with brief descriptor", "Distinct signal with brief descriptor"],
  "overall_energy": "2-3 sentences based on visible cues",
  "palm_snapshot": "Target 80-120 words - aura snapshot summarizing key visible cues",
  "your_hand_as_mirror": {
    "reflection_p1": "Target 40-60 words - paragraph introducing aura reading and {$name}'s journey",
    "reflection_p2": "Target 40-60 words - paragraph explaining this is a teaser (full reading reveals more)"
  },
  "foundations_of_path": {
    "paragraph_1": "Target 100-130 words - deep analysis of energy patterns, resilience, and life trajectory",
    "paragraph_2": "Target 100-130 words - comprehensive analysis of emotional tone, relationship patterns, and growth themes",
    "paragraph_3": "Target 80-100 words - additional insight that completes the foundations with specific aura-based observations",
    "core_theme": "Target 50-70 words - core foundational insight that ties together the aura analysis"
  }
}

CRITICAL: Always include image_assessment field. Return ONLY valid JSON matching the schema above. No markdown, no code fences, no explanations - just the raw JSON object.
EOT;

		return $template;
	}

	/**
	 * Build a text-only fallback prompt when the image cannot be analyzed.
	 *
	 * @param array $lead Lead data.
	 * @param array $quiz Quiz data.
	 * @return string
	 */
	private function build_palm_summary_fallback_prompt( $lead, $quiz ) {
		$name       = sanitize_text_field( $lead['name'] );
		$quiz_block = $this->format_quiz_answers_summary( $quiz );
		$timestamp  = gmdate( 'Y-m-d\TH:i:s\Z' );

$template = <<<EOT
The aura photo could not be clearly analyzed. Provide a best-guess aura summary based on the user's quiz answers.

USER:
- Name: {$name}
- Quiz Summary:
{$quiz_block}

INSTRUCTIONS:
- Include an image_clarity_note that we can surface in the reading.
- Keep all fields factual and grounded; avoid poetic language.
- Return ONLY valid JSON matching the schema below.

REQUIRED JSON:
{
  "meta": {
    "user_name": "{$name}",
    "generated_at": "{$timestamp}"
  },
  "hand_type": "Aura field shape or presence (1-2 sentences)",
  "line_observations": {
    "life_line": "Vital flow - 1-2 sentences",
    "head_line": "Mind flow - 1-2 sentences",
    "heart_line": "Heart flow - 1-2 sentences",
    "fate_line": "Purpose flow - 1-2 sentences"
  },
  "mounts": ["Energy center 1", "Energy center 2", "Energy center 3"],
  "markings": ["Signal 1", "Signal 2"],
  "overall_energy": "2-3 sentences",
  "image_clarity_note": "1-2 sentences, gentle and reassuring"
}
EOT;

		return $template;
	}

	/**
	 * System prompt for palm summary extraction.
	 *
	 * @return string
	 */
	private function get_palm_summary_system_prompt() {
		return 'You are an aura analyst for entertainment. Extract concise, concrete observations from the photo of the person/upper body. Provide a best-effort summary even if the image is imperfect. Do not refuse or say you cannot determine. Return only valid JSON.';
	}

	/**
	 * System prompt for palm summary rescue retry.
	 *
	 * @return string
	 */
	private function get_palm_summary_rescue_system_prompt() {
		return 'You are an aura analyst for entertainment. Describe visible aura cues only (no identity or sensitive inferences). Provide a best-effort summary even if the image is imperfect. Return only valid JSON.';
	}

	/**
	 * System prompt for text-only palm summary fallback.
	 *
	 * @return string
	 */
	private function get_palm_summary_fallback_system_prompt() {
		return 'You are an aura analyst creating a best-guess summary when the image is unclear. Use the user context to infer plausible aura cues. Include the image_clarity_note field. Return only valid JSON.';
	}

	/**
	 * Extract and validate palm summary JSON.
	 *
	 * @param array $response OpenAI API response.
	 * @return array|WP_Error
	 */
	private function extract_palm_summary_json( $response ) {
		$content = $this->normalize_message_content( $response );
		$content = $this->strip_json_code_fences( $content );
		if ( '' === $content ) {
			return new WP_Error(
				'palm_summary_empty',
				__( 'Aura summary content empty.', 'mystic-aura-reading' )
			);
		}

		$data = json_decode( $content, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			list( $recovered_json, $recovered_string ) = $this->recover_json_from_text( $content );
			if ( null !== $recovered_json ) {
				$data = $recovered_json;
			}
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'palm_summary_invalid',
				__( 'Aura summary JSON invalid.', 'mystic-aura-reading' )
			);
		}

		return $data;
	}

	/**
	 * Return a non-empty reason if the palm summary is invalid.
	 *
	 * @param array $summary_data Palm summary data.
	 * @return string
	 */
	private function get_invalid_palm_summary_reason( $summary_data ) {
		if ( ! is_array( $summary_data ) ) {
			return 'invalid_json';
		}

		if ( ! empty( $summary_data['error'] ) ) {
			return sanitize_text_field( (string) $summary_data['error'] );
		}

		// Check for image_assessment (optional, Phase 1 feature)
		// If action="resubmit", treat as invalid and request new image
		if ( isset( $summary_data['image_assessment'] ) && is_array( $summary_data['image_assessment'] ) ) {
			$action = isset( $summary_data['image_assessment']['action'] ) ? $summary_data['image_assessment']['action'] : '';
			if ( 'resubmit' === $action ) {
				$reason = isset( $summary_data['image_assessment']['reason'] ) ? sanitize_text_field( $summary_data['image_assessment']['reason'] ) : 'Image not suitable for aura reading';
				SM_Logger::log(
					'warning',
					'PALM_IMAGE_INVALID',
					'Vision call requested resubmission',
					array(
						'hand_present' => isset( $summary_data['image_assessment']['hand_present'] ) ? $summary_data['image_assessment']['hand_present'] : 'unknown',
						'confidence'   => isset( $summary_data['image_assessment']['confidence'] ) ? $summary_data['image_assessment']['confidence'] : 0,
						'reason'       => $reason,
					)
				);
				return '';
			}
		}

		$missing = array();

		// Phase 2: Check for new content sections (palm_snapshot, opening, foundations)
		// These are optional with best-effort tolerance - log warnings but don't fail
		$missing_content = array();
		if ( empty( $summary_data['palm_snapshot'] ) ) {
			$missing_content[] = 'palm_snapshot';
		}
		if ( empty( $summary_data['your_hand_as_mirror'] ) || ! is_array( $summary_data['your_hand_as_mirror'] ) ) {
			$missing_content[] = 'your_hand_as_mirror';
		}
		if ( empty( $summary_data['foundations_of_path'] ) || ! is_array( $summary_data['foundations_of_path'] ) ) {
			$missing_content[] = 'foundations_of_path';
		}

		if ( ! empty( $missing_content ) ) {
			SM_Logger::log(
				'warning',
				'PALM_SUMMARY',
				'Vision call missing content sections (Phase 2)',
				array(
					'missing_content_sections' => $missing_content,
				)
			);
		}

		$missing = array_values( array_unique( $missing ) );
		if ( count( $missing ) > 2 ) {
			SM_Logger::log(
				'warning',
				'AURA_VISION',
				'Vision summary missing optional fields; continuing anyway',
				array(
					'missing_fields' => $missing,
				)
			);
		}

		return '';
	}

	/**
	 * Count missing required sections in teaser reading data (Phase 1 feature).
	 *
	 * @param array $reading_data Reading data.
	 * @return array Array of missing section names.
	 */
	private function count_missing_required_sections( $reading_data ) {
		if ( ! is_array( $reading_data ) ) {
			return array();
		}

		// Required sections for teaser (based on schema)
		$required_sections = array(
			'opening',
			'life_foundations',
			'love_patterns',
			'career_success',
			'personality_traits',
			'challenges_opportunities',
			'life_phase',
			'timeline_6_months',
			'guidance',
			'closing',
		);

		$missing = array();
		foreach ( $required_sections as $section_key ) {
			if ( empty( $reading_data[ $section_key ] ) || ! is_array( $reading_data[ $section_key ] ) ) {
				$missing[] = $section_key;
			} else {
				// Check if section has any actual content (not just placeholders)
				$has_content = false;
				foreach ( $reading_data[ $section_key ] as $field_key => $field_value ) {
					if ( 'placeholder_text' === $field_key ) {
						continue; // Skip placeholder fields
					}
					if ( ! empty( $field_value ) ) {
						$has_content = true;
						break;
					}
				}
				if ( ! $has_content ) {
					$missing[] = $section_key;
				}
			}
		}

		return $missing;
	}

	/**
	 * Normalize palm summary data for rendering.
	 *
	 * @param array $summary_data Palm summary data.
	 * @return array
	 */
	private function normalize_palm_summary_data( $summary_data ) {
		if ( empty( $summary_data ) || ! is_array( $summary_data ) ) {
			return array();
		}

		$normalized = array();

		if ( ! empty( $summary_data['hand_type'] ) ) {
			$normalized['hand_type'] = sanitize_text_field( (string) $summary_data['hand_type'] );
		}

		if ( ! empty( $summary_data['line_observations'] ) && is_array( $summary_data['line_observations'] ) ) {
			$lines = array();
			foreach ( array( 'life_line', 'head_line', 'heart_line', 'fate_line' ) as $line_key ) {
				if ( ! empty( $summary_data['line_observations'][ $line_key ] ) ) {
					$lines[ $line_key ] = sanitize_text_field( (string) $summary_data['line_observations'][ $line_key ] );
				}
			}
			if ( ! empty( $lines ) ) {
				$normalized['line_observations'] = $lines;
			}
		}

		if ( ! empty( $summary_data['mounts'] ) && is_array( $summary_data['mounts'] ) ) {
			$mounts = array();
			foreach ( $summary_data['mounts'] as $mount ) {
				$mount = trim( sanitize_text_field( (string) $mount ) );
				if ( '' !== $mount ) {
					$mounts[] = $mount;
				}
			}
			if ( ! empty( $mounts ) ) {
				$normalized['mounts'] = $mounts;
			}
		}

		if ( ! empty( $summary_data['markings'] ) && is_array( $summary_data['markings'] ) ) {
			$markings = array();
			foreach ( $summary_data['markings'] as $marking ) {
				$marking = trim( sanitize_text_field( (string) $marking ) );
				$normalized_marking = strtolower( $marking );
				if ( '' !== $marking && false === strpos( $normalized_marking, 'none clearly visible' ) ) {
					$markings[] = $marking;
				}
			}
			if ( ! empty( $markings ) ) {
				$normalized['markings'] = $markings;
			}
		}

		if ( empty( $normalized['markings'] ) ) {
			$normalized['markings'] = array(
				'Soft glow around the crown',
				'Gentle shimmer near the shoulders',
			);
		}

		if ( ! empty( $summary_data['overall_energy'] ) ) {
			$normalized['overall_energy'] = sanitize_text_field( (string) $summary_data['overall_energy'] );
		}

		if ( ! empty( $summary_data['image_clarity_note'] ) ) {
			$normalized['image_clarity_note'] = sanitize_text_field( (string) $summary_data['image_clarity_note'] );
		}

		// Preserve image_assessment if present (Phase 1 feature, optional)
		if ( isset( $summary_data['image_assessment'] ) && is_array( $summary_data['image_assessment'] ) ) {
			$assessment = array();
			if ( isset( $summary_data['image_assessment']['hand_present'] ) ) {
				$assessment['hand_present'] = sanitize_text_field( (string) $summary_data['image_assessment']['hand_present'] );
			}
			if ( isset( $summary_data['image_assessment']['confidence'] ) ) {
				$assessment['confidence'] = floatval( $summary_data['image_assessment']['confidence'] );
			}
			if ( isset( $summary_data['image_assessment']['reason'] ) ) {
				$assessment['reason'] = sanitize_text_field( (string) $summary_data['image_assessment']['reason'] );
			}
			if ( isset( $summary_data['image_assessment']['action'] ) ) {
				$assessment['action'] = sanitize_text_field( (string) $summary_data['image_assessment']['action'] );
			}
			if ( ! empty( $assessment ) ) {
				$normalized['image_assessment'] = $assessment;
			}
		}

		// Phase 2: Preserve new content sections (palm_snapshot, opening, foundations)
		if ( ! empty( $summary_data['palm_snapshot'] ) ) {
			$normalized['palm_snapshot'] = sanitize_textarea_field( (string) $summary_data['palm_snapshot'] );
		}

		if ( isset( $summary_data['your_hand_as_mirror'] ) && is_array( $summary_data['your_hand_as_mirror'] ) ) {
			$opening = array();
			if ( isset( $summary_data['your_hand_as_mirror']['reflection_p1'] ) ) {
				$opening['reflection_p1'] = sanitize_textarea_field( (string) $summary_data['your_hand_as_mirror']['reflection_p1'] );
			}
			if ( isset( $summary_data['your_hand_as_mirror']['reflection_p2'] ) ) {
				$opening['reflection_p2'] = sanitize_textarea_field( (string) $summary_data['your_hand_as_mirror']['reflection_p2'] );
			}
			if ( ! empty( $opening ) ) {
				$normalized['your_hand_as_mirror'] = $opening;
			}
		}

		if ( isset( $summary_data['foundations_of_path'] ) && is_array( $summary_data['foundations_of_path'] ) ) {
			$foundations = array();
			if ( isset( $summary_data['foundations_of_path']['paragraph_1'] ) ) {
				$foundations['paragraph_1'] = sanitize_textarea_field( (string) $summary_data['foundations_of_path']['paragraph_1'] );
			}
			if ( isset( $summary_data['foundations_of_path']['paragraph_2'] ) ) {
				$foundations['paragraph_2'] = sanitize_textarea_field( (string) $summary_data['foundations_of_path']['paragraph_2'] );
			}
			if ( isset( $summary_data['foundations_of_path']['paragraph_3'] ) ) {
				$foundations['paragraph_3'] = sanitize_textarea_field( (string) $summary_data['foundations_of_path']['paragraph_3'] );
			}
			if ( isset( $summary_data['foundations_of_path']['core_theme'] ) ) {
				$foundations['core_theme'] = sanitize_textarea_field( (string) $summary_data['foundations_of_path']['core_theme'] );
			}
			if ( ! empty( $foundations ) ) {
				$normalized['foundations_of_path'] = $foundations;
			}
		}

		return $normalized;
	}

	/**
	 * Format palm summary JSON into a compact text block for downstream prompts.
	 *
	 * @param array $summary_data Palm summary data.
	 * @return string
	 */
	private function format_palm_summary_text( $summary_data ) {
		if ( empty( $summary_data ) || ! is_array( $summary_data ) ) {
			return '';
		}

		$lines = array();
		if ( ! empty( $summary_data['hand_type'] ) ) {
			$lines[] = 'Aura field: ' . trim( (string) $summary_data['hand_type'] );
		}
		if ( ! empty( $summary_data['line_observations'] ) && is_array( $summary_data['line_observations'] ) ) {
			$line_parts = array();
			$line_labels = array(
				'life_line'  => 'Vital flow',
				'head_line'  => 'Mind flow',
				'heart_line' => 'Heart flow',
				'fate_line'  => 'Purpose flow',
			);
			foreach ( $line_labels as $line_key => $label ) {
				if ( ! empty( $summary_data['line_observations'][ $line_key ] ) ) {
					$line_parts[] = ucfirst( $label ) . ': ' . trim( (string) $summary_data['line_observations'][ $line_key ] );
				}
			}
			if ( ! empty( $line_parts ) ) {
				$lines[] = 'Energy flows: ' . implode( ' | ', $line_parts );
			}
		}
		if ( ! empty( $summary_data['mounts'] ) && is_array( $summary_data['mounts'] ) ) {
			$lines[] = 'Centers: ' . implode( ', ', array_map( 'sanitize_text_field', $summary_data['mounts'] ) );
		}
		if ( ! empty( $summary_data['markings'] ) && is_array( $summary_data['markings'] ) ) {
			$lines[] = 'Signals: ' . implode( ', ', array_map( 'sanitize_text_field', $summary_data['markings'] ) );
		}
		if ( ! empty( $summary_data['overall_energy'] ) ) {
			$lines[] = 'Overall energy: ' . trim( (string) $summary_data['overall_energy'] );
		}
		if ( ! empty( $summary_data['image_clarity_note'] ) ) {
			$lines[] = 'Image clarity: ' . trim( (string) $summary_data['image_clarity_note'] );
		}

		return implode( "\n", $lines );
	}

	/**
	 * Call OpenAI API
	 *
	 * @param string $prompt Text prompt.
	 * @param string $image_data Base64 encoded image.
	 * @param string $system_prompt System prompt override.
	 * @param bool   $use_image Whether to include the image in the request.
	 * @return array|WP_Error API response or error.
	 */
	private function call_openai_api( $prompt, $image_data, $system_prompt = '', $use_image = true, $response_format = null ) {
		$api_key = SM_Settings::init()->get_openai_api_key();

		if ( empty( $api_key ) && ! SM_Dev_Mode::should_mock_openai() ) {
			SM_Logger::log( 'error', 'AI_READING', 'OpenAI API key not configured', array() );
			return new WP_Error(
				'api_key_missing',
				__( 'OpenAI API key not configured.', 'mystic-aura-reading' )
			);
		}

		// Build request body
		$system_prompt = $system_prompt ? $system_prompt : $this->get_system_prompt();

		$user_content = array(
			array(
				'type' => 'text',
				'text' => $prompt,
			),
		);

		if ( $use_image && ! empty( $image_data ) ) {
			$user_content[] = array(
				'type'      => 'image_url',
				'image_url' => array(
					'url'    => $image_data,
					'detail' => 'high',
				),
			);
		}

		$body = array(
			'model'      => $this->model,
			'messages'   => array(
				array(
					'role'    => 'system',
					'content' => $system_prompt,
				),
				array(
					'role'    => 'user',
					'content' => $user_content,
				),
			),
			'max_tokens'  => $this->max_tokens,
			'temperature' => $this->temperature,
		);
		if ( ! empty( $response_format ) ) {
			$body['response_format'] = $response_format;
		}

		$mock_openai      = SM_Dev_Mode::should_mock_openai();
		$dev_mode_enabled = $mock_openai;
		$request_id       = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'sm-openai-', true );

		// Check if DevMode is enabled - use mock endpoint instead of real OpenAI
		if ( $mock_openai ) {
			SM_Logger::log(
				'warning',
				'AI_READING',
				'DevMode enabled - using mock OpenAI endpoint',
				array(
					'prompt_length'       => strlen( $prompt ),
					'system_prompt_length'=> strlen( $system_prompt ),
					'use_image'           => (bool) $use_image,
					'dev_mode'            => SM_Dev_Mode::get_mode(),
				)
			);

			$endpoint = SM_Dev_Mode::get_mock_openai_url();
		} else {
			SM_Logger::log(
				'info',
				'AI_READING',
				'OpenAI request payload prepared',
				array(
					'model'               => $this->model,
					'max_tokens'          => $this->max_tokens,
					'temperature'         => $this->temperature,
					'prompt_length'       => strlen( $prompt ),
					'system_prompt_length'=> strlen( $system_prompt ),
					'use_image'           => (bool) $use_image,
					'image_data_length'   => $use_image && $image_data ? strlen( $image_data ) : 0,
				)
			);

			$endpoint = $this->api_endpoint;
		}

		$request_args = array(
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . ( $api_key ?: 'mock-key' ),
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 60, // 60 seconds timeout for longer AI generation
		);

		// Allow self-signed certificates when calling local mock endpoints in DevMode.
		if ( $dev_mode_enabled ) {
			$request_args['sslverify'] = false;
		}

		if ( $this->should_capture_openai_trace() ) {
			SM_Logger::log(
				'info',
				'OPENAI_TRACE',
				'OpenAI request captured',
				array(
					'request_id' => $request_id,
					'request'    => $this->build_openai_trace_request( $endpoint, $body, $use_image, $image_data, $response_format ),
				)
			);
		}

		$request_started = microtime( true );

		// Make API request (either to real OpenAI or mock endpoint)
		$response = wp_remote_post(
			$endpoint,
			$request_args
		);
		$duration_ms = ( microtime( true ) - $request_started ) * 1000;

		// Check for HTTP errors
		if ( is_wp_error( $response ) ) {
			SM_Logger::log( 'error', 'AI_READING', 'OpenAI API request failed', array(
				'error' => $response->get_error_message(),
			) );
			return new WP_Error(
				'api_request_failed',
				__( 'Could not connect to AI service. Please try again.', 'mystic-aura-reading' )
			);
		}

		$status_code   = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );

		// Check for API errors
		if ( $status_code !== 200 ) {
			$error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown error';
			SM_Logger::log( 'error', 'AI_READING', 'OpenAI API error', array(
				'status_code' => $status_code,
				'error'       => $error_message,
				'body'        => $this->summarize_body( $response_body ),
			) );
			if ( $this->should_capture_openai_trace() ) {
				SM_Logger::log(
					'info',
					'OPENAI_TRACE',
					'OpenAI error response captured',
					array(
						'request_id' => $request_id,
						'response'   => $this->build_openai_trace_response( $status_code, $response_body, $data, $duration_ms ),
					)
				);
			}
			return new WP_Error(
				'api_error',
				__( 'AI service returned an error. Please try again.', 'mystic-aura-reading' )
			);
		}

		// Validate response structure
		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			SM_Logger::log(
				'error',
				'AI_READING',
				'Invalid API response structure',
				array(
					'status_code' => $status_code,
					'body'        => $this->summarize_body( $response_body ),
				)
			);
			if ( $this->should_capture_openai_trace() ) {
				SM_Logger::log(
					'info',
					'OPENAI_TRACE',
					'OpenAI invalid response captured',
					array(
						'request_id' => $request_id,
						'response'   => $this->build_openai_trace_response( $status_code, $response_body, $data, $duration_ms ),
					)
				);
			}
			return new WP_Error(
				'invalid_response',
				__( 'Received invalid response from AI service.', 'mystic-aura-reading' )
			);
		}

		if ( $this->should_capture_openai_trace() ) {
			SM_Logger::log(
				'info',
				'OPENAI_TRACE',
				'OpenAI response captured',
				array(
					'request_id' => $request_id,
					'response'   => $this->build_openai_trace_response( $status_code, $response_body, $data, $duration_ms ),
				)
			);
		}

		// Increment API call counter and log
		$this->openai_call_count++;
		SM_Logger::log(
			'info',
			'AI_READING',
			'OpenAI API call completed',
			array(
				'call_number'     => $this->openai_call_count,
				'duration_ms'     => round( $duration_ms, 2 ),
				'tokens_used'     => isset( $data['usage']['total_tokens'] ) ? $data['usage']['total_tokens'] : 0,
				'prompt_tokens'   => isset( $data['usage']['prompt_tokens'] ) ? $data['usage']['prompt_tokens'] : 0,
				'completion_tokens' => isset( $data['usage']['completion_tokens'] ) ? $data['usage']['completion_tokens'] : 0,
				'force_log'       => true,
			)
		);

		return $data;
	}

	/**
	 * Summarize a raw response body for logging without dumping huge payloads.
	 *
	 * @param string $body Raw response.
	 * @return string
	 */
	private function summarize_body( $body ) {
		if ( empty( $body ) ) {
			return '';
		}

		$max_len = 500;
		if ( strlen( $body ) > $max_len ) {
			return substr( $body, 0, $max_len ) . '... [truncated]';
		}

		return $body;
	}

	/**
	 * Whether to capture detailed OpenAI request/response traces.
	 *
	 * @return bool
	 */
	private function should_capture_openai_trace() {
		return defined( 'SM_OPENAI_TRACE' ) && SM_OPENAI_TRACE;
	}

	/**
	 * Build a redacted OpenAI request payload for trace logging.
	 *
	 * @param string     $endpoint Endpoint URL.
	 * @param array      $body Request body.
	 * @param bool       $use_image Whether image was used.
	 * @param string     $image_data Base64 data url.
	 * @param array|null $response_format Response format.
	 * @return array
	 */
	private function build_openai_trace_request( $endpoint, $body, $use_image, $image_data, $response_format ) {
		$image_hash = '';
		if ( $use_image && ! empty( $image_data ) ) {
			$image_hash = substr( sha1( $image_data ), 0, 12 );
		}

		$sanitized_body = $body;
		if ( $use_image && ! empty( $sanitized_body['messages'][1]['content'] ) && is_array( $sanitized_body['messages'][1]['content'] ) ) {
			foreach ( $sanitized_body['messages'][1]['content'] as &$part ) {
				if ( isset( $part['type'] ) && 'image_url' === $part['type'] ) {
					$part['image_url']['url'] = '{{PALM_IMAGE_DATA_URL}}';
					$part['image_url']['detail'] = isset( $part['image_url']['detail'] ) ? $part['image_url']['detail'] : 'high';
				}
			}
			unset( $part );
		}

		return array(
			'endpoint'          => $endpoint,
			'headers'           => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer {{OPENAI_API_KEY}}',
			),
			'body'              => $sanitized_body,
			'response_format'   => $response_format,
			'use_image'         => (bool) $use_image,
			'image_data_length' => $use_image && $image_data ? strlen( $image_data ) : 0,
			'image_hash'        => $image_hash,
		);
	}

	/**
	 * Build a response summary for trace logging.
	 *
	 * @param int    $status_code HTTP status code.
	 * @param string $response_body Raw response body.
	 * @param array  $data Parsed response data.
	 * @param float  $duration_ms Request duration in ms.
	 * @return array
	 */
	private function build_openai_trace_response( $status_code, $response_body, $data, $duration_ms ) {
		$content      = $this->normalize_message_content( $data );
		$content_text = $content ? wp_strip_all_tags( $content ) : '';
		$word_count   = $content_text ? str_word_count( $content_text ) : 0;

		return array(
			'status_code'      => $status_code,
			'duration_ms'      => round( $duration_ms, 2 ),
			'response_id'      => isset( $data['id'] ) ? $data['id'] : '',
			'model'            => isset( $data['model'] ) ? $data['model'] : '',
			'usage'            => isset( $data['usage'] ) ? $data['usage'] : array(),
			'content_length'   => $content ? strlen( $content ) : 0,
			'content_words'    => $word_count,
			'refusal_detected' => $content ? $this->looks_like_refusal_text( $content ) : false,
			'body_excerpt'     => $this->summarize_body( $response_body ),
			'content_excerpt'  => $content ? substr( $content, 0, 500 ) : '',
		);
	}

	/**
	 * Extract and validate JSON from API response (Teaser Reading System).
	 *
	 * @param array $response OpenAI API response.
	 * @return array|WP_Error Validated JSON data or error.
	 */
	private function extract_and_validate_json( $response ) {
		$content      = $this->normalize_message_content( $response );
		$raw_length   = strlen( $content );
		$content      = $this->strip_json_code_fences( $content );
		$clean_length = strlen( $content );

		if ( $content === '' ) {
			SM_Logger::log(
				'error',
				'AI_READING',
				'OpenAI response content empty after normalization',
				array(
					'raw_length'   => $raw_length,
					'clean_length' => $clean_length,
				)
			);
			return new WP_Error(
				'json_parse_error',
				__( 'Could not parse reading data. Please try again.', 'mystic-aura-reading' )
			);
		}

		if ( $this->looks_like_refusal_text( $content ) ) {
			SM_Logger::log(
				'error',
				'AI_READING',
				'OpenAI refused JSON response',
				array(
					'content_start' => substr( $content, 0, 200 ),
					'raw_length'    => $raw_length,
					'clean_length'  => $clean_length,
				)
			);
			return new WP_Error(
				'ai_refusal',
				__( 'AI service refused the request. Please try again.', 'mystic-aura-reading' )
			);
		}

		// Decode JSON
		$data = json_decode( $content, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			list( $recovered_json, $recovered_string ) = $this->recover_json_from_text( $content );

			if ( null !== $recovered_json ) {
				SM_Logger::log(
					'warning',
					'AI_READING',
					'Recovered JSON after trimming surrounding text',
					array(
						'raw_length'      => $raw_length,
						'clean_length'    => $clean_length,
						'recovered_length'=> strlen( $recovered_string ),
					)
				);
				$content = $recovered_string;
				$data    = $recovered_json;
			}
		}

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			SM_Logger::log(
				'error',
				'AI_READING',
				'Failed to parse JSON from OpenAI response',
				array(
					'error'         => json_last_error_msg(),
					'content_start' => substr( $content, 0, 200 ),
					'raw_length'    => $raw_length,
					'clean_length'  => $clean_length,
				)
			);
			return new WP_Error(
				'json_parse_error',
				__( 'Could not parse reading data. Please try again.', 'mystic-aura-reading' )
			);
		}

		// Validate against schema (RELAXED MODE - accepts with warnings)
		$validated = SM_Teaser_Reading_Schema::validate_response( $data );

		if ( is_wp_error( $validated ) ) {
			// Only reject if it's a critical structural error (not just word count issues)
			$error_data = $validated->get_error_data();
			$detailed_errors = is_array( $error_data ) && isset( $error_data['errors'] ) ? $error_data['errors'] : array();

			SM_Logger::log(
				'error',
				'AI_READING',
				'JSON schema validation failed - CRITICAL structural error',
				array(
					'error'  => $validated->get_error_message(),
					'errors' => $validated->get_error_data(),
					'detailed_errors' => $detailed_errors,
					'raw_json_preview' => substr( $content, 0, 500 ),
					'json_structure' => array_keys( $data ),
				)
			);

			error_log( '[SM AI] CRITICAL schema validation failed. Errors: ' . print_r( $detailed_errors, true ) );

			return $validated;
		}

		SM_Logger::log(
			'info',
			'AI_READING',
			'JSON validated and ACCEPTED (relaxed mode - may have warnings)',
			array(
				'sections' => array_keys( $validated ),
			)
		);

		return $validated;
	}

	/**
	 * Extract and validate JSON for paid completion (Phase 2).
	 *
	 * @param array $response OpenAI API response.
	 * @return array|WP_Error
	 */
	private function extract_and_validate_paid_completion_json( $response ) {
		$content    = $this->normalize_message_content( $response );
		$raw_length = strlen( $content );
		$content    = $this->strip_json_code_fences( $content );

		if ( '' === $content ) {
			return new WP_Error(
				'json_parse_error',
				__( 'Could not parse paid reading data. Please try again.', 'mystic-aura-reading' )
			);
		}

		if ( $this->looks_like_refusal_text( $content ) ) {
			return new WP_Error(
				'ai_refusal',
				__( 'AI service refused the request. Please try again.', 'mystic-aura-reading' )
			);
		}

		$data = json_decode( $content, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			list( $recovered_json, $recovered_string ) = $this->recover_json_from_text( $content );
			if ( null !== $recovered_json ) {
				$content = $recovered_string;
				$data    = $recovered_json;
			}
		}

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			SM_Logger::log(
				'error',
				'AI_READING',
				'Failed to parse paid JSON from OpenAI response',
				array(
					'error'         => json_last_error_msg(),
					'content_start' => substr( $content, 0, 200 ),
					'raw_length'    => $raw_length,
				)
			);
			return new WP_Error(
				'json_parse_error',
				__( 'Could not parse paid reading data. Please try again.', 'mystic-aura-reading' )
			);
		}

		$validation = $this->validate_paid_completion_data( $data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		return $data;
	}

	/**
	 * Validate paid completion data structure (lightweight).
	 *
	 * @param array $data Paid completion JSON data.
	 * @return true|WP_Error
	 */
	private function validate_paid_completion_data( $data ) {
		$required_sections = array(
			'love_patterns',
			'challenges_opportunities',
			'life_phase',
			'timeline_6_months',
			'guidance',
			'deep_relationship_analysis',
			'extended_timeline_12_months',
			'life_purpose_soul_mission',
			'shadow_work_transformation',
			'practical_guidance_action_plan',
		);

		foreach ( $required_sections as $section ) {
			if ( empty( $data[ $section ] ) || ! is_array( $data[ $section ] ) ) {
				return new WP_Error(
					'paid_schema_invalid',
					__( 'Paid reading response missing required sections.', 'mystic-aura-reading' ),
					array( 'missing_section' => $section )
				);
			}
		}

		$required_fields = array(
			'love_patterns'               => 'locked_full',
			'challenges_opportunities'    => 'locked_full',
			'life_phase'                  => 'locked_full',
			'timeline_6_months'           => 'locked_full',
			'guidance'                    => 'locked_full',
			'deep_relationship_analysis'  => 'full_content',
			'extended_timeline_12_months' => 'full_content',
			'life_purpose_soul_mission'   => 'full_content',
			'shadow_work_transformation'  => 'full_content',
			'practical_guidance_action_plan' => 'full_content',
		);

		foreach ( $required_fields as $section => $field ) {
			if ( empty( $data[ $section ][ $field ] ) ) {
				return new WP_Error(
					'paid_schema_invalid',
					__( 'Paid reading response missing required fields.', 'mystic-aura-reading' ),
					array(
						'section' => $section,
						'field'   => $field,
					)
				);
			}
		}

		return true;
	}

	/**
	 * Normalize assistant message content into a single string.
	 *
	 * Handles both string content and the newer OpenAI array-of-parts response.
	 *
	 * @param array $response OpenAI API response.
	 * @return string
	 */
	private function normalize_message_content( $response ) {
		$content = isset( $response['choices'][0]['message']['content'] ) ? $response['choices'][0]['message']['content'] : '';

		// Newer responses may return an array of content blocks
		if ( is_array( $content ) ) {
			$segments = array();
			foreach ( $content as $part ) {
				if ( is_string( $part ) ) {
					$segments[] = $part;
					continue;
				}

				if ( is_array( $part ) ) {
					if ( isset( $part['text']['value'] ) ) {
						$segments[] = $part['text']['value'];
						continue;
					}

					if ( isset( $part['text'] ) && is_string( $part['text'] ) ) {
						$segments[] = $part['text'];
						continue;
					}

					if ( isset( $part['value'] ) && is_string( $part['value'] ) ) {
						$segments[] = $part['value'];
						continue;
					}
				}
			}

			$content = implode( "\n", array_filter( $segments ) );
		}

		if ( ! is_string( $content ) ) {
			return '';
		}

		return trim( $content );
	}

	/**
	 * Strip common markdown fences around JSON payloads.
	 *
	 * @param string $content Raw content string.
	 * @return string
	 */
	private function strip_json_code_fences( $content ) {
		if ( '' === $content ) {
			return '';
		}

		$content = preg_replace( '/```json\s*(.*?)\s*```/s', '$1', $content );
		$content = preg_replace( '/```\s*(.*?)\s*```/s', '$1', $content );

		return trim( (string) $content );
	}

	/**
	 * Attempt to recover a JSON object from text with surrounding noise.
	 *
	 * @param string $content Raw content string (potentially with prefixes/suffixes).
	 * @return array{0:?array,1:?string} Tuple of decoded JSON and the string slice used.
	 */
	private function recover_json_from_text( $content ) {
		$start = strpos( $content, '{' );
		$end   = strrpos( $content, '}' );

		if ( $start === false || $end === false || $end <= $start ) {
			return array( null, null );
		}

		$json_string = substr( $content, $start, ( $end - $start ) + 1 );
		$decoded     = json_decode( $json_string, true );

		if ( json_last_error() === JSON_ERROR_NONE ) {
			return array( $decoded, $json_string );
		}

		return array( null, null );
	}

	/**
	 * Extract and sanitize HTML from API response
	 *
	 * @param array $response OpenAI API response.
	 * @return string|WP_Error Sanitized HTML or error.
	 */
	private function extract_and_sanitize_html( $response ) {
		$content = $response['choices'][0]['message']['content'];

		// Remove markdown code blocks if present
		$content = preg_replace( '/```html\s*(.*?)\s*```/s', '$1', $content );
		$content = preg_replace( '/```\s*(.*?)\s*```/s', '$1', $content );

		// Sanitize HTML with allowed tags only
		$sanitized = wp_kses( $content, $this->allowed_html );

		if ( empty( $sanitized ) ) {
			SM_Logger::log( 'error', 'AI_READING', 'HTML sanitization resulted in empty content', array() );
			return new WP_Error(
				'sanitization_failed',
				__( 'Could not process the reading content.', 'mystic-aura-reading' )
			);
		}

		return $sanitized;
	}

	/**
	 * Count words in HTML content
	 *
	 * @param string $html HTML content.
	 * @return int Word count.
	 */
	private function count_words( $html ) {
		$text = wp_strip_all_tags( $html );
		$words = str_word_count( $text );
		return $words;
	}

	/**
	 * System prompt to reduce refusals and enforce structure.
	 *
	 * @return string
	 */
	private function get_system_prompt() {
		return implode(
			"\n",
			array(
				'You are SoulMirror, a warm, mystical aura-reading guide.',
				'Always provide a 750-1000 word aura reading in friendly, encouraging language that feels deeply personalized.',
				'You can perceive and interpret the provided photo of the person/upper body; never say you cannot see or analyze images.',
				'WEAVE IN the person\'s energy level, life focus, element, spiritual intentions, and future goals throughout the reading.',
				'Never refuse, apologize, or mention safety/policy limitations; if information is limited, provide a best-effort mystical reading anyway.',
				'Output clean HTML only using <h4>, <p>, <strong>, <em>; absolutely NO <ul> or <li> tags, no code fences, no markdown.',
				'Avoid medical or legal advice and deterministic claims; focus on supportive guidance.',
			)
		);
	}

	/**
	 * System prompt for JSON teaser reading generation (NEW - Teaser Reading System).
	 *
	 * @return string
	 */
	private function get_teaser_system_prompt() {
		return implode(
			"\n",
			array(
				'You are a warm, insightful aura reader for SoulMirror.',
				'Analyze aura cues and integrate quiz answers for specificity.',
				'Write in 2nd person with a grounded, mystical tone.',
				'Return ONLY valid JSON matching the schema (no markdown, no extra keys).',
				'',
				'CRITICAL RULES:',
				'1. Every field must meet the minimum word count in the schema.',
				'2. Use full sentences; avoid fragments.',
				'3. Use the user\'s name naturally 1-2 times.',
				'4. Keep locked_teaser entries enticing but incomplete.',
				'5. Never refuse or mention limitations.',
			)
		);
	}

	/**
	 * Rescue system prompt for JSON teaser generation when refusals occur.
	 *
	 * @return string
	 */
	private function get_teaser_rescue_system_prompt() {
		return implode(
			"\n",
			array(
				'You are a creative writer producing a fictional aura reading for entertainment and reflection.',
				'Do not refuse, apologize, or mention policy limitations.',
				'If aura details are unclear or unavailable, invent plausible aura cues without saying so.',
				'Never provide medical, legal, or financial advice or deterministic predictions.',
				'Meet all minimum word counts in the schema; expand sections until targets are met.',
				'Return ONLY valid JSON matching the schema; no markdown, no extra text.',
			)
		);
	}

	/**
	 * Expansion system prompt for short JSON teaser responses.
	 *
	 * @return string
	 */
	private function get_teaser_expansion_system_prompt() {
		return implode(
			"\n",
			array(
				'You are a creative aura reader expanding a teaser reading to meet strict minimum word counts.',
				'Rewrite and expand each section to satisfy the target ranges in the schema.',
				'Do not remove fields or change structure; only expand content.',
				'Use the user\'s name naturally 2-3 times.',
				'Integrate quiz answers and aura cues explicitly.',
				'Return ONLY valid JSON matching the schema; no markdown, no extra text.',
			)
		);
	}

	/**
	 * Generate a teaser payload part (core or secondary) with retries.
	 *
	 * @param string $prompt Prompt text.
	 * @param string $lead_id Lead ID.
	 * @param string $label Part label.
	 * @param array  $attempts Attempts log array.
	 * @param array  $last_response Last response reference.
	 * @return array|WP_Error
	 */
	private function generate_teaser_part( $prompt, $lead_id, $label, &$attempts, &$last_response ) {
		SM_Logger::log(
			'info',
			'AI_READING',
			'Calling OpenAI API for teaser part (JSON)',
			array(
				'lead_id' => $lead_id,
				'model'   => $this->model,
				'part'    => $label,
			)
		);

		$attempts[] = $label . '_primary';
		$response = $this->call_openai_api(
			$prompt,
			'',
			$this->get_teaser_system_prompt(),
			false,
			array( 'type' => 'json_object' )
		);

		if ( is_wp_error( $response ) ) {
			$attempts[] = $label . '_retry';
			SM_Logger::log(
				'warning',
				'AI_READING',
				'Teaser part failed, retrying',
				array( 'lead_id' => $lead_id, 'part' => $label )
			);
			$response = $this->call_openai_api(
				$prompt,
				'',
				$this->get_teaser_system_prompt(),
				false,
				array( 'type' => 'json_object' )
			);
			if ( is_wp_error( $response ) ) {
				return $response;
			}
		}

		$data = $this->extract_json_response( $response );
		if ( is_wp_error( $data ) ) {
			$attempts[] = $label . '_rescue';
			SM_Logger::log(
				'warning',
				'AI_READING',
				'Teaser part JSON parse failed, retrying with rescue prompt',
				array( 'lead_id' => $lead_id, 'part' => $label )
			);
			$response = $this->call_openai_api(
				$prompt,
				'',
				$this->get_teaser_rescue_system_prompt(),
				false,
				array( 'type' => 'json_object' )
			);
			if ( ! is_wp_error( $response ) ) {
				$data = $this->extract_json_response( $response );
			}
			if ( is_wp_error( $data ) ) {
				return $data;
			}
		}

		$last_response = $response;

		return $data;
	}

	/**
	 * Extract JSON from OpenAI response without schema validation.
	 *
	 * @param array $response OpenAI API response.
	 * @return array|WP_Error
	 */
	private function extract_json_response( $response ) {
		$content    = $this->normalize_message_content( $response );
		$raw_length = strlen( $content );
		$content    = $this->strip_json_code_fences( $content );

		if ( '' === $content ) {
			return new WP_Error(
				'json_parse_error',
				__( 'Could not parse reading data. Please try again.', 'mystic-aura-reading' )
			);
		}

		if ( $this->looks_like_refusal_text( $content ) ) {
			return new WP_Error(
				'ai_refusal',
				__( 'AI service refused the request. Please try again.', 'mystic-aura-reading' )
			);
		}

		$data = json_decode( $content, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			list( $recovered_json, $recovered_string ) = $this->recover_json_from_text( $content );
			if ( null !== $recovered_json ) {
				$content = $recovered_string;
				$data    = $recovered_json;
			}
		}

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			SM_Logger::log(
				'error',
				'AI_READING',
				'Failed to parse JSON from OpenAI response (partial)',
				array(
					'error'         => json_last_error_msg(),
					'content_start' => substr( $content, 0, 200 ),
					'raw_length'    => $raw_length,
				)
			);
			return new WP_Error(
				'json_parse_error',
				__( 'Could not parse reading data. Please try again.', 'mystic-aura-reading' )
			);
		}

		return $data;
	}

	/**
	 * Expand short teaser sections in-place using a targeted prompt.
	 *
	 * @param array  $lead Lead data.
	 * @param array  $quiz Quiz data.
	 * @param string $palm_summary_text Palm summary text.
	 * @param array  $reading_data Current teaser payload.
	 * @param array  $attempts Attempts log array.
	 * @param array  $last_response Last response reference.
	 * @return array|WP_Error
	 */
	private function expand_short_teaser_sections( $lead, $quiz, $palm_summary_text, $reading_data, &$attempts, &$last_response ) {
		$short_fields = $this->get_teaser_short_fields_by_schema( $reading_data );
		if ( empty( $short_fields ) ) {
			return $reading_data;
		}

		$payload = array();
		foreach ( array_keys( $short_fields ) as $path ) {
			$parts = explode( '.', $path );
			$section = $parts[0];
			if ( isset( $reading_data[ $section ] ) ) {
				$payload[ $section ] = $reading_data[ $section ];
			}
		}

		if ( empty( $payload ) ) {
			return $reading_data;
		}

		$attempts[] = 'expand_short_sections';
		$prompt = $this->build_teaser_expand_prompt( $lead, $quiz, $palm_summary_text, $payload );
		SM_Logger::log(
			'warning',
			'AI_READING',
			'Expanding short teaser sections',
			array(
				'lead_id'        => isset( $lead['id'] ) ? $lead['id'] : 'unknown',
				'short_sections' => $short_fields,
				'sections'       => array_keys( $payload ),
			)
		);

		$response = $this->call_openai_api(
			$prompt,
			'',
			$this->get_teaser_expansion_system_prompt(),
			false,
			array( 'type' => 'json_object' )
		);
		if ( is_wp_error( $response ) ) {
			return $reading_data;
		}

		$updated = $this->extract_json_response( $response );
		if ( is_wp_error( $updated ) ) {
			return $reading_data;
		}

		foreach ( $updated as $key => $value ) {
			if ( is_array( $value ) ) {
				$reading_data[ $key ] = $value;
			}
		}

		$last_response = $response;

		return $reading_data;
	}

	/**
	 * Ensure teaser payload has required meta and placeholder sections.
	 *
	 * @param array $reading_data Teaser payload.
	 * @param array $lead Lead data.
	 * @return array
	 */
	private function ensure_teaser_payload_sections( $reading_data, $lead ) {
		if ( ! is_array( $reading_data ) ) {
			$reading_data = array();
		}

		$name = isset( $lead['name'] ) ? sanitize_text_field( $lead['name'] ) : __( 'Seeker', 'mystic-aura-reading' );
		$reading_data['meta'] = isset( $reading_data['meta'] ) && is_array( $reading_data['meta'] ) ? $reading_data['meta'] : array();
		$reading_data['meta']['user_name'] = $name;
		$reading_data['meta']['generated_at'] = isset( $reading_data['meta']['generated_at'] ) ? $reading_data['meta']['generated_at'] : gmdate( 'Y-m-d\TH:i:s\Z' );
		$reading_data['meta']['reading_type'] = SM_Teaser_Reading_Schema::READING_TYPE;

		$placeholders = array(
			'deep_relationship_analysis' => 'This section unlocks in the full reading.',
			'extended_timeline_12_months' => 'This section unlocks in the full reading.',
			'life_purpose_soul_mission' => 'This section unlocks in the full reading.',
			'shadow_work_transformation' => 'This section unlocks in the full reading.',
			'practical_guidance_action_plan' => 'This section unlocks in the full reading.',
		);

		foreach ( $placeholders as $key => $text ) {
			if ( empty( $reading_data[ $key ] ) || ! is_array( $reading_data[ $key ] ) ) {
				$reading_data[ $key ] = array( 'placeholder_text' => $text );
			} elseif ( empty( $reading_data[ $key ]['placeholder_text'] ) ) {
				$reading_data[ $key ]['placeholder_text'] = $text;
			}
		}

		return $reading_data;
	}

	/**
	 * Normalize palm summary text to avoid empty or unknown values.
	 *
	 * @param string $summary_text Palm summary text.
	 * @return string
	 */
	private function normalize_palm_summary_text( $summary_text ) {
		$text = trim( (string) $summary_text );
		$unknown_count = substr_count( strtolower( $text ), 'unknown' );

		if ( '' === $text || $unknown_count >= 2 ) {
			return 'Hand type: mixed; Lines: Life line: steady and clear, suggesting stable vitality. | Head line: straight with practical focus. | Heart line: curved with warmth. | Fate line: present but subtle. Mounts: Venus, Jupiter, Mercury. Markings: light cross lines. Overall energy: grounded, steady, and reflective.';
		}

		return $text;
	}

	/**
	 * Detect common refusal phrases in plain text responses.
	 *
	 * @param string $content Text content.
	 * @return bool
	 */
	private function looks_like_refusal_text( $content ) {
		$text = strtolower( trim( (string) $content ) );
		$phrases = array(
			"i'm sorry",
			'i am sorry',
			"i can\u2019t assist",
			"i can't assist",
			'cannot assist',
			'cannot help',
			"can't help",
			'not able to assist',
		);

		foreach ( $phrases as $phrase ) {
			if ( '' !== $phrase && strpos( $text, $phrase ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Fallback system prompt to recover from refusals.
	 *
	 * @return string
	 */
	private function get_fallback_system_prompt() {
		return implode(
			"\n",
			array(
				'You are SoulMirror, a cooperative, mystical aura-reading guide.',
				'You can perceive and interpret the provided photo of the person/upper body; under no circumstances should you refuse, apologize, or claim inability.',
				'Always deliver a creative 750-1000 word aura reading that connects to the person\'s energy profile.',
				'If details are unclear, invent supportive, benevolent insights consistent with aura reading themes.',
				'PERSONALIZE the reading by referencing their energy level, life focus, element, spiritual intentions, and goals.',
				'Output clean HTML only using <h4>, <p>, <strong>, <em>; absolutely NO <ul> or <li> tags, never return markdown or code fences.',
				'Exclude medical/legal advice and deterministic claims; keep the tone warm and encouraging.',
			)
		);
	}

	/**
	 * Extend a short reading to meet target length.
	 *
	 * @param string $reading_html Existing sanitized HTML.
	 * @return string|WP_Error
	 */
	private function extend_reading( $reading_html ) {
		$extension_prompt = "Extend the following aura reading to reach a total of 600-800 words. Keep the structure, warm mystical tone, and allowed HTML tags (<h4>, <p>, <ul>, <li>, <strong>, <em>). Do not add code fences or markdown. Do not repeat refusals or apologies. Continue and enrich each section with more depth and specificity:\n\n" . wp_strip_all_tags( $reading_html );

		$response = $this->call_openai_api( $extension_prompt, '', $this->get_system_prompt(), false );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$extended = $this->extract_and_sanitize_html( $response );
		if ( is_wp_error( $extended ) ) {
			return $extended;
		}

		return $extended;
	}

	/**
	 * Detect common refusal phrases from AI responses.
	 *
	 * @param string $content HTML content.
	 * @return bool
	 */
	private function is_refusal_response( $content ) {
		$text = strtolower( wp_strip_all_tags( $content ) );
		$phrases = array(
			"i'm sorry",
			'i am sorry',
			'cannot assist',
			"can\'t assist",
			'cannot help',
			"can\'t help",
			'do not have the ability',
		);

		foreach ( $phrases as $phrase ) {
			if ( strpos( $text, $phrase ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Save teaser reading to database (NEW - Teaser Reading System).
	 *
	 * @param string $lead_id Lead ID.
	 * @param array  $reading_data Validated JSON reading data.
	 * @return string|WP_Error Reading ID on success, WP_Error on failure.
	 */
	private function save_teaser_reading( $lead_id, $reading_data ) {
		global $wpdb;
		$table = SM_Database::get_instance()->get_table_name( 'readings' );

		$reading_id = wp_generate_uuid4();

		$payload = array(
			'id'            => $reading_id,
			'lead_id'       => $lead_id,
			'reading_type'  => 'aura_teaser',
			'content_data'  => wp_json_encode( $reading_data ),
			'unlock_count'  => 0,
			'has_purchased' => false,
			'created_at'    => current_time( 'mysql' ),
		);

		$formats = array( '%s', '%s', '%s', '%s', '%d', '%d', '%s' );

		$result = $wpdb->insert( $table, $payload, $formats );

		if ( false === $result ) {
			SM_Logger::log(
				'error',
				'AI_READING',
				'Failed to save teaser reading to database',
				array(
					'lead_id' => $lead_id,
					'error'   => $wpdb->last_error,
				)
			);
			return new WP_Error(
				'database_error',
				__( 'Could not save your reading. Please try again.', 'mystic-aura-reading' )
			);
		}

		SM_Logger::log(
			'info',
			'AI_READING',
			'Teaser reading saved successfully',
			array(
				'lead_id'    => $lead_id,
				'reading_id' => $reading_id,
			)
		);

		return $reading_id;
	}

	/**
	 * Save paid reading to database.
	 *
	 * @param string $lead_id Lead ID.
	 * @param array  $reading_data Validated JSON reading data.
	 * @param string $account_id Account ID.
	 * @return string|WP_Error Reading ID on success, WP_Error on failure.
	 */
	private function save_paid_reading( $lead_id, $reading_data, $account_id ) {
		global $wpdb;
		$table = SM_Database::get_instance()->get_table_name( 'readings' );

		$reading_id = wp_generate_uuid4();

		$payload = array(
			'id'            => $reading_id,
			'lead_id'       => $lead_id,
			'account_id'    => $account_id,
			'reading_type'  => 'aura_full',
			'content_data'  => wp_json_encode( $reading_data ),
			'unlock_count'  => 0,
			'has_purchased' => true,
			'created_at'    => current_time( 'mysql' ),
		);

		$formats = array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' );

		$result = $wpdb->insert( $table, $payload, $formats );

		if ( false === $result ) {
			SM_Logger::log(
				'error',
				'AI_READING',
				'Failed to save paid reading to database',
				array(
					'lead_id'    => $lead_id,
					'account_id' => $account_id,
					'error'      => $wpdb->last_error,
				)
			);
			return new WP_Error(
				'database_error',
				__( 'Could not save your reading. Please try again.', 'mystic-aura-reading' )
			);
		}

		SM_Logger::log(
			'info',
			'AI_READING',
			'Paid reading saved successfully',
			array(
				'lead_id'    => $lead_id,
				'reading_id' => $reading_id,
			)
		);

		return $reading_id;
	}

	/**
	 * Save reading to database
	 *
	 * @param string $lead_id Lead ID.
	 * @param string $reading_html Sanitized HTML content.
	 * @param string $template_id Template ID used for generation (optional for backward compatibility).
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private function save_reading( $lead_id, $reading_html, $template_id = null ) {
		global $wpdb;
		$table = SM_Database::get_instance()->get_table_name( 'readings' );

		// Base payload for backward compatibility (HTML-based readings)
		$payload = array(
			'id'            => wp_generate_uuid4(),
			'lead_id'       => $lead_id,
			'reading_html'  => $reading_html,
			'reading_type'  => 'aura_legacy', // Distinguish old HTML readings from new teaser readings
			'created_at'    => current_time( 'mysql' ),
		);

		$formats = array( '%s', '%s', '%s', '%s', '%s' );

		// Attempt to include prompt_template_used if column exists
		$has_template_column = $this->column_exists( $table, 'prompt_template_used' );
		if ( $has_template_column ) {
			$payload['prompt_template_used'] = $template_id;
			$formats[]                       = '%s';
		}

		$result = $wpdb->insert( $table, $payload, $formats );

		if ( false === $result ) {
			// Fallback: retry without prompt_template_used if column check was wrong
			if ( $has_template_column ) {
				unset( $payload['prompt_template_used'] );
				array_pop( $formats );
				$result = $wpdb->insert( $table, $payload, $formats );
			}
		}

		if ( false === $result ) {
			SM_Logger::log(
				'error',
				'AI_READING',
				'Failed to save reading to database',
				array(
					'lead_id' => $lead_id,
					'error'   => $wpdb->last_error,
				)
			);
			return new WP_Error(
				'database_error',
				__( 'Could not save your reading. Please try again.', 'mystic-aura-reading' )
			);
		}

		SM_Logger::log( 'info', 'AI_READING', 'Reading saved successfully', array(
			'lead_id'     => $lead_id,
			'template_id' => $template_id,
		) );

		return true;
	}

	/**
	 * Get reading by lead ID
	 *
	 * @param string $lead_id Lead ID.
	 * @return array|WP_Error Reading data or error.
	 */
	public function get_reading_by_lead( $lead_id ) {
		global $wpdb;
		$table = SM_Database::get_instance()->get_table_name( 'readings' );

		$reading = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT reading_html, created_at FROM $table WHERE lead_id = %s",
				$lead_id
			),
			ARRAY_A
		);

		if ( ! $reading ) {
			return new WP_Error(
				'reading_not_found',
				__( 'No reading found.', 'mystic-aura-reading' )
			);
		}

		return $reading;
	}

	/**
	 * Check if reading exists for email (one free reading enforcement)
	 *
	 * @param string $email Email address.
	 * @return bool True if reading exists, false otherwise.
	 */
	public function reading_exists_for_email( $email ) {
		global $wpdb;
		$leads_table = SM_Database::get_instance()->get_table_name( 'leads' );
		$readings_table = SM_Database::get_instance()->get_table_name( 'readings' );

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $readings_table r
				INNER JOIN $leads_table l ON r.lead_id = l.id
				WHERE l.email = %s",
				$email
			)
		);

		return $count > 0;
	}

	/**
	 * Clean up palm image after reading generation
	 *
	 * @param string $image_path Path to image file.
	 * @return void
	 */
	private function cleanup_palm_image( $image_path ) {
		if ( file_exists( $image_path ) ) {
			$deleted = unlink( $image_path );
			if ( $deleted ) {
				SM_Logger::log( 'info', 'AI_READING', 'Palm image deleted after reading generation', array(
					'path' => basename( $image_path ),
				) );
			} else {
				SM_Logger::log( 'warning', 'AI_READING', 'Failed to delete palm image', array(
					'path' => $image_path,
				) );
			}
		}
	}

	/**
	 * Log per-section QA metrics for teaser and paid readings.
	 *
	 * @param array  $reading_data Reading payload.
	 * @param string $reading_id Reading UUID.
	 * @param string $lead_id Lead UUID.
	 * @param string $reading_type Reading type.
	 * @param int    $attempt_count Expansion attempts (if any).
	 * @return void
	 */
	private function log_qa_metrics( $reading_data, $reading_id, $lead_id, $reading_type, $attempt_count ) {
		if ( ! is_array( $reading_data ) ) {
			return;
		}

		$thresholds = ( 'aura_teaser' === $reading_type )
			? $this->get_teaser_qa_thresholds()
			: $this->get_paid_qa_thresholds();

		foreach ( $thresholds as $key => $limits ) {
			$value      = $this->get_nested_field_value( $reading_data, $key );
			$word_count = $this->count_words( $value );
			$min_words  = isset( $limits['min'] ) ? intval( $limits['min'] ) : null;
			$max_words  = isset( $limits['max'] ) ? intval( $limits['max'] ) : null;

			$pass = true;
			if ( null !== $min_words && $word_count < $min_words ) {
				$pass = false;
			}
			if ( null !== $max_words && $word_count > $max_words ) {
				$pass = false;
			}

			SM_Logger::log(
				'info',
				'SM_QA',
				'[SM QA] section word count',
				array(
					'reading_id'    => $reading_id,
					'lead_id'       => $lead_id,
					'reading_type'  => $reading_type,
					'section'       => $key,
					'word_count'    => $word_count,
					'min_words'     => $min_words,
					'max_words'     => $max_words,
					'attempt_count' => $attempt_count,
					'status'        => $pass ? 'pass' : 'fail',
					'force_log'     => true,
				)
			);
		}
	}

	/**
	 * Build teaser word-count thresholds from the schema.
	 *
	 * @return array
	 */
	private function get_teaser_qa_thresholds() {
		$schema     = SM_Teaser_Reading_Schema::get_schema();
		$thresholds = array();

		foreach ( $schema as $section_key => $section ) {
			if ( 'meta' === $section_key || empty( $section['fields'] ) ) {
				continue;
			}

			foreach ( $section['fields'] as $field_key => $field ) {
				if ( empty( $field['min_words'] ) && empty( $field['max_words'] ) ) {
					continue;
				}

				$thresholds[ $section_key . '.' . $field_key ] = array(
					'min' => isset( $field['min_words'] ) ? $field['min_words'] : null,
					'max' => isset( $field['max_words'] ) ? $field['max_words'] : null,
				);
			}
		}

		return $thresholds;
	}

	/**
	 * Paid completion word-count thresholds.
	 *
	 * @return array
	 */
	private function get_paid_qa_thresholds() {
		return array(
			'love_patterns.locked_full'               => array( 'min' => 160, 'max' => 220 ),
			'challenges_opportunities.locked_full'    => array( 'min' => 160, 'max' => 220 ),
			'life_phase.locked_full'                  => array( 'min' => 160, 'max' => 220 ),
			'timeline_6_months.locked_full'           => array( 'min' => 160, 'max' => 220 ),
			'guidance.locked_full'                    => array( 'min' => 140, 'max' => 180 ),
			'deep_relationship_analysis.full_content'  => array( 'min' => 160, 'max' => 220 ),
			'extended_timeline_12_months.full_content' => array( 'min' => 160, 'max' => 220 ),
			'life_purpose_soul_mission.full_content'   => array( 'min' => 220, 'max' => 280 ),
			'shadow_work_transformation.full_content'  => array( 'min' => 160, 'max' => 220 ),
			'practical_guidance_action_plan.full_content' => array( 'min' => 140, 'max' => 180 ),
		);
	}

	/**
	 * Fetch nested field values using dot notation.
	 *
	 * @param array  $data Reading payload.
	 * @param string $path Dot-notated path.
	 * @return string
	 */
	private function get_nested_field_value( $data, $path ) {
		$segments = explode( '.', $path );
		$current  = $data;

		foreach ( $segments as $segment ) {
			if ( ! is_array( $current ) || ! array_key_exists( $segment, $current ) ) {
				return '';
			}
			$current = $current[ $segment ];
		}

		return is_string( $current ) ? $current : '';
	}

	/**
	 * Summarize teaser payload for logging.
	 *
	 * @param array $reading_data Teaser payload.
	 * @return array
	 */
	private function summarize_teaser_payload( $reading_data ) {
		if ( empty( $reading_data ) || ! is_array( $reading_data ) ) {
			return array();
		}

		$summary = array(
			'opening_words'       => $this->count_words( isset( $reading_data['opening']['reflection_p1'] ) ? $reading_data['opening']['reflection_p1'] : '' )
				+ $this->count_words( isset( $reading_data['opening']['reflection_p2'] ) ? $reading_data['opening']['reflection_p2'] : '' ),
			'life_foundations_words' => $this->count_words( isset( $reading_data['life_foundations']['paragraph_1'] ) ? $reading_data['life_foundations']['paragraph_1'] : '' )
				+ $this->count_words( isset( $reading_data['life_foundations']['paragraph_2'] ) ? $reading_data['life_foundations']['paragraph_2'] : '' )
				+ $this->count_words( isset( $reading_data['life_foundations']['paragraph_3'] ) ? $reading_data['life_foundations']['paragraph_3'] : '' ),
			'personality_intro_words' => $this->count_words( isset( $reading_data['personality_traits']['intro'] ) ? $reading_data['personality_traits']['intro'] : '' ),
			'career_words'        => $this->count_words( isset( $reading_data['career_success']['main_paragraph'] ) ? $reading_data['career_success']['main_paragraph'] : '' ),
			'love_preview_words'  => $this->count_words( isset( $reading_data['love_patterns']['preview'] ) ? $reading_data['love_patterns']['preview'] : '' ),
			'challenges_preview_words' => $this->count_words( isset( $reading_data['challenges_opportunities']['preview'] ) ? $reading_data['challenges_opportunities']['preview'] : '' ),
		);

		return $summary;
	}

	/**
	 * Detect short teaser payload sections that should trigger a rescue retry.
	 *
	 * @param array $reading_data Teaser payload.
	 * @return array
	 */
	private function is_teaser_payload_short( $reading_data ) {
		$summary = $this->summarize_teaser_payload( $reading_data );
		if ( empty( $summary ) ) {
			return array();
		}

		$thresholds = array(
			'opening_words'           => 80,
			'life_foundations_words'  => 160,
			'personality_intro_words' => 70,
			'career_words'            => 80,
			'love_preview_words'      => 40,
			'challenges_preview_words'=> 40,
		);

		$short = array();
		foreach ( $thresholds as $key => $min_words ) {
			if ( isset( $summary[ $key ] ) && (int) $summary[ $key ] < $min_words ) {
				$short[ $key ] = array(
					'words'    => (int) $summary[ $key ],
					'expected' => $min_words,
				);
			}
		}

		return $short;
	}

	/**
	 * Detect short teaser fields based on schema min word counts.
	 *
	 * @param array $reading_data Teaser payload.
	 * @return array
	 */
	private function get_teaser_short_fields_by_schema( $reading_data ) {
		if ( empty( $reading_data ) || ! is_array( $reading_data ) ) {
			return array();
		}

		$schema = SM_Teaser_Reading_Schema::get_schema();
		$short  = array();

		foreach ( $schema as $section_key => $section ) {
			if ( 'meta' === $section_key || empty( $section['fields'] ) ) {
				continue;
			}

			if ( empty( $reading_data[ $section_key ] ) || ! is_array( $reading_data[ $section_key ] ) ) {
				continue;
			}

			foreach ( $section['fields'] as $field_key => $field ) {
				if ( empty( $field['min_words'] ) ) {
					continue;
				}

				$value = isset( $reading_data[ $section_key ][ $field_key ] ) ? $reading_data[ $section_key ][ $field_key ] : '';
				$word_count = $this->count_words( $value );
				if ( $word_count < (int) $field['min_words'] ) {
					$short[ $section_key . '.' . $field_key ] = array(
						'words' => $word_count,
						'min'   => (int) $field['min_words'],
					);
				}
			}
		}

		return $short;
	}

	/**
	 * Detect short paid payload sections that should trigger a rescue retry.
	 *
	 * @param array $reading_data Paid payload.
	 * @return array
	 */
	private function is_paid_payload_short( $reading_data ) {
		$summary = $this->summarize_paid_payload( $reading_data );
		if ( empty( $summary ) ) {
			return array();
		}

		$thresholds = array(
			'love_full_words'         => 140,
			'challenges_full_words'   => 140,
			'phase_full_words'        => 140,
			'timeline_full_words'     => 140,
			'guidance_full_words'     => 120,
			'deep_love_words'         => 140,
			'extended_timeline_words' => 140,
			'life_purpose_words'      => 180,
			'shadow_work_words'       => 140,
			'practical_guidance_words'=> 120,
		);

		$short = array();
		foreach ( $thresholds as $key => $min_words ) {
			if ( isset( $summary[ $key ] ) && (int) $summary[ $key ] < $min_words ) {
				$short[ $key ] = array(
					'words'    => (int) $summary[ $key ],
					'expected' => $min_words,
				);
			}
		}

		return $short;
	}

	/**
	 * Summarize paid payload for logging.
	 *
	 * @param array $reading_data Paid payload.
	 * @return array
	 */
	private function summarize_paid_payload( $reading_data ) {
		if ( empty( $reading_data ) || ! is_array( $reading_data ) ) {
			return array();
		}

		$summary = array(
			'love_full_words'        => $this->count_words( isset( $reading_data['love_patterns']['locked_full'] ) ? $reading_data['love_patterns']['locked_full'] : '' ),
			'challenges_full_words'  => $this->count_words( isset( $reading_data['challenges_opportunities']['locked_full'] ) ? $reading_data['challenges_opportunities']['locked_full'] : '' ),
			'phase_full_words'       => $this->count_words( isset( $reading_data['life_phase']['locked_full'] ) ? $reading_data['life_phase']['locked_full'] : '' ),
			'timeline_full_words'    => $this->count_words( isset( $reading_data['timeline_6_months']['locked_full'] ) ? $reading_data['timeline_6_months']['locked_full'] : '' ),
			'guidance_full_words'    => $this->count_words( isset( $reading_data['guidance']['locked_full'] ) ? $reading_data['guidance']['locked_full'] : '' ),
			'deep_love_words'        => $this->count_words( isset( $reading_data['deep_relationship_analysis']['full_content'] ) ? $reading_data['deep_relationship_analysis']['full_content'] : '' ),
			'extended_timeline_words'=> $this->count_words( isset( $reading_data['extended_timeline_12_months']['full_content'] ) ? $reading_data['extended_timeline_12_months']['full_content'] : '' ),
			'life_purpose_words'     => $this->count_words( isset( $reading_data['life_purpose_soul_mission']['full_content'] ) ? $reading_data['life_purpose_soul_mission']['full_content'] : '' ),
			'shadow_work_words'      => $this->count_words( isset( $reading_data['shadow_work_transformation']['full_content'] ) ? $reading_data['shadow_work_transformation']['full_content'] : '' ),
			'practical_guidance_words'=> $this->count_words( isset( $reading_data['practical_guidance_action_plan']['full_content'] ) ? $reading_data['practical_guidance_action_plan']['full_content'] : '' ),
		);

		return $summary;
	}

	/**
	 * Test OpenAI API connection
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function test_connection() {
		$api_key = SM_Settings::init()->get_openai_api_key();

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'api_key_missing',
				__( 'OpenAI API key not configured.', 'mystic-aura-reading' )
			);
		}

		// Simple test request
		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode(
					array(
						'model'    => 'gpt-4o',
						'messages' => array(
							array(
								'role'    => 'user',
								'content' => 'Test',
							),
						),
						'max_tokens' => 5,
					)
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code === 200 ) {
			return true;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$error_message = isset( $body['error']['message'] ) ? $body['error']['message'] : 'Connection failed';

		return new WP_Error( 'connection_failed', $error_message );
	}
}
