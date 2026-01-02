<?php
/**
 * Reports Handler
 *
 * Handles fetching, formatting, and presenting user reading reports for the dashboard.
 * Provides read-only access to past readings with pagination and metadata generation.
 *
 * @package MysticPalmReading
 * @since 2.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SM_Reports_Handler
 *
 * Manages user reports listing, pagination, and formatting for dashboard display.
 */
class SM_Reports_Handler {

	/**
	 * Singleton instance
	 *
	 * @var SM_Reports_Handler|null
	 */
	private static $instance = null;

	/**
	 * Initialize the handler (singleton pattern)
	 *
	 * @return SM_Reports_Handler
	 */
	public static function init() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get singleton instance
	 *
	 * @return SM_Reports_Handler
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
		// Nothing to initialize yet
	}

	/**
	 * Get reports for a specific user with pagination.
	 *
	 * Retrieves all readings associated with the user's account_id, ordered by
	 * most recent first. Joins with leads table to get user metadata.
	 *
	 * @param string $account_id Account Service user ID.
	 * @param int    $limit      Number of reports per page (10, 20, or 30).
	 * @param int    $offset     SQL offset for pagination.
	 * @return array Array of formatted report objects ready for template.
	 */
	public static function get_user_reports( $account_id, $limit = 10, $offset = 0 ) {
		global $wpdb;

		// Validate inputs
		if ( empty( $account_id ) ) {
			SM_Logger::log(
				'warning',
				'REPORTS_GET',
				'Empty account_id provided to get_user_reports',
				array()
			);
			return array();
		}

		// Validate and sanitize pagination parameters
		$limit  = self::validate_per_page( $limit );
		$offset = max( 0, intval( $offset ) );

		$db             = SM_Database::get_instance();
		$readings_table = $db->get_table_name( 'readings' );
		$leads_table    = $db->get_table_name( 'leads' );

		// Query to fetch readings with joined lead data
		$query = $wpdb->prepare(
			"SELECT
				r.id,
				r.account_id,
				r.reading_type,
				r.content_data,
				r.reading_html,
				r.created_at,
				r.updated_at,
				r.has_purchased,
				r.unlock_count,
				l.id as lead_id,
				l.name,
				l.email
			FROM {$readings_table} r
			INNER JOIN {$leads_table} l ON r.lead_id = l.id
			WHERE r.account_id = %s
			ORDER BY r.created_at DESC
			LIMIT %d OFFSET %d",
			$account_id,
			$limit,
			$offset
		);

		$results = $wpdb->get_results( $query );

		if ( empty( $results ) ) {
			SM_Logger::log(
				'info',
				'REPORTS_GET',
				'No reports found for user',
				array(
					'account_id' => $account_id,
					'limit'      => $limit,
					'offset'     => $offset,
				)
			);
			return array();
		}

		// Format each report for template consumption
		$formatted_reports = array();
		foreach ( $results as $reading ) {
			$formatted_reports[] = self::format_report_for_template( $reading );
		}

		SM_Logger::log(
			'info',
			'REPORTS_GET',
			'Retrieved reports for user',
			array(
				'account_id'    => $account_id,
				'reports_count' => count( $formatted_reports ),
				'limit'         => $limit,
				'offset'        => $offset,
			)
		);

		return $formatted_reports;
	}

	/**
	 * Get total count of reports for a user.
	 *
	 * Used for pagination to calculate total pages.
	 *
	 * @param string $account_id Account Service user ID.
	 * @return int Total number of reports.
	 */
	public static function get_user_reports_count( $account_id ) {
		global $wpdb;

		if ( empty( $account_id ) ) {
			return 0;
		}

		$db             = SM_Database::get_instance();
		$readings_table = $db->get_table_name( 'readings' );

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$readings_table} WHERE account_id = %s",
				$account_id
			)
		);

		return intval( $count );
	}

	/**
	 * Generate report title from reading data (hybrid approach).
	 *
	 * Attempts to extract a meaningful title from the content_data JSON.
	 * Falls back to date-based title if extraction fails.
	 *
	 * Strategy:
	 * 1. Try to extract from opening section or first heading in content_data
	 * 2. Fallback: "Palm Reading from {formatted_date}"
	 *
	 * @param object $reading Reading database row.
	 * @return string Generated title.
	 */
	private static function generate_report_title( $reading ) {
		$default_title = 'Palm Reading';

		if ( empty( $reading->created_at ) ) {
			return $default_title;
		}

		// Try to extract title from content_data JSON
		if ( ! empty( $reading->content_data ) ) {
			$content = json_decode( $reading->content_data, true );

			// Strategy 1: Check for opening section with a title
			if ( isset( $content['sections'] ) && is_array( $content['sections'] ) ) {
				foreach ( $content['sections'] as $section ) {
					if ( isset( $section['id'] ) && $section['id'] === 'opening' && ! empty( $section['title'] ) ) {
						$title = sanitize_text_field( $section['title'] );
						return self::append_report_time( $title, $reading->created_at );
					}
				}

				// Strategy 2: Use first section title if available
				if ( ! empty( $content['sections'][0]['title'] ) ) {
					$title = sanitize_text_field( $content['sections'][0]['title'] );
					return self::append_report_time( $title, $reading->created_at );
				}
			}

			// Strategy 3: Check for top-level title field
			if ( ! empty( $content['title'] ) ) {
				$title = sanitize_text_field( $content['title'] );
				return self::append_report_time( $title, $reading->created_at );
			}
		}

		// Fallback: Generate date-based title
		$date = mysql2date( 'F j, Y', $reading->created_at, false );
		return sprintf( 'Palm Reading from %s', $date ) . self::format_report_time_suffix( $reading->created_at );
	}

	/**
	 * Append time to a report title.
	 *
	 * @param string $title Title text.
	 * @param string $created_at Datetime string.
	 * @return string
	 */
	private static function append_report_time( $title, $created_at ) {
		return $title . self::format_report_time_suffix( $created_at );
	}

	/**
	 * Format report time suffix.
	 *
	 * @param string $created_at Datetime string.
	 * @return string
	 */
	private static function format_report_time_suffix( $created_at ) {
		if ( empty( $created_at ) ) {
			return '';
		}
		$time = mysql2date( 'H:i', $created_at, false );
		return ' - ' . $time;
	}

	/**
	 * Calculate estimated reading time from content.
	 *
	 * Counts words in all sections of the reading and estimates time
	 * based on average reading speed (200 words per minute).
	 *
	 * @param string $content_data JSON string of reading content.
	 * @param string $reading_html HTML fallback for legacy readings.
	 * @return string Formatted time estimate (e.g., "~4 min").
	 */
	private static function calculate_reading_time( $content_data, $reading_html = '' ) {
		$word_count = 0;

		if ( ! empty( $content_data ) ) {
			$word_count = self::count_words_in_content( $content_data );
		}

		if ( $word_count === 0 && ! empty( $reading_html ) ) {
			$word_count = str_word_count( wp_strip_all_tags( $reading_html ) );
		}

		if ( $word_count === 0 ) {
			return 'N/A';
		}

		// Average reading speed: 200 words per minute
		$minutes = max( 1, round( $word_count / 200 ) );

		return sprintf( '~%d min', $minutes );
	}

	/**
	 * Count total words in reading content JSON.
	 *
	 * Iterates through all sections and counts words in text content.
	 *
	 * @param string $content_data JSON string.
	 * @return int Total word count.
	 */
	private static function count_words_in_content( $content_data ) {
		$content = json_decode( $content_data, true );

		if ( empty( $content ) || ! is_array( $content ) ) {
			return 0;
		}

		$total_words = 0;

		// Count words in sections
		if ( isset( $content['sections'] ) && is_array( $content['sections'] ) ) {
			foreach ( $content['sections'] as $section ) {
				// Count title words
				if ( ! empty( $section['title'] ) ) {
					$total_words += str_word_count( strip_tags( $section['title'] ) );
				}

				// Count content words
				if ( ! empty( $section['content'] ) ) {
					$total_words += str_word_count( strip_tags( $section['content'] ) );
				}

				// Count locked_preview words (if exists)
				if ( ! empty( $section['locked_preview'] ) ) {
					$total_words += str_word_count( strip_tags( $section['locked_preview'] ) );
				}
			}
		}

		// Count words in top-level content field (alternative structure)
		if ( isset( $content['content'] ) && is_string( $content['content'] ) ) {
			$total_words += str_word_count( strip_tags( $content['content'] ) );
		}

		if ( $total_words === 0 ) {
			$total_words = self::count_words_recursive( $content );
		}

		return $total_words;
	}

	/**
	 * Recursively count words in nested arrays.
	 *
	 * @param mixed $value Value to inspect.
	 * @return int Total word count.
	 */
	private static function count_words_recursive( $value ) {
		$total = 0;

		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				$total += self::count_words_recursive( $item );
			}
		} elseif ( is_string( $value ) ) {
			$total += str_word_count( wp_strip_all_tags( $value ) );
		}

		return $total;
	}

	/**
	 * Format report data for template consumption.
	 *
	 * Converts database row into template-friendly format matching the
	 * structure expected by reportsGridTemplate.html.
	 *
	 * Template expects:
	 * {
	 *   id: "abc-123",
	 *   title: "Palm Reading from Nov 15",
	 *   date: "2023-11-15",
	 *   time: "14:30",
	 *   readingTime: "~4 min",
	 *   status: "completed"
	 * }
	 *
	 * @param object $reading Reading database row with joined lead data.
	 * @return array Formatted report array for JavaScript/template.
	 */
	private static function format_report_for_template( $reading ) {
		return array(
			'id'          => $reading->id,
			'lead_id'     => $reading->lead_id,
			'title'       => self::generate_report_title( $reading ),
			'date'        => mysql2date( 'Y-m-d', $reading->created_at, false ),
			'time'        => mysql2date( 'H:i', $reading->created_at, false ),
			'readingTime' => self::calculate_reading_time( $reading->content_data, $reading->reading_html ),
			'status'      => 'completed', // All readings in DB are completed
			'type'        => ! empty( $reading->reading_type ) ? $reading->reading_type : 'aura_teaser',
			'isPurchased' => ! empty( $reading->has_purchased ),
			'unlockCount' => intval( $reading->unlock_count ),
		);
	}

	/**
	 * Validate and sanitize per_page parameter.
	 *
	 * Only allows 10, 20, or 30 items per page. Defaults to 10 if invalid.
	 *
	 * @param int $per_page Requested items per page.
	 * @return int Validated items per page (10, 20, or 30).
	 */
	private static function validate_per_page( $per_page ) {
		$allowed_values = array( 10, 20, 30 );
		$per_page       = intval( $per_page );

		if ( ! in_array( $per_page, $allowed_values, true ) ) {
			return 10; // Default
		}

		return $per_page;
	}
}
