<?php
/**
 * Quiz handler for SoulMirror.
 *
 * @package MysticPalmReadingAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles quiz answer collection, validation, and storage.
 */
class SM_Quiz_Handler {

	/**
	 * Singleton instance.
	 *
	 * @var SM_Quiz_Handler|null
	 */
	private static $instance = null;

	/**
	 * Quiz table name.
	 *
	 * @var string
	 */
	private $table = '';

	/**
	 * Required question keys.
	 *
	 * @var array
	 */
	private $required_questions = array(
		'energy',
		'focus',
		'element',
		'intentions',
		'future_goals',
	);

	/**
	 * Maximum character length for free text answers.
	 *
	 * @var int
	 */
	private const MAX_TEXT_LENGTH = 500;

	/**
	 * Initialize the handler.
	 *
	 * @return SM_Quiz_Handler
	 */
	public static function init() {
		return self::get_instance();
	}

	/**
	 * Get singleton instance.
	 *
	 * @return SM_Quiz_Handler
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
			$db          = SM_Database::get_instance();
			$this->table = $db->get_table_name( 'quiz' );
		}
	}

	/**
	 * Save quiz answers for a lead.
	 *
	 * @param string $lead_id Lead UUID.
	 * @param array  $answers Associative array of quiz answers.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function save_quiz( $lead_id, $answers ) {
		global $wpdb;

		// Validate inputs.
		$lead_id = sanitize_text_field( (string) $lead_id );
		if ( '' === $lead_id ) {
			$this->log(
				'error',
				'QUIZ_SAVE',
				'Invalid lead ID provided',
				array( 'lead_id' => $lead_id )
			);

			return new WP_Error(
				'invalid_lead_id',
				__( 'Invalid lead ID.', 'mystic-palm-reading' )
			);
		}

		if ( '' === $this->table ) {
			$this->log(
				'error',
				'QUIZ_SAVE',
				'Database table not ready',
				array( 'lead_id' => $lead_id )
			);

			return new WP_Error(
				'db_not_ready',
				__( 'Database is not ready yet.', 'mystic-palm-reading' )
			);
		}

		// Validate quiz structure.
		$validation = $this->validate_quiz( $answers );
		if ( is_wp_error( $validation ) ) {
			$this->log(
				'error',
				'QUIZ_SAVE',
				'Quiz validation failed',
				array(
					'lead_id' => $lead_id,
					'error'   => $validation->get_error_message(),
				)
			);

			return $validation;
		}

		// Sanitize answers.
		$sanitized_answers = $this->sanitize_answers( $answers );

		// Check if quiz already exists for this lead.
		$existing = $this->get_quiz_by_lead( $lead_id );

		$now          = current_time( 'mysql' );
		$answers_json = wp_json_encode( $sanitized_answers );

		if ( $existing ) {
			// Update existing quiz.
			$updated = $wpdb->update(
				$this->table,
				array(
					'answers_json' => $answers_json,
					'completed_at' => $now,
				),
				array( 'lead_id' => $lead_id ),
				array( '%s', '%s' ),
				array( '%s' )
			);

			if ( false === $updated ) {
				$this->log(
					'error',
					'QUIZ_SAVE',
					'Failed to update quiz',
					array(
						'lead_id' => $lead_id,
						'error'   => $wpdb->last_error,
					)
				);

				return new WP_Error(
					'quiz_update_failed',
					__( 'Could not update quiz answers.', 'mystic-palm-reading' )
				);
			}

			$this->log(
				'info',
				'QUIZ_SAVE',
				'Quiz updated successfully',
				array(
					'lead_id'        => $lead_id,
					'question_count' => count( $sanitized_answers ),
				)
			);

			return true;
		}

		// Insert new quiz.
		$quiz_id  = $this->generate_uuid();
		$inserted = $wpdb->insert(
			$this->table,
			array(
				'id'           => $quiz_id,
				'lead_id'      => $lead_id,
				'answers_json' => $answers_json,
				'completed_at' => $now,
			),
			array( '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			$this->log(
				'error',
				'QUIZ_SAVE',
				'Failed to insert quiz',
				array(
					'lead_id' => $lead_id,
					'error'   => $wpdb->last_error,
				)
			);

			return new WP_Error(
				'quiz_insert_failed',
				__( 'Could not save quiz answers.', 'mystic-palm-reading' )
			);
		}

		$this->log(
			'info',
			'QUIZ_SAVE',
			'Quiz saved successfully',
			array(
				'lead_id'        => $lead_id,
				'quiz_id'        => $quiz_id,
				'question_count' => count( $sanitized_answers ),
			)
		);

		return true;
	}

	/**
	 * Retrieve quiz answers for a lead.
	 *
	 * @param string $lead_id Lead UUID.
	 * @return object|null Quiz object or null if not found.
	 */
	public function get_quiz_by_lead( $lead_id ) {
		global $wpdb;

		$lead_id = sanitize_text_field( (string) $lead_id );
		if ( '' === $lead_id || '' === $this->table ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE lead_id = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$lead_id
			),
			OBJECT
		);
	}

	/**
	 * Get quiz answers as array (decoded JSON).
	 *
	 * @param string $lead_id Lead UUID.
	 * @return array|null Associative array of answers or null if not found.
	 */
	public function get_answers( $lead_id ) {
		$quiz = $this->get_quiz_by_lead( $lead_id );

		if ( ! $quiz || empty( $quiz->answers_json ) ) {
			return null;
		}

		$answers = json_decode( $quiz->answers_json, true );

		return is_array( $answers ) ? $answers : null;
	}

	/**
	 * Validate quiz structure and required questions.
	 *
	 * @param array $answers Quiz answers array.
	 * @return bool|WP_Error True if valid, WP_Error on failure.
	 */
	private function validate_quiz( $answers ) {
		// Must be an array.
		if ( ! is_array( $answers ) ) {
			return new WP_Error(
				'invalid_quiz_format',
				__( 'Quiz answers must be an array.', 'mystic-palm-reading' )
			);
		}

		// Check if this is new dynamic format (has 'questions' and 'demographics' fields)
		if ( isset( $answers['questions'] ) && isset( $answers['demographics'] ) ) {
			return $this->validate_dynamic_quiz( $answers );
		}

		// Otherwise, validate old static format
		// Check for required questions.
		foreach ( $this->required_questions as $question ) {
			if ( ! array_key_exists( $question, $answers ) ) {
				return new WP_Error(
					'missing_required_question',
					sprintf(
						/* translators: %s: question key */
						__( 'Missing required question: %s', 'mystic-palm-reading' ),
						$question
					)
				);
			}

			// Check for empty required fields (except arrays which can be empty for multi-select).
			if ( ! is_array( $answers[ $question ] ) && '' === trim( (string) $answers[ $question ] ) ) {
				return new WP_Error(
					'empty_required_answer',
					sprintf(
						/* translators: %s: question key */
						__( 'Answer required for question: %s', 'mystic-palm-reading' ),
						$question
					)
				);
			}
		}

		return true;
	}

	/**
	 * Validate dynamic quiz format (new Q&A structure).
	 *
	 * @param array $data Quiz data with demographics and questions.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private function validate_dynamic_quiz( $data ) {
		// Validate demographics
		if ( ! isset( $data['demographics']['age_range'] ) || ! isset( $data['demographics']['gender'] ) ) {
			return new WP_Error(
				'missing_demographics',
				__( 'Demographics (age_range, gender) are required.', 'mystic-palm-reading' )
			);
		}

		// Validate questions array
		if ( ! is_array( $data['questions'] ) || empty( $data['questions'] ) ) {
			return new WP_Error(
				'invalid_questions',
				__( 'Questions array is required and must not be empty.', 'mystic-palm-reading' )
			);
		}

		// Should have 4 questions
		if ( count( $data['questions'] ) !== 4 ) {
			return new WP_Error(
				'invalid_question_count',
				__( 'Exactly 4 questions are required.', 'mystic-palm-reading' )
			);
		}

		// Validate each question structure
		foreach ( $data['questions'] as $index => $question ) {
			if ( ! isset( $question['question_id'] ) ) {
				return new WP_Error(
					'missing_question_id',
					sprintf(
						/* translators: %d: question index */
						__( 'Question %d is missing question_id.', 'mystic-palm-reading' ),
						$index + 1
					)
				);
			}

			if ( ! isset( $question['question_text'] ) ) {
				return new WP_Error(
					'missing_question_text',
					sprintf(
						/* translators: %d: question index */
						__( 'Question %d is missing question_text.', 'mystic-palm-reading' ),
						$index + 1
					)
				);
			}

			if ( ! isset( $question['answer'] ) ) {
				return new WP_Error(
					'missing_answer',
					sprintf(
						/* translators: %d: question index */
						__( 'Question %d is missing answer.', 'mystic-palm-reading' ),
						$index + 1
					)
				);
			}

			// Validate answer is not empty (can be string or array for multi-select)
			if ( is_array( $question['answer'] ) && empty( $question['answer'] ) ) {
				return new WP_Error(
					'empty_answer',
					sprintf(
						/* translators: %d: question index */
						__( 'Question %d has empty answer.', 'mystic-palm-reading' ),
						$index + 1
					)
				);
			}

			if ( ! is_array( $question['answer'] ) && '' === trim( (string) $question['answer'] ) ) {
				return new WP_Error(
					'empty_answer',
					sprintf(
						/* translators: %d: question index */
						__( 'Question %d has empty answer.', 'mystic-palm-reading' ),
						$index + 1
					)
				);
			}
		}

		return true;
	}

	/**
	 * Sanitize quiz answers.
	 *
	 * @param array $answers Raw quiz answers.
	 * @return array Sanitized answers.
	 */
	private function sanitize_answers( $answers ) {
		// Check if this is new dynamic format
		if ( isset( $answers['questions'] ) && isset( $answers['demographics'] ) ) {
			return $this->sanitize_dynamic_quiz( $answers );
		}

		// Old static format sanitization
		$sanitized = array();

		foreach ( $answers as $key => $value ) {
			$key = sanitize_text_field( (string) $key );

			// Handle multi-select arrays (e.g., intentions).
			if ( is_array( $value ) ) {
				$sanitized[ $key ] = array_map( 'sanitize_text_field', $value );
			} else {
				// Handle text answers.
				$value = sanitize_text_field( (string) $value );

				// Limit free text to MAX_TEXT_LENGTH characters.
				if ( strlen( $value ) > self::MAX_TEXT_LENGTH ) {
					$value = substr( $value, 0, self::MAX_TEXT_LENGTH );
				}

				$sanitized[ $key ] = $value;
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize dynamic quiz format (new Q&A structure).
	 *
	 * @param array $data Quiz data with demographics and questions.
	 * @return array Sanitized quiz data.
	 */
	private function sanitize_dynamic_quiz( $data ) {
		$sanitized = array(
			'demographics' => array(
				'age_range' => sanitize_text_field( $data['demographics']['age_range'] ),
				'gender'    => sanitize_text_field( $data['demographics']['gender'] ),
			),
			'questions'    => array(),
			'selected_at'  => isset( $data['selected_at'] ) ? sanitize_text_field( $data['selected_at'] ) : current_time( 'mysql' ),
		);

		// Sanitize each question
		foreach ( $data['questions'] as $question ) {
			$sanitized_question = array(
				'position'      => isset( $question['position'] ) ? absint( $question['position'] ) : 0,
				'question_id'   => sanitize_text_field( $question['question_id'] ),
				'question_text' => sanitize_text_field( $question['question_text'] ),
				'question_type' => sanitize_text_field( $question['question_type'] ?? 'multiple_choice' ),
				'category'      => sanitize_text_field( $question['category'] ?? '' ),
			);

			// Sanitize answer (can be string or array for multi-select)
			if ( is_array( $question['answer'] ) ) {
				$sanitized_question['answer'] = array_map( 'sanitize_text_field', $question['answer'] );
			} else {
				$answer = sanitize_text_field( (string) $question['answer'] );

				// Limit free text to MAX_TEXT_LENGTH characters
				if ( strlen( $answer ) > self::MAX_TEXT_LENGTH ) {
					$answer = substr( $answer, 0, self::MAX_TEXT_LENGTH );
				}

				$sanitized_question['answer'] = $answer;
			}

			// Include options if present (for context)
			if ( isset( $question['options'] ) && is_array( $question['options'] ) ) {
				$sanitized_question['options'] = array_map( 'sanitize_text_field', $question['options'] );
			}

			$sanitized['questions'][] = $sanitized_question;
		}

		return $sanitized;
	}

	/**
	 * Generate a UUID for quiz ID.
	 *
	 * @return string UUID.
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
	 * Proxy logger to avoid fatal if missing.
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
}
