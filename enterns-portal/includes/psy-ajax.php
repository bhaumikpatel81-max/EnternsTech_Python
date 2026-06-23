<?php
/**
 * Psychometric module — AJAX endpoints.
 *
 * All candidate endpoints are nopriv (token is the auth mechanism).
 * Submit is rate-limited (5 attempts per token per hour via transient).
 * Autosave must NOT return scores or answer keys.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Generate assessment link (admin) ──────────────────────────────────────────

add_action( 'wp_ajax_enp_psy_generate_link', 'enp_psy_ajax_generate_link' );

function enp_psy_ajax_generate_link(): void {
	check_ajax_referer( 'enp_portal', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized.' );
	}

	$name      = sanitize_text_field( wp_unslash( $_POST['candidate_name']  ?? '' ) );
	$email     = sanitize_email( wp_unslash( $_POST['candidate_email']     ?? '' ) );
	$phone     = sanitize_text_field( wp_unslash( $_POST['candidate_phone'] ?? '' ) );
	$region    = sanitize_key( wp_unslash( $_POST['region']                ?? 'UK' ) );
	$edu_level = (int) ( $_POST['education_level'] ?? 0 );
	$field     = sanitize_key( wp_unslash( $_POST['field']                  ?? '' ) );
	$pay_ref   = sanitize_text_field( wp_unslash( $_POST['payment_ref']     ?? '' ) );

	$valid_regions = array( 'UK', 'US', 'CA', 'IN', 'AUTO' );
	$valid_fields  = array( 'IT','DATA_AI','BUSINESS','HR','FINANCE','MARKETING','INFRA','INFOSEC','HONORS' );

	if ( $email && ! is_email( $email ) ) {
		wp_send_json_error( 'Invalid email address.' );
	}
	if ( ! in_array( strtoupper( $region ), $valid_regions, true ) ) {
		wp_send_json_error( 'Invalid region.' );
	}
	if ( ! in_array( strtoupper( $field ), $valid_fields, true ) ) {
		wp_send_json_error( 'Please select a field.' );
	}
	if ( $edu_level < 1 || $edu_level > 4 ) {
		wp_send_json_error( 'Please select an education level.' );
	}

	global $wpdb;
	$p = $wpdb->prefix;

	// Auto-region resolution.
	$region_source = 'admin';
	$defaulted     = 0;
	if ( 'AUTO' === strtoupper( $region ) ) {
		// For now: default to UK (no billing-country info at link-creation time).
		$region        = 'UK';
		$region_source = 'auto_default';
		$defaulted     = 1;
	}

	$token = enp_psy_generate_token();
	$wpdb->insert(
		"{$p}psy_assessments",
		array(
			'token'           => $token,
			'candidate_name'  => $name,
			'candidate_email' => $email,
			'candidate_phone' => $phone,
			'region'          => strtoupper( $region ),
			'region_source'   => $region_source,
			'education_level' => $edu_level,
			'field'           => strtoupper( $field ),
			'created_by'      => get_current_user_id(),
			'payment_ref'     => $pay_ref,
			'status'          => 'pending',
			'expires_at'      => enp_psy_token_expiry(),
			'defaulted'       => $defaulted,
		),
		array( '%s','%s','%s','%s','%s','%s','%d','%s','%d','%s','%s','%s','%d' )
	);

	if ( ! $wpdb->insert_id ) {
		wp_send_json_error( 'Could not create assessment. Please try again.' );
	}

	$url = enp_psy_assessment_url( $token );
	wp_send_json_success( array(
		'token'     => $token,
		'url'       => $url,
		'defaulted' => (bool) $defaulted,
		'region'    => strtoupper( $region ),
	) );
}

// ── Email assessment link (admin) ─────────────────────────────────────────────

add_action( 'wp_ajax_enp_psy_email_link', 'enp_psy_ajax_email_link' );

function enp_psy_ajax_email_link(): void {
	check_ajax_referer( 'enp_portal', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized.' );
	}

	$token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
	if ( ! $token ) {
		wp_send_json_error( 'No token.' );
	}

	global $wpdb;
	$p  = $wpdb->prefix;
	$a  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$p}psy_assessments WHERE token = %s LIMIT 1", $token ) );
	if ( ! $a || ! $a->candidate_email ) {
		wp_send_json_error( 'No candidate email on record — enter one first.' );
	}

	$url  = enp_psy_assessment_url( $token );
	$name = $a->candidate_name ?: 'Candidate';
	$days = ENP_PSY_LINK_EXPIRY_DAYS;

	$body  = "<h2 style='color:#22D3EE;margin:0 0 16px'>Your Assessment Link</h2>";
	$body .= "<p>Hi " . esc_html( $name ) . ",</p>";
	$body .= "<p>You have been invited to complete a professional assessment by Enterns Tech. "
	       . "The assessment takes approximately 25–30 minutes.</p>";
	$body .= "<p style='margin:24px 0'><a href='" . esc_url( $url ) . "'"
	       . " style='background:#22D3EE;color:#05080F;padding:12px 28px;border-radius:8px;"
	       . "text-decoration:none;font-weight:700;display:inline-block'>Begin Assessment &rarr;</a></p>";
	$body .= "<p style='color:#94a3b8;font-size:13px'>This link is valid for {$days} days and can only be used once. "
	       . "If you have any questions, please reply to this email.</p>";
	$body .= "<p style='color:#94a3b8;font-size:13px'>Your responses are confidential and used for placement purposes only.</p>";

	if ( function_exists( 'enp_send_mail' ) ) {
		$ok = enp_send_mail( $a->candidate_email, 'Your Enterns Tech Assessment — Action Required', $body, true );
		if ( $ok ) {
			wp_send_json_success( 'Email sent to ' . $a->candidate_email );
		} else {
			wp_send_json_error( 'Mail send failed. Check SMTP settings.' );
		}
	} else {
		wp_send_json_error( 'Mail module not loaded.' );
	}
}

// ── Validate token (candidate — step 1) ──────────────────────────────────────

add_action( 'wp_ajax_enp_psy_validate_token',        'enp_psy_ajax_validate_token' );
add_action( 'wp_ajax_nopriv_enp_psy_validate_token', 'enp_psy_ajax_validate_token' );

function enp_psy_ajax_validate_token(): void {
	check_ajax_referer( 'enp_psy_public', 'nonce' );
	$token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
	$a     = $token ? enp_psy_get_valid_assessment( $token ) : null;
	if ( ! $a ) {
		wp_send_json_error( 'invalid' );
	}

	$meta = enp_psy_section_meta();
	wp_send_json_success( array(
		'assessment_id'   => (int) $a->id,
		'status'          => $a->status,
		'candidate_name'  => $a->candidate_name,
		'candidate_email' => $a->candidate_email,
		'candidate_phone' => $a->candidate_phone,
		'field'           => $a->field,
		'education_level' => (int) $a->education_level,
		'sections'        => count( $meta ),
		'has_paper'       => (bool) $a->selected_items,
	) );
}

// ── Save candidate details (step 3) ───────────────────────────────────────────

add_action( 'wp_ajax_enp_psy_save_details',        'enp_psy_ajax_save_details' );
add_action( 'wp_ajax_nopriv_enp_psy_save_details', 'enp_psy_ajax_save_details' );

function enp_psy_ajax_save_details(): void {
	check_ajax_referer( 'enp_psy_public', 'nonce' );

	$token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
	$a     = $token ? enp_psy_get_valid_assessment( $token ) : null;
	if ( ! $a ) {
		wp_send_json_error( 'Invalid or expired link.' );
	}

	$name      = sanitize_text_field( wp_unslash( $_POST['candidate_name']  ?? '' ) );
	$email     = sanitize_email( wp_unslash( $_POST['candidate_email']     ?? '' ) );
	$phone     = sanitize_text_field( wp_unslash( $_POST['candidate_phone'] ?? '' ) );
	$edu_level = (int) ( $_POST['education_level'] ?? $a->education_level );
	$field     = sanitize_key( wp_unslash( $_POST['field'] ?? $a->field ) );

	if ( ! $name ) wp_send_json_error( 'Full name is required.' );
	if ( ! is_email( $email ) ) wp_send_json_error( 'Valid email is required.' );
	if ( ! $phone ) wp_send_json_error( 'Contact number is required.' );
	if ( $edu_level < 1 || $edu_level > 4 ) wp_send_json_error( 'Please select your education level.' );

	$valid_fields = array( 'IT','DATA_AI','BUSINESS','HR','FINANCE','MARKETING','INFRA','INFOSEC','HONORS' );
	if ( ! in_array( strtoupper( $field ), $valid_fields, true ) ) {
		wp_send_json_error( 'Please select a field of interest.' );
	}

	global $wpdb;
	$p = $wpdb->prefix;

	$wpdb->update(
		"{$p}psy_assessments",
		array(
			'candidate_name'  => $name,
			'candidate_email' => $email,
			'candidate_phone' => $phone,
			'education_level' => $edu_level,
			'field'           => strtoupper( $field ),
			'status'          => 'in_progress',
		),
		array( 'id' => (int) $a->id ),
		array( '%s','%s','%s','%d','%s','%s' ),
		array( '%d' )
	);

	// Build paper if not already done.
	if ( ! $a->selected_items ) {
		$resolver = new ENP_Psy_Resolver( $a->region, $edu_level, strtoupper( $field ) );
		$resolver->resolve_and_persist( (int) $a->id );
		// Reload.
		$a = enp_psy_get_valid_assessment( $token );
	}

	// Return public paper (no sensitive fields).
	$resolver = new ENP_Psy_Resolver( $a->region, $edu_level, strtoupper( $field ) );
	$paper    = $resolver->rebuild_from_persisted( $a->selected_items, true );

	wp_send_json_success( array(
		'paper'     => $paper,
		'meta'      => enp_psy_section_meta(),
	) );
}

// ── Autosave answers (per-section, during runner) ─────────────────────────────

add_action( 'wp_ajax_enp_psy_autosave',        'enp_psy_ajax_autosave' );
add_action( 'wp_ajax_nopriv_enp_psy_autosave', 'enp_psy_ajax_autosave' );

function enp_psy_ajax_autosave(): void {
	check_ajax_referer( 'enp_psy_public', 'nonce' );

	$token   = sanitize_text_field( wp_unslash( $_POST['token']   ?? '' ) );
	$section = (int) ( $_POST['section'] ?? 0 );
	$answers = $_POST['answers'] ?? array(); // array: item_id => value

	$a = $token ? enp_psy_get_valid_assessment( $token ) : null;
	if ( ! $a ) {
		wp_send_json_error( 'Session expired.' );
	}
	if ( 'submitted' === $a->status ) {
		wp_send_json_error( 'Already submitted.' );
	}

	global $wpdb;
	$p = $wpdb->prefix;

	// Validate item_ids against the persisted paper.
	$persisted  = json_decode( $a->selected_items ?: '{}', true );
	$sec_ids    = $persisted[ $section ] ?? array();
	$allowed    = array_flip( $sec_ids );

	foreach ( $answers as $item_id => $raw_value ) {
		$item_id = sanitize_key( $item_id );
		if ( ! isset( $allowed[ $item_id ] ) ) {
			continue; // Reject items not in the paper.
		}
		// answer_value is JSON-encoded for rank; plain string for everything else.
		$value = is_array( $raw_value )
			? wp_json_encode( array_map( 'intval', $raw_value ) )
			: sanitize_text_field( wp_unslash( (string) $raw_value ) );

		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$p}psy_responses WHERE assessment_id = %d AND item_id = %s LIMIT 1",
			(int) $a->id,
			$item_id
		) );
		if ( $exists ) {
			$wpdb->update(
				"{$p}psy_responses",
				array( 'answer_value' => $value ),
				array( 'assessment_id' => (int) $a->id, 'item_id' => $item_id ),
				array( '%s' ),
				array( '%d', '%s' )
			);
		} else {
			$wpdb->insert(
				"{$p}psy_responses",
				array(
					'assessment_id' => (int) $a->id,
					'item_id'       => $item_id,
					'section'       => $section,
					'answer_value'  => $value,
				),
				array( '%d','%s','%d','%s' )
			);
		}
	}

	// CRITICAL: Return only {status:"ok"} — no answers, no scores.
	wp_send_json_success( array( 'status' => 'ok' ) );
}

// ── Submit assessment ─────────────────────────────────────────────────────────

add_action( 'wp_ajax_enp_psy_submit',        'enp_psy_ajax_submit' );
add_action( 'wp_ajax_nopriv_enp_psy_submit', 'enp_psy_ajax_submit' );

function enp_psy_ajax_submit(): void {
	check_ajax_referer( 'enp_psy_public', 'nonce' );

	$token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
	if ( ! $token ) {
		wp_send_json_error( 'Missing token.' );
	}

	// Rate limit: max 5 submit attempts per token per hour.
	$rate_key = 'enp_psy_submit_' . md5( $token );
	$attempts = (int) get_transient( $rate_key );
	if ( $attempts >= 5 ) {
		wp_send_json_error( 'Too many attempts. Please wait before trying again.' );
	}
	set_transient( $rate_key, $attempts + 1, HOUR_IN_SECONDS );

	global $wpdb;
	$p = $wpdb->prefix;

	// Re-validate server-side.
	$a = enp_psy_get_valid_assessment( $token );
	if ( ! $a ) {
		wp_send_json_error( 'This link is no longer valid.' );
	}
	if ( 'submitted' === $a->status ) {
		// Idempotent: already submitted.
		wp_send_json_success( array( 'status' => 'ok' ) );
	}
	if ( ! $a->selected_items ) {
		wp_send_json_error( 'Assessment paper not found.' );
	}

	// Mark submitted.
	$wpdb->update(
		"{$p}psy_assessments",
		array( 'status' => 'submitted' ),
		array( 'id' => (int) $a->id ),
		array( '%s' ),
		array( '%d' )
	);

	// Score.
	$scores = ENP_Psy_Scorer::score( (int) $a->id, $a->selected_items );
	ENP_Psy_Scorer::persist( (int) $a->id, $scores );

	// Email admin.
	enp_psy_email_admin_result( $a, $scores );

	// CRITICAL: Return ONLY {status:"ok"} — no scores, no bands, no feedback.
	wp_send_json_success( array( 'status' => 'ok' ) );
}

// ── Save admin recommendation ─────────────────────────────────────────────────

add_action( 'wp_ajax_enp_psy_save_recommendation', 'enp_psy_ajax_save_recommendation' );

function enp_psy_ajax_save_recommendation(): void {
	check_ajax_referer( 'enp_portal', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized.' );
	}

	$assessment_id = (int) ( $_POST['assessment_id'] ?? 0 );
	$rec           = sanitize_textarea_field( wp_unslash( $_POST['recommendation'] ?? '' ) );

	global $wpdb;
	$wpdb->update(
		"{$wpdb->prefix}psy_scores",
		array( 'recommendation' => $rec ),
		array( 'assessment_id' => $assessment_id ),
		array( '%s' ),
		array( '%d' )
	);

	wp_send_json_success( 'Saved.' );
}

// ── Admin email ───────────────────────────────────────────────────────────────

function enp_psy_email_admin_result( object $assessment, array $scores ): void {
	if ( ! function_exists( 'enp_send_mail' ) ) {
		return;
	}

	$name    = $assessment->candidate_name   ?: 'Unknown';
	$email   = $assessment->candidate_email  ?: '—';
	$phone   = $assessment->candidate_phone  ?: '—';
	$region  = $assessment->region           ?: '—';
	$field   = $assessment->field            ?: '—';
	$edu_map = array( 1 => 'School-leaving', 2 => 'Diploma/Associate', 3 => 'Bachelor', 4 => 'Postgraduate' );
	$edu     = $edu_map[ (int) $assessment->education_level ] ?? $assessment->education_level;

	$top3  = is_array( $scores['motivation_top3'] ) ? implode( ', ', $scores['motivation_top3'] ) : '—';
	$band_fn = function( $v ) use ( $scores ) {
		if ( $v === null ) return '—';
		return ENP_Psy_Scorer::band_label( (float) $v ) . ' (' . round( (float) $v, 1 ) . ')';
	};

	$trait_labels = array( 'C' => 'Conscientiousness', 'E' => 'Extraversion', 'ES' => 'Emotional Stability', 'O' => 'Openness', 'A' => 'Agreeableness' );

	$body  = "<h2 style='color:#22D3EE;margin:0 0 4px'>New Psychometric Result</h2>";
	$body .= "<p style='color:#94a3b8;margin:0 0 20px'>{$name} &middot; {$region} / {$field} / {$edu}</p>";

	$body .= "<table style='width:100%;border-collapse:collapse;font-size:14px'>";
	$body .= "<tr><td style='padding:6px 12px 6px 0;color:#94a3b8'>Name</td><td style='padding:6px 0'>" . esc_html( $name ) . "</td></tr>";
	$body .= "<tr><td style='padding:6px 12px 6px 0;color:#94a3b8'>Email</td><td style='padding:6px 0'>" . esc_html( $email ) . "</td></tr>";
	$body .= "<tr><td style='padding:6px 12px 6px 0;color:#94a3b8'>Phone</td><td style='padding:6px 0'>" . esc_html( $phone ) . "</td></tr>";
	$body .= "<tr><td style='padding:6px 12px 6px 0;color:#94a3b8'>Region</td><td style='padding:6px 0'>" . esc_html( $region ) . "</td></tr>";
	$body .= "<tr><td style='padding:6px 12px 6px 0;color:#94a3b8'>Field</td><td style='padding:6px 0'>" . esc_html( $field ) . "</td></tr>";
	$body .= "<tr><td style='padding:6px 12px 6px 0;color:#94a3b8'>Education</td><td style='padding:6px 0'>" . esc_html( $edu ) . "</td></tr>";
	$body .= "</table>";

	$body .= "<hr style='border:none;border-top:1px solid #1f2937;margin:20px 0'>";
	$body .= "<h3 style='color:#22D3EE;margin:0 0 12px'>Scores</h3>";
	$body .= "<table style='width:100%;border-collapse:collapse;font-size:14px'>";
	$body .= "<tr><td style='padding:6px 12px 6px 0;color:#94a3b8'>Strengths Index</td><td>" . $band_fn( $scores['strengths_index'] ) . "</td></tr>";
	$body .= "<tr><td style='padding:6px 12px 6px 0;color:#94a3b8'>Learning</td><td>" . $band_fn( $scores['learning_index'] ) . "</td></tr>";
	$body .= "<tr><td style='padding:6px 12px 6px 0;color:#94a3b8'>Engagement</td><td>" . $band_fn( $scores['engagement_index'] ) . "</td></tr>";
	foreach ( array( 'c' => 'C', 'e' => 'E', 'es' => 'ES', 'o' => 'O', 'a' => 'A' ) as $key => $trait ) {
		$score_key = 'trait_' . $key;
		$label     = $trait_labels[ $trait ] ?? $trait;
		$body .= "<tr><td style='padding:6px 12px 6px 0;color:#94a3b8'>" . esc_html( $label ) . "</td><td>" . $band_fn( $scores[ $score_key ] ?? null ) . "</td></tr>";
	}
	$body .= "<tr><td style='padding:6px 12px 6px 0;color:#94a3b8'>Reasoning</td><td>" . esc_html( $scores['reasoning_score'] ?? 0 ) . "/6 (" . esc_html( $scores['reasoning_band'] ?? '' ) . ")</td></tr>";
	$body .= "<tr><td style='padding:6px 12px 6px 0;color:#94a3b8'>Top Motivators</td><td>" . esc_html( $top3 ) . "</td></tr>";
	$body .= "<tr><td style='padding:6px 12px 6px 0;color:#94a3b8'>Preference</td><td>" . esc_html( $scores['preference_profile'] ?? '' ) . "</td></tr>";
	$body .= "<tr><td style='padding:6px 12px 6px 0;color:#94a3b8'>Overall Band</td><td><strong>" . esc_html( $scores['overall_band'] ?? '' ) . "</strong></td></tr>";
	$body .= "</table>";

	// Open responses.
	$open = is_array( $scores['open_responses'] ) ? $scores['open_responses'] : array();
	if ( ! empty( $open ) ) {
		$body .= "<hr style='border:none;border-top:1px solid #1f2937;margin:20px 0'>";
		$body .= "<h3 style='color:#22D3EE;margin:0 0 12px'>Open Responses</h3>";
		foreach ( $open as $resp ) {
			$body .= "<p style='color:#94a3b8;margin:0 0 4px;font-size:13px'>" . esc_html( $resp['question'] ?? '' ) . "</p>";
			$body .= "<p style='margin:0 0 16px'>" . nl2br( esc_html( $resp['answer'] ?? '' ) ) . "</p>";
		}
	}

	$admin_url = admin_url( 'admin.php?page=enp-settings' );
	$body .= "<p style='margin-top:24px'><a href='" . esc_url( $admin_url ) . "'"
	       . " style='background:#22D3EE;color:#05080F;padding:10px 20px;border-radius:8px;"
	       . "text-decoration:none;font-weight:700;display:inline-block'>View in Admin Portal</a></p>";

	$subject = sprintf( 'New Psychometric Result — %s (%s/%s)', $name, $region, $field );
	enp_send_mail( 'admin@enternstech.com', $subject, $body, true );
}
