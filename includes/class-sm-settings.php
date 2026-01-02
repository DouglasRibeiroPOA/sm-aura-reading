<?php
/**
 * Admin settings management for SoulMirror.
 *
 * @package MysticAuraReadingAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles Settings API registration, admin UI, and secure storage helpers.
 */
class SM_Settings {

	/**
	 * Option name for persisted settings.
	 */
	const OPTION_KEY = 'sm_aura_settings';

	/**
	 * Nonce action for log clearing.
	 */
	const CLEAR_LOG_ACTION = 'sm_clear_log';

	/**
	 * Encryption cipher.
	 */
	const CIPHER = 'aes-256-cbc';

	/**
	 * Singleton instance.
	 *
	 * @var SM_Settings|null
	 */
	private static $instance = null;

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_' . self::CLEAR_LOG_ACTION, array( $this, 'handle_clear_log' ) );
	}

	/**
	 * Register the admin menu entry.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Aura Reading Settings', 'mystic-aura-reading' ),
			__( 'Aura Reading', 'mystic-aura-reading' ),
			'manage_options',
			'sm-settings',
			array( $this, 'render_settings_page' ),
			'dashicons-visibility',
			65
		);
	}

	/**
	 * Register settings, sections, and fields.
	 */
	public function register_settings() {
		register_setting(
			'sm_aura_settings_group',
			self::OPTION_KEY,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
		register_setting(
			'sm_settings_group',
			self::OPTION_KEY,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		register_setting(
			'sm_aura_settings_group',
			'sm_report_template',
			array(
				'type'              => 'string',
				'default'           => 'traditional',
				'sanitize_callback' => array( $this, 'sanitize_report_template' ),
			)
		);

		add_settings_section(
			'sm_api_section',
			__( 'API Credentials', 'mystic-aura-reading' ),
			'__return_false',
			'sm-settings'
		);

		add_settings_field(
			'openai_api_key',
			__( 'OpenAI API Key', 'mystic-aura-reading' ),
			array( $this, 'render_openai_field' ),
			'sm-settings',
			'sm_api_section'
		);

		add_settings_field(
			'mailerlite_api_key',
			__( 'MailerLite API Key', 'mystic-aura-reading' ),
			array( $this, 'render_mailerlite_field' ),
			'sm-settings',
			'sm_api_section'
		);

		add_settings_field(
			'mailerlite_group_id',
			__( 'MailerLite Group ID', 'mystic-aura-reading' ),
			array( $this, 'render_mailerlite_group_field' ),
			'sm-settings',
			'sm_api_section'
		);

		add_settings_section(
			'sm_account_service_section',
			__( 'Account Service Integration', 'mystic-aura-reading' ),
			array( $this, 'render_account_service_section_description' ),
			'sm-settings'
		);

		add_settings_field(
			'enable_account_integration',
			__( 'Enable Account Integration', 'mystic-aura-reading' ),
			array( $this, 'render_enable_account_integration_field' ),
			'sm-settings',
			'sm_account_service_section'
		);

		add_settings_field(
			'account_service_url',
			__( 'Account Service URL', 'mystic-aura-reading' ),
			array( $this, 'render_account_service_url_field' ),
			'sm-settings',
			'sm_account_service_section'
		);

		add_settings_field(
			'service_slug',
			__( 'Service Slug', 'mystic-aura-reading' ),
			array( $this, 'render_service_slug_field' ),
			'sm-settings',
			'sm_account_service_section'
		);

		add_settings_field(
			'auth_callback_url',
			__( 'Auth Callback URL', 'mystic-aura-reading' ),
			array( $this, 'render_auth_callback_url_field' ),
			'sm-settings',
			'sm_account_service_section'
		);

		add_settings_field(
			'login_button_text',
			__( 'Login Button Text', 'mystic-aura-reading' ),
			array( $this, 'render_login_button_text_field' ),
			'sm-settings',
			'sm_account_service_section'
		);

		add_settings_field(
			'show_login_button',
			__( 'Show Login Button', 'mystic-aura-reading' ),
			array( $this, 'render_show_login_button_field' ),
			'sm-settings',
			'sm_account_service_section'
		);

		add_settings_section(
			'sm_template_section',
			__( 'Report Template Settings', 'mystic-aura-reading' ),
			'__return_false',
			'sm-settings'
		);

		add_settings_field(
			'sm_report_template',
			__( 'Report Template', 'mystic-aura-reading' ),
			array( $this, 'render_report_template_field' ),
			'sm-settings',
			'sm_template_section'
		);

		add_settings_section(
			'sm_otp_section',
			__( 'OTP Configuration', 'mystic-aura-reading' ),
			'__return_false',
			'sm-settings'
		);

		add_settings_field(
			'otp_expiration_minutes',
			__( 'OTP Expiration (minutes)', 'mystic-aura-reading' ),
			array( $this, 'render_otp_expiration_field' ),
			'sm-settings',
			'sm_otp_section'
		);

		add_settings_field(
			'otp_max_attempts',
			__( 'Max OTP Attempts', 'mystic-aura-reading' ),
			array( $this, 'render_otp_attempts_field' ),
			'sm-settings',
			'sm_otp_section'
		);

		add_settings_field(
			'otp_resend_cooldown_minutes',
			__( 'OTP Resend Cooldown (minutes)', 'mystic-aura-reading' ),
			array( $this, 'render_otp_resend_field' ),
			'sm-settings',
			'sm_otp_section'
		);

		add_settings_section(
			'sm_logging_section',
			__( 'Logging', 'mystic-aura-reading' ),
			'__return_false',
			'sm-settings'
		);

		add_settings_field(
			'debug_logging',
			__( 'Enable Debug Logging', 'mystic-aura-reading' ),
			array( $this, 'render_debug_logging_field' ),
			'sm-settings',
			'sm_logging_section'
		);

		add_settings_section(
			'sm_devmode_section',
			__( 'Development Mode', 'mystic-aura-reading' ),
			array( $this, 'render_devmode_section_description' ),
			'sm-settings'
		);

		add_settings_field(
			'dev_mode_enabled',
			__( 'DevMode Mode', 'mystic-aura-reading' ),
			array( $this, 'render_devmode_field' ),
			'sm-settings',
			'sm_devmode_section'
		);

		add_settings_section(
			'sm_redirects_section',
			__( 'Redirects', 'mystic-aura-reading' ),
			'__return_false',
			'sm-settings'
		);

		add_settings_field(
			'no_credit_redirect_url',
			__( 'No-credit Redirect URL', 'mystic-aura-reading' ),
			array( $this, 'render_no_credit_redirect_field' ),
			'sm-settings',
			'sm_redirects_section'
		);

		add_settings_field(
			'offerings_url',
			__( 'Offerings Page URL', 'mystic-aura-reading' ),
			array( $this, 'render_offerings_url_field' ),
			'sm-settings',
			'sm_redirects_section'
		);

		add_settings_field(
			'profile_url',
			__( 'Profile Page URL', 'mystic-aura-reading' ),
			array( $this, 'render_profile_url_field' ),
			'sm-settings',
			'sm_redirects_section'
		);

		add_settings_field(
			'dashboard_credits_url',
			__( 'Dashboard Credits URL', 'mystic-aura-reading' ),
			array( $this, 'render_dashboard_credits_url_field' ),
			'sm-settings',
			'sm_redirects_section'
		);
	}

	/**
	 * Sanitize report template selection.
	 *
	 * @param string $value Raw input.
	 * @return string Sanitized template slug.
	 */
	public function sanitize_report_template( $value ) {
		$value = sanitize_text_field( $value );
		$allowed = array( 'traditional', 'swipeable-cards' );

		return in_array( $value, $allowed, true ) ? $value : 'traditional';
	}

	/**
	 * Sanitize and validate settings input.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		if ( ! is_array( $input ) ) {
			return $this->get_settings();
		}

		$existing = $this->get_settings();
		$output   = $existing;
		$debug_on = ! empty( $existing['debug_logging'] );

		// OpenAI key.
		if ( ! empty( $input['openai_api_key'] ) ) {
			$key              = $this->normalize_api_key( $input['openai_api_key'] );
			$encrypted        = $this->encrypt_value( $key );
			if ( '' !== $encrypted ) {
				$output['openai_api_key'] = $encrypted;
				if ( $debug_on && class_exists( 'SM_Logger' ) ) {
					SM_Logger::log(
						'info',
						'SETTINGS',
						'OpenAI API key updated',
						$this->build_key_meta( $key )
					);
				}
			}
		}

		// MailerLite key.
		if ( ! empty( $input['mailerlite_api_key'] ) ) {
			$key                   = $this->normalize_api_key( $input['mailerlite_api_key'] );
			$encrypted             = $this->encrypt_value( $key );
			if ( '' !== $encrypted ) {
				$output['mailerlite_api_key'] = $encrypted;
				if ( $debug_on && class_exists( 'SM_Logger' ) ) {
					SM_Logger::log(
						'info',
						'SETTINGS',
						'MailerLite API key updated',
						$this->build_key_meta( $key )
					);
				}
			}
		}

		// MailerLite Group ID.
		if ( isset( $input['mailerlite_group_id'] ) ) {
			$output['mailerlite_group_id'] = trim( sanitize_text_field( $input['mailerlite_group_id'] ) );
		}

		// Account Service URL.
		if ( isset( $input['account_service_url'] ) ) {
			$url = esc_url_raw( trim( $input['account_service_url'] ) );
			if ( ! empty( $url ) && ( 0 === strpos( $url, 'http://' ) || 0 === strpos( $url, 'https://' ) ) ) {
				$output['account_service_url'] = $url;
			} else {
				$output['account_service_url'] = $existing['account_service_url'];
				if ( ! empty( $input['account_service_url'] ) ) {
					add_settings_error(
						'sm_settings',
						'invalid_account_service_url',
						__( 'Account Service URL must be a full URL starting with http:// or https://. Changes were not saved.', 'mystic-aura-reading' ),
						'error'
					);
				}
			}
		}

		// Service Slug.
		if ( isset( $input['service_slug'] ) ) {
			$slug = sanitize_key( $input['service_slug'] );
			if ( preg_match( '/^[a-z0-9-]+$/', $slug ) ) {
				$output['service_slug'] = $slug;
			} else {
				$output['service_slug'] = $existing['service_slug'];
				if ( ! empty( $input['service_slug'] ) ) {
					add_settings_error(
						'sm_settings',
						'invalid_service_slug',
						__( 'Service Slug must be alphanumeric with hyphens only. Changes were not saved.', 'mystic-aura-reading' ),
						'error'
					);
				}
			}
		}

		// Login Button Text.
		if ( isset( $input['login_button_text'] ) ) {
			$output['login_button_text'] = sanitize_text_field( $input['login_button_text'] );
		}

		// Show Login Button.
		if ( isset( $input['show_login_button'] ) && is_array( $input['show_login_button'] ) ) {
			foreach ( $input['show_login_button'] as $key => $value ) {
				$output['show_login_button'][ $key ] = (int) $value;
			}
		} else {
			$output['show_login_button'] = array(
				'dashboard' => 0,
				'teaser_page' => 0,
				'quiz_steps' => 0,
			);
		}

		// OTP expiration.
		if ( isset( $input['otp_expiration_minutes'] ) ) {
			$minutes = max( 1, absint( $input['otp_expiration_minutes'] ) );
			$output['otp_expiration_minutes'] = $minutes;
		}

		// OTP attempts.
		if ( isset( $input['otp_max_attempts'] ) ) {
			$attempts = max( 1, absint( $input['otp_max_attempts'] ) );
			$output['otp_max_attempts'] = $attempts;
		}

		// OTP resend cooldown.
		if ( isset( $input['otp_resend_cooldown_minutes'] ) ) {
			$cooldown = max( 1, absint( $input['otp_resend_cooldown_minutes'] ) );
			$output['otp_resend_cooldown_minutes'] = $cooldown;
		}

		// Redirect URL for exhausted credits.
		if ( isset( $input['no_credit_redirect_url'] ) ) {
			$url = esc_url_raw( trim( $input['no_credit_redirect_url'] ) );
			$output['no_credit_redirect_url'] = $url ?: $existing['no_credit_redirect_url'];
		}

		// Offerings URL (must be full URL for cross-site redirects).
		if ( isset( $input['offerings_url'] ) ) {
			$url = esc_url_raw( trim( $input['offerings_url'] ) );

			// Validate that it's a full URL (starts with http:// or https://)
			if ( ! empty( $url ) && ( 0 === strpos( $url, 'http://' ) || 0 === strpos( $url, 'https://' ) ) ) {
				$output['offerings_url'] = $url;
			} else {
				// Invalid URL - keep existing or use default
				$output['offerings_url'] = $existing['offerings_url'];

				// Show error if user tried to save an invalid URL
				if ( ! empty( $input['offerings_url'] ) ) {
					add_settings_error(
						'sm_settings',
						'invalid_offerings_url',
						__( 'Offerings URL must be a full URL starting with http:// or https://. Changes were not saved.', 'mystic-aura-reading' ),
						'error'
					);
				}
			}
		}

		// Profile URL (must be full URL).
		if ( isset( $input['profile_url'] ) ) {
			$url = esc_url_raw( trim( $input['profile_url'] ) );
			if ( ! empty( $url ) && ( 0 === strpos( $url, 'http://' ) || 0 === strpos( $url, 'https://' ) ) ) {
				$output['profile_url'] = $url;
			} else {
				$output['profile_url'] = $existing['profile_url'];
				if ( ! empty( $input['profile_url'] ) ) {
					add_settings_error(
						'sm_settings',
						'invalid_profile_url',
						__( 'Profile URL must be a full URL starting with http:// or https://. Changes were not saved.', 'mystic-aura-reading' ),
						'error'
					);
				}
			}
		}

		// Dashboard credits URL (must be full URL).
		if ( isset( $input['dashboard_credits_url'] ) ) {
			$url = esc_url_raw( trim( $input['dashboard_credits_url'] ) );
			if ( ! empty( $url ) && ( 0 === strpos( $url, 'http://' ) || 0 === strpos( $url, 'https://' ) ) ) {
				$output['dashboard_credits_url'] = $url;
			} else {
				$output['dashboard_credits_url'] = $existing['dashboard_credits_url'];
				if ( ! empty( $input['dashboard_credits_url'] ) ) {
					add_settings_error(
						'sm_settings',
						'invalid_dashboard_credits_url',
						__( 'Dashboard Credits URL must be a full URL starting with http:// or https://. Changes were not saved.', 'mystic-aura-reading' ),
						'error'
					);
				}
			}
		}

		// Debug logging toggle.
		$output['debug_logging'] = isset( $input['debug_logging'] ) ? 1 : 0;

		// Enable Account Integration toggle.
		$output['enable_account_integration'] = isset( $input['enable_account_integration'] ) ? 1 : 0;

		// Handle DevMode mode selection (stored in separate option).
		if ( isset( $input['sm_devmode_mode'] ) ) {
			$mode = sanitize_key( $input['sm_devmode_mode'] );
			if ( SM_Dev_Mode::set_mode( $mode ) ) {
				add_settings_error(
					'sm_settings',
					'devmode_mode',
					sprintf( __( 'DevMode set to: %s', 'mystic-aura-reading' ), $mode ),
					'success'
				);
			} else {
				add_settings_error(
					'sm_settings',
					'devmode_mode_invalid',
					__( 'Invalid DevMode selection. No changes were saved.', 'mystic-aura-reading' ),
					'error'
				);
			}
		}

		return $output;
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings        = $this->get_settings();
		$db_status       = $this->get_database_status();
		$log_excerpt     = $this->get_log_excerpt( 100 );
		$log_path        = class_exists( 'SM_Logger' ) ? SM_Logger::get_log_file_path() : SM_AURA_PLUGIN_DIR . 'debug.log';
		$invalid_stats   = $this->get_invalid_image_stats();
		$devmode_enabled = SM_Dev_Mode::is_enabled();
		$devmode_mode    = SM_Dev_Mode::get_mode();
		$mock_services   = array();
		if ( SM_Dev_Mode::should_mock_openai() ) {
			$mock_services[] = 'OpenAI';
		}
		if ( SM_Dev_Mode::should_mock_mailerlite() ) {
			$mock_services[] = 'MailerLite';
		}
		$mock_services_text = $mock_services ? implode( ', ', $mock_services ) : __( 'None', 'mystic-aura-reading' );
		$clear_log_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::CLEAR_LOG_ACTION ),
			self::CLEAR_LOG_ACTION
		);
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Aura Reading Settings', 'mystic-aura-reading' ); ?></h1>

			<?php if ( $devmode_enabled ) : ?>
				<div class="notice notice-warning" style="margin-top: 16px; padding: 12px; border-left-width: 4px;">
					<p style="margin: 0.5em 0;">
						<strong style="color: #d63638;">⚠️ <?php echo esc_html__( 'DEVELOPMENT MODE ACTIVE', 'mystic-aura-reading' ); ?></strong><br>
						<?php
						echo esc_html(
							sprintf(
								__( 'Mode: %s. Mocking: %s.', 'mystic-aura-reading' ),
								$devmode_mode,
								$mock_services_text
							)
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<h2><?php echo esc_html__( 'System Status', 'mystic-aura-reading' ); ?></h2>
			<table class="widefat striped" style="max-width: 700px;">
				<tbody>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Plugin Version', 'mystic-aura-reading' ); ?></th>
						<td><?php echo esc_html( defined( 'SM_AURA_VERSION' ) ? SM_AURA_VERSION : 'n/a' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Database Version', 'mystic-aura-reading' ); ?></th>
						<td><?php echo esc_html( $settings['db_version'] ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Database Tables', 'mystic-aura-reading' ); ?></th>
						<td>
							<?php
							if ( empty( $db_status['missing'] ) ) {
								echo esc_html__( 'All tables present', 'mystic-aura-reading' );
							} else {
								echo esc_html__( 'Missing tables:', 'mystic-aura-reading' ) . ' ' . esc_html( implode( ', ', $db_status['missing'] ) );
							}
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Development Mode', 'mystic-aura-reading' ); ?></th>
						<td>
							<?php if ( $devmode_enabled ) : ?>
								<span style="color: #d63638; font-weight: 600;">
									<?php echo esc_html( sprintf( __( '⚠️ ENABLED (Mocking: %s)', 'mystic-aura-reading' ), $mock_services_text ) ); ?>
								</span>
							<?php else : ?>
								<span style="color: #00a32a;">
									<?php echo esc_html__( '✓ Disabled (Real APIs)', 'mystic-aura-reading' ); ?>
								</span>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>

			<h2 style="margin-top:32px;"><?php echo esc_html__( 'Palm Image Quality Stats', 'mystic-aura-reading' ); ?></h2>
			<table class="widefat striped" style="max-width: 700px;">
				<tbody>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Invalid image events', 'mystic-aura-reading' ); ?></th>
						<td><?php echo esc_html( (string) $invalid_stats['total'] ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Lockouts after retries', 'mystic-aura-reading' ); ?></th>
						<td><?php echo esc_html( (string) $invalid_stats['locked'] ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Top reasons', 'mystic-aura-reading' ); ?></th>
						<td><?php echo esc_html( $invalid_stats['reasons_summary'] ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Last seen', 'mystic-aura-reading' ); ?></th>
						<td><?php echo esc_html( $invalid_stats['last_seen'] ); ?></td>
					</tr>
				</tbody>
			</table>

			<form action="options.php" method="post" style="margin-top: 24px; max-width: 700px;">
				<?php
				settings_fields( 'sm_aura_settings_group' );
				do_settings_sections( 'sm-settings' );
				submit_button();
				?>
			</form>

			<h2 style="margin-top:32px;"><?php echo esc_html__( 'Debug Log (last 100 lines)', 'mystic-aura-reading' ); ?></h2>
			<p><?php echo esc_html( sprintf( __( 'Logs are stored at: %s', 'mystic-aura-reading' ), $log_path ) ); ?></p>
			<pre style="background:#fff;border:1px solid #ccd0d4;padding:12px;max-height:240px;overflow:auto;"><?php echo esc_html( $log_excerpt ); ?></pre>
			<p>
				<a class="button" href="<?php echo esc_url( $clear_log_url ); ?>"><?php echo esc_html__( 'Clear Log', 'mystic-aura-reading' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Render OpenAI API key field.
	 */
	public function render_openai_field() {
		$current_key = $this->get_openai_api_key();
		?>
		<input type="password" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[openai_api_key]" value="" class="regular-text" autocomplete="off" placeholder="<?php echo esc_attr__( 'Enter new key or leave blank to keep current', 'mystic-aura-reading' ); ?>" />
		<p class="description"><?php echo esc_html__( 'Stored encrypted in the database. Leave blank to retain the existing key.', 'mystic-aura-reading' ); ?></p>
		<p class="description"><?php echo esc_html( $this->format_key_status( $current_key ) ); ?></p>
		<?php
	}

	/**
	 * Render MailerLite API key field.
	 */
	public function render_mailerlite_field() {
		$current_key = $this->get_mailerlite_api_key();
		?>
		<input type="password" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[mailerlite_api_key]" value="" class="regular-text" autocomplete="off" placeholder="<?php echo esc_attr__( 'Enter new key or leave blank to keep current', 'mystic-aura-reading' ); ?>" />
		<p class="description"><?php echo esc_html__( 'Stored encrypted in the database. Leave blank to retain the existing key.', 'mystic-aura-reading' ); ?></p>
		<p class="description"><?php echo esc_html( $this->format_key_status( $current_key ) ); ?></p>
		<?php
	}

	/**
	 * Render MailerLite group field.
	 */
	public function render_mailerlite_group_field() {
		$settings = $this->get_settings();
		?>
		<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[mailerlite_group_id]" value="<?php echo esc_attr( $settings['mailerlite_group_id'] ); ?>" class="regular-text" />
		<p class="description"><?php echo esc_html__( 'MailerLite group ID for subscriber placement.', 'mystic-aura-reading' ); ?></p>
		<?php
	}

	/**
	 * Render Account Service section description.
	 */
	public function render_account_service_section_description() {
		echo '<p>' . esc_html__( 'Configure the integration with the SoulMirror Account Service for SSO, credits, and paid readings.', 'mystic-aura-reading' ) . '</p>';
	}

	/**
	 * Render Enable Account Integration checkbox field.
	 */
	public function render_enable_account_integration_field() {
		$settings = $this->get_settings();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_account_integration]" value="1" <?php checked( 1, (int) $settings['enable_account_integration'] ); ?> />
			<?php echo esc_html__( 'Turn account service integration on/off', 'mystic-aura-reading' ); ?>
		</label>
		<?php
	}

	/**
	 * Render Account Service URL field.
	 */
	public function render_account_service_url_field() {
		$settings = $this->get_settings();
		?>
		<input type="url" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[account_service_url]" value="<?php echo esc_attr( $settings['account_service_url'] ); ?>" class="regular-text" placeholder="https://account.soulmirror.com" />
		<p class="description"><?php echo esc_html__( 'Base URL of the Account Service. Must be a valid http:// or https:// URL.', 'mystic-aura-reading' ); ?></p>
		<?php
	}

	/**
	 * Render Service Slug field.
	 */
	public function render_service_slug_field() {
		$settings = $this->get_settings();
		?>
		<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[service_slug]" value="<?php echo esc_attr( $settings['service_slug'] ); ?>" class="regular-text" placeholder="aura_reading" />
		<p class="description"><?php echo esc_html__( 'Unique identifier for this service (provided by Account Service admin).', 'mystic-aura-reading' ); ?></p>
		<?php
	}

	/**
	 * Render Auth Callback URL field.
	 */
	public function render_auth_callback_url_field() {
		$callback_url = home_url( '/aura-reading/auth/callback' );
		?>
		<input type="text" value="<?php echo esc_attr( $callback_url ); ?>" class="regular-text" readonly />
		<p class="description"><?php echo esc_html__( 'Where users return after authentication (auto-generated).', 'mystic-aura-reading' ); ?></p>
		<?php
	}

	/**
	 * Render Login Button Text field.
	 */
	public function render_login_button_text_field() {
		$settings = $this->get_settings();
		?>
		<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[login_button_text]" value="<?php echo esc_attr( $settings['login_button_text'] ); ?>" class="regular-text" />
		<p class="description"><?php echo esc_html__( 'Text displayed on the login button.', 'mystic-aura-reading' ); ?></p>
		<?php
	}

	/**
	 * Render Show Login Button field.
	 */
	public function render_show_login_button_field() {
		$settings = $this->get_settings();
		$locations = array(
			'dashboard' => __( 'Dashboard', 'mystic-aura-reading' ),
			'teaser_page' => __( 'Teaser Page', 'mystic-aura-reading' ),
			'quiz_steps' => __( 'Quiz Steps', 'mystic-aura-reading' ),
		);
		foreach ( $locations as $key => $label ) {
			?>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[show_login_button][<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( 1, (int) $settings['show_login_button'][ $key ] ); ?> />
				<?php echo esc_html( $label ); ?>
			</label>
			<br>
			<?php
		}
	}

	/**
	 * Render report template selection field.
	 */
	public function render_report_template_field() {
		$current = get_option( 'sm_report_template', 'traditional' );
		?>
		<label>
			<input type="radio" name="sm_report_template" value="traditional" <?php checked( $current, 'traditional' ); ?> />
			<?php echo esc_html__( 'Traditional Scrolling Layout', 'mystic-aura-reading' ); ?>
		</label>
		<p class="description">
			<?php echo esc_html__( 'Single-page vertical scroll with all sections visible.', 'mystic-aura-reading' ); ?>
		</p>
		<label>
			<input type="radio" name="sm_report_template" value="swipeable-cards" <?php checked( $current, 'swipeable-cards' ); ?> />
			<?php echo esc_html__( 'Swipeable Card Interface', 'mystic-aura-reading' ); ?>
		</label>
		<p class="description">
			<?php echo esc_html__( 'Card-based swipe navigation (one section per card).', 'mystic-aura-reading' ); ?>
		</p>
		<?php
	}

	/**
	 * Render OTP expiration field.
	 */
	public function render_otp_expiration_field() {
		$settings = $this->get_settings();
		?>
		<input type="number" min="1" step="1" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[otp_expiration_minutes]" value="<?php echo esc_attr( $settings['otp_expiration_minutes'] ); ?>" class="small-text" />
		<p class="description"><?php echo esc_html__( 'Default: 10 minutes.', 'mystic-aura-reading' ); ?></p>
		<?php
	}

	/**
	 * Render OTP attempts field.
	 */
	public function render_otp_attempts_field() {
		$settings = $this->get_settings();
		?>
		<input type="number" min="1" step="1" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[otp_max_attempts]" value="<?php echo esc_attr( $settings['otp_max_attempts'] ); ?>" class="small-text" />
		<p class="description"><?php echo esc_html__( 'Default: 3 attempts.', 'mystic-aura-reading' ); ?></p>
		<?php
	}

	/**
	 * Render OTP resend cooldown field.
	 */
	public function render_otp_resend_field() {
		$settings = $this->get_settings();
		?>
		<input type="number" min="1" step="1" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[otp_resend_cooldown_minutes]" value="<?php echo esc_attr( $settings['otp_resend_cooldown_minutes'] ); ?>" class="small-text" />
		<p class="description"><?php echo esc_html__( 'Default: 10 minutes.', 'mystic-aura-reading' ); ?></p>
		<?php
	}

	/**
	 * Render debug logging toggle.
	 */
	public function render_debug_logging_field() {
		$settings = $this->get_settings();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[debug_logging]" value="1" <?php checked( 1, (int) $settings['debug_logging'] ); ?> />
			<?php echo esc_html__( 'Enable verbose logging for troubleshooting.', 'mystic-aura-reading' ); ?>
		</label>
		<?php
	}

	/**
	 * Render DevMode section description.
	 */
	public function render_devmode_section_description() {
		$mode = SM_Dev_Mode::get_mode();
		$is_enabled = ( 'production' !== $mode );
		$status_class = $is_enabled ? 'notice-warning' : 'notice-info';
		$status_text = $is_enabled
			? sprintf( __( 'ENABLED (%s)', 'mystic-aura-reading' ), $mode )
			: __( 'DISABLED - Using real API endpoints', 'mystic-aura-reading' );
		?>
		<div class="notice <?php echo esc_attr( $status_class ); ?> inline" style="margin: 10px 0; padding: 8px 12px;">
			<p style="margin: 0.5em 0;">
				<strong><?php echo esc_html__( 'Current Status:', 'mystic-aura-reading' ); ?></strong> <?php echo esc_html( $status_text ); ?>
			</p>
		</div>
		<p>
			<?php echo esc_html__( 'DevMode can mock OpenAI and/or MailerLite independently to support testing without spending credits or creating subscribers.', 'mystic-aura-reading' ); ?>
		</p>
		<p>
			<strong style="color: #d63638;"><?php echo esc_html__( 'WARNING:', 'mystic-aura-reading' ); ?></strong>
			<?php echo esc_html__( 'Never enable DevMode in production. This feature is for development environments only.', 'mystic-aura-reading' ); ?>
		</p>
		<?php
	}

	/**
	 * Render DevMode toggle field.
	 */
	public function render_devmode_field() {
		$mode = SM_Dev_Mode::get_mode();
		?>
		<fieldset>
			<label>
				<input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[sm_devmode_mode]" value="production" <?php checked( 'production', $mode ); ?> />
				<?php echo esc_html__( 'Production (real OpenAI + real MailerLite)', 'mystic-aura-reading' ); ?>
			</label><br>
			<label>
				<input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[sm_devmode_mode]" value="dev_all" <?php checked( 'dev_all', $mode ); ?> />
				<?php echo esc_html__( 'DevMode (mock OpenAI + mock MailerLite)', 'mystic-aura-reading' ); ?>
			</label><br>
			<label>
				<input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[sm_devmode_mode]" value="dev_mailerlite_only" <?php checked( 'dev_mailerlite_only', $mode ); ?> />
				<?php echo esc_html__( 'DevMode (mock MailerLite only)', 'mystic-aura-reading' ); ?>
			</label><br>
			<label>
				<input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[sm_devmode_mode]" value="dev_openai_only" <?php checked( 'dev_openai_only', $mode ); ?> />
				<?php echo esc_html__( 'DevMode (mock OpenAI only)', 'mystic-aura-reading' ); ?>
			</label>
		</fieldset>
		<p class="description">
			<strong><?php echo esc_html__( 'Mock URLs:', 'mystic-aura-reading' ); ?></strong><br>
			OpenAI: <code><?php echo esc_html( SM_Dev_Mode::get_mock_openai_url() ); ?></code><br>
			MailerLite: <code><?php echo esc_html( SM_Dev_Mode::get_mock_mailerlite_url() ); ?></code>
		</p>
		<?php
	}

	/**
	 * Render no-credit redirect URL field.
	 */
	public function render_no_credit_redirect_field() {
		$settings = $this->get_settings();
		?>
		<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[no_credit_redirect_url]" value="<?php echo esc_attr( $settings['no_credit_redirect_url'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr__( '/offerings?show_message=no_more_credits', 'mystic-aura-reading' ); ?>" />
		<p class="description"><?php echo esc_html__( 'Where to send users who have already used their free reading. Supports full or relative URLs.', 'mystic-aura-reading' ); ?></p>
		<?php
	}

	/**
	 * Render offerings URL field.
	 */
	public function render_offerings_url_field() {
		$settings = $this->get_settings();
		?>
		<input type="url" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[offerings_url]" value="<?php echo esc_attr( $settings['offerings_url'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr__( 'https://example.com/offerings', 'mystic-aura-reading' ); ?>" />
		<p class="description">
			<?php echo esc_html__( 'Full URL (including http:// or https://) where users are redirected after using their 2 free unlocks. This supports cross-domain redirects.', 'mystic-aura-reading' ); ?>
		</p>
		<p class="description" style="color: #d63638;">
			<strong><?php echo esc_html__( 'Important:', 'mystic-aura-reading' ); ?></strong>
			<?php echo esc_html__( 'Must be a complete URL starting with http:// or https://. Relative URLs (e.g., /offerings) are not supported.', 'mystic-aura-reading' ); ?>
		</p>
		<?php
	}

	/**
	 * Render profile URL field.
	 */
	public function render_profile_url_field() {
		$settings = $this->get_settings();
		?>
		<input type="url" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[profile_url]" value="<?php echo esc_attr( $settings['profile_url'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr__( 'https://example.com/account/profile', 'mystic-aura-reading' ); ?>" />
		<p class="description"><?php echo esc_html__( 'Full URL to the Account Profile page shown on the dashboard.', 'mystic-aura-reading' ); ?></p>
		<?php
	}

	/**
	 * Render dashboard credits URL field.
	 */
	public function render_dashboard_credits_url_field() {
		$settings = $this->get_settings();
		?>
		<input type="url" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[dashboard_credits_url]" value="<?php echo esc_attr( $settings['dashboard_credits_url'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr__( 'https://example.com/credits', 'mystic-aura-reading' ); ?>" />
		<p class="description"><?php echo esc_html__( 'Full URL used by the "Get More Credits" button on the dashboard.', 'mystic-aura-reading' ); ?></p>
		<?php
	}

	/**
	 * Handle log clearing action.
	 */
	public function handle_clear_log() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'mystic-aura-reading' ) );
		}

		check_admin_referer( self::CLEAR_LOG_ACTION );

		if ( class_exists( 'SM_Logger' ) ) {
			SM_Logger::clear();
		} else {
			$log_path = SM_AURA_PLUGIN_DIR . 'debug.log';
			if ( file_exists( $log_path ) && is_writable( $log_path ) ) {
				file_put_contents( $log_path, '' );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=sm-settings&cleared=1' ) );
		exit;
	}

	/**
	 * Get settings merged with defaults and derived values.
	 *
	 * @return array
	 */
	public function get_settings() {
		$defaults = array(
			'openai_api_key'              => '',
			'mailerlite_api_key'          => '',
			'mailerlite_group_id'         => '',
			'enable_account_integration'  => 1,
			'account_service_url'         => 'http://account.sm-aura-reading.local',
			'service_slug'                => 'aura_reading',
			'login_button_text'           => 'Login / Sign Up',
			'show_login_button'           => array(
				'dashboard' => 1,
				'teaser_page' => 1,
				'quiz_steps' => 0,
			),
			'otp_expiration_minutes'      => 10,
			'otp_max_attempts'            => 3,
			'otp_resend_cooldown_minutes' => 10,
			'debug_logging'               => 0,
			'db_version'                  => get_option( SM_Database::OPTION_KEY, SM_Database::DB_VERSION ),
			'no_credit_redirect_url'      => '/offerings?show_message=no_more_credits',
			'offerings_url'               => 'https://soulmirror.com/offerings',
			'profile_url'                 => '',
			'dashboard_credits_url'       => '',
		);

		$saved = get_option( self::OPTION_KEY, array() );

		return array_merge( $defaults, is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Decrypt and return the OpenAI API key.
	 *
	 * @return string
	 */
	public function get_openai_api_key() {
		$settings = $this->get_settings();
		return $this->decrypt_value( $settings['openai_api_key'] );
	}

	/**
	 * Decrypt and return the MailerLite API key.
	 *
	 * @return string
	 */
	public function get_mailerlite_api_key() {
		$settings = $this->get_settings();
		return $this->decrypt_value( $settings['mailerlite_api_key'] );
	}

	/**
	 * Return MailerLite group ID.
	 *
	 * @return string
	 */
	public function get_mailerlite_group_id() {
		$settings = $this->get_settings();
		return $settings['mailerlite_group_id'];
	}


	/**
	 * Get redirect URL for exhausted credits.
	 *
	 * @return string
	 */
	public function get_no_credit_redirect_url() {
		$settings = $this->get_settings();
		$url      = isset( $settings['no_credit_redirect_url'] ) ? $settings['no_credit_redirect_url'] : '';

		if ( empty( $url ) ) {
			return '/offerings?show_message=no_more_credits';
		}

		return esc_url_raw( $url );
	}

	/**
	 * Get offerings page URL (for unlock limit redirect).
	 *
	 * @return string Full URL with http:// or https://
	 */
	public function get_offerings_url() {
		$settings = $this->get_settings();
		$url      = isset( $settings['offerings_url'] ) ? $settings['offerings_url'] : '';

		// Validate it's a full URL
		if ( empty( $url ) || ( 0 !== strpos( $url, 'http://' ) && 0 !== strpos( $url, 'https://' ) ) ) {
			// Fall back to default if invalid
			return 'https://soulmirror.com/offerings';
		}

		return esc_url_raw( $url );
	}

	/**
	 * Get the full offerings URL with an optional return_url parameter.
	 *
	 * @param string $return_url The URL to return to after the offerings page.
	 * @return string Full offerings URL.
	 */
	public function get_offerings_redirect_url( $return_url = '' ) {
		$offerings_url = $this->get_offerings_url();

		if ( ! empty( $return_url ) ) {
			// Ensure the return_url is safe and encoded.
			$encoded_return_url = urlencode( esc_url_raw( $return_url ) );
			$offerings_url      = add_query_arg( 'return_url', $encoded_return_url, $offerings_url );
		}

		return $offerings_url;
	}

	/**
	 * Get profile page URL for dashboard.
	 *
	 * @return string
	 */
	public function get_profile_url() {
		$settings = $this->get_settings();
		$url      = isset( $settings['profile_url'] ) ? $settings['profile_url'] : '';

		if ( empty( $url ) && ! empty( $settings['account_service_url'] ) ) {
			$base = rtrim( $settings['account_service_url'], '/' );
			$url  = $base . '/account/profile';
		}

		if ( empty( $url ) ) {
			return '#';
		}

		return esc_url_raw( $url );
	}

	/**
	 * Get dashboard credits URL.
	 *
	 * @return string
	 */
	public function get_dashboard_credits_url() {
		$settings = $this->get_settings();
		$url      = isset( $settings['dashboard_credits_url'] ) ? $settings['dashboard_credits_url'] : '';

		if ( empty( $url ) ) {
			$url = $this->get_offerings_url();
		}

		if ( empty( $url ) ) {
			return '#';
		}

		return esc_url_raw( $url );
	}

	/**
	 * Get OTP expiration in minutes.
	 *
	 * @return int
	 */
	public function get_otp_expiration_minutes() {
		$settings = $this->get_settings();
		return (int) $settings['otp_expiration_minutes'];
	}

	/**
	 * Get max OTP attempts.
	 *
	 * @return int
	 */
	public function get_otp_max_attempts() {
		$settings = $this->get_settings();
		return (int) $settings['otp_max_attempts'];
	}

	/**
	 * Get OTP resend cooldown in minutes.
	 *
	 * @return int
	 */
	public function get_otp_resend_cooldown_minutes() {
		$settings = $this->get_settings();
		return (int) $settings['otp_resend_cooldown_minutes'];
	}

	/**
	 * Whether debug logging is enabled.
	 *
	 * @return bool
	 */
	public function is_debug_enabled() {
		$settings = $this->get_settings();
		return (bool) $settings['debug_logging'];
	}

	/**
	 * Encrypt a value using WP salts.
	 *
	 * @param string $value Plaintext.
	 * @return string Encrypted payload (base64) or empty on failure.
	 */
	private function encrypt_value( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return 'plain:' . $value;
		}

		$key = $this->get_encryption_key();
		if ( empty( $key ) ) {
			return 'plain:' . $value;
		}

		$iv        = openssl_random_pseudo_bytes( 16 );
		$ciphertext = openssl_encrypt( $value, self::CIPHER, $key, 0, $iv );

		if ( false === $ciphertext ) {
			return 'plain:' . $value;
		}

		return base64_encode( $iv . $ciphertext );
	}

	/**
	 * Decrypt an encrypted value.
	 *
	 * @param string $value Encrypted payload (base64).
	 * @return string Plaintext or empty string on failure.
	 */
	private function decrypt_value( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		if ( 0 === strpos( $value, 'plain:' ) ) {
			return substr( $value, 6 );
		}

		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}

		$key = $this->get_encryption_key();
		if ( empty( $key ) ) {
			return '';
		}

		$decoded = base64_decode( $value, true );
		if ( false === $decoded || strlen( $decoded ) < 17 ) {
			return '';
		}

		$iv         = substr( $decoded, 0, 16 );
		$ciphertext = substr( $decoded, 16 );

		$plaintext = openssl_decrypt( $ciphertext, self::CIPHER, $key, 0, $iv );

		return false === $plaintext ? '' : $plaintext;
	}

	/**
	 * Derive encryption key from WP salts.
	 *
	 * @return string
	 */
	private function get_encryption_key() {
		$secret_source = '';

		if ( defined( 'AUTH_KEY' ) ) {
			$secret_source .= AUTH_KEY;
		}
		if ( defined( 'SECURE_AUTH_KEY' ) ) {
			$secret_source .= SECURE_AUTH_KEY;
		}

		if ( empty( $secret_source ) ) {
			return '';
		}

		return hash( 'sha256', $secret_source );
	}

	/**
	 * Normalize API keys (trim and remove whitespace).
	 *
	 * @param string $value Raw key input.
	 * @return string
	 */
	private function normalize_api_key( $value ) {
		$key = sanitize_text_field( (string) $value );
		$key = preg_replace( '/\s+/', '', $key );
		return trim( $key );
	}

	/**
	 * Format a masked key status string.
	 *
	 * @param string $key Decrypted key.
	 * @return string
	 */
	private function format_key_status( $key ) {
		if ( empty( $key ) ) {
			return __( 'Status: not set', 'mystic-aura-reading' );
		}

		$length = strlen( $key );
		$tail   = $length > 4 ? substr( $key, -4 ) : $key;

		return sprintf(
			/* translators: 1: key length, 2: last 4 chars */
			__( 'Status: configured (length %1$d, last 4: %2$s)', 'mystic-aura-reading' ),
			$length,
			$tail
		);
	}

	/**
	 * Build masked key metadata for debug logs.
	 *
	 * @param string $key Plaintext key.
	 * @return array
	 */
	private function build_key_meta( $key ) {
		$length = strlen( $key );
		$tail   = $length > 4 ? substr( $key, -4 ) : $key;

		return array(
			'key_length' => $length,
			'key_tail'   => $tail,
		);
	}

	/**
	 * Get database status info.
	 *
	 * @return array{missing: array<string>}
	 */
	private function get_database_status() {
		$missing = array();

		if ( class_exists( 'SM_Database' ) ) {
			$db = SM_Database::get_instance();
			$missing = $db->missing_tables();
		}

		return array(
			'missing' => $missing,
		);
	}

	/**
	 * Aggregate invalid palm image stats from the debug log.
	 *
	 * @return array{total:int,locked:int,reasons_summary:string,last_seen:string}
	 */
	private function get_invalid_image_stats() {
		$stats = array(
			'total'           => 0,
			'locked'          => 0,
			'reasons_summary' => __( 'No entries yet.', 'mystic-aura-reading' ),
			'last_seen'       => __( 'Never', 'mystic-aura-reading' ),
		);

		if ( ! class_exists( 'SM_Logger' ) || ! method_exists( 'SM_Logger', 'get_log_file_path' ) ) {
			return $stats;
		}

		$log_path = SM_Logger::get_log_file_path();
		if ( empty( $log_path ) || ! file_exists( $log_path ) ) {
			return $stats;
		}

		$handle = @fopen( $log_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return $stats;
		}

		$reasons = array();
		$last_seen = '';

		while ( ( $line = fgets( $handle ) ) !== false ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fgets
			if ( false === strpos( $line, '[PALM_IMAGE_INVALID]' ) ) {
				continue;
			}

			$stats['total']++;
			$last_seen = $this->extract_log_timestamp( $line );

			$meta = $this->extract_log_meta( $line );
			if ( isset( $meta['locked'] ) && (int) $meta['locked'] === 1 ) {
				$stats['locked']++;
			}
			if ( ! empty( $meta['reason'] ) ) {
				$reason = sanitize_text_field( (string) $meta['reason'] );
				$reasons[ $reason ] = isset( $reasons[ $reason ] ) ? $reasons[ $reason ] + 1 : 1;
			}
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( ! empty( $last_seen ) ) {
			$stats['last_seen'] = $last_seen;
		}

		if ( ! empty( $reasons ) ) {
			arsort( $reasons );
			$summary = array();
			foreach ( $reasons as $reason => $count ) {
				$summary[] = sprintf( '%s (%d)', $reason, $count );
			}
			$stats['reasons_summary'] = implode( ', ', $summary );
		}

		return $stats;
	}

	/**
	 * Extract timestamp from a log line.
	 *
	 * @param string $line Log line.
	 * @return string
	 */
	private function extract_log_timestamp( $line ) {
		$matches = array();
		if ( preg_match( '/^\[([^\]]+)\]/', $line, $matches ) ) {
			return sanitize_text_field( $matches[1] );
		}

		return '';
	}

	/**
	 * Extract JSON metadata from a log line.
	 *
	 * @param string $line Log line.
	 * @return array<string,mixed>
	 */
	private function extract_log_meta( $line ) {
		$parts = explode( ' | ', $line, 2 );
		if ( count( $parts ) < 2 ) {
			return array();
		}

		$meta = json_decode( trim( $parts[1] ), true );
		return is_array( $meta ) ? $meta : array();
	}

	/**
	 * Get the last N lines of the debug log.
	 *
	 * @param int $lines Number of lines.
	 * @return string
	 */
	private function get_log_excerpt( $lines = 100 ) {
		if ( class_exists( 'SM_Logger' ) ) {
			return SM_Logger::tail( $lines );
		}

		return __( 'Logger service is unavailable.', 'mystic-aura-reading' );
	}
}
