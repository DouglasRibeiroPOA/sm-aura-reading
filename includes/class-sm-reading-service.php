<?php
/**
 * Reading Service Class
 *
 * Handles CRUD operations for palm readings (teaser and legacy formats).
 * Provides methods for creating, retrieving, updating, and deleting readings.
 *
 * @package MysticAuraReading
 * @since 1.3.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SM_Reading_Service
 *
 * Service layer for reading data management.
 */
class SM_Reading_Service {

	/**
	 * Singleton instance
	 *
	 * @var SM_Reading_Service|null
	 */
	private static $instance = null;

	/**
	 * Table name (without prefix)
	 *
	 * @var string
	 */
	private $table_name = 'sm_aura_readings';

	/**
	 * Initialize the service (singleton pattern)
	 *
	 * @return SM_Reading_Service
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
	 * @return SM_Reading_Service
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::init();
		}
		return self::$instance;
	}

	/**
	 * Create a new reading (used by AI handler after generation)
	 *
	 * This method is primarily called by SM_AI_Handler::save_teaser_reading()
	 * but is exposed here for flexibility.
	 *
	 * @param array $data Reading data.
	 * @return string|WP_Error Reading ID on success, WP_Error on failure.
	 */
	public function create_reading( $data ) {
		global $wpdb;
		$table = SM_Database::get_instance()->get_table_name('readings');

		// Validate required fields
		if ( empty( $data['lead_id'] ) ) {
			return new WP_Error(
				'missing_lead_id',
				__( 'Lead ID is required to create a reading.', 'mystic-aura-reading' )
			);
		}

		if ( empty( $data['reading_type'] ) ) {
			$data['reading_type'] = 'aura_teaser'; // Default to teaser
		}

		// Generate UUID if not provided
		if ( empty( $data['id'] ) ) {
			$data['id'] = wp_generate_uuid4();
		}

		// Validate reading type
		$allowed_types = array( 'aura_teaser', 'aura_full', 'love_insight' );
		if ( ! in_array( $data['reading_type'], $allowed_types, true ) ) {
			return new WP_Error(
				'invalid_reading_type',
				__( 'Invalid reading type.', 'mystic-aura-reading' )
			);
		}

		// Build payload
		$payload = array(
			'id'            => $data['id'],
			'lead_id'       => sanitize_text_field( $data['lead_id'] ),
			'reading_type'  => sanitize_text_field( $data['reading_type'] ),
			'unlock_count'  => isset( $data['unlock_count'] ) ? intval( $data['unlock_count'] ) : 0,
			'has_purchased' => isset( $data['has_purchased'] ) ? (bool) $data['has_purchased'] : false,
			'created_at'    => isset( $data['created_at'] ) ? $data['created_at'] : current_time( 'mysql' ),
		);

		// Add content_data for JSON-based readings
		if ( isset( $data['content_data'] ) ) {
			if ( is_array( $data['content_data'] ) ) {
				$payload['content_data'] = wp_json_encode( $data['content_data'] );
			} else {
				$payload['content_data'] = $data['content_data'];
			}
		}

		// Add reading_html for legacy HTML-based readings
		if ( isset( $data['reading_html'] ) ) {
			$payload['reading_html'] = $data['reading_html'];
		}

		// Add optional fields
		if ( array_key_exists( 'unlocked_section', $data ) ) {
			$payload['unlocked_section'] = $this->normalize_unlocked_sections_for_storage( $data['unlocked_section'] );
		}

		if ( isset( $data['prompt_template_used'] ) ) {
			$payload['prompt_template_used'] = sanitize_text_field( $data['prompt_template_used'] );
		}

		$formats = array(
			'%s', // id
			'%s', // lead_id
			'%s', // reading_type
			'%d', // unlock_count
			'%d', // has_purchased
			'%s', // created_at
		);

		if ( isset( $payload['content_data'] ) ) {
			$formats[] = '%s';
		}
		if ( isset( $payload['reading_html'] ) ) {
			$formats[] = '%s';
		}
		if ( isset( $payload['unlocked_section'] ) ) {
			$formats[] = '%s';
		}
		if ( isset( $payload['prompt_template_used'] ) ) {
			$formats[] = '%s';
		}

		$result = $wpdb->insert( $table, $payload, $formats );

		if ( false === $result ) {
			SM_Logger::log(
				'error',
				'READING_SERVICE',
				'Failed to create reading',
				array(
					'lead_id' => $data['lead_id'],
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
			'READING_SERVICE',
			'Reading created successfully',
			array(
				'reading_id'   => $payload['id'],
				'lead_id'      => $data['lead_id'],
				'reading_type' => $data['reading_type'],
			)
		);

		return $payload['id'];
	}

	/**
	 * Get a reading by ID
	 *
	 * @param string $reading_id Reading UUID.
	 * @param bool   $parse_json Whether to parse content_data JSON. Default true.
	 * @return object|WP_Error Reading object on success, WP_Error on failure.
	 */
	public function get_reading_by_id( $reading_id, $parse_json = true ) {
		global $wpdb;
		$table = SM_Database::get_instance()->get_table_name('readings');

		if ( empty( $reading_id ) ) {
			return new WP_Error(
				'missing_reading_id',
				__( 'Reading ID is required.', 'mystic-aura-reading' )
			);
		}

		$reading = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE id = %s",
				$reading_id
			)
		);

		if ( ! $reading ) {
			return new WP_Error(
				'reading_not_found',
				__( 'Reading not found.', 'mystic-aura-reading' )
			);
		}

		// Parse JSON content if requested
		if ( $parse_json && ! empty( $reading->content_data ) ) {
			$parsed = json_decode( $reading->content_data, true );
			if ( $parsed ) {
				$reading->content_data_parsed = $parsed;
			}
		}

		return $reading;
	}

