<?php
/**
 * Unlock Handler
 *
 * Manages teaser reading unlock state transitions and limits.
 *
 * @package MysticPalmReading
 * @since 1.3.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SM_Unlock_Handler
 *
 * Implements the 2-free-unlocks state machine and offerings redirect.
 */
class SM_Unlock_Handler {

	const MAX_FREE_UNLOCKS = 2;

	/**
	 * Singleton instance.
	 *
	 * @var SM_Unlock_Handler|null
	 */
	private static $instance = null;

	/**
	 * Initialize the handler (singleton).
	 *
	 * @return SM_Unlock_Handler
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Attempt to unlock a section for a reading.
	 *
	 * @param string $reading_id  Reading UUID.
	 * @param string $section     Section key to unlock.
	 * @param string $lead_id     Lead UUID (ownership validation). Optional but recommended.
	 * @return array|WP_Error Unlock result.
	 */
	public function attempt_unlock( $reading_id, $section, $lead_id = '', $current_page_url = '' ) {
		$section = $this->sanitize_section( $section );

		if ( '' === $section ) {
			return new WP_Error(
				'invalid_section',
				__( 'Invalid section requested.', 'mystic-palm-reading' )
			);
		}

		$reading_service = SM_Reading_Service::get_instance();
		$reading         = $reading_service->get_reading_by_id( $reading_id, true );

		if ( is_wp_error( $reading ) ) {
			return $reading;
		}

		// Optional ownership validation.
		if ( ! empty( $lead_id ) && isset( $reading->lead_id ) && $reading->lead_id !== $lead_id ) {
			return new WP_Error(
				'unauthorized',
				__( 'This reading does not belong to you.', 'mystic-palm-reading' ),
				array( 'status' => 403 )
			);
		}

		$allowed_sections = $this->get_allowed_sections();

		if ( ! in_array( $section, $allowed_sections, true ) ) {
			return new WP_Error(
				'invalid_section',
				__( 'Invalid section requested.', 'mystic-palm-reading' )
			);
		}

		$unlock_count      = isset( $reading->unlock_count ) ? intval( $reading->unlock_count ) : 0;
		$current_unlocked  = $reading_service->parse_unlocked_sections( isset( $reading->unlocked_section ) ? $reading->unlocked_section : '' );
		$redirect_url      = $this->get_offerings_url( $current_page_url );
		$has_purchased     = ! empty( $reading->has_purchased );

		// --- DIAGNOSTIC LOGGING ---
		SM_Logger::log(
			'debug',
			'UNLOCK_ATTEMPT_CHECK',
			'Checking unlock count before attempt',
			array(
				'reading_id'           => $reading_id,
				'section_attempted'    => $section,
				'current_unlock_count' => $unlock_count,
				'max_free_unlocks'     => self::MAX_FREE_UNLOCKS,
				'is_premium_section'   => $this->is_premium_section( $section ),
				'has_purchased_reading' => $has_purchased,
				'current_page_url'     => $current_page_url,
			)
		);
		// --- END LOGGING ---

		// Premium sections require payment - redirect immediately if not purchased.
		if ( $this->is_premium_section( $section ) && ! $has_purchased ) {
			SM_Logger::log(
				'info',
				'PREMIUM_UNLOCK_BLOCKED',
				'Premium section unlock attempt without purchase',
				array(
					'reading_id' => $reading_id,
					'section'    => $section,
				)
			);

			return array(
				'status'        => 'premium_locked',
				'message'       => __( 'This premium insight requires a full reading purchase.', 'mystic-palm-reading' ),
				'section'       => $section,
				'redirect_url'  => $redirect_url,
			);
		}

		if ( $has_purchased ) {
			return array(
				'status'             => 'unlocked_all',
				'section'            => $section,
				'unlocked_sections'  => $allowed_sections,
				'unlocks_remaining'  => 0,
			);
		}

		if ( in_array( $section, $current_unlocked, true ) ) {
			return array(
				'status'             => 'already_unlocked',
				'section'            => $section,
				'unlocked_sections'  => $current_unlocked,
				'unlocks_remaining'  => max( 0, self::MAX_FREE_UNLOCKS - $unlock_count ),
			);
		}

		if ( $unlock_count >= self::MAX_FREE_UNLOCKS ) {
			return array(
				'status'        => 'limit_reached',
				'message'       => __( 'Unlock limit reached.', 'mystic-palm-reading' ),
				'redirect_url'  => $redirect_url,
				'unlock_count'  => $unlock_count,
			);
		}

		$current_unlocked[] = $section;
		$current_unlocked   = array_values( array_unique( $current_unlocked ) );
		$new_count          = $unlock_count + 1;

		$update_result = $reading_service->update_reading(
			$reading_id,
			array(
				'unlocked_section' => $current_unlocked,
				'unlock_count'     => $new_count,
			)
		);

		if ( is_wp_error( $update_result ) ) {
			SM_Logger::log(
				'error',
				'UNLOCK_HANDLER',
				'Failed to update unlock state',
				array(
					'reading_id' => $reading_id,
					'section'    => $section,
					'error'      => $update_result->get_error_message(),
				)
			);

			return $update_result;
		}

		SM_Logger::log(
			'info',
			'UNLOCK_HANDLER',
			'Section unlocked',
			array(
				'reading_id' => $reading_id,
				'section'    => $section,
				'unlock_count' => $new_count,
			)
		);

		return array(
			'status'             => 'unlocked',
			'section'            => $section,
			'unlocked_sections'  => $current_unlocked,
			'unlocks_remaining'  => max( 0, self::MAX_FREE_UNLOCKS - $new_count ),
		);
	}

