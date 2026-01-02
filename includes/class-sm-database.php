<?php
/**
 * Handles database schema creation and migrations for SoulMirror.
 *
 * @package MysticAuraReadingAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database manager for custom plugin tables.
 */
class SM_Database {

	/**
	 * Current database schema version.
	 */
	const DB_VERSION = '1.4.6';

	/**
	 * Option key used to track the stored schema version.
	 */
	const OPTION_KEY = 'sm_aura_db_version';

	/**
	 * Singleton instance.
	 *
	 * @var SM_Database|null
	 */
	private static $instance = null;

	/**
	 * Array of table names keyed by logical identifier.
	 *
	 * @var array<string,string>
	 */
	private $tables = array();

	/**
	 * Initialize the database manager.
	 *
	 * @return SM_Database
	 */
	public static function init() {
		$instance = self::get_instance();
		$instance->maybe_upgrade();
		return $instance;
	}

	/**
	 * Get singleton instance.
	 *
	 * @return SM_Database
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Run database setup on plugin activation.
	 */
	public static function activate() {
		self::get_instance()->maybe_upgrade();
	}

	/**
	 * Constructor.
	 *
	 * Sets up table names on $wpdb for convenience.
	 */
	private function __construct() {
		global $wpdb;

		$this->tables = array(
			'leads'         => $wpdb->prefix . 'sm_aura_leads',
			'otps'          => $wpdb->prefix . 'sm_aura_otps',
			'quiz'          => $wpdb->prefix . 'sm_aura_quiz',
			'readings'      => $wpdb->prefix . 'sm_aura_readings',
			'logs'          => $wpdb->prefix . 'sm_aura_logs',
			'flow_sessions' => $wpdb->prefix . 'sm_aura_flow_sessions',
		);

		// Expose custom tables on $wpdb for query helpers.
		$wpdb->sm_aura_leads         = $this->tables['leads'];
		$wpdb->sm_aura_otps          = $this->tables['otps'];
		$wpdb->sm_aura_quiz          = $this->tables['quiz'];
		$wpdb->sm_aura_readings      = $this->tables['readings'];
		$wpdb->sm_aura_logs          = $this->tables['logs'];
		$wpdb->sm_aura_flow_sessions = $this->tables['flow_sessions'];
	}

	/**
	 * Get a fully qualified table name.
	 *
	 * @param string $key Logical table identifier (leads, otps, quiz, readings, logs).
	 * @return string Table name or empty string if key is unknown.
	 */
	public function get_table_name( $key ) {
		return isset( $this->tables[ $key ] ) ? $this->tables[ $key ] : '';
	}

	/**
	 * Check if all required tables exist.
	 *
	 * @return bool
	 */
	public function tables_exist() {
		return empty( $this->missing_tables() );
	}

	/**
	 * Get a list of missing tables.
	 *
	 * @return array<string>
	 */
	public function missing_tables() {
		global $wpdb;

		$missing = array();

		foreach ( $this->tables as $table ) {
			$table_name = $wpdb->esc_like( $table );
			$exists     = $wpdb->get_var(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
			);

			if ( $exists !== $table ) {
				$missing[] = $table;
			}
		}

		return $missing;
	}

	/**
	 * Create or update the schema if needed.
	 */
	public function maybe_upgrade() {
		$current_version = get_option( self::OPTION_KEY );

		// Fresh install or tables were removed.
		if ( false === $current_version || ! $this->tables_exist() ) {
			$this->create_or_update_tables();
			update_option( self::OPTION_KEY, self::DB_VERSION );
			return;
		}

		// Run migrations if schema version is behind.
		if ( version_compare( $current_version, self::DB_VERSION, '<' ) ) {
			$this->run_migrations( $current_version );
			update_option( self::OPTION_KEY, self::DB_VERSION );
		}

		// Ensure critical columns exist even if version matches (stale installs).
		$this->ensure_schema_integrity();
	}