	/**
	 * Get all readings for a specific lead
	 *
	 * @param string $lead_id Lead UUID.
	 * @param array  $args Optional query arguments.
	 * @return array|WP_Error Array of reading objects on success, WP_Error on failure.
	 */
	public function get_user_readings( $lead_id, $args = array() ) {
		global $wpdb;
		$table = SM_Database::get_instance()->get_table_name('readings');

		if ( empty( $lead_id ) ) {
			return new WP_Error(
				'missing_lead_id',
				__( 'Lead ID is required.', 'mystic-aura-reading' )
			);
		}

		// Parse arguments
		$defaults = array(
			'reading_type' => null,       // Filter by type (e.g., 'aura_teaser')
			'order_by'     => 'created_at', // Order by field
			'order'        => 'DESC',      // ASC or DESC
			'limit'        => null,        // Limit results
			'offset'       => 0,           // Offset for pagination
			'parse_json'   => true,        // Parse content_data JSON
		);

		$args = wp_parse_args( $args, $defaults );

		// Build query
		$where = $wpdb->prepare( 'WHERE lead_id = %s', $lead_id );

		if ( ! empty( $args['reading_type'] ) ) {
			$where .= $wpdb->prepare( ' AND reading_type = %s', $args['reading_type'] );
		}

		$order_by = sanitize_sql_orderby( $args['order_by'] . ' ' . $args['order'] );
		if ( ! $order_by ) {
			$order_by = 'created_at DESC';
		}

		$limit_clause = '';
		if ( ! empty( $args['limit'] ) ) {
			$limit_clause = $wpdb->prepare( 'LIMIT %d OFFSET %d', intval( $args['limit'] ), intval( $args['offset'] ) );
		}

		$query = "SELECT * FROM $table $where ORDER BY $order_by $limit_clause";

		$readings = $wpdb->get_results( $query );

		if ( $wpdb->last_error ) {
			SM_Logger::log(
				'error',
				'READING_SERVICE',
				'Failed to retrieve user readings',
				array(
					'lead_id' => $lead_id,
					'error'   => $wpdb->last_error,
				)
			);
			return new WP_Error(
				'database_error',
				__( 'Could not retrieve readings.', 'mystic-aura-reading' )
			);
		}

		// Parse JSON content if requested
		if ( $args['parse_json'] && ! empty( $readings ) ) {
			foreach ( $readings as $reading ) {
				if ( ! empty( $reading->content_data ) ) {
					$parsed = json_decode( $reading->content_data, true );
					if ( $parsed ) {
						$reading->content_data_parsed = $parsed;
					}
				}
			}
		}

		return $readings;
	}

