<?php
/**
 * Plugin Name: Entire RCM Site Bootstrap
 * Description: Provisions the Entire RCM landing page (Elementor), the audit forms and the design system.
 * Version:     1.0.0
 * Author:      Entire RCM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ER_RCM_VERSION', '1.1.0' );
define( 'ER_RCM_DIR', __DIR__ . '/entire-rcm' );
define( 'ER_RCM_URL', WPMU_PLUGIN_URL . '/entire-rcm' );

/**
 * Front-end assets: fonts + design system + interactions.
 *
 * This file deliberately does NOT load the installer, so a syntax error in the
 * provisioning code can never take the live site down.
 */
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style(
		'er-rcm-fonts',
		'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap',
		array(),
		null
	);
	wp_enqueue_style(
		'er-rcm-icons',
		'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap',
		array(),
		null
	);
	wp_enqueue_style( 'er-rcm', ER_RCM_URL . '/payload/css/design.css', array(), ER_RCM_VERSION );
	wp_enqueue_script( 'er-rcm', ER_RCM_URL . '/payload/js/theme.js', array(), ER_RCM_VERSION, true );
}, 20 );

add_action( 'wp_head', function () {
	echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
	echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
	echo '<meta name="theme-color" content="#122056">' . "\n";
}, 1 );

add_filter( 'body_class', function ( $c ) {
	$c[] = 'er-body';
	return $c;
} );

/**
 * Provisioning. Only runs for a logged-in administrator, and never retries
 * automatically once it has recorded a fatal error.
 */
add_action( 'admin_init', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$forced = isset( $_GET['er_rcm_build'] ); // phpcs:ignore WordPress.Security.NonceVerification
	$stale  = get_option( 'er_rcm_version' ) !== ER_RCM_VERSION;
	$fatal  = get_option( 'er_rcm_fatal', array() );

	if ( ! $forced && ( ! $stale || $fatal ) ) {
		return;
	}

	register_shutdown_function( function () {
		$e = error_get_last();
		if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			update_option( 'er_rcm_fatal', $e, false );
		}
	} );

	require_once ER_RCM_DIR . '/inc/installer.php';

	$log = er_rcm_install();
	delete_option( 'er_rcm_fatal' );
	update_option( 'er_rcm_version', ER_RCM_VERSION, false );
	update_option( 'er_rcm_log', $log, false );

	if ( $forced ) {
		wp_safe_redirect( add_query_arg( 'er_rcm_built', '1', admin_url( '?er_rcm_log=1' ) ) );
		exit;
	}
}, 5 );

/**
 * Diagnostics: /wp-admin/?er_rcm_log=1 dumps the last install log.
 */
add_action( 'admin_init', function () {
	if ( ! isset( $_GET['er_rcm_log'] ) && ! isset( $_GET['er_rcm_fatal'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Forbidden', 403 );
	}
	$key = isset( $_GET['er_rcm_fatal'] ) ? 'er_rcm_fatal' : 'er_rcm_log'; // phpcs:ignore WordPress.Security.NonceVerification
	header( 'Content-Type: application/json; charset=utf-8' );
	echo wp_json_encode( get_option( $key, array() ), JSON_PRETTY_PRINT );
	exit;
}, 4 );
