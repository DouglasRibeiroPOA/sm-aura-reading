<?php
/**
 * MailerLite integration handler for SoulMirror.
 *
 * @package MysticPalmReadingAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles MailerLite v3 API integration for subscriber management.
 */
class SM_MailerLite_Handler {

	/**
	 * MailerLite API base URL.
	 */
	private const API_BASE_URL = 'https://connect.mailerlite.com/api';

	/**
	 * Singleton instance.
	 *
	 * @var SM_MailerLite_Handler|null
	 */
	private static $instance = null;

	/**
	 * Initialize the handler.
	 *
	 * @return SM_MailerLite_Handler
	 */
	public static function init() {
		$instance = self::get_instance();
		add_action( 'sm_mailerlite_sync', array( $instance, 'handle_scheduled_sync' ), 10, 2 );
		return $instance;
	}

	/**
	 * Get singleton instance.
	 *
	 * @return SM_MailerLite_Handler
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
		// Private constructor for singleton.
	}

	/**
	 * Sync subscriber to MailerLite after OTP verification.
	 *
	 * This method is non-blocking - failures will be logged but won't prevent user progression.
	 *
	 * @param string $lead_id The lead UUID.
	 * @return bool True on success, false on failure.
	 */
	public function sync_subscriber( $lead_id, $extra_fields = array() ) {
		if ( SM_Dev_Mode::should_skip_mailerlite() ) {
			SM_Logger::log(
				'info',
				'MAILERLITE_SYNC',
				'MailerLite sync skipped (DevMode MailerLite disabled)',
				array( 'lead_id' => $lead_id )
			);
			return true;
		}

		$settings = SM_Settings::init();
		// Retrieve lead data.
		$lead = SM_Lead_Handler::get_instance()->get_lead_by_id( $lead_id );

		if ( ! $lead ) {
			SM_Logger::log(
				'error',
				'MAILERLITE_SYNC',
				'Lead not found for MailerLite sync',
				array( 'lead_id' => $lead_id )
			);
			return false;
		}

		// Get API credentials from settings.
		$api_key  = $settings->get_mailerlite_api_key();
		$group_id = $settings->get_mailerlite_group_id();

		if ( empty( $api_key ) || empty( $group_id ) ) {
			SM_Logger::log(
				'warning',
				'MAILERLITE_SYNC',
				'MailerLite API credentials not configured',
				array( 'lead_id' => $lead_id )
			);
			return false;
		}

		SM_Logger::log(
			'info',
			'MAILERLITE_SYNC',
			'Starting MailerLite sync',
			array(
				'lead_id' => $lead_id,
				'email'   => $this->mask_email( $lead->email ),
			)
		);

		// Upsert subscriber.
		$subscriber = $this->upsert_subscriber( $lead, $api_key, $extra_fields );

		if ( ! $subscriber ) {
			// Error already logged in upsert_subscriber.
			return false;
		}

		// Assign to group.
		$group_assigned = $this->assign_to_group( $subscriber['id'], $group_id, $api_key );

		if ( ! $group_assigned ) {
			// Error already logged in assign_to_group.
			return false;
		}

		// Log success.
		SM_Logger::log(
			'info',
			'MAILERLITE_SYNC',
			'Subscriber synced successfully',
			array(
				'lead_id'       => $lead_id,
				'email'         => $this->mask_email( $lead->email ),
				'subscriber_id' => $subscriber['id'],
				'group_id'      => $group_id,
			)
		);

		// Fire custom hook.
		do_action( 'sm_mailerlite_synced', $lead_id, $subscriber['id'] );

		return true;
	}

	/**
	 * Schedule a MailerLite sync to avoid blocking user-facing requests.
	 *
	 * @param string $lead_id Lead UUID.
	 * @return bool True if scheduled, false otherwise.
	 */
	public function schedule_sync( $lead_id, $extra_fields = array() ) {
		$lead_id = sanitize_text_field( (string) $lead_id );
		if ( '' === $lead_id ) {
			return false;
		}

		$extra_fields = $this->sanitize_extra_fields( $extra_fields );

		if ( SM_Dev_Mode::should_skip_mailerlite() ) {
			SM_Logger::log(
				'info',
				'MAILERLITE_SYNC',
				'MailerLite sync not scheduled (DevMode MailerLite disabled)',
				array( 'lead_id' => $lead_id )
			);
			return true;
		}

		if ( wp_next_scheduled( 'sm_mailerlite_sync', array( $lead_id, $extra_fields ) ) ) {
			return true;
		}
		if ( empty( $extra_fields ) && wp_next_scheduled( 'sm_mailerlite_sync', array( $lead_id ) ) ) {
			return true;
		}

		SM_Logger::log(
			'info',
			'MAILERLITE_SYNC',
			'MailerLite sync scheduled',
			array( 'lead_id' => $lead_id )
		);

		return (bool) wp_schedule_single_event( time() + 5, 'sm_mailerlite_sync', array( $lead_id, $extra_fields ) );
	}

