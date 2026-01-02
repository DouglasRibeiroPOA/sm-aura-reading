<?php
/**
 * Database cleanup and maintenance scheduler.
 *
 * @package MysticPalmReadingAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles scheduled cleanup tasks for database maintenance.
 */
class SM_Cleanup {

	/**
	 * Cron hook name for daily cleanup.
	 */
	const CLEANUP_HOOK = 'sm_daily_cleanup';

	/**
	 * Log retention period in days.
	 */
	const LOG_RETENTION_DAYS = 30;

	/**
	 * Singleton instance.
	 *
	 * @var SM_Cleanup|null
	 */
	private static $instance = null;

	/**
	 * Initialize the cleanup scheduler.
	 *
	 * @return SM_Cleanup
	 */
	public static function init() {
		return self::get_instance();
	}

	/**
	 * Get singleton instance.
	 *
	 * @return SM_Cleanup
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
		// Schedule cleanup task if not already scheduled.
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CLEANUP_HOOK );
		}

		// Hook into the scheduled event.
		add_action( self::CLEANUP_HOOK, array( $this, 'run_daily_cleanup' ) );
	}

	/**
	 * Run all daily cleanup tasks.
	 *
	 * @return void
	 */
	public function run_daily_cleanup() {
		SM_Logger::info(
			'CLEANUP_STARTED',
			'Daily cleanup task started',
			array( 'time' => current_time( 'mysql' ) )
		);

		$results = array(
			'expired_otps'     => $this->cleanup_expired_otps(),
			'old_logs'         => $this->cleanup_old_logs(),
			'orphaned_records' => $this->cleanup_orphaned_records(),
		);

		SM_Logger::info(
			'CLEANUP_COMPLETED',
			'Daily cleanup task completed',
			$results
		);
	}

	/**
	 * Delete expired OTP records.
	 *
	 * @return int Number of records deleted.
	 */
	public function cleanup_expired_otps() {
		global $wpdb;

		$db    = SM_Database::get_instance();
		$table = $db->get_table_name( 'otps' );

		if ( empty( $table ) ) {
			return 0;
		}

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE expires_at < %s",
				current_time( 'mysql' )
			)
		);

		if ( false === $deleted ) {
			SM_Logger::error(
				'CLEANUP_ERROR',
				'Failed to delete expired OTPs',
				array( 'error' => $wpdb->last_error )
			);
			return 0;
		}

		if ( $deleted > 0 ) {
			SM_Logger::debug(
				'CLEANUP_OTPS',
				'Expired OTPs deleted',
				array( 'count' => $deleted )
			);
		}

		return (int) $deleted;
	}

	/**
	 * Delete log entries older than retention period.
	 *
	 * @return int Number of records deleted.
	 */
	public function cleanup_old_logs() {
		global $wpdb;

		$db    = SM_Database::get_instance();
		$table = $db->get_table_name( 'logs' );

		if ( empty( $table ) ) {
			return 0;
		}

		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( '-' . self::LOG_RETENTION_DAYS . ' days' ) );

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < %s",
				$cutoff_date
			)
		);

		if ( false === $deleted ) {
			SM_Logger::error(
				'CLEANUP_ERROR',
				'Failed to delete old logs',
				array( 'error' => $wpdb->last_error )
			);
			return 0;
		}

		if ( $deleted > 0 ) {
			SM_Logger::debug(
				'CLEANUP_LOGS',
				'Old log entries deleted',
				array(
					'count'       => $deleted,
					'older_than'  => $cutoff_date,
					'days'        => self::LOG_RETENTION_DAYS,
				)
			);
		}

		return (int) $deleted;
	}

	/**
	 * Delete orphaned records (OTPs, quiz, readings without valid lead_id).
	 *
	 * @return int Total number of records deleted.
	 */
	public function cleanup_orphaned_records() {
		global $wpdb;

		$db          = SM_Database::get_instance();
		$leads_table = $db->get_table_name( 'leads' );

		if ( empty( $leads_table ) ) {
			return 0;
		}

		$total_deleted = 0;

		// Cleanup orphaned OTPs.
		$otps_table = $db->get_table_name( 'otps' );
		if ( ! empty( $otps_table ) ) {
			$deleted = $wpdb->query(
				"DELETE FROM {$otps_table}
				WHERE lead_id NOT IN (SELECT id FROM {$leads_table})"
			);

			if ( false !== $deleted ) {
				$total_deleted += (int) $deleted;
			}
		}

		// Cleanup orphaned quiz records.
		$quiz_table = $db->get_table_name( 'quiz' );
		if ( ! empty( $quiz_table ) ) {
			$deleted = $wpdb->query(
				"DELETE FROM {$quiz_table}
				WHERE lead_id NOT IN (SELECT id FROM {$leads_table})"
			);

			if ( false !== $deleted ) {
				$total_deleted += (int) $deleted;
			}
		}

		// Cleanup orphaned readings.
		$readings_table = $db->get_table_name( 'readings' );
		if ( ! empty( $readings_table ) ) {
			$deleted = $wpdb->query(
				"DELETE FROM {$readings_table}
				WHERE lead_id NOT IN (SELECT id FROM {$leads_table})"
			);

			if ( false !== $deleted ) {
				$total_deleted += (int) $deleted;
			}
		}

		if ( $total_deleted > 0 ) {
			SM_Logger::debug(
				'CLEANUP_ORPHANED',
				'Orphaned records deleted',
				array( 'count' => $total_deleted )
			);
		}

		return $total_deleted;
	}

	/**
	 * Manual cleanup trigger (for admin use).
	 *
	 * @return array Results of cleanup operations.
	 */
	public static function run_manual_cleanup() {
		$instance = self::get_instance();

		$results = array(
			'expired_otps'     => $instance->cleanup_expired_otps(),
			'old_logs'         => $instance->cleanup_old_logs(),
			'orphaned_records' => $instance->cleanup_orphaned_records(),
		);

		SM_Logger::info(
			'CLEANUP_MANUAL',
			'Manual cleanup triggered',
			$results
		);

		return $results;
	}

	/**
	 * Unschedule cleanup task (for plugin deactivation).
	 *
	 * @return void
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::CLEANUP_HOOK );
	}
}
