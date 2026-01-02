<?php
/**
 * Diagnostic Script for Mystic Palm Reading AI Plugin
 *
 * Run with: wp eval-file tests/diagnose.php
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    die( 'This script can only be run via WP-CLI' );
}

WP_CLI::line( '============================================' );
WP_CLI::line( 'Mystic Palm Reading AI - Diagnostic Report' );
WP_CLI::line( '============================================' );
WP_CLI::line( '' );

// 1. Check if plugin is active
WP_CLI::line( '1. PLUGIN STATUS' );
WP_CLI::line( '---' );
$active_plugins = get_option( 'active_plugins' );
$plugin_active = in_array( 'sm-aura-reading/mystic-aura-reading.php', $active_plugins );
WP_CLI::line( 'Plugin Active: ' . ( $plugin_active ? WP_CLI::colorize( '%G✓ YES%n' ) : WP_CLI::colorize( '%R✗ NO%n' ) ) );

if ( ! $plugin_active ) {
    WP_CLI::error( 'Plugin is not active. Activate it first with: wp plugin activate sm-aura-reading' );
}

// 2. Check if constants are defined
WP_CLI::line( '' );
WP_CLI::line( '2. PLUGIN CONSTANTS' );
WP_CLI::line( '---' );
$constants = array( 'SM_AURA_VERSION', 'SM_AURA_PLUGIN_DIR', 'SM_AURA_PLUGIN_URL', 'SM_AURA_PLUGIN_FILE' );
foreach ( $constants as $const ) {
    $defined = defined( $const );
    WP_CLI::line( "$const: " . ( $defined ? WP_CLI::colorize( '%G✓ Defined%n' ) . ' - ' . constant( $const ) : WP_CLI::colorize( '%R✗ Not Defined%n' ) ) );
}

// 3. Check if classes exist
WP_CLI::line( '' );
WP_CLI::line( '3. CORE CLASSES' );
WP_CLI::line( '---' );
$classes = array(
    'SM_Database',
    'SM_Settings',
    'SM_Logger',
    'SM_Lead_Handler',
    'SM_OTP_Handler',
    'SM_MailerLite_Handler',
    'SM_Image_Handler',
    'SM_Quiz_Handler',
    'SM_AI_Handler',
    'SM_REST_Controller',
    'SM_Sanitizer',
    'SM_Cleanup'
);

foreach ( $classes as $class ) {
    $exists = class_exists( $class );
    WP_CLI::line( "$class: " . ( $exists ? WP_CLI::colorize( '%G✓ Exists%n' ) : WP_CLI::colorize( '%R✗ Missing%n' ) ) );
}

// 4. Check database tables
WP_CLI::line( '' );
WP_CLI::line( '4. DATABASE TABLES' );
WP_CLI::line( '---' );
global $wpdb;
$tables = array(
    'sm_leads',
    'sm_otps',
    'sm_quiz',
    'sm_readings',
    'sm_logs'
);

foreach ( $tables as $table ) {
    $table_name = $wpdb->prefix . $table;
    $exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
    WP_CLI::line( "$table_name: " . ( $exists ? WP_CLI::colorize( '%G✓ Exists%n' ) : WP_CLI::colorize( '%R✗ Missing%n' ) ) );
}

// 5. Check REST API endpoints
WP_CLI::line( '' );
WP_CLI::line( '5. REST API ENDPOINTS' );
WP_CLI::line( '---' );
$rest_server = rest_get_server();
$namespaces = $rest_server->get_namespaces();
$sm_namespace_exists = in_array( 'soulmirror/v1', $namespaces );
WP_CLI::line( 'Namespace soulmirror/v1: ' . ( $sm_namespace_exists ? WP_CLI::colorize( '%G✓ Registered%n' ) : WP_CLI::colorize( '%R✗ Not Registered%n' ) ) );

if ( $sm_namespace_exists ) {
    $routes = $rest_server->get_routes( 'soulmirror/v1' );
    WP_CLI::line( 'Registered routes:' );
    foreach ( $routes as $route => $handlers ) {
        WP_CLI::line( "  - $route" );
    }
}

// 6. Check settings
WP_CLI::line( '' );
WP_CLI::line( '6. PLUGIN SETTINGS' );
WP_CLI::line( '---' );
$openai_key = get_option( 'sm_openai_api_key' );
$mailerlite_key = get_option( 'sm_mailerlite_api_key' );
$mailerlite_group = get_option( 'sm_mailerlite_group_id' );

WP_CLI::line( 'OpenAI API Key: ' . ( ! empty( $openai_key ) ? WP_CLI::colorize( '%G✓ Configured%n' ) . ' (***' . substr( $openai_key, -4 ) . ')' : WP_CLI::colorize( '%R✗ Not Set%n' ) ) );
WP_CLI::line( 'MailerLite API Key: ' . ( ! empty( $mailerlite_key ) ? WP_CLI::colorize( '%G✓ Configured%n' ) . ' (***' . substr( $mailerlite_key, -4 ) . ')' : WP_CLI::colorize( '%R✗ Not Set%n' ) ) );
WP_CLI::line( 'MailerLite Group ID: ' . ( ! empty( $mailerlite_group ) ? WP_CLI::colorize( '%G✓ Configured%n' ) . " ($mailerlite_group)" : WP_CLI::colorize( '%R✗ Not Set%n' ) ) );

// 7. Check debug.log
WP_CLI::line( '' );
WP_CLI::line( '7. DEBUG LOG' );
WP_CLI::line( '---' );
$log_file = SM_AURA_PLUGIN_DIR . '/debug.log';
if ( file_exists( $log_file ) ) {
    $log_size = filesize( $log_file );
    WP_CLI::line( "Log file exists: %G✓ YES%n ($log_size bytes)" );
    WP_CLI::line( 'Last 5 entries:' );
    $lines = file( $log_file );
    $last_lines = array_slice( $lines, -5 );
    foreach ( $last_lines as $line ) {
        WP_CLI::line( '  ' . trim( $line ) );
    }
} else {
    WP_CLI::line( 'Log file: ' . WP_CLI::colorize( '%Y⚠ Does not exist yet%n' ) );
}

// 8. Check shortcode registration
WP_CLI::line( '' );
WP_CLI::line( '8. SHORTCODE' );
WP_CLI::line( '---' );
global $shortcode_tags;
$shortcode_exists = isset( $shortcode_tags['soulmirror_aura_reading'] );
WP_CLI::line( '[soulmirror_aura_reading]: ' . ( $shortcode_exists ? WP_CLI::colorize( '%G✓ Registered%n' ) : WP_CLI::colorize( '%R✗ Not Registered%n' ) ) );

// 9. Check asset files
WP_CLI::line( '' );
WP_CLI::line( '9. FRONTEND ASSETS' );
WP_CLI::line( '---' );
$js_file = SM_AURA_PLUGIN_DIR . '/assets/js/script.js';
$css_file = SM_AURA_PLUGIN_DIR . '/assets/css/styles.css';
WP_CLI::line( 'script.js: ' . ( file_exists( $js_file ) ? WP_CLI::colorize( '%G✓ Exists%n' ) . ' (' . filesize( $js_file ) . ' bytes)' : WP_CLI::colorize( '%R✗ Missing%n' ) ) );
WP_CLI::line( 'styles.css: ' . ( file_exists( $css_file ) ? WP_CLI::colorize( '%G✓ Exists%n' ) . ' (' . filesize( $css_file ) . ' bytes)' : WP_CLI::colorize( '%R✗ Missing%n' ) ) );

// 10. Summary
WP_CLI::line( '' );
WP_CLI::line( '============================================' );
WP_CLI::line( 'DIAGNOSTIC COMPLETE' );
WP_CLI::line( '============================================' );

WP_CLI::success( 'Diagnostic report complete. Check for any ✗ or ⚠ markers above.' );
