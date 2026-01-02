<?php
/**
 * Quote Handler for Teaser Reading Locked Sections
 *
 * Provides deterministic, gender-specific inspirational quotes
 * to replace wasted API-generated locked_teaser content.
 *
 * @package SoulMirror_Palm_Reading
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Quote Handler Class
 *
 * Manages selection of inspirational quotes for locked teaser sections.
 */
class SM_Quote_Handler {

	/**
	 * Quote database loaded from JSON
	 *
	 * @var array
	 */
	private static $quotes = null;

	/**
	 * Available sections for quotes
	 *
	 * @var array
	 */
	private static $sections = array(
		'love_patterns',
		'challenges_opportunities',
		'life_phase',
		'timeline_6_months',
		'guidance',
	);

	/**
	 * Load quotes from JSON file
	 *
	 * @return array Quote database
	 */
	private static function load_quotes() {
		if ( self::$quotes !== null ) {
			return self::$quotes;
		}

		$json_file = plugin_dir_path( __FILE__ ) . 'teaser-quotes.json';

		if ( ! file_exists( $json_file ) ) {
			SM_Logger::log(
				'error',
				'QUOTE_HANDLER',
				'Quote database file not found',
				array( 'file' => $json_file )
			);
			return array();
		}

		$json_content = file_get_contents( $json_file );
		if ( $json_content === false ) {
			SM_Logger::log(
				'error',
				'QUOTE_HANDLER',
				'Failed to read quote database file',
				array( 'file' => $json_file )
			);
			return array();
		}

		$quotes = json_decode( $json_content, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			SM_Logger::log(
				'error',
				'QUOTE_HANDLER',
				'Failed to parse quote database JSON',
				array(
					'error' => json_last_error_msg(),
					'file'  => $json_file,
				)
			);
			return array();
		}

		self::$quotes = $quotes;
		return self::$quotes;
	}

	/**
	 * Get quote for a specific section and gender
	 *
	 * Selection is deterministic based on reading_id to ensure
	 * the same reading always gets the same quote.
	 *
	 * @param string $section Section identifier (love_patterns, challenges_opportunities, etc.)
	 * @param string $gender Gender identifier (male, female)
	 * @param int    $reading_id Reading ID for deterministic selection
	 * @return string Selected quote
	 */
	public static function get_quote( $section, $gender, $reading_id ) {
		// Validate section
		if ( ! in_array( $section, self::$sections, true ) ) {
			SM_Logger::log(
				'warning',
				'QUOTE_HANDLER',
				'Invalid section requested',
				array(
					'section'    => $section,
					'reading_id' => $reading_id,
				)
			);
			return 'Your journey continues beyond what you can see now...';
		}

		// Normalize gender
		$gender = strtolower( trim( $gender ) );
		if ( ! in_array( $gender, array( 'male', 'female' ), true ) ) {
			SM_Logger::log(
				'warning',
				'QUOTE_HANDLER',
				'Invalid gender, defaulting to female',
				array(
					'gender'     => $gender,
					'reading_id' => $reading_id,
				)
			);
			$gender = 'female';
		}

		// Load quotes
		$quotes = self::load_quotes();
		if ( empty( $quotes ) ) {
			SM_Logger::log(
				'error',
				'QUOTE_HANDLER',
				'Quote database is empty',
				array(
					'section'    => $section,
					'gender'     => $gender,
					'reading_id' => $reading_id,
				)
			);
			return 'Your journey continues beyond what you can see now...';
		}

		// Get quotes for section and gender
		if ( ! isset( $quotes[ $section ] ) || ! isset( $quotes[ $section ][ $gender ] ) ) {
			SM_Logger::log(
				'error',
				'QUOTE_HANDLER',
				'Quotes not found for section/gender',
				array(
					'section'    => $section,
					'gender'     => $gender,
					'reading_id' => $reading_id,
				)
			);
			return 'Your journey continues beyond what you can see now...';
		}

		$quote_pool = $quotes[ $section ][ $gender ];
		if ( empty( $quote_pool ) ) {
			SM_Logger::log(
				'error',
				'QUOTE_HANDLER',
				'Quote pool is empty for section/gender',
				array(
					'section'    => $section,
					'gender'     => $gender,
					'reading_id' => $reading_id,
				)
			);
			return 'Your journey continues beyond what you can see now...';
		}

		// Deterministic selection using reading_id as seed
		// Combine reading_id with section name for additional variance
		$seed  = intval( $reading_id ) + crc32( $section );
		$index = $seed % count( $quote_pool );
		$quote = $quote_pool[ $index ];

		SM_Logger::log(
			'info',
			'QUOTE_HANDLER',
			'Quote selected',
			array(
				'section'     => $section,
				'gender'      => $gender,
				'reading_id'  => $reading_id,
				'seed'        => $seed,
				'pool_size'   => count( $quote_pool ),
				'index'       => $index,
				'quote_start' => substr( $quote, 0, 50 ),
			)
		);

		return $quote;
	}

	/**
	 * Get all quotes for a reading
	 *
	 * Returns an associative array with quote placeholders as keys.
	 *
	 * @param int    $reading_id Reading ID
	 * @param string $gender Gender identifier (male, female)
	 * @return array Associative array of quote placeholders => quotes
	 */
	public static function get_all_quotes( $reading_id, $gender ) {
		$quotes = array();

		foreach ( self::$sections as $section ) {
			$quote_key         = strtoupper( str_replace( '_', '_', $section ) ) . '_QUOTE';
			$quotes[ $quote_key ] = self::get_quote( $section, $gender, $reading_id );
		}

		SM_Logger::log(
			'info',
			'QUOTE_HANDLER',
			'All quotes generated for reading',
			array(
				'reading_id'  => $reading_id,
				'gender'      => $gender,
				'quote_count' => count( $quotes ),
			)
		);

		return $quotes;
	}

	/**
	 * Validate quote database integrity
	 *
	 * Checks that all sections and genders have exactly 20 quotes each.
	 *
	 * @return array Validation results
	 */
	public static function validate_quote_database() {
		$quotes = self::load_quotes();
		$results = array(
			'valid'   => true,
			'errors'  => array(),
			'summary' => array(),
		);

		foreach ( self::$sections as $section ) {
			foreach ( array( 'male', 'female' ) as $gender ) {
				if ( ! isset( $quotes[ $section ] ) || ! isset( $quotes[ $section ][ $gender ] ) ) {
					$results['valid']   = false;
					$results['errors'][] = "Missing quotes for {$section}/{$gender}";
					continue;
				}

				$count = count( $quotes[ $section ][ $gender ] );
				$results['summary'][ "{$section}/{$gender}" ] = $count;

				if ( $count !== 20 ) {
					$results['valid']   = false;
					$results['errors'][] = "Expected 20 quotes for {$section}/{$gender}, found {$count}";
				}
			}
		}

		return $results;
	}
}