	/**
	 * Normalize section key.
	 *
	 * @param string $section Section name.
	 * @return string
	 */
	private function sanitize_section( $section ) {
		if ( ! is_string( $section ) ) {
			return '';
		}

		return sanitize_key( $section );
	}

	/**
	 * Get allowed sections based on configuration.
	 *
	 * @return array
	 */
	private function get_allowed_sections() {
		$configured = get_option(
			'sm_locked_sections',
			array( 'love', 'challenges', 'phase', 'timeline', 'guidance' )
		);

		if ( ! is_array( $configured ) ) {
			$configured = array( 'love', 'challenges', 'phase', 'timeline', 'guidance' );
		}

		$base = array_map( 'sanitize_key', $configured );
		if ( in_array( 'life_phase', $base, true ) && ! in_array( 'phase', $base, true ) ) {
			$base[] = 'phase';
		}

		// Modals are grouped as one unlock.
		$base[] = 'modals';

		// Add premium sections (100% locked, require payment).
		$premium_sections = $this->get_premium_sections();
		$base = array_merge( $base, $premium_sections );

		return array_values( array_unique( array_filter( $base ) ) );
	}

	/**
	 * Get list of premium sections that require payment.
	 *
	 * @return array
	 */
	private function get_premium_sections() {
		return array(
			'deep_relationship_analysis',
			'extended_timeline_12_months',
			'life_purpose_soul_mission',
			'shadow_work_transformation',
			'practical_guidance_action_plan',
		);
	}

	/**
	 * Check if a section is a premium section.
	 *
	 * @param string $section Section key.
	 * @return bool
	 */
	private function is_premium_section( $section ) {
		return in_array( $section, $this->get_premium_sections(), true );
	}

	/**
	 * Fetch offerings redirect URL from settings.
	 *
	 * @param string $return_url Optional. The URL to return to after the offerings page.
	 * @return string Full URL for cross-site redirect support.
	 */
	private function get_offerings_url( $return_url = '' ) {
		$settings = SM_Settings::init();
		return $settings->get_offerings_redirect_url( $return_url );
	}
}
