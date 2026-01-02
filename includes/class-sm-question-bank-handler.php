<?php
/**
 * Question Bank Handler
 *
 * Handles loading, caching, and selecting questions from the dynamic question bank.
 * Implements personalized question selection based on user demographics (age, gender).
 *
 * @package Mystic_Palm_Reading
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SM_Question_Bank_Handler
 *
 * Manages dynamic question selection from JSON question bank.
 */
class SM_Question_Bank_Handler {

	/**
	 * Singleton instance
	 *
	 * @var SM_Question_Bank_Handler|null
	 */
	private static $instance = null;

	/**
	 * Cached question bank data
	 *
	 * @var array|null
	 */
	private $question_bank = null;

	/**
	 * Path to question bank JSON file
	 *
	 * @var string
	 */
	private $question_bank_path;

	/**
	 * Private constructor for singleton
	 */
	private function __construct() {
		$this->question_bank_path = plugin_dir_path( __FILE__ ) . 'data/question-bank.json';
	}

	/**
	 * Get singleton instance
	 *
	 * @return SM_Question_Bank_Handler
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Load question bank from JSON file with caching
	 *
	 * @return array|WP_Error Question bank data or error
	 */
	public function load_question_bank() {
		// Return cached data if available
		if ( null !== $this->question_bank ) {
			return $this->question_bank;
		}

		// Check if file exists
		if ( ! file_exists( $this->question_bank_path ) ) {
			SM_Logger::log(
				'error',
				'QUESTION_BANK_LOAD',
				'Question bank file not found',
				array( 'path' => $this->question_bank_path )
			);
			return new WP_Error(
				'question_bank_missing',
				__( 'Question bank file not found.', 'mystic-palm-reading' )
			);
		}

		// Read and parse JSON
		$json_content = file_get_contents( $this->question_bank_path );
		if ( false === $json_content ) {
			SM_Logger::log(
				'error',
				'QUESTION_BANK_READ',
				'Failed to read question bank file',
				array( 'path' => $this->question_bank_path )
			);
			return new WP_Error(
				'question_bank_read_error',
				__( 'Failed to read question bank.', 'mystic-palm-reading' )
			);
		}

		$question_bank = json_decode( $json_content, true );
		if ( null === $question_bank || JSON_ERROR_NONE !== json_last_error() ) {
			SM_Logger::log(
				'error',
				'QUESTION_BANK_PARSE',
				'Failed to parse question bank JSON',
				array(
					'error' => json_last_error_msg(),
					'path'  => $this->question_bank_path,
				)
			);
			return new WP_Error(
				'question_bank_parse_error',
				__( 'Failed to parse question bank.', 'mystic-palm-reading' )
			);
		}

		// Cache and return
		$this->question_bank = $question_bank;

		SM_Logger::log(
			'info',
			'QUESTION_BANK_LOADED',
			'Question bank loaded successfully',
			array(
				'first_hooks_count' => isset( $question_bank['first_hooks'] ) ? count( $question_bank['first_hooks'], COUNT_RECURSIVE ) : 0,
			)
		);

		return $this->question_bank;
	}

