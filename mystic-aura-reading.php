<?php
/**
 * Plugin Name: SoulMirror Aura Reading
 * Plugin URI: https://soulmirror.com
 * Description: An immersive AI-powered aura reading experience that captures leads, verifies emails, and delivers personalized energetic insights using OpenAI Vision and GPT-4o.
 * Version: 1.0.0
 * Author: SoulMirror
 * Author URI: https://soulmirror.com
 * Text Domain: mystic-aura-reading
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: Proprietary
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin Constants
 */
define( 'SM_AURA_VERSION', '1.0.0' );
define( 'SM_AURA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SM_AURA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SM_AURA_PLUGIN_FILE', __FILE__ );
define( 'SM_AURA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for plugin classes
 *
 * Automatically loads classes from the includes/ directory
 * Class names must follow WordPress naming conventions:
 * - Class SM_Example_Class maps to class-sm-example-class.php
 */
function sm_autoload_classes( $class_name ) {
	// Only autoload classes with SM_ prefix
	if ( strpos( $class_name, 'SM_' ) !== 0 ) {
		return;
	}

	// Convert class name to filename
	// SM_REST_Controller -> class-sm-rest-controller.php
	$class_file = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';
	$file_path = SM_AURA_PLUGIN_DIR . 'includes/' . $class_file;

	if ( file_exists( $file_path ) ) {
		require_once $file_path;
	}
}
spl_autoload_register( 'sm_autoload_classes' );

/**
 * Plugin Initialization
 *
 * Runs after WordPress, plugins, and theme are fully loaded
 */
function sm_init_plugin() {
	// Load text domain for translations
	load_plugin_textdomain(
		'mystic-aura-reading',
		false,
		dirname( SM_AURA_PLUGIN_BASENAME ) . '/languages'
	);

	// Initialize core components (will be added in future units)
	SM_Database::init();
	SM_Settings::init();
	SM_Logger::init();
	SM_Dev_Mode::init(); // Development mode for API mocking
	SM_Test_Helpers::init(); // Test helper endpoints (only active in DevMode)
	SM_Rate_Limiter::init();
	SM_Cleanup::init();
	SM_Lead_Handler::init();
	SM_OTP_Handler::init();
	SM_MailerLite_Handler::init();
	SM_Image_Handler::init();
	SM_Quiz_Handler::init();
	SM_AI_Handler::init();
	SM_Reading_Job_Handler::init();
	SM_Reading_Service::init();
	SM_Unlock_Handler::init();
	SM_Template_Renderer::init();
	SM_REST_Controller::init();
	SM_Auth_Handler::init();
	SM_Reports_Handler::init();

	// Log any wp_mail() failures to assist production debugging (e.g., SMTP issues).
	if ( ! has_action( 'wp_mail_failed', 'sm_log_mail_failure' ) ) {
		add_action( 'wp_mail_failed', 'sm_log_mail_failure' );
	}
}
add_action( 'plugins_loaded', 'sm_init_plugin' );

/**
 * Enqueue frontend assets
 *
 * Loads JavaScript and CSS only on pages with the shortcode
 */
function sm_enqueue_assets( $force = false ) {
	global $post;
	static $did_enqueue = false;

	if ( $did_enqueue ) {
		return;
	}

	// Only load on pages that use our shortcode
	$has_shortcode = false;
	if ( is_a( $post, 'WP_Post' ) ) {
		$has_shortcode = has_shortcode( $post->post_content, 'soulmirror_aura_reading' );
		if ( ! $has_shortcode ) {
			$elementor_data = get_post_meta( $post->ID, '_elementor_data', true );
			if ( is_string( $elementor_data ) && false !== strpos( $elementor_data, 'soulmirror_aura_reading' ) ) {
				$has_shortcode = true;
			}
		}
	}

	if ( $force ) {
		$has_shortcode = true;
	}

	if ( $has_shortcode ) {

		// Enqueue Font Awesome (for icons)
		wp_enqueue_style(
			'sm-font-awesome-6',
			'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
			array(),
			'6.4.0'
		);

		// Enqueue dashboard fonts
		wp_enqueue_style(
			'sm-dashboard-fonts',
			'https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Inter:wght@400;500;600;700&display=swap',
			array(),
			null
		);

		// Enqueue CSS
		wp_enqueue_style(
			'sm-styles',
			SM_AURA_PLUGIN_URL . 'assets/css/styles.css',
			array(),
			SM_AURA_VERSION
		);

		// Enqueue Auth CSS (for login button and user info)
		wp_enqueue_style(
			'sm-auth-styles',
			SM_AURA_PLUGIN_URL . 'assets/css/auth.css',
			array( 'sm-styles' ),
			SM_AURA_VERSION
		);

		// Enqueue questionnaire spacing overrides (mobile-friendly tweaks).
		wp_enqueue_style(
			'sm-questionnaire-overrides',
			SM_AURA_PLUGIN_URL . 'assets/css/questionnaire-overrides.css',
			array( 'sm-auth-styles' ),
			SM_AURA_VERSION
		);

		// --- ROBUST SCRIPT LOADING ---
		// 1. Register a virtual handle for the teaser script.
		$teaser_script_handle = 'sm-teaser-reading';
		wp_register_script( $teaser_script_handle, '', array(), SM_AURA_VERSION, true );

		// 2. Load the teaser script's contents and attach it as an inline script to the virtual handle.
		$teaser_script_path = SM_AURA_PLUGIN_DIR . 'assets/js/teaser-reading.js';
		if ( file_exists( $teaser_script_path ) && is_readable( $teaser_script_path ) ) {
			$teaser_inline = file_get_contents( $teaser_script_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
			wp_add_inline_script( $teaser_script_handle, $teaser_inline );
		}

		// 3. Enqueue the virtual teaser script. WordPress will now print its inline content.
		wp_enqueue_script( $teaser_script_handle );
		
		// 4. Enqueue the main script and declare its dependency on the teaser script.
		// This guarantees the teaser script runs before the main script.
		wp_enqueue_script(
			'sm-script',
			SM_AURA_PLUGIN_URL . 'assets/js/script.js',
			array( $teaser_script_handle ), // Explicit dependency
			SM_AURA_VERSION,
			true
		);

		// Pass data to JavaScript (attached to the main script)
		$settings         = SM_Settings::init()->get_settings();
		$auth_handler     = SM_Auth_Handler::init();
		$show_login_flow  = ! empty( $settings['enable_account_integration'] )
			&& ! empty( $settings['show_login_button'] )
			&& is_array( $settings['show_login_button'] )
			&& ! empty( $settings['show_login_button']['dashboard'] )
			&& ! $auth_handler->is_user_logged_in();
		$login_url        = $show_login_flow ? $auth_handler->get_login_url() : '';
		$login_button_text = ! empty( $settings['login_button_text'] ) ? $settings['login_button_text'] : 'Login / Sign Up';

		wp_localize_script( 'sm-script', 'smData', array(
			'nonce'           => wp_create_nonce( 'wp_rest' ),
			'nonceExpiresIn'  => apply_filters( 'nonce_life', DAY_IN_SECONDS ),
			'nonceRefreshUrl' => rest_url( 'soulmirror/v1/nonce/refresh' ),
			'apiUrl'          => rest_url( 'soulmirror/v1/' ),
			'pluginUrl'       => SM_AURA_PLUGIN_URL,
			'homeUrl'         => home_url( '/' ),
			'offeringsUrl'    => SM_Settings::init()->get_offerings_url(),
			'isLoggedIn'      => $auth_handler->is_user_logged_in(),
			'accountIntegrationEnabled' => ! empty( $settings['enable_account_integration'] ),
			'auth'            => array(
				'showLoginButton' => ( ! empty( $login_url ) ),
				'loginUrl'        => $login_url,
				'loginText'       => $login_button_text,
			),
		) );

		// Enqueue API integration (must load after main script). Inline to avoid host WAF/403 on static .js fetch.
		$api_script_handle = 'sm-api-integration';
		$api_script_path   = SM_AURA_PLUGIN_DIR . 'assets/js/api-integration.js';
		$api_script_url    = SM_AURA_PLUGIN_URL . 'assets/js/api-integration.js';
		$api_inline        = '';

		if ( file_exists( $api_script_path ) && is_readable( $api_script_path ) ) {
			$api_inline = file_get_contents( $api_script_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		}

		if ( ! empty( $api_inline ) ) {
			// Register empty handle then inject inline JS to bypass public fetch.
			wp_register_script( $api_script_handle, '', array( 'sm-script' ), SM_AURA_VERSION, true );
			wp_add_inline_script( $api_script_handle, $api_inline );
			wp_enqueue_script( $api_script_handle );
		} else {
			// Fallback to normal enqueue if inline failed.
			wp_enqueue_script(
				$api_script_handle,
				$api_script_url,
				array( 'sm-script' ),
				SM_AURA_VERSION,
				true
			);
		}
	}

	$did_enqueue = true;
}
add_action( 'wp_enqueue_scripts', 'sm_enqueue_assets' );

/**
 * Ensure assets load when Elementor renders the shortcode in canvas templates.
 */
function sm_enqueue_assets_for_elementor() {
	if ( did_action( 'wp_enqueue_scripts' ) ) {
		sm_enqueue_assets( true );
	}
}
add_action( 'elementor/frontend/after_enqueue_scripts', 'sm_enqueue_assets_for_elementor' );

/**
 * Register shortcode
 *
 * Shortcode: [soulmirror_aura_reading]
 */
function sm_render_shortcode( $atts ) {
	$force_start = isset( $_GET['start_new'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['start_new'] ) );
	$has_magic   = isset( $_GET['sm_magic'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['sm_magic'] ) );
	$has_report  = isset( $_GET['sm_report'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['sm_report'] ) );
	$show_reports = isset( $_GET['sm_reports'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['sm_reports'] ) );
	$force_start = $force_start || $has_magic;
	$force_start = $force_start || $has_report;

	sm_enqueue_assets( true );
	if ( ! wp_style_is( 'sm-font-awesome-6', 'done' ) ) {
		echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	// If the user is logged in, show reports when requested.
	if ( $show_reports && SM_Auth_Handler::get_instance()->is_user_logged_in() ) {
		ob_start();
		include SM_AURA_PLUGIN_DIR . 'templates/user-reports.php';
		return ob_get_clean();
	}

	// If the user is logged in, show the dashboard unless they are starting a new reading.
	if ( ! $force_start && SM_Auth_Handler::get_instance()->is_user_logged_in() ) {
		ob_start();
		include SM_AURA_PLUGIN_DIR . 'templates/dashboard.php';
		return ob_get_clean();
	}

	// Otherwise, show the main app container.
	ob_start();
	include SM_AURA_PLUGIN_DIR . 'templates/container.php';
	return ob_get_clean();
}
add_shortcode( 'soulmirror_aura_reading', 'sm_render_shortcode' );

/**
 * Register query vars for report downloads.
 *
 * @param array $vars Query vars.
 * @return array
 */
function sm_register_download_query_vars( $vars ) {
	$vars[] = 'sm_download';
	$vars[] = 'reading_id';
	$vars[] = 'sm_download_nonce';
	return $vars;
}
add_filter( 'query_vars', 'sm_register_download_query_vars' );

/**
 * Handle standalone HTML report downloads.
 */
function sm_handle_report_download() {
	if ( ! get_query_var( 'sm_download' ) ) {
		return;
	}

	$reading_id = get_query_var( 'reading_id' );
	if ( empty( $reading_id ) && isset( $_GET['reading_id'] ) ) {
		$reading_id = sanitize_text_field( wp_unslash( $_GET['reading_id'] ) );
	}

	$nonce = get_query_var( 'sm_download_nonce' );
	if ( empty( $nonce ) && isset( $_GET['sm_download_nonce'] ) ) {
		$nonce = sanitize_text_field( wp_unslash( $_GET['sm_download_nonce'] ) );
	}

	if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'sm_download' ) ) {
		wp_die( esc_html__( 'Invalid download request.', 'mystic-palm-reading' ), esc_html__( 'Download Error', 'mystic-palm-reading' ), array( 'response' => 403 ) );
	}

	if ( empty( $reading_id ) ) {
		wp_die( esc_html__( 'Missing reading ID.', 'mystic-palm-reading' ), esc_html__( 'Download Error', 'mystic-palm-reading' ), array( 'response' => 400 ) );
	}

	$auth_handler = SM_Auth_Handler::get_instance();
	$user_data    = $auth_handler->get_current_user();
	$account_id   = ! empty( $user_data['account_id'] ) ? sanitize_text_field( $user_data['account_id'] ) : '';

	if ( empty( $account_id ) ) {
		wp_die( esc_html__( 'You must be logged in to download this reading.', 'mystic-palm-reading' ), esc_html__( 'Download Error', 'mystic-palm-reading' ), array( 'response' => 401 ) );
	}

	$reading_service = SM_Reading_Service::get_instance();
	$reading         = $reading_service->get_reading_by_id( $reading_id, false );

	if ( is_wp_error( $reading ) ) {
		wp_die( esc_html( $reading->get_error_message() ), esc_html__( 'Download Error', 'mystic-palm-reading' ), array( 'response' => 404 ) );
	}

	if ( empty( $reading->account_id ) || $reading->account_id !== $account_id ) {
		wp_die( esc_html__( 'You do not have permission to download this reading.', 'mystic-palm-reading' ), esc_html__( 'Download Error', 'mystic-palm-reading' ), array( 'response' => 403 ) );
	}

	if ( empty( $reading->has_purchased ) ) {
		wp_die( esc_html__( 'Download is available for paid readings only.', 'mystic-palm-reading' ), esc_html__( 'Download Error', 'mystic-palm-reading' ), array( 'response' => 403 ) );
	}

	$template_path = defined( 'SM_AURA_PLUGIN_DIR' )
		? SM_AURA_PLUGIN_DIR . 'aura-reading-template-full.html'
		: plugin_dir_path( dirname( __FILE__ ) ) . 'aura-reading-template-full.html';

	$renderer = SM_Full_Template_Renderer::get_instance();
	$html     = $renderer->render_reading_with_template( $reading_id, $template_path );

	if ( is_wp_error( $html ) ) {
		wp_die( esc_html( $html->get_error_message() ), esc_html__( 'Download Error', 'mystic-palm-reading' ), array( 'response' => 500 ) );
	}

	$created_at = ! empty( $reading->created_at ) ? $reading->created_at : current_time( 'mysql' );
	$date_stamp = mysql2date( 'Y-m-d_H-i-s', $created_at, false );
	$filename   = sanitize_file_name( 'soulmirror-reading-' . $date_stamp . '.html' );

	while ( ob_get_level() ) {
		ob_end_clean();
	}

	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'X-Content-Type-Options: nosniff' );

	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit;
}
add_action( 'template_redirect', 'sm_handle_report_download' );

/**
 * Redirect paid report links to login when no session is present.
 * RUNS VERY EARLY to bypass WordPress authentication checks.
 */
function sm_redirect_paid_report_to_login() {
	// Skip for admin/ajax
	if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
		return;
	}

	// Skip if not a report URL
	if ( ! isset( $_GET['sm_report'] ) ) {
		return;
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
	$full_request_url = home_url( $request_uri );

	// Initialize auth handler and settings
	$auth_handler = SM_Auth_Handler::get_instance();
	$settings = SM_Settings::init()->get_settings();

	// Check if user is already logged in via JWT
	if ( $auth_handler->is_user_logged_in() ) {
		// User is logged in, let WordPress continue normally
		return;
	}

	// User is NOT logged in - redirect to Account Service login
	if ( empty( $settings['enable_account_integration'] ) ) {
		return;
	}

	$base_url = isset( $settings['account_service_url'] ) ? rtrim( $settings['account_service_url'], '/' ) : '';
	if ( empty( $base_url ) ) {
		return;
	}

	// Build the callback URL with the original report URL as redirect parameter
	$auth_handler->ensure_session();
	$_SESSION[ SM_Auth_Handler::SESSION_REDIRECT_KEY ] = esc_url_raw( $full_request_url );

	// Build callback URL - add_query_arg handles encoding
	$callback_url = add_query_arg(
		array(
			'redirect' => $full_request_url,
		),
		home_url( '/aura-reading/auth/callback' )
	);

	// Build login URL - add_query_arg handles encoding
	$login_url = add_query_arg(
		array(
			'redirect_url' => $callback_url,
		),
		$base_url . '/account/login'
	);

	if ( class_exists( 'SM_Logger' ) ) {
		SM_Logger::info(
			'AUTH_REDIRECT',
			'Redirecting unauthenticated report access to Account Service login',
			array(
				'return_url' => $full_request_url,
				'login_url'  => $login_url,
			)
		);
	}

	// Force redirect immediately
	// Use wp_redirect (not wp_safe_redirect) because Account Service is external domain
	wp_redirect( $login_url, 302 );
	exit;
}
// Hook into 'wp' - runs AFTER WordPress is set up but BEFORE template selection
// This runs before any authentication redirects from themes/plugins
add_action( 'wp', 'sm_redirect_paid_report_to_login', 1 );

/**
 * Allow redirects to Account Service domain.
 * wp_safe_redirect() only allows whitelisted domains.
 */
function sm_allow_account_service_redirects( $hosts ) {
	$settings = SM_Settings::init()->get_settings();
	$base_url = isset( $settings['account_service_url'] ) ? $settings['account_service_url'] : '';

	if ( ! empty( $base_url ) ) {
		$parsed = wp_parse_url( $base_url );
		if ( ! empty( $parsed['host'] ) ) {
			$hosts[] = $parsed['host'];
		}
	}

	return $hosts;
}
add_filter( 'allowed_redirect_hosts', 'sm_allow_account_service_redirects' );

/**
 * Capture wp_mail failures for debugging.
 *
 * @param WP_Error $wp_error Mail error.
 */
function sm_log_mail_failure( $wp_error ) {
	if ( ! class_exists( 'SM_Logger' ) || ! is_wp_error( $wp_error ) ) {
		return;
	}

	SM_Logger::error(
		'MAIL_SEND_FAILED',
		'Email send failed',
		array(
			'error' => $wp_error->get_error_message(),
			'data'  => $wp_error->get_error_data(),
		)
	);
}

/**
 * Plugin Activation Hook
 *
 * Runs when plugin is activated
 * - Creates database tables (will be implemented in Unit 1.2)
 * - Sets default options
 * - Checks system requirements
 */
function sm_activate_plugin() {
	// Check WordPress version
	if ( version_compare( get_bloginfo( 'version' ), '5.8', '<' ) ) {
		wp_die(
			esc_html__( 'This plugin requires WordPress 5.8 or higher.', 'mystic-palm-reading' ),
			esc_html__( 'Plugin Activation Error', 'mystic-palm-reading' )
		);
	}

	// Check PHP version
	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		wp_die(
			esc_html__( 'This plugin requires PHP 7.4 or higher.', 'mystic-palm-reading' ),
			esc_html__( 'Plugin Activation Error', 'mystic-palm-reading' )
		);
	}

	// Database setup
	SM_Database::activate();

	// Set plugin version
	update_option( 'sm_aura_version', SM_AURA_VERSION );

	// Log activation
	if ( class_exists( 'SM_Logger' ) ) {
		SM_Logger::log(
			'info',
			'PLUGIN_ACTIVATED',
			'Plugin activated successfully',
			array(
				'version' => SM_AURA_VERSION,
			)
		);
	}

	// Flush rewrite rules to ensure REST API endpoints work
	flush_rewrite_rules();
}
register_activation_hook( SM_AURA_PLUGIN_FILE, 'sm_activate_plugin' );

