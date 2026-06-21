<?php
/**
 * Front Page Template
 *
 * Serves the Design Canvas bundled site as the homepage.
 * Set this page as your static front page in:
 *   Settings → Reading → A static page → Front page
 *
 * @package EnternsTech
 */

while ( ob_get_level() ) {
	ob_end_clean();
}

$bundled = get_template_directory() . '/static/index.html';

if ( file_exists( $bundled ) ) {
	header( 'Content-Type: text/html; charset=utf-8' );

	$html = file_get_contents( $bundled );

	// Inject PayPal config + SDK just before </head> so the bundle can use it
	// (readfile would bypass wp_head, so we build the snippet here).
	$client   = defined( 'ENTERNSTECH_PAYPAL_CLIENT' ) ? ENTERNSTECH_PAYPAL_CLIENT : '';
	$env      = function_exists( 'enternstech_paypal_env' ) ? enternstech_paypal_env() : 'sandbox';
	$create   = esc_url_raw( rest_url( 'enternstech/v1/paypal/create' ) );
	$capture  = esc_url_raw( rest_url( 'enternstech/v1/paypal/capture' ) );

	$inject  = '<script>window.ENTERNSTECH_PAYPAL=' . wp_json_encode( array(
		'clientId'   => $client,
		'env'        => $env,
		'createUrl'  => $create,
		'captureUrl' => $capture,
	) ) . ';</script>';

	if ( $client ) {
		$inject .= '<script src="https://www.paypal.com/sdk/js?client-id=' . rawurlencode( $client )
			. '&currency=USD" data-namespace="enternsPayPal"></script>';
	}

	$html = preg_replace( '#</head>#i', $inject . '</head>', $html, 1 );

	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit;
}

// Fallback when the static file is missing.
get_header();
?>
<main id="main" class="site-main" style="display:flex;align-items:center;justify-content:center;min-height:calc(100vh - 80px);">
	<div style="text-align:center;padding:2rem;">
		<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 80 80" style="margin:0 auto 1.5rem;display:block;">
			<rect width="80" height="80" rx="16" fill="#0C1426"/>
			<circle cx="40" cy="40" r="28" fill="none" stroke="#22D3EE" stroke-opacity="0.4" stroke-width="1.5"/>
			<text x="40" y="51" font-family="sans-serif" font-size="26" font-weight="700" fill="#22D3EE" text-anchor="middle">et</text>
		</svg>
		<h1 style="font-size:1.75rem;color:#22D3EE;margin-bottom:0.75rem;">Enterns Tech</h1>
		<p style="color:#6B7280;">The site bundle is missing. Please re-upload <code>static/index.html</code> to the theme folder.</p>
	</div>
</main>
<?php
get_footer();
