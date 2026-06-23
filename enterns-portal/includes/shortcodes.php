<?php
/**
 * Shortcodes: [enp_admin], [enp_mentor], [enp_student], [enp_partner_form].
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Asset loading ─────────────────────────────────────────────────────────────

add_action( 'wp_enqueue_scripts', 'enp_maybe_enqueue_assets' );
function enp_maybe_enqueue_assets() {
	global $post;
	if ( ! is_a( $post, 'WP_Post' ) ) {
		return;
	}
	$tags = array( 'enp_admin', 'enp_mentor', 'enp_student', 'enp_partner_form', 'enp_psychometric' );
	$found = false;
	foreach ( $tags as $tag ) {
		if ( has_shortcode( $post->post_content, $tag ) ) {
			$found = true;
			break;
		}
	}
	if ( ! $found ) {
		return;
	}
	wp_enqueue_style(
		'enp-portal',
		ENP_URL . 'assets/css/portal.css',
		array(),
		ENP_VERSION
	);
	wp_enqueue_script(
		'enp-portal',
		ENP_URL . 'assets/js/portal.js',
		array( 'jquery' ),
		ENP_VERSION,
		true
	);
	wp_localize_script( 'enp-portal', 'ENP', array(
		'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
		'nonce'         => wp_create_nonce( 'enp_portal' ),
		'rzpNonce'      => wp_create_nonce( 'enp_razorpay' ),
		'rzpConfigured' => function_exists( 'enp_razorpay_configured' ) && enp_razorpay_configured(),
		'homeUrl'       => esc_url( home_url() ),
		'studentUrl'    => esc_url( home_url( '/student/' ) ),
		'currentEmail'  => is_user_logged_in() ? wp_get_current_user()->user_email : '',
	) );

	if ( function_exists( 'enp_razorpay_configured' ) && enp_razorpay_configured() ) {
		wp_enqueue_script( 'razorpay-checkout', 'https://checkout.razorpay.com/v1/checkout.js', array(), null, true );
	}
}

// ── [enp_admin] ───────────────────────────────────────────────────────────────

add_shortcode( 'enp_admin', 'enp_shortcode_admin' );
function enp_shortcode_admin( $atts ) {
	if ( ! is_user_logged_in() ) {
		return enp_login_prompt( home_url( '/et-admin/' ) );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return enp_access_denied();
	}
	$portal_url = esc_url( home_url( '/admin-portal/' ) );
	return '<div class="enp-wrap">'
		. '<div class="enp-notice enp-notice--info" style="margin-bottom:1.5rem">'
		. '<strong>' . esc_html__( 'Admin Portal', 'enterns-portal' ) . '</strong> &mdash; '
		. esc_html__( 'Use the standalone admin interface for all management tasks.', 'enterns-portal' )
		. '</div>'
		. '<p><a href="' . $portal_url . '" class="enp-btn enp-btn--primary">'
		. esc_html__( 'Open Admin Portal', 'enterns-portal' )
		. ' &rarr;</a></p>'
		. '</div>';
}

// ── [enp_mentor] ──────────────────────────────────────────────────────────────

add_shortcode( 'enp_mentor', 'enp_shortcode_mentor' );
function enp_shortcode_mentor( $atts ) {
	if ( ! is_user_logged_in() ) {
		return enp_login_prompt( home_url( '/mentor/' ) );
	}
	$user = wp_get_current_user();
	if ( ! in_array( 'et_mentor', (array) $user->roles, true )
		&& ! current_user_can( 'manage_options' ) ) {
		return enp_access_denied();
	}
	ob_start();
	include ENP_DIR . 'templates/mentor-dashboard.php';
	return ob_get_clean();
}

// ── [enp_student] ─────────────────────────────────────────────────────────────

add_shortcode( 'enp_student', 'enp_shortcode_student' );
function enp_shortcode_student( $atts ) {
	if ( ! is_user_logged_in() ) {
		return enp_login_prompt( home_url( '/student/' ) );
	}
	$user = wp_get_current_user();
	if ( ! in_array( 'et_student', (array) $user->roles, true )
		&& ! current_user_can( 'manage_options' ) ) {
		return enp_access_denied();
	}
	ob_start();
	include ENP_DIR . 'templates/student-dashboard.php';
	return ob_get_clean();
}

// ── [enp_partner_form] ────────────────────────────────────────────────────────

add_shortcode( 'enp_partner_form', 'enp_shortcode_partner_form' );
function enp_shortcode_partner_form( $atts ) {
	ob_start();
	include ENP_DIR . 'templates/partner-form.php';
	return ob_get_clean();
}

// ── Shared helpers ────────────────────────────────────────────────────────────

function enp_login_prompt( $redirect = '' ) {
	if ( ! $redirect ) {
		$redirect = home_url( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/' );
	}
	$login_url = wp_login_url( $redirect );
	ob_start();
	include ENP_DIR . 'templates/login-form.php';
	return ob_get_clean();
}

function enp_access_denied() {
	return '<div class="enp-wrap"><div class="enp-notice enp-notice--error">'
		. esc_html__( 'You do not have permission to view this page.', 'enterns-portal' )
		. '</div></div>';
}