	/**
	 * Select 4 personalized questions based on demographics
	 *
	 * Main selection algorithm:
	 * - Q1: First hook (determines concern category)
	 * - Q2-Q3: Concern-focused questions
	 * - Q4: Free text question
	 *
	 * @param string $age_range Age range (e.g., 'age_18_25', 'age_26_35')
	 * @param string $gender Gender identity (e.g., 'male', 'female', 'prefer_not_to_say')
	 * @return array|WP_Error Array of 4 question objects or error
	 */
	public function select_questions( $age_range, $gender ) {
		$question_bank = $this->load_question_bank();

		if ( is_wp_error( $question_bank ) ) {
			return $question_bank;
		}

		// Validate inputs
		$age_range = $this->normalize_age_range( $age_range );
		$gender    = $this->normalize_gender( $gender );

		if ( is_wp_error( $age_range ) ) {
			return $age_range;
		}
		if ( is_wp_error( $gender ) ) {
			return $gender;
		}

		$selected_questions = array();

		// Q1: Select first hook (determines concern category)
		$first_hook = $this->get_first_hook( $age_range, $gender );
		if ( is_wp_error( $first_hook ) ) {
			return $first_hook;
		}

		$first_hook['position'] = 1;
		$selected_questions[]   = $first_hook;

		// Get concern category from first hook (will be determined by user's answer)
		// For now, we'll just structure Q2-Q4 to come from different categories
		// In practice, the frontend will need to call a second endpoint after Q1 is answered
		// For initial selection, we'll select from a random concern category
		$concern_categories = array(
			'emotional_state',
			'energy_flow',
			'relationships',
			'life_direction',
			'spiritual_memory',
			'intentions_growth',
		);
		$random_concern     = $concern_categories[ array_rand( $concern_categories ) ];

		// Q2-Q3: Select concern-focused questions
		$follow_up_questions = $this->get_follow_up_questions( $random_concern, $age_range, $gender, 2 );
		if ( is_wp_error( $follow_up_questions ) ) {
			return $follow_up_questions;
		}

		foreach ( $follow_up_questions as $index => $question ) {
			$question['position'] = $index + 2; // Positions 2, 3
			$selected_questions[] = $question;
		}

		// Q4: Select free text question
		$free_text_question = $this->get_free_text_question();
		if ( is_wp_error( $free_text_question ) ) {
			return $free_text_question;
		}

		$free_text_question['position'] = 4;
		$selected_questions[]            = $free_text_question;

		SM_Logger::log(
			'info',
			'QUESTIONS_SELECTED',
			'4 questions selected successfully',
			array(
				'age_range'      => $age_range,
				'gender'         => $gender,
				'first_hook_id'  => $first_hook['id'],
				'concern'        => $random_concern,
				'question_count' => count( $selected_questions ),
			)
		);

		return $selected_questions;
	}

	/**
	 * Get first hook question (Q1) based on demographics
	 *
	 * @param string $age_range Normalized age range
	 * @param string $gender Normalized gender
	 * @return array|WP_Error Question object or error
	 */
	public function get_first_hook( $age_range, $gender ) {
		$question_bank = $this->question_bank ?? $this->load_question_bank();

		if ( is_wp_error( $question_bank ) ) {
			return $question_bank;
		}

		// Navigate to first_hooks -> age_range -> gender
		if ( ! isset( $question_bank['first_hooks'][ $age_range ][ $gender ] ) ) {
			SM_Logger::log(
				'error',
				'FIRST_HOOK_NOT_FOUND',
				'No first hook questions found for demographics',
				array(
					'age_range' => $age_range,
					'gender'    => $gender,
				)
			);
			return new WP_Error(
				'first_hook_not_found',
				__( 'No questions available for your demographic.', 'mystic-palm-reading' )
			);
		}

		$available_hooks = $question_bank['first_hooks'][ $age_range ][ $gender ];

		if ( empty( $available_hooks ) ) {
			return new WP_Error(
				'no_first_hooks',
				__( 'No first hook questions available.', 'mystic-palm-reading' )
			);
		}

		// Weighted random selection (currently all weight 1.0, but supports future weighting)
		$selected_hook = $this->weighted_random_select( $available_hooks );

		return $selected_hook;
	}

