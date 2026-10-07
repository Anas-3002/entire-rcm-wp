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

define( 'ER_RCM_VERSION', '4.6.0' );
define( 'ER_RCM_DIR', __DIR__ . '/entire-rcm' );
define( 'ER_RCM_URL', plugins_url( 'entire-rcm', __FILE__ ) );

/**
 * True on the provisioned landing page (and its Elementor editor preview).
 */
function er_rcm_is_landing() {
	static $is = null;
	if ( null !== $is ) {
		return $is;
	}
	$page_id = (int) get_option( 'er_rcm_page_id' );
	$is      = ( $page_id && is_page( $page_id ) ) || ( $page_id && is_front_page() );
	return $is;
}

/**
 * The landing page is a full-page Elementor Canvas carrying the Stitch export's
 * own reset (Tailwind's preflight). Hello Elementor still enqueues its reset and
 * theme stylesheets, which restyle raw <button>/<input> elements — a 1px border
 * on every button, a different input background, and so on. On the landing page
 * they are dropped so the design's own base is the only base.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! er_rcm_is_landing() ) {
		return;
	}
	foreach ( array( 'hello-elementor', 'hello-elementor-theme-style', 'hello-elementor-header-footer' ) as $handle ) {
		wp_dequeue_style( $handle );
		wp_deregister_style( $handle );
	}
}, 100 );

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
	wp_enqueue_style( 'er-rcm', ER_RCM_URL . '/payload/css/entire-rcm.css', array(), ER_RCM_VERSION );
	wp_enqueue_script( 'er-rcm', ER_RCM_URL . '/payload/js/theme.js', array(), ER_RCM_VERSION, true );
}, 20 );

add_action( 'wp_head', function () {
	echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
	echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
	echo '<meta name="theme-color" content="#122056">' . "\n";
}, 1 );

/**
 * The theme's skip link points at #content; the landing page owns its own
 * markup, so provide the anchor.
 */
add_action( 'wp_body_open', function () {
	echo '<span id="content" class="er-sr"></span>' . "\n";
}, 1 );

/**
 * The Stitch document's own <html> and <body> classes carry the base surface
 * colour, the Inter body face and the text-selection tint. WordPress owns both
 * tags, so the design's classes are replayed onto them here — without them the
 * document falls back to the system font stack and every block re-wraps.
 */
add_filter( 'language_attributes', function ( $out ) {
	return $out . ' class="scroll-smooth"';
} );

add_filter( 'body_class', function ( $c ) {
	$c[] = 'er-body';
	foreach ( array(
		'bg-background',
		'text-on-surface',
		'font-body-md',
		'antialiased',
		'selection:bg-secondary-fixed',
		'selection:text-on-secondary-fixed',
	) as $design_class ) {
		$c[] = $design_class;
	}
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
 * Introspection helper: /wp-admin/?er_rcm_peek=1
 * Reports Elementor's registered control names for containers and widgets, plus
 * what is actually stored in the page's _elementor_data meta.
 */
add_action( 'admin_init', function () {
	if ( ! isset( $_GET['er_rcm_peek'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Forbidden', 403 );
	}

	$out = array( 'elementor' => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : null );

	if ( class_exists( '\Elementor\Plugin' ) ) {
		$mgr = \Elementor\Plugin::$instance->elements_manager;
		foreach ( array( 'container', 'section', 'column' ) as $type ) {
			$obj = $mgr->get_element_types( $type );
			if ( $obj ) {
				$out['controls'][ $type ] = array_keys( (array) $obj->get_controls() );
			}
		}
		$wmgr = \Elementor\Plugin::$instance->widgets_manager;
		foreach ( array( 'heading', 'button', 'accordion' ) as $type ) {
			$obj = $wmgr->get_widget_types( $type );
			if ( $obj ) {
				$out['controls'][ 'widget:' . $type ] = array_keys( (array) $obj->get_controls() );
			}
		}
	}

	$page_id = (int) get_option( 'er_rcm_page_id' );
	$raw     = get_post_meta( $page_id, '_elementor_data', true );
	$decoded = json_decode( (string) $raw, true );
	if ( is_array( $decoded ) ) {
		$out['stored_first_container_settings'] = $decoded[0]['settings'];
		$out['stored_count']                    = count( $decoded );
		$out['first_container_has_css_classes'] = isset( $decoded[0]['settings']['_css_classes'] );
	}

	foreach ( get_posts( array( 'post_type' => 'wpcf7_contact_form', 'numberposts' => 6, 'post_status' => 'publish' ) ) as $cf ) {
		$p    = function_exists( 'wpcf7_contact_form' ) ? wpcf7_contact_form( $cf->ID ) : null;
		$prop = $p ? $p->get_properties() : array();
		$out['forms'][ $cf->ID ] = array(
			'title'           => $cf->post_title,
			'meta_keys'       => array_keys( get_post_meta( $cf->ID ) ),
			'prop_mail_subj'  => isset( $prop['mail']['subject'] ) ? $prop['mail']['subject'] : null,
			'prop_mail_to'    => isset( $prop['mail']['recipient'] ) ? $prop['mail']['recipient'] : null,
			'prop_mail_from'  => isset( $prop['mail']['sender'] ) ? $prop['mail']['sender'] : null,
			'prop_form_head'  => isset( $prop['form'] ) ? substr( $prop['form'], 0, 90 ) : null,
		);
	}

	header( 'Content-Type: application/json; charset=utf-8' );
	echo wp_json_encode( $out, JSON_PRETTY_PRINT );
	exit;
}, 3 );

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
