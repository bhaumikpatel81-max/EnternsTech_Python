<?php
/**
 * Psychometric module — DB tables, seed, page creation.
 * Called from enp_activate() in install.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ENP_PSY_LINK_EXPIRY_DAYS', 7 );

// ── Table creation ─────────────────────────────────────────────────────────────

function enp_psy_create_tables(): void {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$c = $wpdb->get_charset_collate();
	$p = $wpdb->prefix;

	// psy_items — imported question bank (correct + reverse_scored are server-side only)
	dbDelta( "CREATE TABLE {$p}psy_items (
  id               bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  item_id          varchar(20)  NOT NULL DEFAULT '',
  section          tinyint(3)   unsigned NOT NULL DEFAULT 0,
  type             varchar(20)  NOT NULL DEFAULT '',
  region           varchar(20)  NOT NULL DEFAULT 'ALL',
  edu_min          varchar(10)  NOT NULL DEFAULT 'ALL',
  edu_max          varchar(10)  NOT NULL DEFAULT 'ALL',
  field            varchar(30)  NOT NULL DEFAULT 'ALL',
  difficulty       tinyint(3)   unsigned DEFAULT NULL,
  reverse_scored   varchar(1)   NOT NULL DEFAULT 'N',
  trait_or_cluster varchar(30)  NOT NULL DEFAULT '',
  question_text    text         NOT NULL,
  option_a         varchar(500) NOT NULL DEFAULT '',
  option_b         varchar(500) NOT NULL DEFAULT '',
  option_c         varchar(500) NOT NULL DEFAULT '',
  option_d         varchar(500) NOT NULL DEFAULT '',
  correct          varchar(500) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  UNIQUE KEY item_id (item_id),
  KEY section (section),
  KEY region  (region),
  KEY field   (field)
) $c;" );

	// psy_assessments — one row per generated link / assessment session
	dbDelta( "CREATE TABLE {$p}psy_assessments (
  id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  token           varchar(64)  NOT NULL DEFAULT '',
  candidate_name  varchar(200) NOT NULL DEFAULT '',
  candidate_email varchar(200) NOT NULL DEFAULT '',
  candidate_phone varchar(30)  NOT NULL DEFAULT '',
  region          varchar(10)  NOT NULL DEFAULT 'UK',
  region_source   varchar(20)  NOT NULL DEFAULT 'admin',
  education_level tinyint(3)   unsigned NOT NULL DEFAULT 0,
  field           varchar(30)  NOT NULL DEFAULT '',
  created_by      bigint(20)   unsigned DEFAULT NULL,
  payment_ref     varchar(200) NOT NULL DEFAULT '',
  status          varchar(20)  NOT NULL DEFAULT 'pending',
  expires_at      datetime     NOT NULL,
  selected_items  longtext     DEFAULT NULL,
  defaulted       tinyint(1)   NOT NULL DEFAULT 0,
  razorpay_auto   tinyint(1)   NOT NULL DEFAULT 0,
  created_at      datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  UNIQUE KEY token (token),
  KEY status (status),
  KEY region (region),
  KEY field  (field)
) $c;" );

	// psy_responses — one row per item answered
	dbDelta( "CREATE TABLE {$p}psy_responses (
  id            bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  assessment_id bigint(20) unsigned NOT NULL,
  item_id       varchar(20) NOT NULL DEFAULT '',
  section       tinyint(3)  unsigned NOT NULL DEFAULT 0,
  answer_value  longtext    NOT NULL,
  created_at    datetime    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    datetime    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  UNIQUE KEY assessment_item (assessment_id, item_id),
  KEY assessment_id (assessment_id),
  KEY section (section)
) $c;" );

	// psy_scores — computed results (admin-only; never exposed to candidate)
	dbDelta( "CREATE TABLE {$p}psy_scores (
  id                bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  assessment_id     bigint(20) unsigned NOT NULL,
  strengths_index   decimal(5,2) DEFAULT NULL,
  strengths_clusters longtext    DEFAULT NULL,
  preference_profile varchar(500) NOT NULL DEFAULT '',
  learning_index    decimal(5,2) DEFAULT NULL,
  motivation_top3   longtext    DEFAULT NULL,
  engagement_index  decimal(5,2) DEFAULT NULL,
  trait_c           decimal(5,2) DEFAULT NULL,
  trait_e           decimal(5,2) DEFAULT NULL,
  trait_es          decimal(5,2) DEFAULT NULL,
  trait_o           decimal(5,2) DEFAULT NULL,
  trait_a           decimal(5,2) DEFAULT NULL,
  reasoning_score   tinyint(3)  unsigned DEFAULT NULL,
  reasoning_band    varchar(20) NOT NULL DEFAULT '',
  open_responses    longtext    DEFAULT NULL,
  overall_band      varchar(20) NOT NULL DEFAULT '',
  recommendation    text        DEFAULT NULL,
  computed_at       datetime    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  UNIQUE KEY assessment_id (assessment_id)
) $c;" );
}

// ── Seed psy_items from bank ───────────────────────────────────────────────────

function enp_psy_seed_items(): void {
	global $wpdb;
	$p = $wpdb->prefix;

	// Idempotent: skip if already seeded.
	if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}psy_items" ) > 0 ) {
		return;
	}

	if ( ! function_exists( 'enp_psy_question_bank' ) ) {
		require_once ENP_DIR . 'includes/psy-bank.php';
	}

	$bank = enp_psy_question_bank();
	foreach ( $bank as $item ) {
		$wpdb->insert(
			"{$p}psy_items",
			array(
				'item_id'          => $item['item_id'],
				'section'          => (int) $item['section'],
				'type'             => (string) $item['type'],
				'region'           => (string) $item['region'],
				'edu_min'          => (string) $item['edu_min'],
				'edu_max'          => (string) $item['edu_max'],
				'field'            => (string) $item['field'],
				'difficulty'       => ( $item['difficulty'] !== null && $item['difficulty'] !== '' )
				                        ? (int) $item['difficulty'] : null,
				'reverse_scored'   => (string) $item['reverse_scored'],
				'trait_or_cluster' => (string) $item['trait_or_cluster'],
				'question_text'    => (string) $item['question_text'],
				'option_a'         => (string) $item['option_a'],
				'option_b'         => (string) $item['option_b'],
				'option_c'         => (string) $item['option_c'],
				'option_d'         => (string) $item['option_d'],
				'correct'          => (string) $item['correct'],
			),
			array( '%s','%d','%s','%s','%s','%s','%s','%d','%s','%s','%s','%s','%s','%s','%s','%s' )
		);
	}
}

// ── Create WordPress page for candidate assessment ─────────────────────────────

function enp_psy_create_page(): void {
	$existing = get_page_by_path( 'psy-assessment' );
	if ( $existing ) {
		return;
	}
	wp_insert_post( array(
		'post_type'    => 'page',
		'post_title'   => 'Psychometric Assessment',
		'post_name'    => 'psy-assessment',
		'post_content' => '[enp_psychometric]',
		'post_status'  => 'publish',
	) );
}

// ── Token helpers ──────────────────────────────────────────────────────────────

function enp_psy_generate_token(): string {
	return bin2hex( random_bytes( 32 ) );
}

function enp_psy_token_expiry(): string {
	return gmdate( 'Y-m-d H:i:s', time() + ENP_PSY_LINK_EXPIRY_DAYS * DAY_IN_SECONDS );
}

/**
 * Look up a valid (not expired, not submitted) assessment by token.
 * Returns the DB row or null.
 */
