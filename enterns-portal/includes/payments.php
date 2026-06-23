<?php
/**
 * Phase 4: Razorpay payments and student activation.
 *
 * Add to wp-config.php (never commit these):
 *   define( 'ENP_RAZORPAY_KEY_ID',     'rzp_test_XXXXXXXXXXXXXXXX' );
 *   define( 'ENP_RAZORPAY_KEY_SECRET', 'XXXXXXXXXXXXXXXXXXXXXXXX' );
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Plan catalogue ─────────────────────────────────────────────────────────────

/**
 * Canonical plan prices in INR paise (server-side only — browser never dictates amount).
 * Source: $et_plans / $et_combos arrays in enternstech/front-page.php.
 *
 * @return array plan_id => paise (INR * 100)
 */
function enp_plan_prices() {
	return array(
		'basic'       => 15000000,  // ₹1,50,000
		'elite'       => 25000000,  // ₹2,50,000
		'premium'     => 35000000,  // ₹3,50,000
		'accelerator' => 55000000,  // ₹5,50,000 — Career Accelerator Combo
		'starter'     => 37500000,  // ₹3,75,000 — Career Starter Combo
	);
}

/**
 * Default sessions_total per plan, within the configured 4–8 range.
 *
 * @return array plan_id => sessions count
 */
function enp_plan_sessions() {
	return array(
		'basic'       => 4,
		'elite'       => 6,
		'premium'     => 8,
		'accelerator' => 8,
		'starter'     => 6,
	);
}

/**
 * @return bool True when both Razorpay constants are defined in wp-config.php.
 */
function enp_razorpay_configured() {
	return defined( 'ENP_RAZORPAY_KEY_ID' ) && defined( 'ENP_RAZORPAY_KEY_SECRET' );
}

// ── Razorpay Orders API ────────────────────────────────────────────────────────

/**
 * Create a Razorpay order via their REST API.
 *
 * @param string $plan_id
 * @return array|WP_Error Decoded order object on success.
 */
function enp_create_razorpay_order_api( $plan_id ) {
	$prices = enp_plan_prices();
	if ( ! isset( $prices[ $plan_id ] ) ) {
		return new WP_Error( 'invalid_plan', 'Unknown plan: ' . esc_html( $plan_id ) );
	}

	$response = wp_remote_post(
		'https://api.razorpay.com/v1/orders',
		array(
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Basic ' . base64_encode( ENP_RAZORPAY_KEY_ID . ':' . ENP_RAZORPAY_KEY_SECRET ),
			),
			'body'    => wp_json_encode( array(
				'amount'          => $prices[ $plan_id ],
				'currency'        => 'INR',
				'receipt'         => 'enp_' . sanitize_key( $plan_id ) . '_' . time(),
				'partial_payment' => false,
			) ),
			'timeout' => 20,
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code !== 200 || empty( $body['id'] ) ) {
		$msg = isset( $body['error']['description'] )
			? $body['error']['description']
			: 'Razorpay API error (HTTP ' . $code . ')';
		return new WP_Error( 'rzp_api_error', $msg );
	}

	return $body;
}

// ── Shared student activation ──────────────────────────────────────────────────

/**
 * Mark a payment row as paid, provision a WP user with the et_student role,
 * upsert the wp_enp_students row, and email a set-password link on first
 * user creation. Fully idempotent — safe to call twice for the same email.
 *
 * @param string $email
 * @param string $plan_id  Must exist in enp_plan_prices().
 * @param int    $payment_id  Row ID in wp_enp_payments (already inserted as 'created').
 * @return true|WP_Error
 */
