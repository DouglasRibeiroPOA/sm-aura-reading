<?php
/**
 * Lead handler for SoulMirror.
 *
 * @package MysticPalmReadingAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles lead creation and retrieval.
 */
class SM_Lead_Handler {

	/**
	 * Singleton instance.
	 *
	 * @var SM_Lead_Handler|null
	 */
	private static $instance = null;

	/**
	 * Leads table name.
	 *
	 * @var string
	 */
	private $table = '';

	/**
	 * Initialize the handler.
	 *
	 * @return SM_Lead_Handler
	 */
	public static function init() {
		return self::get_instance();
	}

	/**
	 * Get singleton instance.
	 *
	 * @return SM_Lead_Handler
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
			$db = SM_Database::get_instance();
			$this->table = $db->get_table_name( 'leads' );
		}
	}

	/**
	 * Create a new lead or return an existing one by email.
	 *
	 * @param string $name     Lead name.
	 * @param string $email    Lead email.
	 * @param string $identity Identity value (e.g., gender/identity selection).
	 * @param bool   $gdpr     GDPR consent flag.
	 * @param int|null $age      Lead age (optional).
	 * @param string   $age_range Age range key (optional).
	 * @return array|WP_Error {
	 *     lead_id: string,
	 *     email_status: bool,
	 *     exists_before: bool,
	 * }
	 */
	public function create_lead( $name, $email, $identity, $gdpr, $age = null, $age_range = '' ) {
		global $wpdb;

		if ( '' === $this->table ) {
			return new WP_Error(
				'db_not_ready',
				__( 'Database is not ready yet.', 'mystic-palm-reading' )
			);
		}

		$sanitized = $this->sanitize_input( $name, $email, $identity, $gdpr, $age, $age_range );
		if ( is_wp_error( $sanitized ) ) {
			return $sanitized;
		}

		do_action( 'sm_before_lead_create', $sanitized );

		$existing = $this->get_lead_by_email( $sanitized['email'] );
		if ( ! empty( $existing ) ) {
			$updates = array();

			if ( $sanitized['gdpr'] && (int) $existing->gdpr !== 1 ) {
				$updates['gdpr'] = 1;
			}
			if ( $sanitized['name'] !== $existing->name && '' !== $sanitized['name'] ) {
				$updates['name'] = $sanitized['name'];
			}
			if ( $sanitized['identity'] !== $existing->identity && '' !== $sanitized['identity'] ) {
				$updates['identity'] = $sanitized['identity'];
			}
			if ( null !== $sanitized['age'] ) {
				$updates['age'] = $sanitized['age'];
			}
			if ( '' !== $sanitized['age_range'] ) {
				$updates['age_range'] = $sanitized['age_range'];
			}

			if ( ! empty( $updates ) ) {
				$updated = $this->update_lead( $existing->id, $updates );
				if ( is_wp_error( $updated ) ) {
					return $updated;
				}
			}

			$this->log(
				'info',
				'LEAD_EXISTS',
				'Lead already exists for email',
				array(
					'lead_id'   => $existing->id,
					'email'     => $existing->email,
					'confirmed' => (bool) $existing->email_confirmed,
				)
			);

			return array(
				'lead_id'       => $existing->id,
				'email_status'  => (bool) $existing->email_confirmed,
				'exists_before' => true,
			);
		}

		$lead_id  = $this->generate_uuid();
		$now      = current_time( 'mysql' );
		$consent  = $sanitized['gdpr'] ? $now : null;
		$inserted = $wpdb->insert(
			$this->table,
			array(
				'id'              => $lead_id,
				'name'            => $sanitized['name'],
				'email'           => $sanitized['email'],
				'identity'        => $sanitized['identity'],
				'age'             => $sanitized['age'],
				'age_range'       => $sanitized['age_range'],
				'gdpr'            => $sanitized['gdpr'] ? 1 : 0,
				'gdpr_timestamp'  => $consent,
				'email_confirmed' => 0,
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			$this->log(
				'error',
				'LEAD_CREATE_FAILED',
				'Database insert failed for lead',
				array(
					'email'  => $sanitized['email'],
					'error'  => $wpdb->last_error,
				)
			);

			return new WP_Error(
				'lead_create_failed',
				__( 'Could not create lead at this time.', 'mystic-palm-reading' )
			);
		}

		$this->log(
			'info',
			'LEAD_CREATED',
			'Lead created successfully',
			array(
				'lead_id' => $lead_id,
				'email'   => $sanitized['email'],
			)
		);

		do_action( 'sm_after_lead_create', $lead_id, $sanitized );

		return array(
			'lead_id'       => $lead_id,
			'email_status'  => false,
			'exists_before' => false,
		);
	}

	/**
	 * Create a new lead record even if one already exists for the email.
	 *
	 * @param string   $name      Lead name.
	 * @param string   $email     Lead email.
	 * @param string   $identity  Identity value.
	 * @param bool     $gdpr      GDPR consent flag.
	 * @param int|null $age       Lead age (optional).
	 * @param string   $age_range Age range key (optional).
	 * @return array|WP_Error
	 */
	public function create_lead_fresh( $name, $email, $identity, $gdpr, $age = null, $age_range = '' ) {
		global $wpdb;

		if ( '' === $this->table ) {
			return new WP_Error(
				'db_not_ready',
				__( 'Database is not ready yet.', 'mystic-palm-reading' )
			);
		}

		$sanitized = $this->sanitize_input( $name, $email, $identity, $gdpr, $age, $age_range );
		if ( is_wp_error( $sanitized ) ) {
			return $sanitized;
		}

		do_action( 'sm_before_lead_create', $sanitized );

		$lead_id  = $this->generate_uuid();
		$now      = current_time( 'mysql' );
		$consent  = $sanitized['gdpr'] ? $now : null;
		$inserted = $wpdb->insert(
			$this->table,
			array(
				'id'              => $lead_id,
				'name'            => $sanitized['name'],
				'email'           => $sanitized['email'],
				'identity'        => $sanitized['identity'],
				'age'             => $sanitized['age'],
				'age_range'       => $sanitized['age_range'],
				'gdpr'            => $sanitized['gdpr'] ? 1 : 0,
				'gdpr_timestamp'  => $consent,
				'email_confirmed' => 0,
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			$this->log(
				'error',
				'LEAD_CREATE_FAILED',
				'Database insert failed for lead',
				array(
					'email'  => $sanitized['email'],
					'error'  => $wpdb->last_error,
				)
			);

			return new WP_Error(
				'lead_create_failed',
				__( 'Could not create lead at this time.', 'mystic-palm-reading' )
			);
		}

		$this->log(
			'info',
			'LEAD_CREATED',
			'Lead created successfully',
			array(
				'lead_id' => $lead_id,
				'email'   => $sanitized['email'],
				'fresh'   => true,
			)
		);

		do_action( 'sm_after_lead_create', $lead_id, $sanitized );

		return array(
			'lead_id'       => $lead_id,
			'email_status'  => false,
			'exists_before' => false,
		);
	}

	/**
	 * Retrieve a lead by ID.
	 *
	 * @param string $lead_id Lead UUID.
	 * @return object|null
	 */
	public function get_lead_by_id( $lead_id ) {
		global $wpdb;

		$lead_id = sanitize_text_field( (string) $lead_id );
		if ( '' === $lead_id ) {
			return null;
		}
		if ( '' === $this->table ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %s LIMIT 1", $lead_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			OBJECT
		);
	}

	/**
	 * Retrieve a lead by email.
	 *
	 * @param string $email Email address.
	 * @return object|null
	 */
	public function get_lead_by_email( $email ) {
		global $wpdb;

		$email = sanitize_email( (string) $email );
		if ( empty( $email ) ) {
			return null;
		}
		if ( '' === $this->table ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE email = %s ORDER BY created_at DESC LIMIT 1",
				$email
			),
			OBJECT
		);
	}

	/**
	 * Update lead fields.
	 *
	 * @param string $lead_id Lead UUID.
	 * @param array  $data    Associative array of fields to update.
	 * @return bool|WP_Error
	 */
	public function update_lead( $lead_id, $data ) {
		global $wpdb;

		$lead_id = sanitize_text_field( (string) $lead_id );
		if ( '' === $lead_id ) {
			return new WP_Error( 'invalid_lead_id', __( 'Invalid lead ID.', 'mystic-palm-reading' ) );
		}

		if ( '' === $this->table ) {
			return new WP_Error( 'db_not_ready', __( 'Database is not ready yet.', 'mystic-palm-reading' ) );
		}

		$allowed = array(
			'name',
			'email',
			'identity',
			'age',
			'age_range',
			'gdpr',
			'gdpr_timestamp',
			'email_confirmed',
			'invalid_image_attempts',
			'invalid_image_locked',
			'invalid_image_last_reason',
			'invalid_image_last_at',
		);
		$update  = array();

		foreach ( $allowed as $field ) {
			if ( ! array_key_exists( $field, $data ) ) {
				continue;
			}

			switch ( $field ) {
				case 'name':
					$update['name'] = $this->sanitize_name( $data['name'] );
					break;
				case 'email':
					$email = sanitize_email( $data['email'] );
					if ( empty( $email ) || ! is_email( $email ) ) {
						return new WP_Error( 'invalid_email', __( 'Invalid email address.', 'mystic-palm-reading' ) );
					}

					$existing = $this->get_lead_by_email( $email );
					if ( ! empty( $existing ) && $existing->id !== $lead_id ) {
						return new WP_Error( 'email_in_use', __( 'Email is already registered.', 'mystic-palm-reading' ) );
					}
					$update['email'] = $email;
					break;
				case 'identity':
					$update['identity'] = $this->sanitize_identity( $data['identity'] );
					break;
				case 'age':
					$sanitized_age = $this->sanitize_age( $data['age'] );
					if ( is_wp_error( $sanitized_age ) ) {
						return $sanitized_age;
					}
					$update['age'] = $sanitized_age;
					break;
				case 'age_range':
					$sanitized_age_range = $this->sanitize_age_range( $data['age_range'] );
					if ( is_wp_error( $sanitized_age_range ) ) {
						return $sanitized_age_range;
					}
					$update['age_range'] = $sanitized_age_range;
					break;
				case 'gdpr':
					$update['gdpr']           = ! empty( $data['gdpr'] ) ? 1 : 0;
					$update['gdpr_timestamp'] = ! empty( $data['gdpr'] ) ? current_time( 'mysql' ) : null;
					break;
				case 'gdpr_timestamp':
					$update['gdpr_timestamp'] = empty( $data['gdpr_timestamp'] ) ? null : sanitize_text_field( $data['gdpr_timestamp'] );
					break;
				case 'email_confirmed':
					$update['email_confirmed'] = ! empty( $data['email_confirmed'] ) ? 1 : 0;
					break;
				case 'invalid_image_attempts':
					$update['invalid_image_attempts'] = absint( $data['invalid_image_attempts'] );
					break;
				case 'invalid_image_locked':
					$update['invalid_image_locked'] = ! empty( $data['invalid_image_locked'] ) ? 1 : 0;
					break;
				case 'invalid_image_last_reason':
					$update['invalid_image_last_reason'] = sanitize_text_field( (string) $data['invalid_image_last_reason'] );
					break;
				case 'invalid_image_last_at':
					$update['invalid_image_last_at'] = empty( $data['invalid_image_last_at'] )
						? null
						: sanitize_text_field( (string) $data['invalid_image_last_at'] );
					break;
			}
		}

		if ( empty( $update ) ) {
			return true;
		}

		$update['updated_at'] = current_time( 'mysql' );

		$result = $wpdb->update(
			$this->table,
			$update,
			array( 'id' => $lead_id ),
			$this->get_formats( $update ),
			array( '%s' )
		);

		if ( false === $result ) {
			$this->log(
				'error',
				'LEAD_UPDATE_FAILED',
				'Lead update failed',
				array(
					'lead_id' => $lead_id,
					'error'   => $wpdb->last_error,
				)
			);

			return new WP_Error( 'lead_update_failed', __( 'Could not update lead.', 'mystic-palm-reading' ) );
		}

		$this->log(
			'info',
			'LEAD_UPDATED',
			'Lead updated',
			array(
				'lead_id' => $lead_id,
				'fields'  => array_keys( $update ),
			)
		);

		return true;
	}

	/**
	 * Mark a lead as email confirmed.
	 *
	 * @param string $lead_id Lead UUID.
	 * @return bool|WP_Error
	 */
	public function mark_email_confirmed( $lead_id ) {
		return $this->update_lead(
			$lead_id,
			array(
				'gdpr'            => 1,
				'gdpr_timestamp'  => current_time( 'mysql' ),
				'email_confirmed' => 1,
			)
		);
	}

	/**
	 * Sanitize and validate lead input.
	 *
	 * @param string $name     Name.
	 * @param string $email    Email.
	 * @param string $identity Identity value.
	 * @param bool   $gdpr     GDPR consent flag.
	 * @param int|null $age       Age (optional).
	 * @param string   $age_range Age range key (optional).
	 * @return array|WP_Error
	 */
	private function sanitize_input( $name, $email, $identity, $gdpr, $age = null, $age_range = '' ) {
		$name     = $this->sanitize_name( $name );
		$email    = sanitize_email( (string) $email );
		$identity = $this->sanitize_identity( $identity );
		$gdpr     = (bool) $gdpr;
		$age      = $this->sanitize_age( $age );
		$age_range = $this->sanitize_age_range( $age_range, true );

		if ( is_wp_error( $age ) ) {
			return $age;
		}

		if ( is_wp_error( $age_range ) ) {
			return $age_range;
		}

		if ( '' === $name || '' === $identity || empty( $email ) || ! is_email( $email ) ) {
			return new WP_Error(
				'invalid_input',
				__( 'Please check your information and try again.', 'mystic-palm-reading' )
			);
		}

		if ( ! $gdpr ) {
			return new WP_Error(
				'gdpr_required',
				__( 'GDPR consent is required.', 'mystic-palm-reading' )
			);
		}

		return array(
			'name'      => $name,
			'email'     => $email,
			'identity'  => $identity,
			'gdpr'      => $gdpr,
			'age'       => $age,
			'age_range' => $age_range,
		);
	}

	/**
	 * Sanitize name field.
	 *
	 * @param string $name Raw name.
	 * @return string
	 */
	private function sanitize_name( $name ) {
		$name = sanitize_text_field( (string) $name );
		return substr( $name, 0, 255 );
	}

	/**
	 * Sanitize identity field.
	 *
	 * @param string $identity Raw identity.
	 * @return string
	 */
	private function sanitize_identity( $identity ) {
		$identity = sanitize_text_field( (string) $identity );
		return substr( $identity, 0, 50 );
	}

	/**
	 * Sanitize age.
	 *
	 * @param mixed $age Raw age.
	 * @return int|null|WP_Error
	 */
	private function sanitize_age( $age ) {
		if ( null === $age || '' === $age ) {
			return null;
		}

		$age_int = (int) $age;

		// Accept only realistic ages; UI enforces 18+.
		if ( $age_int < 0 || $age_int > 120 ) {
			return new WP_Error(
				'invalid_input',
				__( 'Please check your information and try again.', 'mystic-palm-reading' )
			);
		}

		return $age_int;
	}

	/**
	 * Sanitize age range key.
	 *
	 * @param string $age_range   Raw age range.
	 * @param bool   $allow_empty Whether to allow empty value.
	 * @return string|WP_Error
	 */
	private function sanitize_age_range( $age_range, $allow_empty = false ) {
		$age_range = sanitize_text_field( (string) $age_range );

		if ( '' === $age_range && $allow_empty ) {
			return '';
		}

		$allowed = array( 'age_18_25', 'age_26_35', 'age_36_50', 'age_51_65', 'age_65_plus' );

		if ( '' === $age_range || ! in_array( $age_range, $allowed, true ) ) {
			return new WP_Error(
				'invalid_input',
				__( 'Please check your information and try again.', 'mystic-palm-reading' )
			);
		}

		return $age_range;
	}

	/**
	 * Generate a UUID.
	 *
	 * @return string
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
	 * Determine formats for update data.
	 *
	 * @param array $update Update data.
	 * @return array
	 */
	private function get_formats( $update ) {
		$formats = array();

		foreach ( array_keys( $update ) as $field ) {
			switch ( $field ) {
				case 'gdpr':
				case 'email_confirmed':
				case 'age':
				case 'invalid_image_attempts':
				case 'invalid_image_locked':
					$formats[] = '%d';
					break;
				default:
					$formats[] = '%s';
			}
		}

		return $formats;
	}

	/**
	 * Refresh consent metadata on existing lead when applicable.
	 *
	 * @param object $existing Existing lead row.
	 * @param array  $sanitized Incoming sanitized data.
	 * @return void
	 */
	private function maybe_refresh_consent( $existing, $sanitized ) {
		if ( empty( $existing ) || empty( $sanitized['gdpr'] ) ) {
			return;
		}

		if ( (int) $existing->gdpr === 1 ) {
			return;
		}

		$this->update_lead(
			$existing->id,
			array(
				'gdpr' => 1,
			)
		);
	}

	/**
	 * Proxy logger to avoid fatal if missing.
	 *
	 * @param string $level      Level.
	 * @param string $event_type Event type.
	 * @param string $message    Message.
	 * @param array  $context    Context.
	 */
	private function log( $level, $event_type, $message, $context = array() ) {
		if ( class_exists( 'SM_Logger' ) ) {
			SM_Logger::log( $level, $event_type, $message, $context );
		}
	}
}