	/**
	 * Get follow-up questions (Q2-Q3) based on concern and demographics
	 *
	 * @param string $concern Concern category ('emotional_state', 'energy_flow', 'relationships', 'life_direction', 'spiritual_memory', 'intentions_growth')
	 * @param string $age_range Normalized age range
	 * @param string $gender Normalized gender
	 * @param int    $count Number of questions to select (default 3)
	 * @return array|WP_Error Array of question objects or error
	 */
	public function get_follow_up_questions( $concern, $age_range, $gender, $count = 2 ) {
		$question_bank = $this->question_bank ?? $this->load_question_bank();

		if ( is_wp_error( $question_bank ) ) {
			return $question_bank;
		}

		// Navigate to concern_categories -> concern -> age_range -> gender
		if ( ! isset( $question_bank['concern_categories'][ $concern ][ $age_range ][ $gender ] ) ) {
			SM_Logger::log(
				'warning',
				'FOLLOW_UP_NOT_FOUND',
				'No follow-up questions found, trying fallback',
				array(
					'concern'   => $concern,
					'age_range' => $age_range,
					'gender'    => $gender,
				)
			);

			// Fallback: try prefer_not_to_say for this age range
			if ( isset( $question_bank['concern_categories'][ $concern ][ $age_range ]['prefer_not_to_say'] ) ) {
				$available_questions = $question_bank['concern_categories'][ $concern ][ $age_range ]['prefer_not_to_say'];
			} else {
				return new WP_Error(
					'follow_up_not_found',
					__( 'No follow-up questions available.', 'mystic-palm-reading' )
				);
			}
		} else {
			$available_questions = $question_bank['concern_categories'][ $concern ][ $age_range ][ $gender ];
		}

		if ( empty( $available_questions ) ) {
			return new WP_Error(
				'no_follow_up_questions',
				__( 'No follow-up questions available.', 'mystic-palm-reading' )
			);
		}

		// Filter questions by position_preference (should include 2 or 3) and avoid extra text prompts.
		$eligible_questions = array_filter(
			$available_questions,
			function( $q ) {
				$type = isset( $q['type'] ) ? $q['type'] : '';
				return isset( $q['position_preference'] ) &&
					   ( in_array( 2, $q['position_preference'], true ) ||
						 in_array( 3, $q['position_preference'], true ) ) &&
					   'text' !== $type;
			}
		);

		if ( count( $eligible_questions ) < $count ) {
			// If not enough eligible questions, use all non-text questions.
			$eligible_questions = array_filter(
				$available_questions,
				function( $q ) {
					$type = isset( $q['type'] ) ? $q['type'] : '';
					return 'text' !== $type;
				}
			);
		}

		// Randomly select $count questions
		shuffle( $eligible_questions );
		$selected = array_slice( $eligible_questions, 0, $count );

		// If we still have fewer than $count, pull additional questions from other concerns for variety
		if ( count( $selected ) < $count && isset( $question_bank['concern_categories'] ) ) {
			$needed        = $count - count( $selected );
			$fallback_pool = array();

			foreach ( $question_bank['concern_categories'] as $concern_key => $ages ) {
				if ( isset( $ages[ $age_range ][ $gender ] ) && is_array( $ages[ $age_range ][ $gender ] ) ) {
					$fallback_pool = array_merge( $fallback_pool, $ages[ $age_range ][ $gender ] );
				} elseif ( isset( $ages[ $age_range ]['prefer_not_to_say'] ) && is_array( $ages[ $age_range ]['prefer_not_to_say'] ) ) {
					$fallback_pool = array_merge( $fallback_pool, $ages[ $age_range ]['prefer_not_to_say'] );
				}
			}

			$fallback_pool = array_filter(
				$fallback_pool,
				function( $q ) {
					$type = isset( $q['type'] ) ? $q['type'] : '';
					return 'text' !== $type;
				}
			);

			// Remove already selected question ids to avoid duplicates
			$selected_ids = wp_list_pluck( $selected, 'id' );
			$fallback_pool = array_values(
				array_filter(
					$fallback_pool,
					function( $q ) use ( $selected_ids ) {
						return isset( $q['id'] ) && ! in_array( $q['id'], $selected_ids, true );
					}
				)
			);

			if ( ! empty( $fallback_pool ) ) {
				shuffle( $fallback_pool );
				$additional = array_slice( $fallback_pool, 0, $needed );
				$selected   = array_merge( $selected, $additional );
			}
		}

		return $selected;
	}

	/**
	 * Get free text question (Q5)
	 *
	 * @return array|WP_Error Question object or error
	 */
	public function get_free_text_question() {
		$question_bank = $this->question_bank ?? $this->load_question_bank();

		if ( is_wp_error( $question_bank ) ) {
			return $question_bank;
		}

		if ( ! isset( $question_bank['universal_questions']['free_text'] ) ) {
			return new WP_Error(
				'free_text_not_found',
				__( 'No free text questions available.', 'mystic-palm-reading' )
			);
		}

		$free_text_questions = $question_bank['universal_questions']['free_text'];

		if ( empty( $free_text_questions ) ) {
			return new WP_Error(
				'no_free_text_questions',
				__( 'No free text questions available.', 'mystic-palm-reading' )
			);
		}

		// Weighted random selection
		$selected = $this->weighted_random_select( $free_text_questions );

		return $selected;
	}