function enp_activate_student( $email, $plan_id, $payment_id ) {
	global $wpdb;
	$p = $wpdb->prefix;

	// 1. Mark payment paid.
	$wpdb->update(
		"{$p}enp_payments",
		array( 'status' => 'paid' ),
		array( 'id'     => (int) $payment_id ),
		array( '%s' ),
		array( '%d' )
	);

	// 2. Sessions default for this plan.
	$sessions_map = enp_plan_sessions();
	$sessions     = isset( $sessions_map[ $plan_id ] )
		? (int) $sessions_map[ $plan_id ]
		: (int) enp_config( 'sessions_min' );

	// 3. Find or create WP user.
	$existing_user = get_user_by( 'email', $email );
	$is_new_user   = false;

	if ( $existing_user ) {
		$wp_uid  = (int) $existing_user->ID;
		$wp_user = $existing_user;
	} else {
		$is_new_user = true;
		$base_slug   = sanitize_user( (string) strstr( $email, '@', true ), true );
		if ( ! $base_slug ) {
			$base_slug = 'student';
		}
		$login = $base_slug;
		$n     = 1;
		while ( username_exists( $login ) ) {
			$login = $base_slug . $n++;
		}
		$wp_uid = wp_create_user(
			$login,
			wp_generate_password( 24, true, true ),
			$email
		);
		if ( is_wp_error( $wp_uid ) ) {
			return $wp_uid;
		}
		$wp_user = new WP_User( (int) $wp_uid );
	}

	// Always (re)set role — idempotent if already et_student.
	$wp_user->set_role( 'et_student' );

	// 4. Upsert wp_enp_students row.
	$student = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id FROM {$p}enp_students WHERE email = %s LIMIT 1",
			$email
		)
	);

	if ( $student ) {
		$wpdb->update(
			"{$p}enp_students",
			array(
				'plan_id'        => $plan_id,
				'sessions_total' => $sessions,
				'status'         => 'active',
				'user_id'        => (int) $wp_uid,
			),
			array( 'id' => (int) $student->id ),
			array( '%s', '%d', '%s', '%d' ),
			array( '%d' )
		);
		$student_id = (int) $student->id;
	} else {
		$wpdb->insert(
			"{$p}enp_students",
			array(
				'full_name'      => '',
				'email'          => $email,
				'plan_id'        => $plan_id,
				'sessions_total' => $sessions,
				'status'         => 'active',
				'user_id'        => (int) $wp_uid,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%d' )
		);
		$student_id = (int) $wpdb->insert_id;
	}

	// 5. Link student_id back to payment row.
	$wpdb->update(
		"{$p}enp_payments",
		array( 'student_id' => $student_id ),
		array( 'id'         => (int) $payment_id ),
		array( '%d' ),
		array( '%d' )
	);

	// 6. Send set-password email only when a new WP user was just created.
	//    Existing users already know their password; don't spam them.
	if ( $is_new_user && function_exists( 'enp_send_mail' ) ) {
		$key       = get_password_reset_key( $wp_user );
		$reset_url = ! is_wp_error( $key )
			? add_query_arg(
				array(
					'action' => 'rp',
					'key'    => $key,
					'login'  => rawurlencode( $wp_user->user_login ),
				),
				wp_login_url()
			)
			: '';

		$plan_labels = array(
			'basic'       => 'Basic Plan',
			'elite'       => 'Elite Plan',
			'premium'     => 'Premium Plan',
			'accelerator' => 'Career Accelerator Combo',
			'starter'     => 'Career Starter Combo',
		);
		$plan_label = isset( $plan_labels[ $plan_id ] )
			? $plan_labels[ $plan_id ]
			: strtoupper( $plan_id );

		$body  = "<h2 style='color:#22D3EE;margin:0 0 16px'>Welcome to Enterns Tech!</h2>";
		$body .= "<p>Your enrolment for the <strong>" . esc_html( $plan_label ) . "</strong> is confirmed.</p>";
		if ( $reset_url ) {
			$body .= "<p style='margin:24px 0'><a href='" . esc_url( $reset_url ) . "'"
				. " style='background:#22D3EE;color:#05080F;padding:12px 28px;border-radius:8px;"
				. "text-decoration:none;font-weight:700;display:inline-block'>Set Your Password &rarr;</a></p>";
			$body .= "<p style='color:#94a3b8;font-size:13px'>This link expires in 24 hours."
				. " Use <em>Forgot Password</em> on the login page for a fresh link.</p>";
		}
		$body .= "<p style='color:#94a3b8;font-size:13px'>Student portal: <a href='"
			. esc_url( home_url( '/student/' ) )
			. "' style='color:#22D3EE'>"
			. esc_html( home_url( '/student/' ) )
			. "</a></p>";

		enp_send_mail(
			$email,
			'Welcome to Enterns Tech — Set your password',
			$body,
			true
		);
	}

	return true;
}

// ── AJAX: Create Razorpay order ────────────────────────────────────────────────

add_action( 'wp_ajax_enp_create_razorpay_order',        'enp_ajax_create_razorpay_order' );
add_action( 'wp_ajax_nopriv_enp_create_razorpay_order', 'enp_ajax_create_razorpay_order' );

