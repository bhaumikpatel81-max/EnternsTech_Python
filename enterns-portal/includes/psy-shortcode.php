<?php
/**
 * [enp_psychometric] shortcode — candidate assessment page.
 * Also handles asset enqueuing, no-cache headers, and LiteSpeed exclusion.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── No-cache + LiteSpeed exclusion for assessment routes ──────────────────────

add_action( 'template_redirect', 'enp_psy_maybe_nocache' );

function enp_psy_maybe_nocache(): void {
	global $post;
	if ( ! is_a( $post, 'WP_Post' ) ) {
		return;
	}
	if ( ! has_shortcode( $post->post_content, 'enp_psychometric' ) ) {
		return;
	}
	// Prevent caching at every layer.
	nocache_headers();
	header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
	header( 'Pragma: no-cache' );

	// LiteSpeed: tell LSCACHE to exclude this URL.
	if ( defined( 'LSCACHE_NO_CACHE' ) ) {
		define( 'LSCACHE_NO_CACHE', true );
	}
	do_action( 'litespeed_control_set_nocache', 'psychometric assessment page' );
	do_action( 'litespeed_tag_add', 'psy_nostore' );
}

// ── Asset enqueuing ───────────────────────────────────────────────────────────

add_action( 'wp_enqueue_scripts', 'enp_psy_maybe_enqueue' );

function enp_psy_maybe_enqueue(): void {
	global $post;
	if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'enp_psychometric' ) ) {
		return;
	}

	// Portal base CSS (dark theme variables).
	wp_enqueue_style( 'enp-portal', ENP_URL . 'assets/css/portal.css', array(), ENP_VERSION );

	// Psychometric-specific CSS & JS.
	wp_enqueue_style(
		'enp-psy',
		ENP_URL . 'assets/css/psychometric.css',
		array( 'enp-portal' ),
		ENP_VERSION
	);
	wp_enqueue_script(
		'enp-psy',
		ENP_URL . 'assets/js/psychometric.js',
		array( 'jquery' ),
		ENP_VERSION,
		true
	);

	$token = sanitize_text_field( wp_unslash( $_GET['t'] ?? '' ) );

	wp_localize_script( 'enp-psy', 'ENP_PSY', array(
		'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'enp_psy_public' ),
		'token'    => $token,
		'homeUrl'  => esc_url( home_url() ),
	) );
}

// ── [enp_psychometric] shortcode ──────────────────────────────────────────────

add_shortcode( 'enp_psychometric', 'enp_shortcode_psychometric' );

function enp_shortcode_psychometric(): string {
	ob_start();
	include ENP_DIR . 'templates/psy-candidate.php';
	return ob_get_clean();
}
