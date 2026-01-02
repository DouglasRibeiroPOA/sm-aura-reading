<?php
/**
 * Template for User Reports Listing (Read-Only)
 *
 * Displays past aura readings for authenticated users with pagination.
 * This is a strictly presentation layer - no editing, no regeneration.
 *
 * @package Mystic_Aura_Reading
 * @since 2.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ============================================
// AUTHENTICATION CHECK
// ============================================
$auth_handler = SM_Auth_Handler::get_instance();
$user_data    = $auth_handler->get_current_user();

// Redirect to login if not authenticated
	if ( empty( $user_data ) || empty( $user_data['account_id'] ) ) {
	$login_url = home_url( '/aura-reading' );
	wp_safe_redirect( $login_url );
	exit;
}

$account_id = sanitize_text_field( $user_data['account_id'] );

// ============================================
// PAGINATION PARAMETERS
// ============================================
$per_page      = isset( $_GET['per_page'] ) ? intval( $_GET['per_page'] ) : 10;
$current_page  = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$per_page      = in_array( $per_page, array( 10, 20, 30 ), true ) ? $per_page : 10;
$offset        = ( $current_page - 1 ) * $per_page;

// ============================================
// FETCH REPORTS DATA
// ============================================
$reports_handler = SM_Reports_Handler::get_instance();
$reports         = $reports_handler->get_user_reports( $account_id, $per_page, $offset );
$total_reports   = $reports_handler->get_user_reports_count( $account_id );
$total_pages     = max( 1, ceil( $total_reports / $per_page ) );

// Calculate display range
$results_from = $total_reports > 0 ? $offset + 1 : 0;
$results_to   = min( $offset + $per_page, $total_reports );

// ============================================
// URLS
// ============================================
$page_base_url     = get_permalink();
$reports_page_url  = add_query_arg( 'sm_reports', '1', $page_base_url );
$dashboard_url     = $page_base_url; // Back to main page/dashboard
$base_url          = $reports_page_url;
$readings_base_url = $page_base_url; // Base URL for viewing individual readings

// ============================================
// USER INFO
// ============================================
$display_name = ! empty( $user_data['name'] ) ? $user_data['name'] : 'Mystic Seeker';

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Your Aura Readings | <?php bloginfo( 'name' ); ?></title>
	<?php wp_head(); ?>

	<!-- Inline CSS from reportsGridTemplate.html -->
	<style>
		<?php include SM_AURA_PLUGIN_DIR . 'assets/css/reports-listing.css'; ?>
	</style>
</head>
<body <?php body_class( 'sm-reports-page' ); ?>>

<!-- Background Animation -->
<div class="dashboard-bg-animation">
	<div class="aura-circle shape-1"></div>
	<div class="aura-circle shape-2"></div>
</div>

<!-- Reports Container -->
<div class="reports-container">
	<!-- Reports Header -->
	<div class="reports-header">
		<h1><i class="fas fa-scroll"></i> Your Aura Readings</h1>
		<a href="<?php echo esc_url( $dashboard_url ); ?>" class="btn-back-dashboard">
			<i class="fas fa-arrow-left"></i> Back to Dashboard
		</a>
	</div>

	<?php if ( $total_reports > 0 ) : ?>
		<!-- Controls Bar -->
		<div class="controls-bar">
			<div class="results-count">
				Showing <strong><?php echo esc_html( $results_from ); ?></strong> -
				<strong><?php echo esc_html( $results_to ); ?></strong> of
				<strong><?php echo esc_html( $total_reports ); ?></strong> readings
			</div>

			<div class="items-per-page">
				<label for="itemsPerPage">Items per page:</label>
				<select id="itemsPerPage" onchange="smUpdateReportsPerPage(this.value)">
					<option value="10" <?php selected( $per_page, 10 ); ?>>10</option>
					<option value="20" <?php selected( $per_page, 20 ); ?>>20</option>
					<option value="30" <?php selected( $per_page, 30 ); ?>>30</option>
				</select>
			</div>
		</div>

		<!-- Reports Table -->
		<div class="reports-table">
			<!-- Table Header -->
			<div class="table-header">
				<div>Report Title</div>
				<div>Date</div>
				<div>Reading Time</div>
				<div>Actions</div>
			</div>

			<!-- Reports Rows (Server-Side Rendered) -->
			<div id="reportsList">
				<?php foreach ( $reports as $report ) : ?>
					<?php
						$reading_type    = $report_is_paid ? 'aura_full' : ( ! empty( $report['type'] ) ? $report['type'] : 'aura_teaser' );
						$report_url      = add_query_arg(
							array(
								'sm_report'    => '1',
								'lead_id'      => $report['lead_id'],
								'reading_type' => $reading_type,
							),
							$readings_base_url
						);
						$download_url    = add_query_arg(
							array(
								'sm_download'       => '1',
								'reading_id'        => $report['id'],
								'sm_download_nonce' => wp_create_nonce( 'sm_download' ),
							),
							$page_base_url
						);
						$report_is_paid  = ! empty( $report['isPurchased'] );
						$download_class  = $report_is_paid ? '' : ' is-disabled';
						$download_toast  = $report_is_paid
							? 'HTML download coming soon'
							: 'Download is available for paid reports only';
					?>
					<div class="report-row" data-id="<?php echo esc_attr( $report['id'] ); ?>">
						<div class="report-title"><?php echo esc_html( $report['title'] ); ?></div>
						<div class="report-date">
							<i class="fas fa-calendar"></i>
							<span><?php echo esc_html( date( 'M j, Y', strtotime( $report['date'] ) ) ); ?></span>
						</div>
						<div class="report-time">
							<i class="fas fa-clock"></i>
							<span><?php echo esc_html( $report['readingTime'] ); ?></span>
						</div>
						<div class="report-actions">
							<a href="<?php echo esc_url( $report_url ); ?>"
							   class="action-btn view"
							   title="View Report">
								<i class="fas fa-eye"></i>
								<span>View</span>
							</a>
							<?php if ( $report_is_paid ) : ?>
								<a href="<?php echo esc_url( $download_url ); ?>"
									class="action-btn download"
									title="Download HTML Report">
									<i class="fas fa-file-pdf"></i>
									<span>Download</span>
								</a>
							<?php else : ?>
								<button type="button"
									class="action-btn download<?php echo esc_attr( $download_class ); ?>"
									title="Download HTML Report"
									data-toast="<?php echo esc_attr( $download_toast ); ?>"
									aria-disabled="true">
									<i class="fas fa-file-pdf"></i>
									<span>Download</span>
								</button>
							<?php endif; ?>
							<button type="button"
								class="action-btn share"
								title="Share Report"
								data-report-url="<?php echo esc_url( $report_url ); ?>"
								data-report-title="<?php echo esc_attr( $report['title'] ); ?>">
								<i class="fas fa-share-alt"></i>
								<span>Share</span>
							</button>
							<button type="button"
								class="action-btn delete"
								title="Delete Report"
								data-reading-id="<?php echo esc_attr( $report['id'] ); ?>"
								data-report-title="<?php echo esc_attr( $report['title'] ); ?>">
								<i class="fas fa-trash"></i>
								<span>Delete</span>
							</button>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Pagination -->
		<?php if ( $total_pages > 1 ) : ?>
			<div class="pagination">
				<div class="pagination-container">
					<?php if ( $current_page > 1 ) : ?>
						<a href="<?php echo esc_url( add_query_arg( array( 'paged' => 1, 'per_page' => $per_page ), $base_url ) ); ?>"
						   class="pagination-btn"
						   title="First Page">
							<i class="fas fa-angle-double-left"></i>
						</a>
						<a href="<?php echo esc_url( add_query_arg( array( 'paged' => $current_page - 1, 'per_page' => $per_page ), $base_url ) ); ?>"
						   class="pagination-btn"
						   title="Previous Page">
							<i class="fas fa-angle-left"></i>
						</a>
					<?php else : ?>
						<button class="pagination-btn" disabled title="First Page">
							<i class="fas fa-angle-double-left"></i>
						</button>
						<button class="pagination-btn" disabled title="Previous Page">
							<i class="fas fa-angle-left"></i>
						</button>
					<?php endif; ?>

					<span class="pagination-info">
						Page <strong><?php echo esc_html( $current_page ); ?></strong> of
						<strong><?php echo esc_html( $total_pages ); ?></strong>
					</span>

					<?php if ( $current_page < $total_pages ) : ?>
						<a href="<?php echo esc_url( add_query_arg( array( 'paged' => $current_page + 1, 'per_page' => $per_page ), $base_url ) ); ?>"
						   class="pagination-btn"
						   title="Next Page">
							<i class="fas fa-angle-right"></i>
						</a>
						<a href="<?php echo esc_url( add_query_arg( array( 'paged' => $total_pages, 'per_page' => $per_page ), $base_url ) ); ?>"
						   class="pagination-btn"
						   title="Last Page">
							<i class="fas fa-angle-double-right"></i>
						</a>
					<?php else : ?>
						<button class="pagination-btn" disabled title="Next Page">
							<i class="fas fa-angle-right"></i>
						</button>
						<button class="pagination-btn" disabled title="Last Page">
							<i class="fas fa-angle-double-right"></i>
						</button>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

	<?php else : ?>
		<!-- Empty State -->
		<div class="empty-state">
			<div class="empty-state-icon">
				<i class="fas fa-scroll"></i>
			</div>
			<h3>No Readings Yet</h3>
			<p>You haven't completed any aura readings yet. Begin your journey to unlock insights about your energy, relationships, and alignment.</p>
			<a href="<?php echo esc_url( $dashboard_url ); ?>" class="btn-new-reading">
				<i class="fas fa-crystal-ball"></i> Start Your First Reading
			</a>
		</div>
	<?php endif; ?>

	<!-- Reports Footer -->
	<div class="reports-footer">
		<div class="app-info">
			<span class="app-version">v<?php echo esc_html( SM_AURA_VERSION ); ?></span>
			<span class="app-status"><i class="fas fa-circle"></i> Connected</span>
		</div>
	</div>
</div>

<div id="smToastContainer" class="sm-toast-container" aria-live="polite" aria-atomic="true"></div>

<script>
	(function() {
		window.smUpdateReportsPerPage = function(perPage) {
			const url = new URL('<?php echo esc_url( $page_base_url ); ?>');
			url.searchParams.set('sm_reports', '1');
			url.searchParams.set('per_page', perPage);
			url.searchParams.set('paged', '1');
			window.location.href = url.toString();
		};

		const container = document.getElementById('smToastContainer');
		if (!container) {
			return;
		}

		function showToast(message) {
			const toast = document.createElement('div');
			toast.className = 'sm-toast';
			toast.textContent = message;
			container.appendChild(toast);

			requestAnimationFrame(() => {
				toast.classList.add('visible');
			});

			setTimeout(() => {
				toast.classList.remove('visible');
				setTimeout(() => {
					if (toast.parentNode) {
						toast.parentNode.removeChild(toast);
					}
				}, 300);
			}, 2200);
		}

		function updateResultsCountAfterDelete() {
			const resultsCount = document.querySelector('.results-count');
			if (!resultsCount) {
				return;
			}

			const values = resultsCount.querySelectorAll('strong');
			if (values.length < 3) {
				return;
			}

			const fromValue = parseInt(values[0].textContent, 10) || 0;
			const toValue = parseInt(values[1].textContent, 10) || 0;
			const totalValue = parseInt(values[2].textContent, 10) || 0;

			const newTotal = Math.max(0, totalValue - 1);
			const newTo = Math.max(fromValue, toValue - 1);

			values[1].textContent = newTo.toString();
			values[2].textContent = newTotal.toString();
		}

		async function handleShare(button) {
			const shareUrl = button.getAttribute('data-report-url');
			const shareTitle = button.getAttribute('data-report-title') || 'Aura Reading';

			if (!shareUrl) {
				showToast('Unable to share this report right now.');
				return;
			}

			if (navigator.share) {
				try {
					await navigator.share({
						title: shareTitle,
						text: 'My SoulMirror Aura Reading',
						url: shareUrl,
					});
					showToast('Share dialog opened.');
				} catch (error) {
					showToast('Share canceled.');
				}
				return;
			}

			if (navigator.clipboard && navigator.clipboard.writeText) {
				try {
					await navigator.clipboard.writeText(shareUrl);
					showToast('Share link copied to clipboard.');
					return;
				} catch (error) {
					// Fall through to manual message.
				}
			}

			showToast('Share link: ' + shareUrl);
		}

		async function handleDelete(button) {
			const readingId = button.getAttribute('data-reading-id');
			const reportTitle = button.getAttribute('data-report-title') || 'this reading';

			if (!readingId) {
				showToast('Unable to delete this report right now.');
				return;
			}

			const confirmDelete = window.confirm(`Delete "${reportTitle}"? This action cannot be undone.`);
			if (!confirmDelete) {
				return;
			}

			if (!window.smData || !window.smData.apiUrl) {
				showToast('Missing API configuration. Please refresh and try again.');
				return;
			}

			button.disabled = true;
			button.classList.add('is-disabled');

			try {
				const response = await fetch(`${window.smData.apiUrl}reports/delete`, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-SM-Nonce': window.smData.nonce || '',
					},
					body: JSON.stringify({
						reading_id: readingId,
					}),
				});

				const data = await response.json();
				if (!response.ok || !data || !data.success) {
					const message = data && data.message ? data.message : 'Failed to delete this report.';
					throw new Error(message);
				}

				const row = button.closest('.report-row');
				if (row && row.parentNode) {
					row.parentNode.removeChild(row);
				}

				const remainingRows = document.querySelectorAll('.report-row');
				if (remainingRows.length === 0) {
					window.location.reload();
					return;
				}

				updateResultsCountAfterDelete();
				showToast('Report deleted.');
			} catch (error) {
				showToast(error.message || 'Failed to delete this report.');
				button.disabled = false;
				button.classList.remove('is-disabled');
			}
		}

		document.addEventListener('click', function(event) {
			const shareBtn = event.target.closest('.action-btn.share');
			if (shareBtn) {
				event.preventDefault();
				handleShare(shareBtn);
				return;
			}

			const deleteBtn = event.target.closest('.action-btn.delete');
			if (deleteBtn) {
				event.preventDefault();
				handleDelete(deleteBtn);
				return;
			}

			const disabledDownloadBtn = event.target.closest('.action-btn.download.is-disabled');
			if (disabledDownloadBtn) {
				event.preventDefault();
				const message = disabledDownloadBtn.getAttribute('data-toast');
				if (message) {
					showToast(message);
				}
				return;
			}

			const target = event.target.closest('[data-toast]');
			if (!target) {
				return;
			}
			event.preventDefault();
			const message = target.getAttribute('data-toast');
			if (message) {
				showToast(message);
			}
		});
	})();
</script>

<?php wp_footer(); ?>
</body>
</html>
