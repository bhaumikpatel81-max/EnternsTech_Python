<?php
/**
 * EnternsTech Theme Functions
 *
 * @package EnternsTech
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ENTERNSTECH_VERSION', '2.0.0' );

// ──────────────────────────────────────────────────────────────────────────────
// Theme Setup
// ──────────────────────────────────────────────────────────────────────────────
function enternstech_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo', array(
		'height'      => 60,
		'width'       => 200,
		'flex-height' => true,
		'flex-width'  => true,
	) );
	add_theme_support( 'html5', array(
		'search-form', 'comment-form', 'comment-list',
		'gallery', 'caption', 'style', 'script',
	) );
	register_nav_menus( array(
		'primary' => __( 'Primary Navigation', 'enternstech' ),
		'footer'  => __( 'Footer Navigation',  'enternstech' ),
	) );
	$GLOBALS['content_width'] = 1200;
}
add_action( 'after_setup_theme', 'enternstech_setup' );

// ──────────────────────────────────────────────────────────────────────────────
// Enqueue
// ──────────────────────────────────────────────────────────────────────────────
function enternstech_scripts() {
	wp_enqueue_style(
		'enternstech-style',
		get_stylesheet_uri(),
		array(),
		ENTERNSTECH_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'enternstech_scripts' );

// ──────────────────────────────────────────────────────────────────────────────
// Widget Areas
// ──────────────────────────────────────────────────────────────────────────────
function enternstech_widgets_init() {
	register_sidebar( array(
		'name'          => __( 'Footer Widget Area', 'enternstech' ),
		'id'            => 'footer-1',
		'description'   => __( 'Add widgets here to appear in the footer.', 'enternstech' ),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	) );
}
add_action( 'widgets_init', 'enternstech_widgets_init' );

// ──────────────────────────────────────────────────────────────────────────────
// FormSubmit helper – preserves the existing FormSubmit integration
// ──────────────────────────────────────────────────────────────────────────────
function enternstech_formsubmit_email() {
	return apply_filters( 'enternstech_contact_email', 'info@enternstech.com' );
}

// ──────────────────────────────────────────────────────────────────────────────
// PayPal integration
// ──────────────────────────────────────────────────────────────────────────────
//
// The bundled front-end (static/index.html) renders a payment screen. To take
// real payments you connect PayPal here. Two pieces are provided:
//
//   1. A REST endpoint that creates a PayPal order server-side (keeps your
//      secret key off the browser). Front-end posts the plan/amount, gets an
//      approval URL or order id back.
//   2. A REST endpoint that captures the order after the buyer approves.
//
// SET YOUR CREDENTIALS:
//   Define these in wp-config.php (recommended) so they are never committed
//   to GitHub:
//
//       define( 'ENTERNSTECH_PAYPAL_ENV',    'live' );   // 'sandbox' or 'live'
//       define( 'ENTERNSTECH_PAYPAL_CLIENT', 'xxxx' );   // PayPal REST client id
//       define( 'ENTERNSTECH_PAYPAL_SECRET', 'xxxx' );   // PayPal REST secret
//
// Then point the front-end payment button at:
//       POST /wp-json/enternstech/v1/paypal/create   { "plan": "...", "amount": 0 }
//       POST /wp-json/enternstech/v1/paypal/capture  { "orderID": "..." }
//
function enternstech_paypal_env() {
	return defined( 'ENTERNSTECH_PAYPAL_ENV' ) ? ENTERNSTECH_PAYPAL_ENV : 'sandbox';
}

function enternstech_paypal_api_base() {
	return enternstech_paypal_env() === 'live'
		? 'https://api-m.paypal.com'
		: 'https://api-m.sandbox.paypal.com';
}

function enternstech_paypal_access_token() {
	if ( ! defined( 'ENTERNSTECH_PAYPAL_CLIENT' ) || ! defined( 'ENTERNSTECH_PAYPAL_SECRET' ) ) {
		return new WP_Error( 'paypal_unconfigured', 'PayPal credentials are not set in wp-config.php.' );
	}

	$response = wp_remote_post(
		enternstech_paypal_api_base() . '/v1/oauth2/token',
		array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( ENTERNSTECH_PAYPAL_CLIENT . ':' . ENTERNSTECH_PAYPAL_SECRET ),
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
			'body'    => array( 'grant_type' => 'client_credentials' ),
			'timeout' => 20,
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( empty( $body['access_token'] ) ) {
		return new WP_Error( 'paypal_auth_failed', 'Could not obtain a PayPal access token.', $body );
	}
	return $body['access_token'];
}

function enternstech_register_paypal_routes() {
	register_rest_route( 'enternstech/v1', '/paypal/create', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'enternstech_paypal_create_order',
	) );
	register_rest_route( 'enternstech/v1', '/paypal/capture', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'enternstech_paypal_capture_order',
	) );
}
add_action( 'rest_api_init', 'enternstech_register_paypal_routes' );

function enternstech_paypal_create_order( WP_REST_Request $request ) {
	$token = enternstech_paypal_access_token();
	if ( is_wp_error( $token ) ) {
		return new WP_REST_Response( array( 'error' => $token->get_error_message() ), 500 );
	}

	$amount = floatval( $request->get_param( 'amount' ) );
	$plan   = sanitize_text_field( (string) $request->get_param( 'plan' ) );
	if ( $amount <= 0 ) {
		return new WP_REST_Response( array( 'error' => 'Invalid amount.' ), 400 );
	}

	$currency = apply_filters( 'enternstech_paypal_currency', 'USD' );

	$response = wp_remote_post(
		enternstech_paypal_api_base() . '/v2/checkout/orders',
		array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( array(
				'intent'         => 'CAPTURE',
				'purchase_units' => array(
					array(
						'description' => $plan ? $plan : 'Enterns Tech program',
						'amount'      => array(
							'currency_code' => $currency,
							'value'         => number_format( $amount, 2, '.', '' ),
						),
					),
				),
			) ),
			'timeout' => 20,
		)
	);

	if ( is_wp_error( $response ) ) {
		return new WP_REST_Response( array( 'error' => $response->get_error_message() ), 500 );
	}
	return new WP_REST_Response( json_decode( wp_remote_retrieve_body( $response ), true ), 200 );
}

function enternstech_paypal_capture_order( WP_REST_Request $request ) {
	$token = enternstech_paypal_access_token();
	if ( is_wp_error( $token ) ) {
		return new WP_REST_Response( array( 'error' => $token->get_error_message() ), 500 );
	}

	$order_id = sanitize_text_field( (string) $request->get_param( 'orderID' ) );
	if ( ! $order_id ) {
		return new WP_REST_Response( array( 'error' => 'Missing orderID.' ), 400 );
	}

	$response = wp_remote_post(
		enternstech_paypal_api_base() . '/v2/checkout/orders/' . rawurlencode( $order_id ) . '/capture',
		array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 20,
		)
	);

	if ( is_wp_error( $response ) ) {
		return new WP_REST_Response( array( 'error' => $response->get_error_message() ), 500 );
	}
	return new WP_REST_Response( json_decode( wp_remote_retrieve_body( $response ), true ), 200 );
}

// Expose the PayPal client id + env to the front-end so the bundle can load
// the PayPal JS SDK. Reads window.ENTERNSTECH_PAYPAL in the browser.
function enternstech_paypal_front_config() {
	$client = defined( 'ENTERNSTECH_PAYPAL_CLIENT' ) ? ENTERNSTECH_PAYPAL_CLIENT : '';
	?>
	<script>
		window.ENTERNSTECH_PAYPAL = {
			clientId: <?php echo wp_json_encode( $client ); ?>,
			env: <?php echo wp_json_encode( enternstech_paypal_env() ); ?>,
			createUrl: <?php echo wp_json_encode( esc_url_raw( rest_url( 'enternstech/v1/paypal/create' ) ) ); ?>,
			captureUrl: <?php echo wp_json_encode( esc_url_raw( rest_url( 'enternstech/v1/paypal/capture' ) ) ); ?>
		};
	</script>
	<?php
}
add_action( 'wp_head', 'enternstech_paypal_front_config' );