function enp_ajax_create_razorpay_order() {
	check_ajax_referer( 'enp_razorpay', 'nonce' );

	if ( ! enp_razorpay_configured() ) {
		wp_send_json_error( 'Payment gateway not configured yet. Please contact us to enrol.' );
	}

	$plan_id = sanitize_key( wp_unslash( $_POST['plan_id'] ?? '' ) );
	$email   = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$prices  = enp_plan_prices();

	if ( ! isset( $prices[ $plan_id ] ) ) {
		wp_send_json_error( 'Invalid plan selected.' );
	}
	if ( ! is_email( $email ) ) {
		wp_send_json_error( 'Please enter a valid email address.' );
	}

	$order = enp_create_razorpay_order_api( $plan_id );
	if ( is_wp_error( $order ) ) {
		wp_send_json_error( $order->get_error_message() );
	}

	global $wpdb;
	$wpdb->insert(
		$wpdb->prefix . 'enp_payments',
		array(
			'email'            => $email,
			'plan_id'          => $plan_id,
			'amount'           => round( $prices[ $plan_id ] / 100, 2 ),
			'currency'         => 'INR',
			'gateway'          => 'razorpay',
			'gateway_order_id' => $order['id'],
			'status'           => 'created',
		),
		array( '%s', '%s', '%f', '%s', '%s', '%s', '%s' )
	);
	$payment_id = (int) $wpdb->insert_id;

	wp_send_json_success( array(
		'order_id'   => $order['id'],
		'key_id'     => ENP_RAZORPAY_KEY_ID,
		'amount'     => $prices[ $plan_id ],
		'currency'   => 'INR',
		'payment_id' => $payment_id,
	) );
}

// ── AJAX: Verify payment signature + activate student ─────────────────────────

add_action( 'wp_ajax_enp_verify_razorpay_payment',        'enp_ajax_verify_razorpay_payment' );
add_action( 'wp_ajax_nopriv_enp_verify_razorpay_payment', 'enp_ajax_verify_razorpay_payment' );

function enp_ajax_verify_razorpay_payment() {
	check_ajax_referer( 'enp_razorpay', 'nonce' );

	if ( ! enp_razorpay_configured() ) {
		wp_send_json_error( 'Payment gateway not configured.' );
	}

	$rzp_order_id   = sanitize_text_field( wp_unslash( $_POST['razorpay_order_id']   ?? '' ) );
	$rzp_payment_id = sanitize_text_field( wp_unslash( $_POST['razorpay_payment_id'] ?? '' ) );
	$rzp_signature  = sanitize_text_field( wp_unslash( $_POST['razorpay_signature']   ?? '' ) );
	$payment_id     = (int) ( $_POST['payment_id'] ?? 0 );
	$email          = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );

	if ( ! $rzp_order_id || ! $rzp_payment_id || ! $rzp_signature || ! $payment_id || ! is_email( $email ) ) {
		wp_send_json_error( 'Missing required payment parameters.' );
	}

	// Verify HMAC-SHA256: signature must equal SHA256(order_id | payment_id) keyed with secret.
	$expected = hash_hmac( 'sha256', $rzp_order_id . '|' . $rzp_payment_id, ENP_RAZORPAY_KEY_SECRET );
	if ( ! hash_equals( $expected, $rzp_signature ) ) {
		wp_send_json_error( 'Payment signature verification failed.' );
	}

	global $wpdb;
	$p   = $wpdb->prefix;
	$pay = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id, plan_id, status FROM {$p}enp_payments WHERE id = %d AND gateway_order_id = %s LIMIT 1",
			$payment_id,
			$rzp_order_id
		)
	);

	if ( ! $pay ) {
		wp_send_json_error( 'Payment record not found.' );
	}

	// Idempotent: if already paid, return success without re-running activation.
	if ( 'paid' === $pay->status ) {
		wp_send_json_success( array( 'student_url' => home_url( '/student/' ) ) );
	}

	// Stamp the gateway payment ID before activating.
	$wpdb->update(
		"{$p}enp_payments",
		array( 'gateway_payment_id' => $rzp_payment_id ),
		array( 'id' => (int) $pay->id ),
		array( '%s' ),
		array( '%d' )
	);

	$result = enp_activate_student( $email, $pay->plan_id, (int) $pay->id );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message() );
	}

	wp_send_json_success( array(
		'message'     => 'Payment verified and account activated.',
		'student_url' => home_url( '/student/' ),
	) );
}