/**
 * Plugin Deactivation Hook
 *
 * Runs when plugin is deactivated
 * Note: Does NOT delete data (for reactivation scenarios)
 */
function sm_deactivate_plugin() {
	// Log deactivation
	if ( class_exists( 'SM_Logger' ) ) {
		SM_Logger::log(
			'info',
			'PLUGIN_DEACTIVATED',
			'Plugin deactivated',
			array(
				'version' => SM_AURA_VERSION,
			)
		);
	}

	if ( class_exists( 'SM_Rate_Limiter' ) && defined( 'SM_Rate_Limiter::CLEANUP_HOOK' ) ) {
		wp_clear_scheduled_hook( SM_Rate_Limiter::CLEANUP_HOOK );
	}

	if ( class_exists( 'SM_Cleanup' ) && defined( 'SM_Cleanup::CLEANUP_HOOK' ) ) {
		wp_clear_scheduled_hook( SM_Cleanup::CLEANUP_HOOK );
	}

	// Flush rewrite rules
	flush_rewrite_rules();

	// Note: We do NOT delete database tables or options on deactivation
	// Data persistence is important for lead management
	// Cleanup only occurs on uninstall (handled by uninstall.php if needed)
}
register_deactivation_hook( SM_AURA_PLUGIN_FILE, 'sm_deactivate_plugin' );