	/**
	 * Get the latest reading for a lead
	 *
	 * @param string $lead_id Lead UUID.
	 * @param string $reading_type Optional reading type filter.
	 * @return object|WP_Error Reading object on success, WP_Error on failure.
	 */
	public function get_latest_reading( $lead_id, $reading_type = null ) {
		$args = array(
			'limit'      => 1,
			'order_by'   => 'created_at',
			'order'      => 'DESC',
			'parse_json' => true,
		);

		if ( $reading_type ) {
			$args['reading_type'] = $reading_type;
		}

		$readings = $this->get_user_readings( $lead_id, $args );

		if ( is_wp_error( $readings ) ) {
			return $readings;
		}

		if ( empty( $readings ) ) {
			return new WP_Error(
				'reading_not_found',
				__( 'No reading found.', 'mystic-aura-reading' )
			);
		}

		return $readings[0];
	}

	/**
	 * Update a reading (primarily for unlock state changes)
	 *
	 * @param string $reading_id Reading UUID.
	 * @param array  $data Fields to update.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function update_reading( $reading_id, $data ) {
		global $wpdb;
		$table = SM_Database::get_instance()->get_table_name('readings');

		if ( empty( $reading_id ) ) {
			return new WP_Error(
				'missing_reading_id',
				__( 'Reading ID is required.', 'mystic-aura-reading' )
			);
		}

		// Verify reading exists
		$existing = $this->get_reading_by_id( $reading_id, false );
		if ( is_wp_error( $existing ) ) {
			return $existing;
		}

		// Build update payload (only allow specific fields)
		$allowed_fields = array( 'unlocked_section', 'unlock_count', 'has_purchased', 'updated_at' );
		$payload        = array();
		$formats        = array();

		foreach ( $allowed_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				if ( 'unlock_count' === $field ) {
					$payload[ $field ] = intval( $data[ $field ] );
					$formats[]         = '%d';
				} elseif ( 'has_purchased' === $field ) {
					$payload[ $field ] = (bool) $data[ $field ];
					$formats[]         = '%d';
				} else {
					$payload[ $field ] = $this->normalize_unlocked_sections_for_storage( $data[ $field ] );
					$formats[]         = '%s';
				}
			}
		}

		// Always update updated_at timestamp
		if ( ! isset( $payload['updated_at'] ) ) {
			$payload['updated_at'] = current_time( 'mysql' );
			$formats[]             = '%s';
		}

		if ( empty( $payload ) ) {
			return new WP_Error(
				'no_fields_to_update',
				__( 'No valid fields to update.', 'mystic-aura-reading' )
			);
		}

		$result = $wpdb->update(
			$table,
			$payload,
			array( 'id' => $reading_id ),
			$formats,
			array( '%s' )
		);

		if ( false === $result ) {
			SM_Logger::log(
				'error',
				'READING_SERVICE',
				'Failed to update reading',
				array(
					'reading_id' => $reading_id,
					'error'      => $wpdb->last_error,
				)
			);
			return new WP_Error(
				'database_error',
				__( 'Could not update reading.', 'mystic-aura-reading' )
			);
		}

		SM_Logger::log(
			'info',
			'READING_SERVICE',
			'Reading updated successfully',
			array(
				'reading_id' => $reading_id,
				'fields'     => array_keys( $payload ),
			)
		);

		return true;
	}

	/**
	 * Update reading data for upgrade-in-place flows.
	 *
	 * @param string $reading_id Reading UUID.
	 * @param array  $data Fields to update.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function update_reading_data( $reading_id, $data ) {
		global $wpdb;
		$table = SM_Database::get_instance()->get_table_name('readings');

		if ( empty( $reading_id ) ) {
			return new WP_Error(
				'missing_reading_id',
				__( 'Reading ID is required.', 'mystic-aura-reading' )
			);
		}

		$existing = $this->get_reading_by_id( $reading_id, false );
		if ( is_wp_error( $existing ) ) {
			return $existing;
		}

		$allowed_fields = array(
			'reading_type',
			'content_data',
			'account_id',
			'unlock_count',
			'unlocked_section',
			'has_purchased',
			'updated_at',
		);

		$payload = array();
		$formats = array();

		foreach ( $allowed_fields as $field ) {
			if ( ! array_key_exists( $field, $data ) ) {
				continue;
			}

			switch ( $field ) {
				case 'reading_type':
					$payload[ $field ] = sanitize_text_field( $data[ $field ] );
					$formats[]         = '%s';
					break;
				case 'content_data':
					if ( is_array( $data[ $field ] ) ) {
						$payload[ $field ] = wp_json_encode( $data[ $field ] );
					} else {
						$payload[ $field ] = $data[ $field ];
					}
					$formats[] = '%s';
					break;
				case 'account_id':
					$payload[ $field ] = sanitize_text_field( $data[ $field ] );
					$formats[]         = '%s';
					break;
				case 'unlock_count':
					$payload[ $field ] = intval( $data[ $field ] );
					$formats[]         = '%d';
					break;
				case 'has_purchased':
					$payload[ $field ] = (bool) $data[ $field ];
					$formats[]         = '%d';
					break;
				case 'unlocked_section':
					$payload[ $field ] = $this->normalize_unlocked_sections_for_storage( $data[ $field ] );
					$formats[]         = '%s';
					break;
				case 'updated_at':
					$payload[ $field ] = $data[ $field ];
					$formats[]         = '%s';
					break;
			}
		}

		if ( ! isset( $payload['updated_at'] ) ) {
			$payload['updated_at'] = current_time( 'mysql' );
			$formats[]             = '%s';
		}

		if ( empty( $payload ) ) {
			return new WP_Error(
				'no_fields_to_update',
				__( 'No valid fields to update.', 'mystic-aura-reading' )
			);
		}

		$result = $wpdb->update(
			$table,
			$payload,
			array( 'id' => $reading_id ),
			$formats,
			array( '%s' )
		);

		if ( false === $result ) {
			SM_Logger::log(
				'error',
				'READING_SERVICE',
				'Failed to update reading data',
				array(
					'reading_id' => $reading_id,
					'error'      => $wpdb->last_error,
				)
			);
			return new WP_Error(
				'database_error',
				__( 'Could not update your reading. Please try again.', 'mystic-aura-reading' )
			);
		}

		SM_Logger::log(
			'info',
			'READING_SERVICE',
			'Reading data updated successfully',
			array(
				'reading_id' => $reading_id,
			)
		);

		return true;
	}

	/**
	 * Delete a reading (admin only - cascades are handled by DB foreign key)
	 *
	 * @param string $reading_id Reading UUID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete_reading( $reading_id ) {
		global $wpdb;
		$table = SM_Database::get_instance()->get_table_name('readings');

		if ( empty( $reading_id ) ) {
			return new WP_Error(
				'missing_reading_id',
				__( 'Reading ID is required.', 'mystic-aura-reading' )
			);
		}

		// Verify reading exists
		$existing = $this->get_reading_by_id( $reading_id, false );
		if ( is_wp_error( $existing ) ) {
			return $existing;
		}

		$result = $wpdb->delete(
			$table,
			array( 'id' => $reading_id ),
			array( '%s' )
		);

		if ( false === $result ) {
			SM_Logger::log(
				'error',
				'READING_SERVICE',
				'Failed to delete reading',
				array(
					'reading_id' => $reading_id,
					'error'      => $wpdb->last_error,
				)
			);
			return new WP_Error(
				'database_error',
				__( 'Could not delete reading.', 'mystic-aura-reading' )
			);
		}

		SM_Logger::log(
			'info',
			'READING_SERVICE',
			'Reading deleted successfully',
			array(
				'reading_id' => $reading_id,
			)
		);

		return true;
	}

	/**
	 * Count total readings for a lead
	 *
	 * @param string $lead_id Lead UUID.
	 * @param string $reading_type Optional reading type filter.
	 * @return int|WP_Error Count on success, WP_Error on failure.
	 */
	public function count_readings( $lead_id, $reading_type = null ) {
		global $wpdb;
		$table = SM_Database::get_instance()->get_table_name('readings');

		if ( empty( $lead_id ) ) {
			return new WP_Error(
				'missing_lead_id',
				__( 'Lead ID is required.', 'mystic-aura-reading' )
			);
		}

		$where = $wpdb->prepare( 'WHERE lead_id = %s', $lead_id );

		if ( ! empty( $reading_type ) ) {
			$where .= $wpdb->prepare( ' AND reading_type = %s', $reading_type );
		}

		$count = $wpdb->get_var( "SELECT COUNT(*) FROM $table $where" );

		if ( $wpdb->last_error ) {
			SM_Logger::log(
				'error',
				'READING_SERVICE',
				'Failed to count readings',
				array(
					'lead_id' => $lead_id,
					'error'   => $wpdb->last_error,
				)
			);
			return new WP_Error(
				'database_error',
				__( 'Could not count readings.', 'mystic-aura-reading' )
			);
		}

		return intval( $count );
	}