	/**
	 * Weighted random selection from array of questions
	 *
	 * @param array $questions Array of question objects with optional 'weight' property
	 * @return array Selected question
	 */
	private function weighted_random_select( $questions ) {
		if ( 1 === count( $questions ) ) {
			return $questions[0];
		}

		// Build weighted array
		$weighted_questions = array();
		foreach ( $questions as $question ) {
			$weight = isset( $question['weight'] ) ? $question['weight'] : 1.0;
			$weight = max( 0.1, (float) $weight ); // Ensure minimum weight

			// Add question multiple times based on weight
			$times = (int) ( $weight * 10 );
			for ( $i = 0; $i < $times; $i++ ) {
				$weighted_questions[] = $question;
			}
		}

		// Random selection
		return $weighted_questions[ array_rand( $weighted_questions ) ];
	}

	/**
	 * Normalize age range input
	 *
	 * @param string $age_range Raw age range input
	 * @return string|WP_Error Normalized age range or error
	 */
	private function normalize_age_range( $age_range ) {
		$valid_ranges = array(
			'age_18_25',
			'age_26_35',
			'age_36_50',
			'age_51_65',
			'age_65_plus',
		);

		// If already normalized
		if ( in_array( $age_range, $valid_ranges, true ) ) {
			return $age_range;
		}

		// Try to map from number input (e.g., "25" -> "age_18_25")
		if ( is_numeric( $age_range ) ) {
			$age = (int) $age_range;
			if ( $age >= 18 && $age <= 25 ) {
				return 'age_18_25';
			} elseif ( $age >= 26 && $age <= 35 ) {
				return 'age_26_35';
			} elseif ( $age >= 36 && $age <= 50 ) {
				return 'age_36_50';
			} elseif ( $age >= 51 && $age <= 65 ) {
				return 'age_51_65';
			} elseif ( $age > 65 ) {
				return 'age_65_plus';
			}
		}

		SM_Logger::log(
			'error',
			'INVALID_AGE_RANGE',
			'Invalid age range provided',
			array( 'age_range' => $age_range )
		);

		return new WP_Error(
			'invalid_age_range',
			__( 'Invalid age range provided.', 'mystic-palm-reading' )
		);
	}

	/**
	 * Normalize gender input
	 *
	 * @param string $gender Raw gender input
	 * @return string|WP_Error Normalized gender or error
	 */
	private function normalize_gender( $gender ) {
		$valid_genders = array(
			'male',
			'female',
			'prefer_not_to_say',
		);

		$gender = strtolower( trim( $gender ) );

		// Direct match
		if ( in_array( $gender, $valid_genders, true ) ) {
			return $gender;
		}

		// Try to map common variations
		$gender_map = array(
			'man'             => 'male',
			'm'               => 'male',
			'woman'           => 'female',
			'f'               => 'female',
			'prefer not to say' => 'prefer_not_to_say',
			'other'           => 'prefer_not_to_say',
			'non-binary'      => 'prefer_not_to_say',
			'nonbinary'       => 'prefer_not_to_say',
			'nb'              => 'prefer_not_to_say',
		);

		if ( isset( $gender_map[ $gender ] ) ) {
			return $gender_map[ $gender ];
		}

		SM_Logger::log(
			'error',
			'INVALID_GENDER',
			'Invalid gender provided',
			array( 'gender' => $gender )
		);

		return new WP_Error(
			'invalid_gender',
			__( 'Invalid gender provided.', 'mystic-palm-reading' )
		);
	}

	/**
	 * Clear cached question bank (useful for testing or updates)
	 *
	 * @return void
	 */
	public function clear_cache() {
		$this->question_bank = null;
		SM_Logger::log( 'info', 'QUESTION_BANK_CACHE_CLEARED', 'Question bank cache cleared' );
	}
}
