<?php
/**
 * Rollback Script for Migration 1.4.0
 *
 * This script rolls back the database migration to v1.4.0
 * It removes the account_id columns and indexes added in migration 1.4.0
 *
 * Usage: Access via browser at: http://your-site.local/wp-content/plugins/sm-palm-reading/rollback-migration-1.4.0.php
 *
 * IMPORTANT: Only use this if you need to revert the migration!
 * IMPORTANT: Delete this file after running it successfully!
 */

// Load WordPress
require_once '../../../wp-load.php';

// Security check - only allow in local development
if ( ! defined( 'WP_ENVIRONMENT_TYPE' ) || WP_ENVIRONMENT_TYPE !== 'local' ) {
	wp_die( 'This script can only be run in local development environments.' );
}

// Check if user is admin
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'You must be logged in as an administrator to run this script.' );
}

?>
<!DOCTYPE html>
<html>
<head>
	<title>SoulMirror - Rollback Migration 1.4.0</title>
	<style>
		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			max-width: 800px;
			margin: 50px auto;
			padding: 20px;
			background: #f0f0f1;
		}
		.container {
			background: white;
			padding: 30px;
			border-radius: 8px;
			box-shadow: 0 1px 3px rgba(0,0,0,0.1);
		}
		h1 {
			color: #1d2327;
			border-bottom: 2px solid #d63638;
			padding-bottom: 10px;
		}
		.status {
			padding: 15px;
			margin: 15px 0;
			border-radius: 4px;
		}
		.status.info {
			background: #e7f3ff;
			border-left: 4px solid #2271b1;
		}
		.status.success {
			background: #d5f4e6;
			border-left: 4px solid #00a32a;
		}
		.status.error {
			background: #ffd1d1;
			border-left: 4px solid #d63638;
		}
		.button {
			background: #d63638;
			color: white;
			padding: 10px 20px;
			border: none;
			border-radius: 4px;
			cursor: pointer;
			font-size: 14px;
			text-decoration: none;
			display: inline-block;
		}
		.button:hover {
			background: #ab2a2e;
		}
		pre {
			background: #f6f7f7;
			padding: 15px;
			border-radius: 4px;
			overflow-x: auto;
		}
		.warning {
			background: #fcf3cd;
			border-left: 4px solid #dba617;
			padding: 15px;
			margin: 15px 0;
		}
		.danger {
			background: #ffd1d1;
			border-left: 4px solid #d63638;
			padding: 15px;
			margin: 15px 0;
			font-weight: bold;
		}
	</style>