function enp_psy_get_valid_assessment( string $token ): ?object {
	global $wpdb;
	$p = $wpdb->prefix;
	return $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM {$p}psy_assessments
		 WHERE token = %s AND expires_at > %s AND status != 'submitted'
		 LIMIT 1",
		$token,
		current_time( 'mysql', true )
	) );
}

/**
 * Look up any assessment by token (including expired/submitted).
 */
function enp_psy_get_assessment_by_token( string $token ): ?object {
	global $wpdb;
	$p = $wpdb->prefix;
	return $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM {$p}psy_assessments WHERE token = %s LIMIT 1",
		$token
	) );
}

// ── Candidate assessment URL ───────────────────────────────────────────────────

function enp_psy_assessment_url( string $token ): string {
	return add_query_arg( 't', $token, home_url( '/psy-assessment/' ) );
}

// ── Section metadata (counts required + labels) ───────────────────────────────

function enp_psy_section_meta(): array {
	return array(
		1 => array( 'label' => 'Professional Strengths',       'count' => 12, 'type' => 'likert' ),
		2 => array( 'label' => 'Work Preferences',             'count' => 10, 'type' => 'forced_choice' ),
		3 => array( 'label' => 'Learning Orientation',         'count' => 8,  'type' => 'likert' ),
		4 => array( 'label' => 'What Motivates You',           'count' => 10, 'type' => 'rank' ),
		5 => array( 'label' => 'Workplace Engagement',         'count' => 8,  'type' => 'likert' ),
		6 => array( 'label' => 'Personal Style',               'count' => 15, 'type' => 'likert' ),
		7 => array( 'label' => 'Reasoning',                    'count' => 6,  'type' => 'mcq' ),
		8 => array( 'label' => 'Open Reflection',              'count' => 4,  'type' => 'open' ),
	);
}

// ── Content-gap logger ─────────────────────────────────────────────────────────

function enp_psy_log_content_gap( string $msg ): void {
	if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[ENP_PSY content-gap] ' . $msg );
	}
	// Also store in WP options (rolling list of last 20).
	$gaps   = (array) get_option( 'enp_psy_content_gaps', array() );
	$gaps[] = array( 'msg' => $msg, 'time' => current_time( 'mysql' ) );
	if ( count( $gaps ) > 20 ) {
		$gaps = array_slice( $gaps, -20 );
	}
	update_option( 'enp_psy_content_gaps', $gaps, false );
}
