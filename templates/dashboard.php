<?php
/**
 * Template for the logged-in user dashboard.
 *
 * @package Mystic_Aura_Reading
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$auth_handler = SM_Auth_Handler::get_instance();
$user_data = $auth_handler->get_current_user();
$display_name = ! empty( $user_data['name'] ) ? $user_data['name'] : '';
$display_email = ! empty( $user_data['email'] ) ? $user_data['email'] : '';

if ( '' === $display_name ) {
	$profile = $auth_handler->get_account_profile();
	if ( ! empty( $profile['name'] ) ) {
		$display_name = $profile['name'];
	} elseif ( ! empty( $profile['given_name'] ) || ! empty( $profile['first_name'] ) ) {
		$display_name = ! empty( $profile['given_name'] ) ? $profile['given_name'] : $profile['first_name'];
	} elseif ( ! empty( $profile['full_name'] ) ) {
		$display_name = $profile['full_name'];
} elseif ( ! empty( $user_data['email'] ) ) {
	$display_name = $user_data['email'];
}
}

if ( '' === $display_name ) {
	$display_name = 'Mystic Seeker';
}
$credit_balance = SM_Credit_Handler::get_instance()->get_credit_balance();
$settings       = SM_Settings::init();
$profile_url    = $settings->get_profile_url();
$credits_url    = $settings->get_dashboard_credits_url();
$reports_url    = add_query_arg( 'sm_reports', '1', get_permalink() );
$total_credits  = (int) $credit_balance['service'] + (int) $credit_balance['universal'];

?>

<div class="dashboard-bg-animation">
    <div class="aura-circle shape-1"></div>
    <div class="aura-circle shape-2"></div>
</div>

<div class="dashboard-container">
    <div class="dashboard-header">
        <div class="user-greeting">
            <h1><i class="fas fa-sparkles"></i> Welcome, <span class="user-name"><?php echo esc_html( $display_name ); ?></span></h1>
            <?php if ( '' !== $display_email ) : ?>
                <p class="user-email"><?php echo esc_html( $display_email ); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="credit-summary">
        <div class="credit-card mystic-card">
            <div class="credit-header">
                <h3><i class="fas fa-coins"></i> Your Aura Balance</h3>
                <div class="credit-total">
                    <span class="total-label">Total Available</span>
                    <span class="total-amount"><?php echo esc_html( $total_credits ); ?></span>
                </div>
            </div>

            <div class="credit-breakdown">
                <div class="credit-type">
                    <div class="credit-icon palm-icon">
                        <i class="fas fa-sun"></i>
                    </div>
                    <div class="credit-details">
                        <span class="credit-name">Aura Readings</span>
                        <span class="credit-value"><?php echo esc_html( $credit_balance['service'] ); ?></span>
                    </div>
                </div>

                <div class="credit-type">
                    <div class="credit-icon universal-icon">
                        <i class="fas fa-globe"></i>
                    </div>
                    <div class="credit-details">
                        <span class="credit-name">Universal Credits</span>
                        <span class="credit-value"><?php echo esc_html( $credit_balance['universal'] ); ?></span>
                    </div>
                </div>
            </div>

            <a class="btn-buy-credits" href="<?php echo esc_url( $credits_url ); ?>">
                <i class="fas fa-plus-circle"></i> Get More Credits
            </a>
        </div>
    </div>

    <div class="quick-actions">
        <h2><i class="fas fa-bolt"></i> Quick Actions</h2>

        <div class="action-grid">
            <div class="action-card new-reading-card">
                <div class="action-icon">
                    <i class="fas fa-sun"></i>
                </div>
                <h3>New Reading</h3>
                <p>Unlock new energetic insights with a fresh aura reading.</p>
                <div class="action-cost">
                    <span class="cost-icon"><i class="fas fa-sun"></i></span>
                    <span class="cost-text">1 Credit</span>
                </div>
                <button id="generate-new-reading-btn" class="action-btn">
                    Begin Journey
                </button>
            </div>

            <div class="action-card history-card">
                <div class="action-icon">
                    <i class="fas fa-scroll"></i>
                </div>
                <h3>Past Readings</h3>
                <p>Revisit your previous insights and spiritual discoveries.</p>
                <a id="view-my-readings-btn" class="action-btn" href="<?php echo esc_url( $reports_url ); ?>">
                    View My Readings
                </a>
            </div>

            <div class="action-card profile-card">
                <div class="action-icon">
                    <i class="fas fa-user-astronaut"></i>
                </div>
                <h3>Profile</h3>
                <p>Manage your account settings and preferences.</p>
                <a class="action-btn" href="<?php echo esc_url( $profile_url ); ?>">
                    Edit Profile
                </a>
            </div>

            <div class="action-card share-card">
                <div class="action-icon">
                    <i class="fas fa-share-alt"></i>
                </div>
                <h3>Share</h3>
                <p>Invite friends to discover their aura story.</p>
                <button class="action-btn" type="button">
                    Share App
                </button>
            </div>
        </div>
    </div>

    <div class="dashboard-footer">
        <button id="logout-btn" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Log Out
        </button>

        <div class="app-info">
            <span class="app-version"><?php echo esc_html( defined( 'SM_AURA_VERSION' ) ? SM_AURA_VERSION : 'v1.0.0' ); ?></span>
            <span class="app-status"><i class="fas fa-circle"></i> Connected</span>
        </div>
    </div>
</div>

<!-- Hidden elements for script.js compatibility -->
<div style="display: none;">
    <div class="progress-container">
        <div class="progress-bar">
            <div class="progress-fill"></div>
        </div>
        <div class="progress-text">
            <span class="current-step"></span>
            <span class="step-name"></span>
            <span class="total-steps"></span>
        </div>
    </div>
    <div class="navigation">
        <button id="back-btn"></button>
        <button id="next-btn"></button>
    </div>
    <div id="toast"></div>
</div>
