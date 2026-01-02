<?php
/**
 * Teaser Reading JSON Schema & Validation
 *
 * Defines the required OpenAI JSON structure for teaser palm readings and
 * validates AI responses before they are persisted or rendered.
 *
 * @package MysticPalmReading
 * @since 1.3.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SM_Teaser_Reading_Schema
 *
 * Provides schema definition and validation for teaser reading JSON payloads.
 */
class SM_Teaser_Reading_Schema {

	const READING_TYPE = 'aura_teaser';

	/**
	 * Master list of selectable traits.
	 */
	const TRAIT_MASTER_LIST = array(
		'Intuition',
		'Creativity',
		'Resilience',
		'Emotional Depth',
		'Independence',
		'Adaptability',
		'Empathy',
		'Leadership',
		'Analytical Thinking',
		'Passion',
		'Patience',
		'Courage',
		'Wisdom',
		'Authenticity',
		'Determination',
	);

	/**
	 * Return schema definition for all expected sections and fields.
	 *
	 * @return array
	 */
	public static function get_schema() {
		return array(
			'meta'                  => array(
				'required' => true,
				'fields'   => array(
					'user_name'    => array(
						'type'       => 'string',
						'required'   => true,
						'min_length' => 1,
						'max_length' => 120,
					),
					'generated_at' => array(
						'type'     => 'string',
						'required' => true,
						'format'  => 'datetime',
					),
					'reading_type' => array(
						'type'     => 'string',
						'required' => true,
						'allowed'  => array( self::READING_TYPE ),
					),
				),
			),
			'opening'               => array(
				'required' => true,
				'fields'   => array(
					'reflection_p1' => array(
						'type'      => 'string',
						'required'  => true,
						'min_words' => 40,
						'max_words' => 60,
					),
					'reflection_p2' => array(
						'type'      => 'string',
						'required'  => true,
						'min_words' => 40,
						'max_words' => 60,
					),
				),
			),
			'life_foundations'      => array(
				'required' => true,
				'fields'   => array(
					'paragraph_1' => array(
						'type'      => 'string',
						'required'  => true,
						'min_words' => 100,
						'max_words' => 130,
					),
					'paragraph_2' => array(
						'type'      => 'string',
						'required'  => true,
						'min_words' => 100,
						'max_words' => 130,
					),
					'paragraph_3' => array(
						'type'      => 'string',
						'required'  => true,
						'min_words' => 80,
						'max_words' => 100,
					),
					'core_theme'  => array(
						'type'      => 'string',
						'required'  => true,
						'min_words' => 50,
						'max_words' => 70,
					),
				),
			),
			'love_patterns'         => array(
				'required' => true,
				'fields'   => array(
					'preview'      => array(
						'type'      => 'string',
						'required'  => true,
						'min_words' => 120,
						'max_words' => 160,
					),
				),
			),
			'career_success'        => array(
				'required' => true,
				'fields'   => array(
					'main_paragraph'      => array(
						'type'      => 'string',
						'required'  => true,
						'min_words' => 80,
						'max_words' => 120,
					),
				),
			),
			'personality_traits'    => array(
				'required' => true,
				'fields'   => array(
					'intro'        => array(
						'type'      => 'string',
						'required'  => true,
						'min_words' => 70,
						'max_words' => 100,
					),
					'trait_1_name' => array(
						'type'     => 'string',
						'required' => true,
					),
					'trait_1_score' => array(
						'type'      => 'int',
						'required'  => true,
						'min_value' => 0,
						'max_value' => 100,
					),
					'trait_2_name' => array(
						'type'     => 'string',
						'required' => true,
					),
					'trait_2_score' => array(
						'type'      => 'int',
						'required'  => true,
						'min_value' => 0,
						'max_value' => 100,
					),
					'trait_3_name' => array(
						'type'     => 'string',
						'required' => true,
					),
					'trait_3_score' => array(
						'type'      => 'int',
						'required'  => true,
						'min_value' => 0,
						'max_value' => 100,
					),
				),
			),
			'challenges_opportunities' => array(
				'required' => true,
				'fields'   => array(
					'preview'       => array(
						'type'      => 'string',
						'required'  => true,
						'min_words' => 120,
						'max_words' => 160,
					),
				),
			),
			'life_phase'            => array(
				'required' => true,
				'fields'   => array(
					'preview'       => array(
						'type'      => 'string',
						'required'  => true,
						'min_words' => 120,
						'max_words' => 160,
					),
				),
			),
			'timeline_6_months'     => array(
				'required' => true,
				'fields'   => array(
					'preview'       => array(
						'type'      => 'string',
						'required'  => true,
						'min_words' => 120,
						'max_words' => 160,
					),
				),
			),
			'guidance'              => array(
				'required' => true,
				'fields'   => array(
					'preview'       => array(
						'type'      => 'string',
						'required'  => true,
						'min_words' => 120,
						'max_words' => 160,
					),
				),
			),
			'deep_relationship_analysis' => array(
				'required' => false,
				'fields'   => array(
					'placeholder_text' => array(
						'type'     => 'string',
						'required' => false,
						'default'  => 'This section contains deep relationship insights available in the full reading.',
					),
				),
			),
			'extended_timeline_12_months' => array(
				'required' => false,
				'fields'   => array(
					'placeholder_text' => array(
						'type'     => 'string',
						'required' => false,
						'default'  => 'This section contains a 12-month timeline available in the full reading.',
					),
				),
			),
			'life_purpose_soul_mission' => array(
				'required' => false,
				'fields'   => array(
					'placeholder_text' => array(
						'type'     => 'string',
						'required' => false,
						'default'  => 'This section explores life purpose and soul mission in the full reading.',
					),
				),
			),
			'shadow_work_transformation' => array(
				'required' => false,
				'fields'   => array(
					'placeholder_text' => array(
						'type'     => 'string',
						'required' => false,
						'default'  => 'This section covers shadow work insights in the full reading.',
					),
				),
			),
			'practical_guidance_action_plan' => array(
				'required' => false,
				'fields'   => array(
					'placeholder_text' => array(
						'type'     => 'string',
						'required' => false,
						'default'  => 'This section provides a practical action plan in the full reading.',
					),
				),
			),
			'closing'               => array(
				'required' => true,
				'fields'   => array(
					'paragraph_1' => array(
						'type'      => 'string',
						'required'  => true,
						'min_words' => 40,
						'max_words' => 60,
					),
					'paragraph_2' => array(
						'type'      => 'string',
						'required'  => true,
						'min_words' => 40,
						'max_words' => 60,
					),
				),
			),
		);
	}