	/**
	 * Run incremental migrations between versions.
	 *
	 * @param string $current_version Version stored in the database.
	 */
	private function run_migrations( $current_version ) {
		$migrations = array(
			'1.0.0' => array( $this, 'create_or_update_tables' ),
			'1.1.0' => array( $this, 'migrate_to_1_1_0' ),
			'1.2.0' => array( $this, 'migrate_to_1_2_0' ),
			'1.3.0' => array( $this, 'migrate_to_1_3_0' ),
			'1.4.0' => array( $this, 'migrate_to_1_4_0' ),
			'1.4.1' => array( $this, 'migrate_to_1_4_1' ),
			'1.4.5' => array( $this, 'migrate_to_1_4_5' ),
			'1.4.6' => array( $this, 'migrate_to_1_4_6' ),
		);

		foreach ( $migrations as $version => $callback ) {
			if ( version_compare( $current_version, $version, '<' ) && is_callable( $callback ) ) {
				call_user_func( $callback );
			}
		}
	}

	/**
	 * Ensure critical columns/tables exist even if the stored version is current.
	 */
	private function ensure_schema_integrity() {
		global $wpdb;

		$readings_table = $this->tables['readings'];
		$leads_table    = $this->tables['leads'];
		$flow_table     = $this->tables['flow_sessions'];

		$reading_type_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM `{$readings_table}` LIKE %s",
				'reading_type'
			)
		);

		if ( empty( $reading_type_exists ) ) {
			$this->migrate_to_1_3_0();
		}

		$account_id_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM `{$leads_table}` LIKE %s",
				'account_id'
			)
		);

		if ( empty( $account_id_exists ) ) {
			$this->migrate_to_1_4_0();
		}

		$flow_table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $flow_table )
		);

		if ( $flow_table_exists !== $flow_table ) {
			$this->migrate_to_1_4_5();
		}

		$invalid_image_attempts_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM `{$leads_table}` LIKE %s",
				'invalid_image_attempts'
			)
		);

		if ( empty( $invalid_image_attempts_exists ) ) {
			$this->migrate_to_1_4_6();
		}

		update_option( self::OPTION_KEY, self::DB_VERSION );
	}

	/**
	 * Create or update all plugin tables using dbDelta.
	 */
	private function create_or_update_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$schemas = array(
			"CREATE TABLE {$this->tables['leads']} (
				id CHAR(36) NOT NULL,
				name VARCHAR(255) NOT NULL DEFAULT '',
				email VARCHAR(255) NOT NULL,
				account_id VARCHAR(100) NULL DEFAULT NULL,
				identity VARCHAR(50) NOT NULL DEFAULT '',
				age SMALLINT UNSIGNED NULL DEFAULT NULL,
				age_range VARCHAR(50) NOT NULL DEFAULT '',
				gdpr TINYINT(1) NOT NULL DEFAULT 0,
				gdpr_timestamp DATETIME NULL DEFAULT NULL,
				email_confirmed TINYINT(1) NOT NULL DEFAULT 0,
				invalid_image_attempts INT(11) NOT NULL DEFAULT 0,
				invalid_image_locked TINYINT(1) NOT NULL DEFAULT 0,
				invalid_image_last_reason VARCHAR(255) NULL DEFAULT NULL,
				invalid_image_last_at DATETIME NULL DEFAULT NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY idx_email (email),
				KEY idx_account_id (account_id),
				KEY email_confirmed (email_confirmed)
			) {$charset_collate};",
			"CREATE TABLE {$this->tables['otps']} (
				id CHAR(36) NOT NULL,
				lead_id CHAR(36) NOT NULL,
				otp_hash VARCHAR(255) NOT NULL,
				expires_at DATETIME NOT NULL,
				attempts INT(11) NOT NULL DEFAULT 0,
				resend_available DATETIME NULL DEFAULT NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY lead_id (lead_id),
				KEY expires_at (expires_at),
				CONSTRAINT fk_sm_aura_otps_lead FOREIGN KEY (lead_id) REFERENCES {$this->tables['leads']}(id) ON DELETE CASCADE
			) {$charset_collate};",
			"CREATE TABLE {$this->tables['quiz']} (
				id CHAR(36) NOT NULL,
				lead_id CHAR(36) NOT NULL,
				answers_json LONGTEXT NOT NULL,
				completed_at DATETIME NULL DEFAULT NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY lead_id (lead_id),
				CONSTRAINT fk_sm_aura_quiz_lead FOREIGN KEY (lead_id) REFERENCES {$this->tables['leads']}(id) ON DELETE CASCADE
			) {$charset_collate};",
			"CREATE TABLE {$this->tables['readings']} (
				id CHAR(36) NOT NULL,
				lead_id CHAR(36) NOT NULL,
				account_id VARCHAR(100) NULL DEFAULT NULL,
				reading_type VARCHAR(50) NOT NULL DEFAULT 'aura_teaser',
				content_data LONGTEXT NULL DEFAULT NULL,
				reading_html LONGTEXT NULL DEFAULT NULL,
				prompt_template_used VARCHAR(50) NULL DEFAULT NULL,
				unlocked_section VARCHAR(100) NULL DEFAULT NULL,
				unlock_count INT NOT NULL DEFAULT 0,
				has_purchased TINYINT(1) NOT NULL DEFAULT 0,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY lead_id (lead_id),
				KEY idx_account_id (account_id),
				KEY idx_reading_type (reading_type),
				KEY idx_created_at (created_at),
				KEY idx_lead_created (lead_id, created_at),
				CONSTRAINT fk_sm_aura_readings_lead FOREIGN KEY (lead_id) REFERENCES {$this->tables['leads']}(id) ON DELETE CASCADE
			) {$charset_collate};",
			"CREATE TABLE {$this->tables['logs']} (
				id CHAR(36) NOT NULL,
				event_type VARCHAR(100) NOT NULL DEFAULT '',
				status VARCHAR(50) NOT NULL DEFAULT '',
				message TEXT NOT NULL,
				meta LONGTEXT NULL DEFAULT NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY event_type (event_type),
				KEY created_at (created_at)
			) {$charset_collate};",
			"CREATE TABLE {$this->tables['flow_sessions']} (
				flow_id CHAR(36) NOT NULL,
				account_id VARCHAR(100) NULL DEFAULT NULL,
				lead_id CHAR(36) NULL DEFAULT NULL,
				reading_id CHAR(36) NULL DEFAULT NULL,
				email VARCHAR(255) NULL DEFAULT NULL,
				step_id VARCHAR(50) NULL DEFAULT NULL,
				status VARCHAR(50) NULL DEFAULT NULL,
				magic_token_hash VARCHAR(255) NULL DEFAULT NULL,
				magic_expires_at DATETIME NULL DEFAULT NULL,
				expires_at DATETIME NOT NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (flow_id),
				KEY account_id (account_id),
				KEY lead_id (lead_id),
				KEY expires_at (expires_at),
				KEY status (status)
			) {$charset_collate};",
		);

		foreach ( $schemas as $schema ) {
			dbDelta( $schema );
		}
	}

	/**
	 * Migration to version 1.1.0: Add prompt_template_used column to sm_readings table.
	 *
	 * @since 1.1.0
	 */
	private function migrate_to_1_1_0() {
		global $wpdb;

		$table_name = $this->tables['readings'];

		// Check if column already exists (in case of manual intervention)
		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM `{$table_name}` LIKE %s",
				'prompt_template_used'
			)
		);

		if ( empty( $column_exists ) ) {
			// Add the new column
			$wpdb->query(
				"ALTER TABLE `{$table_name}`
				ADD COLUMN `prompt_template_used` VARCHAR(50) NULL DEFAULT NULL
				AFTER `reading_html`"
			);

			SM_Logger::log(
				'info',
				'DB_MIGRATION',
				'Successfully added prompt_template_used column to sm_readings table',
				array( 'version' => '1.1.0' )
			);
		} else {
			SM_Logger::log(
				'info',
				'DB_MIGRATION',
				'Column prompt_template_used already exists in sm_readings table',
				array( 'version' => '1.1.0' )
			);
		}
	}

	/**
	 * Migration to version 1.2.0: Add age and age_range to sm_leads table.
	 *
	 * @since 1.2.0
	 */
	private function migrate_to_1_2_0() {
		global $wpdb;

		$table_name = $this->tables['leads'];

		// Add age column if missing.
		$age_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM `{$table_name}` LIKE %s",
				'age'
			)
		);

		if ( empty( $age_exists ) ) {
			$wpdb->query(
				"ALTER TABLE `{$table_name}`
				ADD COLUMN `age` SMALLINT UNSIGNED NULL DEFAULT NULL
				AFTER `identity`"
			);
		}

		// Add age_range column if missing.
		$age_range_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM `{$table_name}` LIKE %s",
				'age_range'
			)
		);

		if ( empty( $age_range_exists ) ) {
			$wpdb->query(
				"ALTER TABLE `{$table_name}`
				ADD COLUMN `age_range` VARCHAR(50) NOT NULL DEFAULT ''
				AFTER `age`"
			);
		}
	}

	/**
	 * Migration to version 1.3.0: Transform sm_readings table for JSON-based teaser reading system.
	 *
	 * Adds support for:
	 * - Structured JSON content storage
	 * - Multiple reading types (aura_teaser, aura_full, etc.)
	 * - Freemium unlock model with section tracking
	 * - Purchase state management
	 * - Multi-reading support per user
	 *
	 * @since 1.3.0
	 */
	private function migrate_to_1_3_0() {
		global $wpdb;

		$table_name = $this->tables['readings'];

		// Add reading_type column
		$reading_type_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM `{$table_name}` LIKE %s",
				'reading_type'
			)
		);

		if ( empty( $reading_type_exists ) ) {
			$wpdb->query(
				"ALTER TABLE `{$table_name}`
				ADD COLUMN `reading_type` VARCHAR(50) NOT NULL DEFAULT 'aura_teaser'
				AFTER `lead_id`"
			);

			SM_Logger::log(
				'info',
				'DB_MIGRATION',
				'Added reading_type column to sm_readings table',
				array( 'version' => '1.3.0' )
			);
		}

		// Add content_data column (JSON blob storage)
		$content_data_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM `{$table_name}` LIKE %s",
				'content_data'
			)
		);

		if ( empty( $content_data_exists ) ) {
			$wpdb->query(
				"ALTER TABLE `{$table_name}`
				ADD COLUMN `content_data` LONGTEXT NULL DEFAULT NULL
				AFTER `reading_type`"
			);

			SM_Logger::log(
				'info',
				'DB_MIGRATION',
				'Added content_data column to sm_readings table',
				array( 'version' => '1.3.0' )
			);
		}

		// Add unlocked_section column
		$unlocked_section_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM `{$table_name}` LIKE %s",
				'unlocked_section'
			)
		);

		if ( empty( $unlocked_section_exists ) ) {
			$wpdb->query(
				"ALTER TABLE `{$table_name}`
				ADD COLUMN `unlocked_section` VARCHAR(100) NULL DEFAULT NULL
				AFTER `prompt_template_used`"
			);

			SM_Logger::log(
				'info',
				'DB_MIGRATION',
				'Added unlocked_section column to sm_readings table',
				array( 'version' => '1.3.0' )
			);
		}

		// Add unlock_count column
		$unlock_count_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM `{$table_name}` LIKE %s",
				'unlock_count'
			)
		);

		if ( empty( $unlock_count_exists ) ) {
			$wpdb->query(
				"ALTER TABLE `{$table_name}`
				ADD COLUMN `unlock_count` INT NOT NULL DEFAULT 0
				AFTER `unlocked_section`"
			);

			SM_Logger::log(
				'info',
				'DB_MIGRATION',
				'Added unlock_count column to sm_readings table',
				array( 'version' => '1.3.0' )
			);
		}

		// Add has_purchased column
		$has_purchased_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM `{$table_name}` LIKE %s",
				'has_purchased'
			)
		);

		if ( empty( $has_purchased_exists ) ) {
			$wpdb->query(
				"ALTER TABLE `{$table_name}`
				ADD COLUMN `has_purchased` TINYINT(1) NOT NULL DEFAULT 0
				AFTER `unlock_count`"
			);

			SM_Logger::log(
				'info',
				'DB_MIGRATION',
				'Added has_purchased column to sm_readings table',
				array( 'version' => '1.3.0' )
			);
		}

		// Rename generated_at to created_at for consistency
		$created_at_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM `{$table_name}` LIKE %s",
				'created_at'
			)
		);

		$generated_at_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM `{$table_name}` LIKE %s",
				'generated_at'
			)
		);

		if ( ! empty( $generated_at_exists ) && empty( $created_at_exists ) ) {
			$wpdb->query(
				"ALTER TABLE `{$table_name}`
				CHANGE COLUMN `generated_at` `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP"
			);

			SM_Logger::log(
				'info',
				'DB_MIGRATION',
				'Renamed generated_at to created_at in sm_readings table',
				array( 'version' => '1.3.0' )
			);
		}

		// Add updated_at column
		$updated_at_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM `{$table_name}` LIKE %s",
				'updated_at'
			)
		);

		if ( empty( $updated_at_exists ) ) {
			$wpdb->query(
				"ALTER TABLE `{$table_name}`
				ADD COLUMN `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
				AFTER `created_at`"
			);

			SM_Logger::log(
				'info',
				'DB_MIGRATION',
				'Added updated_at column to sm_readings table',
				array( 'version' => '1.3.0' )
			);
		}

		// Add index on reading_type for performance
		$index_exists = $wpdb->get_results(
			"SHOW INDEX FROM `{$table_name}` WHERE Key_name = 'idx_reading_type'"
		);

		if ( empty( $index_exists ) ) {
			$wpdb->query(
				"ALTER TABLE `{$table_name}`
				ADD INDEX `idx_reading_type` (`reading_type`)"
			);

			SM_Logger::log(
				'info',
				'DB_MIGRATION',
				'Added index idx_reading_type to sm_readings table',
				array( 'version' => '1.3.0' )
			);
		}

		// Add index on created_at for sorting reading history
		$index_exists = $wpdb->get_results(
			"SHOW INDEX FROM `{$table_name}` WHERE Key_name = 'idx_created_at'"
		);

		if ( empty( $index_exists ) ) {
			$wpdb->query(
				"ALTER TABLE `{$table_name}`
				ADD INDEX `idx_created_at` (`created_at`)"
			);

			SM_Logger::log(
				'info',
				'DB_MIGRATION',
				'Added index idx_created_at to sm_readings table',
				array( 'version' => '1.3.0' )
			);
		}

		// Add composite index on lead_id and created_at for reading history queries
		$composite_index_exists = $wpdb->get_results(
			"SHOW INDEX FROM `{$table_name}` WHERE Key_name = 'idx_lead_created'"
		);

		if ( empty( $composite_index_exists ) ) {
			$wpdb->query(
				"ALTER TABLE `{$table_name}`
				ADD INDEX `idx_lead_created` (`lead_id`, `created_at`)"
			);

			SM_Logger::log(
				'info',
				'DB_MIGRATION',
				'Added composite index idx_lead_created to sm_readings table',
				array( 'version' => '1.3.0' )
			);
		}

		SM_Logger::log(
			'info',
			'DB_MIGRATION',
			'Successfully completed migration to version 1.3.0 - JSON-based teaser reading system',
			array(
				'version'     => '1.3.0',
				'table'       => $table_name,
				'new_columns' => array(
					'reading_type',
					'content_data',
					'unlocked_section',
					'unlock_count',
					'has_purchased',
					'updated_at',
				),
				'renamed_columns' => array( 'generated_at' => 'created_at' ),
				'new_indexes'     => array(
					'idx_reading_type',
					'idx_created_at',
					'idx_lead_created',
				),
			)
		);
	}

	/**
	 * Migration to version 1.4.0: Add account_id support for Account Service integration.
	 *
	 * Adds support for:
	 * - Linking leads to Account Service user accounts
	 * - Linking readings to Account Service user accounts
	 * - Account-based authentication and credit system
	 * - Backward compatibility (NULL for free users)
	 *
	 * @since 1.4.0
	 */
	private function migrate_to_1_4_0() {
		global $wpdb;

		$leads_table    = $this->tables['leads'];
		$readings_table = $this->tables['readings'];

		// Add account_id column to sm_leads table
		$leads_account_id_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM `{$leads_table}` LIKE %s",
				'account_id'
			)
		);

		if ( empty( $leads_account_id_exists ) ) {
			$wpdb->query(
				"ALTER TABLE `{$leads_table}`
				ADD COLUMN `account_id` VARCHAR(255) NULL DEFAULT NULL
				AFTER `email`"
			);

			SM_Logger::log(
				'info',
				'DB_MIGRATION',
				'Added account_id column to sm_leads table',
				array( 'version' => '1.4.0' )
			);
		}

		// Add index on account_id in sm_leads table
		$leads_index_exists = $wpdb->get_results(
			"SHOW INDEX FROM `{$leads_table}` WHERE Key_name = 'idx_account_id'"
		);

		if ( empty( $leads_index_exists ) ) {
			$wpdb->query(
				"ALTER TABLE `{$leads_table}`
				ADD INDEX `idx_account_id` (`account_id`)"
			);

			SM_Logger::log(
				'info',
				'DB_MIGRATION',
				'Added index idx_account_id to sm_leads table',
				array( 'version' => '1.4.0' )
			);
		}

		// Add account_id column to sm_readings table
		$readings_account_id_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM `{$readings_table}` LIKE %s",
				'account_id'
			)
		);

		if ( empty( $readings_account_id_exists ) ) {
			$wpdb->query(
				"ALTER TABLE `{$readings_table}`
				ADD COLUMN `account_id` VARCHAR(255) NULL DEFAULT NULL
				AFTER `lead_id`"
			);

			SM_Logger::log(
				'info',
				'DB_MIGRATION',
				'Added account_id column to sm_readings table',
				array( 'version' => '1.4.0' )
			);
		}

		// Add index on account_id in sm_readings table
		$readings_index_exists = $wpdb->get_results(
			"SHOW INDEX FROM `{$readings_table}` WHERE Key_name = 'idx_account_id'"
		);

		if ( empty( $readings_index_exists ) ) {
			$wpdb->query(
				"ALTER TABLE `{$readings_table}`
				ADD INDEX `idx_account_id` (`account_id`)"
			);

			SM_Logger::log(
				'info',
				'DB_MIGRATION',
				'Added index idx_account_id to sm_readings table',
				array( 'version' => '1.4.0' )
			);
		}

		SM_Logger::log(
			'info',
			'DB_MIGRATION',
			'Successfully completed migration to version 1.4.0 - Account Service integration',
			array(
				'version'     => '1.4.0',
				'tables'      => array( $leads_table, $readings_table ),
				'new_columns' => array( 'account_id' ),
				'new_indexes' => array( 'idx_account_id' ),
			)
		);
	}

	/**
	 * Migration to version 1.4.1: Allow multiple leads per email.
	 */
	private function migrate_to_1_4_1() {
		global $wpdb;
		$leads_table = $this->get_table_name( 'leads' );

		$email_index = $wpdb->get_results(
			"SHOW INDEX FROM `{$leads_table}` WHERE Key_name = 'email'",
			ARRAY_A
		);

		if ( ! empty( $email_index ) ) {
			$wpdb->query(
				"ALTER TABLE `{$leads_table}` DROP INDEX `email`"
			);
			SM_Logger::log(
				'info',
				'DATABASE_MIGRATION',
				'Dropped unique email index from sm_leads table',
				array( 'version' => '1.4.1' )
			);
		}

		$non_unique = $wpdb->get_results(
			"SHOW INDEX FROM `{$leads_table}` WHERE Key_name = 'idx_email'",
			ARRAY_A
		);

		if ( empty( $non_unique ) ) {
			$wpdb->query(
				"ALTER TABLE `{$leads_table}` ADD INDEX `idx_email` (`email`)"
			);
			SM_Logger::log(
				'info',
				'DATABASE_MIGRATION',
				'Added non-unique email index to sm_leads table',
				array( 'version' => '1.4.1' )
			);
		}

		SM_Logger::log(
			'info',
			'DATABASE_MIGRATION',
			'Successfully completed migration to version 1.4.1 - allow multiple leads per email',
			array( 'version' => '1.4.1' )
		);
	}

	/**
	 * Migration to version 1.4.5: Create flow_sessions table.
	 *
	 * Adds flow session management for tracking user progress through the reading flow.
	 * Critical fix: The flow_sessions table was missing from the schema, causing 500 errors
	 * on fresh page loads.
	 *
	 * @since 1.4.5
	 */
	private function migrate_to_1_4_5() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$flow_table      = $this->get_table_name( 'flow_sessions' );

		// Check if table already exists
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $flow_table )
		);

		if ( $table_exists !== $flow_table ) {
			$schema = "CREATE TABLE {$flow_table} (
				flow_id CHAR(36) NOT NULL,
				account_id VARCHAR(100) NULL DEFAULT NULL,
				lead_id CHAR(36) NULL DEFAULT NULL,
				reading_id CHAR(36) NULL DEFAULT NULL,
				email VARCHAR(255) NULL DEFAULT NULL,
				step_id VARCHAR(50) NULL DEFAULT NULL,
				status VARCHAR(50) NULL DEFAULT NULL,
				magic_token_hash VARCHAR(255) NULL DEFAULT NULL,
				magic_expires_at DATETIME NULL DEFAULT NULL,
				expires_at DATETIME NOT NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (flow_id),
				KEY account_id (account_id),
				KEY lead_id (lead_id),
				KEY expires_at (expires_at),
				KEY status (status)
			) {$charset_collate};";

			dbDelta( $schema );

			SM_Logger::log(
				'info',
				'DATABASE_MIGRATION',
				'Created flow_sessions table',
				array( 'version' => '1.4.5' )
			);
		}

		SM_Logger::log(
			'info',
			'DATABASE_MIGRATION',
			'Successfully completed migration to version 1.4.5 - flow session management',
			array( 'version' => '1.4.5' )
		);
	}

	/**
	 * Migration to version 1.4.6: Track invalid palm image attempts on leads.
	 *
	 * @since 1.4.6
	 */
	private function migrate_to_1_4_6() {
		global $wpdb;

		$leads_table = $this->get_table_name( 'leads' );
		$columns     = array(
			'invalid_image_attempts'    => "INT(11) NOT NULL DEFAULT 0",
			'invalid_image_locked'      => "TINYINT(1) NOT NULL DEFAULT 0",
			'invalid_image_last_reason' => "VARCHAR(255) NULL DEFAULT NULL",
			'invalid_image_last_at'     => "DATETIME NULL DEFAULT NULL",
		);

		foreach ( $columns as $column => $definition ) {
			$exists = $wpdb->get_results(
				$wpdb->prepare(
					"SHOW COLUMNS FROM `{$leads_table}` LIKE %s",
					$column
				)
			);

			if ( empty( $exists ) ) {
				$wpdb->query(
					"ALTER TABLE `{$leads_table}` ADD `{$column}` {$definition}"
				);

				SM_Logger::log(
					'info',
					'DATABASE_MIGRATION',
					"Added {$column} column to sm_leads table",
					array( 'version' => '1.4.6' )
				);
			}
		}

		SM_Logger::log(
			'info',
			'DATABASE_MIGRATION',
			'Successfully completed migration to version 1.4.6 - invalid palm image tracking',
			array( 'version' => '1.4.6' )
		);
	}
}