	/**
	 * Cron callback to perform MailerLite sync.
	 *
	 * @param string $lead_id Lead UUID.
	 * @return void
	 */
	public function handle_scheduled_sync( $lead_id, $extra_fields = array() ) {
		$this->sync_subscriber( $lead_id, $extra_fields );
	}

	/**
	 * Upsert (create or update) a subscriber in MailerLite.
	 *
	 * @param object $lead    Lead data from database.
	 * @param string $api_key MailerLite API key.
	 * @return array|false Subscriber data on success, false on failure.
	 */
	private function upsert_subscriber( $lead, $api_key, $extra_fields = array() ) {
		$endpoint = self::API_BASE_URL . '/subscribers';

		$fields = $this->build_subscriber_fields( $lead, $extra_fields );
		$body = array(
			'email'  => $lead->email,
			'fields' => $fields,
		);

		$response = $this->make_request( 'POST', $endpoint, $body, $api_key );

		if ( is_wp_error( $response ) ) {
			SM_Logger::log(
				'error',
				'MAILERLITE_SYNC',
				'Failed to upsert subscriber',
				array(
					'lead_id' => $lead->id,
					'email'   => $this->mask_email( $lead->email ),
					'error'   => $response->get_error_message(),
				)
			);
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		// MailerLite returns 200/201 for success, 422 if subscriber exists (which is fine).
		if ( in_array( $response_code, array( 200, 201 ), true ) ) {
			return $response_body['data'];
		}

		// Handle existing subscriber (update scenario).
		if ( 422 === $response_code ) {
			// Get existing subscriber by email.
			$subscriber = $this->get_subscriber_by_email( $lead->email, $api_key );

			if ( $subscriber ) {
				// Update subscriber if needed.
				$updated = $this->update_subscriber( $subscriber['id'], $lead, $api_key, $extra_fields );
				return $updated ? $subscriber : false;
			}
		}

		SM_Logger::log(
			'error',
			'MAILERLITE_SYNC',
			'Unexpected response from MailerLite API',
			array(
				'lead_id'       => $lead->id,
				'email'         => $this->mask_email( $lead->email ),
				'response_code' => $response_code,
				'response_body' => $response_body,
			)
		);

		return false;
	}

	/**
	 * Get subscriber by email address.
	 *
	 * @param string $email   Email address.
	 * @param string $api_key MailerLite API key.
	 * @return array|false Subscriber data on success, false on failure.
	 */
	private function get_subscriber_by_email( $email, $api_key ) {
		$endpoint = self::API_BASE_URL . '/subscribers/' . urlencode( $email );

		$response = $this->make_request( 'GET', $endpoint, null, $api_key );

		if ( is_wp_error( $response ) ) {
			SM_Logger::log(
				'error',
				'MAILERLITE_SYNC',
				'Failed to fetch subscriber by email',
				array(
					'email' => $this->mask_email( $email ),
					'error' => $response->get_error_message(),
				)
			);
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 === $response_code && isset( $response_body['data'] ) ) {
			return $response_body['data'];
		}

		return false;
	}

	/**
	 * Update an existing subscriber.
	 *
	 * @param string $subscriber_id MailerLite subscriber ID.
	 * @param object $lead          Lead data from database.
	 * @param string $api_key       MailerLite API key.
	 * @return bool True on success, false on failure.
	 */
	private function update_subscriber( $subscriber_id, $lead, $api_key, $extra_fields = array() ) {
		$endpoint = self::API_BASE_URL . '/subscribers/' . $subscriber_id;

		$fields = $this->build_subscriber_fields( $lead, $extra_fields );
		$body = array(
			'fields' => $fields,
		);

		$response = $this->make_request( 'PUT', $endpoint, $body, $api_key );

		if ( is_wp_error( $response ) ) {
			SM_Logger::log(
				'error',
				'MAILERLITE_SYNC',
				'Failed to update subscriber',
				array(
					'subscriber_id' => $subscriber_id,
					'lead_id'       => $lead->id,
					'error'         => $response->get_error_message(),
				)
			);
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 === $response_code ) {
			return true;
		}

		SM_Logger::log(
			'error',
			'MAILERLITE_SYNC',
			'Unexpected response when updating subscriber',
			array(
				'subscriber_id' => $subscriber_id,
				'lead_id'       => $lead->id,
				'response_code' => $response_code,
				'response_body' => $response_body,
			)
		);

		return false;
	}

	/**
	 * Assign subscriber to a group.
	 *
	 * @param string $subscriber_id MailerLite subscriber ID.
	 * @param string $group_id      MailerLite group ID.
	 * @param string $api_key       MailerLite API key.
	 * @return bool True on success, false on failure.
	 */
	private function assign_to_group( $subscriber_id, $group_id, $api_key ) {
		$endpoint = self::API_BASE_URL . '/subscribers/' . $subscriber_id . '/groups/' . $group_id;

		$response = $this->make_request( 'POST', $endpoint, array(), $api_key );

		if ( is_wp_error( $response ) ) {
			SM_Logger::log(
				'error',
				'MAILERLITE_SYNC',
				'Failed to assign subscriber to group',
				array(
					'subscriber_id' => $subscriber_id,
					'group_id'      => $group_id,
					'error'         => $response->get_error_message(),
				)
			);
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		// MailerLite returns 200 or 201 on success.
		if ( in_array( $response_code, array( 200, 201 ), true ) ) {
			return true;
		}

		SM_Logger::log(
			'error',
			'MAILERLITE_SYNC',
			'Unexpected response when assigning to group',
			array(
				'subscriber_id' => $subscriber_id,
				'group_id'      => $group_id,
				'response_code' => $response_code,
				'response_body' => json_decode( wp_remote_retrieve_body( $response ), true ),
			)
		);

		return false;
	}

	/**
	 * Build MailerLite field payload for a lead.
	 *
	 * @param object $lead Lead data.
	 * @param array  $extra_fields Extra fields to include (e.g., primary_focus).
	 * @return array
	 */
	private function build_subscriber_fields( $lead, $extra_fields = array() ) {
		$fields = array(
			'name'    => $lead->name,
			'company' => $lead->id, // Store lead_id for tracking.
		);

		if ( ! empty( $lead->age_range ) ) {
			$fields['age_range'] = sanitize_text_field( (string) $lead->age_range );
		}
		if ( ! empty( $lead->identity ) ) {
			$fields['gender'] = sanitize_text_field( (string) $lead->identity );
		}

		$extra_fields = $this->sanitize_extra_fields( $extra_fields );
		foreach ( $extra_fields as $key => $value ) {
			if ( '' !== $value ) {
				$fields[ $key ] = $value;
			}
		}

		return $fields;
	}

	/**
	 * Sanitize extra fields for MailerLite updates.
	 *
	 * @param array $extra_fields Raw extra fields.
	 * @return array
	 */
	private function sanitize_extra_fields( $extra_fields ) {
		$sanitized = array();
		if ( ! is_array( $extra_fields ) ) {
			return $sanitized;
		}

		$allowed_keys = array( 'primary_focus' );
		foreach ( $allowed_keys as $key ) {
			if ( isset( $extra_fields[ $key ] ) ) {
				$sanitized[ $key ] = sanitize_text_field( (string) $extra_fields[ $key ] );
			}
		}

		return $sanitized;
	}

	/**
	 * Make an HTTP request to MailerLite API.
	 *
	 * @param string      $method   HTTP method (GET, POST, PUT, DELETE).
	 * @param string      $endpoint Full API endpoint URL.
	 * @param array|null  $body     Request body data.
	 * @param string      $api_key  MailerLite API key.
	 * @return array|WP_Error Response array or WP_Error on failure.
	 */
	private function make_request( $method, $endpoint, $body, $api_key ) {
		$dev_mode_enabled = SM_Dev_Mode::should_mock_mailerlite();

		// Check if DevMode should mock MailerLite - use mock endpoint instead of real MailerLite.
		if ( $dev_mode_enabled ) {
			SM_Logger::log(
				'warning',
				'MAILERLITE_SYNC',
				'DevMode enabled - using mock MailerLite endpoint',
				array(
					'method'   => $method,
					'endpoint' => $endpoint,
				)
			);

			// Use mock endpoint for all MailerLite requests
			$endpoint = SM_Dev_Mode::get_mock_mailerlite_url();
			$method = 'POST'; // Mock endpoint only accepts POST
		}

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . ( $api_key ?: 'mock-key' ),
			),
			'timeout' => 30,
		);

		// Allow self-signed certificates when hitting local mock endpoints in DevMode.
		if ( $dev_mode_enabled ) {
			$args['sslverify'] = false;
		}

		if ( null !== $body && in_array( $method, array( 'POST', 'PUT' ), true ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			SM_Logger::log(
				'error',
				'MAILERLITE_SYNC',
				'MailerLite request failed',
				array(
					'method'   => $method,
					'endpoint' => $endpoint,
					'error'    => $response->get_error_message(),
				)
			);
		}

		return $response;
	}

	/**
	 * Test API connection (for admin settings page).
	 *
	 * @param string $api_key MailerLite API key.
	 * @return bool True if connection successful, false otherwise.
	 */
	public function test_connection( $api_key ) {
		if ( empty( $api_key ) ) {
			return false;
		}

		$endpoint = self::API_BASE_URL . '/subscribers?limit=1';

		$response = $this->make_request( 'GET', $endpoint, null, $api_key );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		return 200 === $response_code;
	}

	/**
	 * Mask email address for logging (privacy).
	 *
	 * @param string $email Email address.
	 * @return string Masked email (e.g., u***@example.com).
	 */
	private function mask_email( $email ) {
		if ( empty( $email ) || ! is_string( $email ) ) {
			return '';
		}

		$parts = explode( '@', $email );

		if ( count( $parts ) !== 2 ) {
			return '***';
		}

		$local  = $parts[0];
		$domain = $parts[1];

		if ( strlen( $local ) > 2 ) {
			$masked_local = substr( $local, 0, 1 ) . '***';
		} else {
			$masked_local = '***';
		}

		return $masked_local . '@' . $domain;
	}
}
