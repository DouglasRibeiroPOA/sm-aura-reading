<?php
/**
 * Prompt Template Handler
 *
 * Handles loading, selecting, and rendering AI prompt templates with dynamic user data.
 * Provides variety in AI reading generation by rotating between multiple template styles.
 *
 * @package Mystic_Palm_Reading
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SM_Prompt_Template_Handler
 *
 * Manages AI prompt templates and placeholder replacement.
 */
class SM_Prompt_Template_Handler {

	/**
	 * Singleton instance
	 *
	 * @var SM_Prompt_Template_Handler|null
	 */
	private static $instance = null;

	/**
	 * Cached template data
	 *
	 * @var array|null
	 */
	private $templates = null;

	/**
	 * Path to prompt templates JSON file
	 *
	 * @var string
	 */
	private $templates_path;

	/**
	 * Private constructor for singleton
	 */
	private function __construct() {
		$this->templates_path = plugin_dir_path( __FILE__ ) . 'data/prompt-templates.json';
	}

	/**
	 * Get singleton instance
	 *
	 * @return SM_Prompt_Template_Handler
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Load prompt templates from JSON file with caching
	 *
	 * @return array|WP_Error Templates data or error
	 */
	public function load_templates() {
		// Return cached data if available
		if ( null !== $this->templates ) {
			return $this->templates;
		}

		// Check if file exists
		if ( ! file_exists( $this->templates_path ) ) {
			SM_Logger::log(
				'error',
				'PROMPT_TEMPLATES_MISSING',
				'Prompt templates file not found',
				array( 'path' => $this->templates_path )
			);
			return new WP_Error(
				'templates_missing',
				__( 'Prompt templates file not found.', 'mystic-palm-reading' )
			);
		}

		// Read and parse JSON
		$json_content = file_get_contents( $this->templates_path );
		if ( false === $json_content ) {
			SM_Logger::log(
				'error',
				'PROMPT_TEMPLATES_READ',
				'Failed to read prompt templates file',
				array( 'path' => $this->templates_path )
			);
			return new WP_Error(
				'templates_read_error',
				__( 'Failed to read prompt templates.', 'mystic-palm-reading' )
			);
		}

		$templates_data = json_decode( $json_content, true );
		if ( null === $templates_data || JSON_ERROR_NONE !== json_last_error() ) {
			SM_Logger::log(
				'error',
				'PROMPT_TEMPLATES_PARSE',
				'Failed to parse prompt templates JSON',
				array(
					'error' => json_last_error_msg(),
					'path'  => $this->templates_path,
				)
			);
			return new WP_Error(
				'templates_parse_error',
				__( 'Failed to parse prompt templates.', 'mystic-palm-reading' )
			);
		}

		// Cache and return
		$this->templates = $templates_data;

		SM_Logger::log(
			'info',
			'PROMPT_TEMPLATES_LOADED',
			'Prompt templates loaded successfully',
			array(
				'template_count' => isset( $templates_data['templates'] ) ? count( $templates_data['templates'] ) : 0,
			)
		);

		return $this->templates;
	}

	/**
	 * Select random template using weighted selection
	 *
	 * @return array|WP_Error Template object or error
	 */
	public function select_random_template() {
		$templates_data = $this->templates ?? $this->load_templates();

		if ( is_wp_error( $templates_data ) ) {
			return $templates_data;
		}

		if ( ! isset( $templates_data['templates'] ) || empty( $templates_data['templates'] ) ) {
			SM_Logger::log(
				'error',
				'NO_TEMPLATES',
				'No templates found in templates file'
			);
			return new WP_Error(
				'no_templates',
				__( 'No prompt templates available.', 'mystic-palm-reading' )
			);
		}

		$templates = $templates_data['templates'];

		// Weighted random selection
		$selected_template = $this->weighted_random_select( $templates );

		SM_Logger::log(
			'info',
			'TEMPLATE_SELECTED',
			'Prompt template selected',
			array(
				'template_id'   => $selected_template['id'],
				'template_name' => $selected_template['name'],
			)
		);

		return $selected_template;
	}

	/**
	 * Replace placeholders in template with user data
	 *
	 * @param array $template Template object with 'prompt' field
	 * @param array $user_data User information (name, age_range, gender)
	 * @param array $quiz_data Quiz questions and answers with full context
	 * @param string $palm_context Palm image analysis results from OpenAI Vision
	 * @return string|WP_Error Rendered prompt or error
	 */
	public function replace_placeholders( $template, $user_data, $quiz_data, $palm_context = '' ) {
		if ( ! isset( $template['prompt'] ) ) {
			return new WP_Error(
				'invalid_template',
				__( 'Template missing prompt field.', 'mystic-palm-reading' )
			);
		}

		$prompt = $template['prompt'];

		// Extract data from inputs
		$user_name  = isset( $user_data['name'] ) ? $user_data['name'] : 'Seeker';
		$age_range  = isset( $user_data['age_range'] ) ? $this->format_age_range( $user_data['age_range'] ) : '';
		$gender     = isset( $user_data['gender'] ) ? $this->format_gender( $user_data['gender'] ) : '';

		// Generate different Q&A formats
		$qa_explicit = $this->generate_qa_explicit_list( $quiz_data );
		$qa_context  = $this->generate_qa_context( $quiz_data );
		$qa_bullets  = $this->generate_qa_bullet_list( $quiz_data );

		// Replace all placeholders
		$replacements = array(
			'{{USER_NAME}}'                      => $user_name,
			'{{AGE_RANGE}}'                      => $age_range,
			'{{GENDER}}'                         => $gender,
			'{{QUESTION_ANSWER_EXPLICIT_LIST}}'  => $qa_explicit,
			'{{QUESTION_ANSWER_CONTEXT}}'        => $qa_context,
			'{{QUESTION_ANSWER_BULLET_LIST}}'    => $qa_bullets,
			'{{PALM_IMAGE_CONTEXT}}'             => $palm_context,
		);

		foreach ( $replacements as $placeholder => $value ) {
			$prompt = str_replace( $placeholder, $value, $prompt );
		}

		SM_Logger::log(
			'info',
			'PROMPT_RENDERED',
			'Prompt placeholders replaced',
			array(
				'template_id'   => $template['id'],
				'user_name'     => $user_name,
				'prompt_length' => strlen( $prompt ),
			)
		);

		return $prompt;
	}

