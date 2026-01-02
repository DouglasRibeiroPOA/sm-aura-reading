<?php
/**
 * Container Template for Aura Reading Experience
 *
 * This template provides the minimal HTML structure needed for the JavaScript
 * application to render. The actual UI/UX is controlled by assets/js/script.js
 * and assets/css/styles.css (source of truth - DO NOT MODIFY)
 *
 * @package Mystic_Aura_Reading
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$auth_handler = class_exists( 'SM_Auth_Handler' ) ? SM_Auth_Handler::get_instance() : null;
$is_logged_in = $auth_handler ? $auth_handler->is_user_logged_in() : false;
$reading_type = isset( $_GET['reading_type'] ) ? sanitize_text_field( wp_unslash( $_GET['reading_type'] ) ) : '';
$is_report    = isset( $_GET['sm_report'] );

if ( $is_report && 'aura_full' === $reading_type && ! $is_logged_in && $auth_handler ) {
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
	$return_url  = home_url( $request_uri );
	$login_url   = $auth_handler->get_login_url( $return_url );

	if ( ! empty( $login_url ) ) {
		wp_safe_redirect( $login_url );
		exit;
	}
}
?>

<div id="sm-aura-reading-app" class="sm-container" data-app-version="<?php echo esc_attr( SM_AURA_VERSION ); ?>">
    <div class="background-animation">
        <div class="aura-circle shape-1"></div>
        <div class="aura-circle shape-2"></div>
        <div class="aura-circle shape-3"></div>
    </div>

	<div class="app-container">
		<div class="progress-container">
			<div class="progress-bar">
				<div class="progress-fill" style="width: 0;"></div>
			</div>
				<div class="progress-text">
					<span class="current-step">1</span>
				<span class="step-name"><?php esc_html_e( 'Welcome', 'mystic-aura-reading' ); ?></span>
					<span class="total-steps">0</span>
				</div>
			</div>

		<div id="app-content" class="app-content" aria-live="polite" aria-label="<?php esc_attr_e( 'Mystic Aura Reading steps', 'mystic-aura-reading' ); ?>"></div>

		<div class="navigation">
			<button id="back-btn" class="btn btn-secondary" type="button" aria-label="<?php esc_attr_e( 'Go to previous step', 'mystic-aura-reading' ); ?>">
				<i class="fas fa-chevron-left"></i>
				<?php esc_html_e( 'Back', 'mystic-aura-reading' ); ?>
			</button>
			<button id="next-btn" class="btn btn-primary" type="button" aria-label="<?php esc_attr_e( 'Go to next step', 'mystic-aura-reading' ); ?>">
				<?php esc_html_e( 'Continue', 'mystic-aura-reading' ); ?>
				<i class="fas fa-chevron-right"></i>
			</button>
		</div>

		<div id="toast" class="toast" role="status" aria-live="polite"></div>
	</div>
</div>