	/**
	 * Validate a decoded OpenAI JSON payload against the expected schema.
	 *
	 * RELAXED MODE: Accept whatever OpenAI gives us, log warnings instead of hard errors.
	 *
	 * @param mixed $data Decoded JSON data.
	 * @return array|WP_Error Normalized data on success, WP_Error on failure.
	 */
	public static function validate_response( $data ) {
		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'invalid_teaser_schema',
				__( 'OpenAI response must be a JSON object.', 'mystic-palm-reading' )
			);
		}

		$schema     = self::get_schema();
		$warnings   = array(); // Collect warnings instead of errors
		$normalized = array();

		foreach ( $schema as $section_key => $section_schema ) {
			$is_required = isset( $section_schema['required'] ) ? (bool) $section_schema['required'] : true;
			$section     = isset( $data[ $section_key ] ) ? $data[ $section_key ] : null;

			if ( ! is_array( $section ) ) {
				if ( $is_required ) {
					// Log warning but don't fail
					$warnings[] = sprintf( 'Missing section: %s', $section_key );
				}
				continue;
			}

			$normalized[ $section_key ] = array();

			foreach ( $section_schema['fields'] as $field_key => $definition ) {
				$field_required = isset( $definition['required'] ) ? (bool) $definition['required'] : true;
				if ( ! array_key_exists( $field_key, $section ) ) {
					if ( $field_required ) {
						// Log warning but don't fail
						$warnings[] = sprintf( 'Missing field: %s.%s', $section_key, $field_key );
					}
					continue;
				}

				$value = $section[ $field_key ];

				// Validate but collect warnings instead of errors
				self::validate_field_relaxed( $value, $definition, $section_key . '.' . $field_key, $warnings );

				// Accept the value regardless of validation warnings
				if ( ! is_null( $value ) && $value !== '' ) {
					$normalized[ $section_key ][ $field_key ] = is_string( $value ) ? trim( $value ) : $value;
				}
			}
		}

		// Validate traits (relaxed)
		self::validate_traits_relaxed( isset( $data['personality_traits'] ) ? $data['personality_traits'] : array(), $warnings );

		// Log warnings but DON'T reject the response
		if ( ! empty( $warnings ) ) {
			if ( class_exists( 'SM_Logger' ) ) {
				SM_Logger::log(
					'warning',
					'SM_QA',
					'Schema validation warnings (accepted)',
					array(
						'warnings'  => $warnings,
						'force_log' => true,
					)
				);
			}
			error_log( '[SM AI] Schema validation warnings (ACCEPTED): ' . print_r( $warnings, true ) );
		}

		return $normalized;
	}

	/**
	 * Validate field in relaxed mode (warnings instead of errors).
	 *
	 * @param mixed  $value Field value.
	 * @param array  $definition Field definition.
	 * @param string $path Field path for warning reporting.
	 * @param array  $warnings Warning bucket (by reference).
	 * @return void
	 */
	private static function validate_field_relaxed( $value, array $definition, $path, array &$warnings ) {
		// Just log warnings, don't enforce minimums
		if ( ! isset( $definition['type'] ) ) {
			return;
		}

		$type = $definition['type'];

		if ( 'string' === $type && isset( $definition['min_words'] ) ) {
			if ( ! is_string( $value ) ) {
				return;
			}
			$word_count = self::count_words( trim( $value ) );
			if ( $word_count < $definition['min_words'] ) {
				$warnings[] = sprintf( '%s has %d words (recommended: %d+)', $path, $word_count, $definition['min_words'] );
			}
		}
	}

	/**
	 * Validate traits in relaxed mode (warnings instead of errors).
	 *
	 * @param array $traits Trait section data.
	 * @param array $warnings Warning bucket (by reference).
	 * @return void
	 */
	private static function validate_traits_relaxed( $traits, array &$warnings ) {
		// Accept whatever traits we get, just log warnings if issues
		if ( ! is_array( $traits ) ) {
			return;
		}

		$names = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$name_key  = 'trait_' . $i . '_name';
			$score_key = 'trait_' . $i . '_score';

			if ( ! empty( $traits[ $name_key ] ) && is_string( $traits[ $name_key ] ) ) {
				$name = trim( $traits[ $name_key ] );
				if ( ! in_array( $name, self::TRAIT_MASTER_LIST, true ) ) {
					$warnings[] = sprintf( 'Trait "%s" not in master list (accepted anyway)', $name );
				}
				$names[] = $name;
			}
		}
	}

	/**
	 * Validate trait selection and scores against master list.
	 *
	 * @param array $traits Trait section data.
	 * @param array $errors Error bucket (by reference).
	 * @return void
	 */
	private static function validate_traits( $traits, array &$errors ) {
		if ( ! is_array( $traits ) ) {
			$errors[] = 'personality_traits must be an object.';
			return;
		}

		$names = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$name_key  = 'trait_' . $i . '_name';
			$score_key = 'trait_' . $i . '_score';

			if ( empty( $traits[ $name_key ] ) || ! is_string( $traits[ $name_key ] ) ) {
				$errors[] = sprintf( 'Missing or invalid personality_traits.%s', $name_key );
				continue;
			}

			$name  = trim( $traits[ $name_key ] );
			$score = isset( $traits[ $score_key ] ) ? $traits[ $score_key ] : null;

			if ( ! is_numeric( $score ) ) {
				$errors[] = sprintf( 'Missing or invalid personality_traits.%s', $score_key );
			} else {
				$int_score = intval( $score );
				if ( $int_score < 0 || $int_score > 100 ) {
					$errors[] = sprintf( 'personality_traits.%s must be between 0 and 100.', $score_key );
				}
			}

			if ( ! in_array( $name, self::TRAIT_MASTER_LIST, true ) ) {
				$errors[] = sprintf( 'Trait "%s" is not in the master list.', $name );
			} else {
				$names[] = $name;
			}
		}

		if ( count( array_unique( $names ) ) !== 3 ) {
			$errors[] = 'personality_traits must contain three unique trait names.';
		}
	}

	/**
	 * Validate an individual field and add errors when constraints fail.
	 *
	 * @param mixed  $value Field value.
	 * @param array  $definition Field definition.
	 * @param string $path Field path for error reporting.
	 * @param array  $errors Error bucket (by reference).
	 * @return void
	 */
	private static function validate_field( $value, array $definition, $path, array &$errors ) {
		if ( ! isset( $definition['type'] ) ) {
			return;
		}

		$type = $definition['type'];

		if ( 'string' === $type ) {
			if ( ! is_string( $value ) ) {
				$errors[] = sprintf( '%s must be a string.', $path );
				return;
			}

			$trimmed = trim( $value );
			if ( isset( $definition['min_length'] ) && strlen( $trimmed ) < $definition['min_length'] ) {
				$errors[] = sprintf( '%s is too short.', $path );
			}
			if ( isset( $definition['max_length'] ) && strlen( $trimmed ) > $definition['max_length'] ) {
				$errors[] = sprintf( '%s exceeds maximum length.', $path );
			}
			if ( isset( $definition['min_words'] ) || isset( $definition['max_words'] ) ) {
				$word_count = self::count_words( $trimmed );
				if ( isset( $definition['min_words'] ) && $word_count < $definition['min_words'] ) {
					$errors[] = sprintf( '%s must be at least %d words (found %d).', $path, $definition['min_words'], $word_count );
				}
				if ( isset( $definition['max_words'] ) && $word_count > $definition['max_words'] ) {
					$errors[] = sprintf( '%s must be at most %d words (found %d).', $path, $definition['max_words'], $word_count );
				}
			}
			if ( isset( $definition['allowed'] ) && ! in_array( $trimmed, $definition['allowed'], true ) ) {
				$errors[] = sprintf( '%s must be one of: %s.', $path, implode( ', ', $definition['allowed'] ) );
			}
			if ( isset( $definition['format'] ) && 'datetime' === $definition['format'] && false === strtotime( $trimmed ) ) {
				$errors[] = sprintf( '%s must be a valid datetime.', $path );
			}
		} elseif ( 'int' === $type ) {
			if ( ! is_numeric( $value ) ) {
				$errors[] = sprintf( '%s must be an integer.', $path );
				return;
			}

			$int_value = intval( $value );
			if ( isset( $definition['min_value'] ) && $int_value < $definition['min_value'] ) {
				$errors[] = sprintf( '%s must be greater than or equal to %d.', $path, $definition['min_value'] );
			}
			if ( isset( $definition['max_value'] ) && $int_value > $definition['max_value'] ) {
				$errors[] = sprintf( '%s must be less than or equal to %d.', $path, $definition['max_value'] );
			}
		}
	}

	/**
	 * Count words in a string, ignoring extra whitespace.
	 *
	 * @param string $text Text to count.
	 * @return int
	 */
	private static function count_words( $text ) {
		$text = wp_strip_all_tags( $text );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		if ( '' === $text ) {
			return 0;
		}

		return str_word_count( $text );
	}
}