	/**
	 * Generate explicit Q&A list (for template_a_explicit)
	 *
	 * Format: Full questions with user's answers, formatted for direct quoting
	 *
	 * @param array $quiz_data Quiz data with questions and answers
	 * @return string Formatted Q&A list
	 */
	private function generate_qa_explicit_list( $quiz_data ) {
		if ( empty( $quiz_data ) || ! isset( $quiz_data['questions'] ) ) {
			return 'No additional context provided.';
		}

		$output = '';
		foreach ( $quiz_data['questions'] as $qa ) {
			$question = isset( $qa['question_text'] ) ? $qa['question_text'] : '';
			$answer   = isset( $qa['answer'] ) ? $qa['answer'] : '';

			// Handle array answers (multi-select)
			if ( is_array( $answer ) ) {
				$answer = implode( ', ', $answer );
			}

			$output .= "Q: {$question}\n";
			$output .= "A: {$answer}\n\n";
		}

		return trim( $output );
	}

	/**
	 * Generate condensed context (for template_b_natural)
	 *
	 * Format: Condensed summary for AI to weave naturally
	 *
	 * @param array $quiz_data Quiz data with questions and answers
	 * @return string Condensed context
	 */
	private function generate_qa_context( $quiz_data ) {
		if ( empty( $quiz_data ) || ! isset( $quiz_data['questions'] ) ) {
			return 'No additional context provided.';
		}

		$context_items = array();
		foreach ( $quiz_data['questions'] as $qa ) {
			$category = isset( $qa['category'] ) ? $qa['category'] : 'general';
			$answer   = isset( $qa['answer'] ) ? $qa['answer'] : '';

			// Handle array answers
			if ( is_array( $answer ) ) {
				$answer = implode( ', ', $answer );
			}

			$context_items[] = ucfirst( $category ) . ': ' . $answer;
		}

		return implode( ' | ', $context_items );
	}

	/**
	 * Generate bullet list (for template_c_summary)
	 *
	 * Format: Bullet points summarizing key concerns
	 *
	 * @param array $quiz_data Quiz data with questions and answers
	 * @return string Bullet list
	 */
	private function generate_qa_bullet_list( $quiz_data ) {
		if ( empty( $quiz_data ) || ! isset( $quiz_data['questions'] ) ) {
			return '• No specific concerns shared';
		}

		$bullets = array();
		foreach ( $quiz_data['questions'] as $qa ) {
			$answer = isset( $qa['answer'] ) ? $qa['answer'] : '';

			// Handle array answers
			if ( is_array( $answer ) ) {
				$answer = implode( ', ', $answer );
			}

			$bullets[] = '• ' . $answer;
		}

		return implode( "\n", $bullets );
	}

	/**
	 * Format age range for display
	 *
	 * @param string $age_range Raw age range (e.g., 'age_18_25')
	 * @return string Formatted age range (e.g., '18-25 years old')
	 */
	private function format_age_range( $age_range ) {
		$age_map = array(
			'age_18_25'   => '18-25 years old',
			'age_26_35'   => '26-35 years old',
			'age_36_50'   => '36-50 years old',
			'age_51_65'   => '51-65 years old',
			'age_65_plus' => '65+ years old',
		);

		return isset( $age_map[ $age_range ] ) ? $age_map[ $age_range ] : 'adult';
	}

	/**
	 * Format gender for display
	 *
	 * @param string $gender Raw gender
	 * @return string Formatted gender
	 */
	private function format_gender( $gender ) {
		$gender_map = array(
			'male'               => 'male',
			'female'             => 'female',
			'prefer_not_to_say'  => 'prefer not to say',
		);

		return isset( $gender_map[ $gender ] ) ? $gender_map[ $gender ] : '';
	}

	/**
	 * Weighted random selection from array of templates
	 *
	 * @param array $templates Array of template objects with optional 'weight' property
	 * @return array Selected template
	 */
	private function weighted_random_select( $templates ) {
		if ( 1 === count( $templates ) ) {
			return $templates[0];
		}

		// Build weighted array
		$weighted_templates = array();
		foreach ( $templates as $template ) {
			$weight = isset( $template['weight'] ) ? $template['weight'] : 1.0;
			$weight = max( 0.1, (float) $weight ); // Ensure minimum weight

			// Add template multiple times based on weight
			$times = (int) ( $weight * 10 );
			for ( $i = 0; $i < $times; $i++ ) {
				$weighted_templates[] = $template;
			}
		}

		// Random selection
		return $weighted_templates[ array_rand( $weighted_templates ) ];
	}

	/**
	 * Clear cached templates (useful for testing or updates)
	 *
	 * @return void
	 */
	public function clear_cache() {
		$this->templates = null;
		SM_Logger::log( 'info', 'PROMPT_TEMPLATES_CACHE_CLEARED', 'Prompt templates cache cleared' );
	}
}