	/**
	 * Check if a reading belongs to a specific lead (ownership validation)
	 *
	 * @param string $reading_id Reading UUID.
	 * @param string $lead_id Lead UUID.
	 * @return bool True if reading belongs to lead, false otherwise.
	 */
	public function verify_ownership( $reading_id, $lead_id ) {
		global $wpdb;
		$table = SM_Database::get_instance()->get_table_name('readings');

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table WHERE id = %s AND lead_id = %s",
				$reading_id,
				$lead_id
			)
		);

		return $count > 0;
	}

	/**
	 * Get reading with unlock status info (for template rendering)
	 *
	 * Returns reading with additional metadata about unlock state.
	 *
	 * @param string $reading_id Reading UUID.
	 * @return array|WP_Error Array with reading and unlock info on success, WP_Error on failure.
	 */
	public function get_reading_with_unlock_info( $reading_id ) {
		$reading = $this->get_reading_by_id( $reading_id, true );

		if ( is_wp_error( $reading ) ) {
			return $reading;
		}

		// Get locked sections configuration from wp_options
		$locked_sections = get_option(
			'sm_locked_sections',
			array( 'love', 'challenges', 'life_phase', 'timeline', 'guidance' )
		);

		$unlocked_sections = $this->parse_unlocked_sections( isset( $reading->unlocked_section ) ? $reading->unlocked_section : '' );

		if ( $reading->has_purchased ) {
			$unlocked_sections = $locked_sections;
		}

		return array(
			'reading'           => $reading,
			'locked_sections'   => $locked_sections,
			'unlocked_sections' => $unlocked_sections,
			'can_unlock_free'   => ( $reading->unlock_count < 2 && ! $reading->has_purchased ),
			'has_full_access'   => (bool) $reading->has_purchased,
		);
	}

	/**
	 * Normalize unlocked sections for storage.
	 *
	 * Accepts either a scalar (single section) or an array of sections and
	 * returns a JSON string suitable for persistence.
	 *
	 * @param mixed $sections Sections input.
	 * @return string
	 */
	private function normalize_unlocked_sections_for_storage( $sections ) {
		// Allow empty values.
		if ( empty( $sections ) && '0' !== $sections ) {
			return '';
		}

		if ( is_array( $sections ) ) {
			$cleaned = array();
			foreach ( $sections as $section ) {
				if ( ! is_string( $section ) ) {
					continue;
				}
				$cleaned[] = sanitize_text_field( strtolower( trim( $section ) ) );
			}

			$cleaned = array_values( array_unique( array_filter( $cleaned ) ) );

			return wp_json_encode( $cleaned );
		}

		// Scalar fallback for backward compatibility.
		return sanitize_text_field( $sections );
	}

	/**
	 * Parse unlocked sections from storage.
	 *
	 * @param string $value Stored value.
	 * @return array
	 */
	public function parse_unlocked_sections( $value ) {
		if ( empty( $value ) ) {
			return array();
		}

		// Attempt to decode JSON array first.
		$decoded = json_decode( $value, true );
		if ( is_array( $decoded ) ) {
			$cleaned = array();
			foreach ( $decoded as $section ) {
				if ( is_string( $section ) && $section !== '' ) {
					$cleaned[] = sanitize_text_field( strtolower( trim( $section ) ) );
				}
			}

			return array_values( array_unique( array_filter( $cleaned ) ) );
		}

		// Fallback: single string stored previously.
		if ( is_string( $value ) ) {
			$single = sanitize_text_field( strtolower( trim( $value ) ) );
			return $single ? array( $single ) : array();
		}

		return array();
	}
}
