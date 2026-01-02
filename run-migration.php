<?php
/**
 * Migration Trigger Script
 *
 * This script manually triggers the database migration to v1.4.0
 *
 * Usage: Access via browser at: http://your-site.local/wp-content/plugins/sm-palm-reading/run-migration.php
 *
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
	<title>SoulMirror - Database Migration</title>
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
			border-bottom: 2px solid #2271b1;
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
			background: #2271b1;
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
			background: #135e96;
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
	</style>
</head>
<body>
	<div class="container">
		<h1>🔧 SoulMirror Database Migration</h1>

		<?php
		// Get current DB version
		$current_version = get_option( 'sm_db_version', 'not set' );
		$target_version = '1.4.0';

		echo '<div class="status info">';
		echo '<strong>Current DB Version:</strong> ' . esc_html( $current_version ) . '<br>';
		echo '<strong>Target Version:</strong> ' . esc_html( $target_version );
		echo '</div>';

		// Check if migration needed
		if ( isset( $_GET['run'] ) && $_GET['run'] === 'yes' ) {
			echo '<h2>Running Migration...</h2>';

			// Get database instance
			$db = SM_Database::get_instance();

			// Get the method using reflection (since it's private)
			$reflection = new ReflectionClass( $db );
			$method = $reflection->getMethod( 'migrate_to_1_4_0' );
			$method->setAccessible( true );

			try {
				// Run the migration
				ob_start();
				$method->invoke( $db );
				$output = ob_get_clean();

				// Update the version
				update_option( 'sm_db_version', $target_version );

				echo '<div class="status success">';
				echo '<strong>✅ Migration completed successfully!</strong><br><br>';
				echo 'Database version updated to: ' . esc_html( $target_version );
				echo '</div>';

				if ( ! empty( $output ) ) {
					echo '<h3>Migration Output:</h3>';
					echo '<pre>' . esc_html( $output ) . '</pre>';
				}

				// Verify columns were added
				global $wpdb;

				// Check sm_readings table
				$readings_table = $wpdb->prefix . 'sm_readings';
				$readings_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$readings_table}`" );

				echo '<h3>sm_readings Table Columns:</h3>';
				echo '<pre>' . esc_html( implode( "\n", $readings_columns ) ) . '</pre>';

				if ( in_array( 'account_id', $readings_columns ) ) {
					echo '<div class="status success">';
					echo '<strong>✅ account_id column added to sm_readings!</strong>';
					echo '</div>';
				} else {
					echo '<div class="status error">';
					echo '<strong>⚠️ account_id column missing from sm_readings!</strong>';
					echo '</div>';
				}

				// Check sm_leads table
				$leads_table = $wpdb->prefix . 'sm_leads';
				$leads_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$leads_table}`" );

				echo '<h3>sm_leads Table Columns:</h3>';
				echo '<pre>' . esc_html( implode( "\n", $leads_columns ) ) . '</pre>';

				if ( in_array( 'account_id', $leads_columns ) ) {
					echo '<div class="status success">';
					echo '<strong>✅ account_id column added to sm_leads!</strong>';
					echo '</div>';
				} else {
					echo '<div class="status error">';
					echo '<strong>⚠️ account_id column missing from sm_leads!</strong>';
					echo '</div>';
				}

				// Check indexes
				$readings_indexes = $wpdb->get_results( "SHOW INDEX FROM `{$readings_table}` WHERE Key_name = 'idx_account_id'" );
				$leads_indexes = $wpdb->get_results( "SHOW INDEX FROM `{$leads_table}` WHERE Key_name = 'idx_account_id'" );

				if ( ! empty( $readings_indexes ) && ! empty( $leads_indexes ) ) {
					echo '<div class="status success">';
					echo '<strong>✅ Indexes created successfully!</strong>';
					echo '</div>';
				} else {
					echo '<div class="status error">';
					echo '<strong>⚠️ Some indexes may be missing!</strong>';
					echo '</div>';
				}

				echo '<div class="warning">';
				echo '<strong>⚠️ IMPORTANT:</strong> Delete this file (run-migration.php) now that the migration is complete!';
				echo '</div>';

				echo '<p><a href="' . admin_url() . '" class="button">Go to WordPress Admin</a></p>';

			} catch ( Exception $e ) {
				echo '<div class="status error">';
				echo '<strong>❌ Migration failed:</strong><br><br>';
				echo esc_html( $e->getMessage() );
				echo '</div>';
			}

		} else {
			// Show run button
			echo '<div class="warning">';
			echo '<strong>⚠️ Warning:</strong> This will run the database migration to v1.4.0.<br>';
			echo 'Make sure you have a database backup before proceeding.';
			echo '</div>';

			echo '<p><strong>This migration will add the following:</strong></p>';
			echo '<ul>';
			echo '<li><code>account_id</code> column to <strong>wp_sm_leads</strong> - VARCHAR(255) NULL</li>';
			echo '<li><code>account_id</code> column to <strong>wp_sm_readings</strong> - VARCHAR(255) NULL</li>';
			echo '<li><code>idx_account_id</code> index on <strong>wp_sm_leads</strong></li>';
			echo '<li><code>idx_account_id</code> index on <strong>wp_sm_readings</strong></li>';
			echo '</ul>';

			echo '<p><strong>Purpose:</strong> Enable Account Service integration for SSO login and credit-based paid readings.</p>';

			echo '<p><a href="?run=yes" class="button">Run Migration Now</a></p>';
		}
		?>

	</div>
</body>
</html>