</head>
<body>
	<div class="container">
		<h1>⚠️ Rollback Migration 1.4.0</h1>

		<?php
		// Get current DB version
		$current_version = get_option( 'sm_db_version', 'not set' );

		echo '<div class="status info">';
		echo '<strong>Current DB Version:</strong> ' . esc_html( $current_version );
		echo '</div>';

		// Check if migration was run
		if ( isset( $_GET['rollback'] ) && $_GET['rollback'] === 'yes' ) {
			echo '<h2>Rolling Back Migration...</h2>';

			global $wpdb;
			$leads_table = $wpdb->prefix . 'sm_leads';
			$readings_table = $wpdb->prefix . 'sm_readings';

			try {
				$success = true;
				$messages = array();

				// Drop index from sm_leads
				$leads_index_exists = $wpdb->get_results(
					"SHOW INDEX FROM `{$leads_table}` WHERE Key_name = 'idx_account_id'"
				);

				if ( ! empty( $leads_index_exists ) ) {
					$result = $wpdb->query( "ALTER TABLE `{$leads_table}` DROP INDEX `idx_account_id`" );
					if ( false === $result ) {
						$success = false;
						$messages[] = "❌ Failed to drop idx_account_id from sm_leads: " . $wpdb->last_error;
					} else {
						$messages[] = "✅ Dropped idx_account_id from sm_leads";
					}
				} else {
					$messages[] = "ℹ️ Index idx_account_id not found in sm_leads (already removed)";
				}

				// Drop index from sm_readings
				$readings_index_exists = $wpdb->get_results(
					"SHOW INDEX FROM `{$readings_table}` WHERE Key_name = 'idx_account_id'"
				);

				if ( ! empty( $readings_index_exists ) ) {
					$result = $wpdb->query( "ALTER TABLE `{$readings_table}` DROP INDEX `idx_account_id`" );
					if ( false === $result ) {
						$success = false;
						$messages[] = "❌ Failed to drop idx_account_id from sm_readings: " . $wpdb->last_error;
					} else {
						$messages[] = "✅ Dropped idx_account_id from sm_readings";
					}
				} else {
					$messages[] = "ℹ️ Index idx_account_id not found in sm_readings (already removed)";
				}

				// Drop account_id column from sm_leads
				$leads_column_exists = $wpdb->get_results(
					$wpdb->prepare(
						"SHOW COLUMNS FROM `{$leads_table}` LIKE %s",
						'account_id'
					)
				);

				if ( ! empty( $leads_column_exists ) ) {
					$result = $wpdb->query( "ALTER TABLE `{$leads_table}` DROP COLUMN `account_id`" );
					if ( false === $result ) {
						$success = false;
						$messages[] = "❌ Failed to drop account_id from sm_leads: " . $wpdb->last_error;
					} else {
						$messages[] = "✅ Dropped account_id column from sm_leads";
					}
				} else {
					$messages[] = "ℹ️ Column account_id not found in sm_leads (already removed)";
				}

				// Drop account_id column from sm_readings
				$readings_column_exists = $wpdb->get_results(
					$wpdb->prepare(
						"SHOW COLUMNS FROM `{$readings_table}` LIKE %s",
						'account_id'
					)
				);

				if ( ! empty( $readings_column_exists ) ) {
					$result = $wpdb->query( "ALTER TABLE `{$readings_table}` DROP COLUMN `account_id`" );
					if ( false === $result ) {
						$success = false;
						$messages[] = "❌ Failed to drop account_id from sm_readings: " . $wpdb->last_error;
					} else {
						$messages[] = "✅ Dropped account_id column from sm_readings";
					}
				} else {
					$messages[] = "ℹ️ Column account_id not found in sm_readings (already removed)";
				}

				// Revert database version
				if ( $success ) {
					update_option( 'sm_db_version', '1.3.0' );
					$messages[] = "✅ Reverted database version to 1.3.0";
				}

				// Log the rollback
				if ( class_exists( 'SM_Logger' ) ) {
					SM_Logger::log(
						'warning',
						'DB_ROLLBACK',
						'Rolled back migration 1.4.0',
						array(
							'success' => $success,
							'messages' => $messages,
						)
					);
				}

				// Display results
				if ( $success ) {
					echo '<div class="status success">';
					echo '<strong>✅ Rollback completed successfully!</strong><br><br>';
					echo 'Database version reverted to: 1.3.0';
					echo '</div>';
				} else {
					echo '<div class="status error">';
					echo '<strong>⚠️ Rollback completed with errors. Check messages below.</strong>';
					echo '</div>';
				}

				echo '<h3>Rollback Messages:</h3>';
				echo '<ul>';
				foreach ( $messages as $message ) {
					echo '<li>' . esc_html( $message ) . '</li>';
				}
				echo '</ul>';

				// Verify columns were removed
				$leads_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$leads_table}`" );
				$readings_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$readings_table}`" );

				echo '<h3>sm_leads Columns After Rollback:</h3>';
				echo '<pre>' . esc_html( implode( "\n", $leads_columns ) ) . '</pre>';

				echo '<h3>sm_readings Columns After Rollback:</h3>';
				echo '<pre>' . esc_html( implode( "\n", $readings_columns ) ) . '</pre>';

				if ( ! in_array( 'account_id', $leads_columns ) && ! in_array( 'account_id', $readings_columns ) ) {
					echo '<div class="status success">';
					echo '<strong>✅ Verification: account_id columns successfully removed from both tables!</strong>';
					echo '</div>';
				} else {
					echo '<div class="status error">';
					echo '<strong>⚠️ Verification: Some account_id columns may still exist!</strong>';
					echo '</div>';
				}

				echo '<div class="warning">';
				echo '<strong>⚠️ IMPORTANT:</strong> Delete this file (rollback-migration-1.4.0.php) now that the rollback is complete!';
				echo '</div>';

				echo '<p><a href="' . admin_url() . '" class="button">Go to WordPress Admin</a></p>';

			} catch ( Exception $e ) {
				echo '<div class="status error">';
				echo '<strong>❌ Rollback failed:</strong><br><br>';
				echo esc_html( $e->getMessage() );
				echo '</div>';
			}

		} else {
			// Show rollback button
			echo '<div class="danger">';
			echo '<strong>⚠️ DANGER:</strong> This will REMOVE the account_id columns and indexes added in migration 1.4.0!<br>';
			echo 'This action will DELETE DATA if any account_id values have been set.<br>';
			echo 'Make sure you have a database backup before proceeding.';
			echo '</div>';

			echo '<p><strong>This rollback will remove the following:</strong></p>';
			echo '<ul>';
			echo '<li><code>account_id</code> column from <strong>wp_sm_leads</strong></li>';
			echo '<li><code>account_id</code> column from <strong>wp_sm_readings</strong></li>';
			echo '<li><code>idx_account_id</code> index from <strong>wp_sm_leads</strong></li>';
			echo '<li><code>idx_account_id</code> index from <strong>wp_sm_readings</strong></li>';
			echo '</ul>';

			echo '<div class="warning">';
			echo '<strong>⚠️ When to use this rollback:</strong><br>';
			echo '• If the migration caused errors<br>';
			echo '• If you need to revert to the previous schema<br>';
			echo '• If you want to test the migration again from scratch<br><br>';
			echo '<strong>⚠️ When NOT to use this rollback:</strong><br>';
			echo '• If you have already started using the Account Service integration<br>';
			echo '• If any leads or readings have account_id values set (data will be lost)<br>';
			echo '• If you are in production (consult with your team first)';
			echo '</div>';

			echo '<p><a href="?rollback=yes" class="button">⚠️ Rollback Migration 1.4.0</a></p>';
		}
		?>

	</div>
</body>
</html>
