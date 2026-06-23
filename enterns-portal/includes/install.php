<?php
/**
 * Activation / deactivation hooks: tables, roles, pages.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function enp_activate() {
	enp_create_tables();
	enp_create_roles();
	enp_create_pages();
	// Psychometric module.
	enp_psy_create_tables();
	enp_psy_seed_items();
	enp_psy_create_page();
	flush_rewrite_rules();
}

function enp_deactivate() {
	flush_rewrite_rules();
}

// ── Tables ────────────────────────────────────────────────────────────────────

function enp_create_tables() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$c = $wpdb->get_charset_collate();
	$p = $wpdb->prefix;

	// wp_enp_mentors
	dbDelta( "CREATE TABLE {$p}enp_mentors (
  id               bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  full_name        varchar(200) NOT NULL DEFAULT '',
  email            varchar(200) NOT NULL DEFAULT '',
  phone            varchar(30) NOT NULL DEFAULT '',
  linkedin         varchar(500) NOT NULL DEFAULT '',
  photo_url        varchar(500) NOT NULL DEFAULT '',
  tech_stack       text,
  available_slots  tinyint(3) unsigned NOT NULL DEFAULT 0,
  rate_per_session decimal(10,2) NOT NULL DEFAULT 500.00,
  extra_fields     longtext,
  status           varchar(20) NOT NULL DEFAULT 'pending',
  admin_note       text,
  user_id          bigint(20) unsigned DEFAULT NULL,
  created_at       datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY status (status),
  KEY user_id (user_id)
) $c;" );

	// wp_enp_students
	dbDelta( "CREATE TABLE {$p}enp_students (
  id                 bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  full_name          varchar(200) NOT NULL DEFAULT '',
  email              varchar(200) NOT NULL DEFAULT '',
  phone              varchar(30) NOT NULL DEFAULT '',
  college            varchar(200) NOT NULL DEFAULT '',
  tech_stack         text,
  cv_url             varchar(500) NOT NULL DEFAULT '',
  live_project       varchar(500) NOT NULL DEFAULT '',
  plan_id            varchar(50) NOT NULL DEFAULT '',
  sessions_total     tinyint(3) unsigned NOT NULL DEFAULT 4,
  sessions_used      tinyint(3) unsigned NOT NULL DEFAULT 0,
  mentor_id          bigint(20) unsigned DEFAULT NULL,
  cv_redesign_status varchar(20) NOT NULL DEFAULT 'pending',
  status             varchar(20) NOT NULL DEFAULT 'pending',
  user_id            bigint(20) unsigned DEFAULT NULL,
  created_at         datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY status (status),
  KEY mentor_id (mentor_id),
  KEY user_id (user_id)
) $c;" );

	// wp_enp_sessions
	dbDelta( "CREATE TABLE {$p}enp_sessions (
  id           bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  student_id   bigint(20) unsigned NOT NULL,
  mentor_id    bigint(20) unsigned NOT NULL,
  scheduled_at datetime NOT NULL,
  duration_min smallint(5) unsigned NOT NULL DEFAULT 60,
  status       varchar(20) NOT NULL DEFAULT 'planned',
  mentor_paid  tinyint(1) NOT NULL DEFAULT 0,
  rate_applied decimal(10,2) NOT NULL DEFAULT 500.00,
  notes        text,
  created_at   datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY student_id (student_id),
  KEY mentor_id (mentor_id),
  KEY status (status)
) $c;" );

	// wp_enp_payments
	dbDelta( "CREATE TABLE {$p}enp_payments (
  id                 bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  student_id         bigint(20) unsigned DEFAULT NULL,
  email              varchar(200) NOT NULL DEFAULT '',
  plan_id            varchar(50) NOT NULL DEFAULT '',
  amount             decimal(10,2) NOT NULL DEFAULT 0.00,
  currency           varchar(3) NOT NULL DEFAULT 'INR',
  gateway            varchar(20) NOT NULL DEFAULT 'razorpay',
  gateway_order_id   varchar(100) NOT NULL DEFAULT '',
  gateway_payment_id varchar(100) NOT NULL DEFAULT '',
  status             varchar(20) NOT NULL DEFAULT 'created',
  created_at         datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY student_id (student_id),
  KEY status (status),
  KEY gateway_order_id (gateway_order_id)
) $c;" );

	// wp_enp_requests
	dbDelta( "CREATE TABLE {$p}enp_requests (
  id         bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  type       varchar(30) NOT NULL DEFAULT '',
  student_id bigint(20) unsigned NOT NULL,
  mentor_id  bigint(20) unsigned DEFAULT NULL,
  payload    longtext,
  status     varchar(20) NOT NULL DEFAULT 'open',
  admin_note text,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY student_id (student_id),
  KEY status (status)
) $c;" );

	// wp_enp_feedback
	dbDelta( "CREATE TABLE {$p}enp_feedback (
  id         bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  session_id bigint(20) unsigned NOT NULL,
  from_role  varchar(10) NOT NULL DEFAULT '',
  from_id    bigint(20) unsigned NOT NULL,
  about_id   bigint(20) unsigned NOT NULL,
  rating     tinyint(1) unsigned DEFAULT NULL,
  comments   text,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY session_id (session_id),
  KEY about_id (about_id)
) $c;" );

	update_option( 'enp_db_version', ENP_VERSION );
}

// ── Roles ─────────────────────────────────────────────────────────────────────

function enp_create_roles() {
	add_role(
		'et_mentor',
		__( 'Mentor', 'enterns-portal' ),
		array( 'read' => true )
	);

	add_role(
		'et_student',
		__( 'Student', 'enterns-portal' ),
		array( 'read' => true )
	);
}

// ── Pages ─────────────────────────────────────────────────────────────────────

function enp_create_pages() {
	$pages = array(
		array(
			'post_title'   => 'ET Admin',
			'post_name'    => 'et-admin',
			'post_content' => '[enp_admin]',
		),
		array(
			'post_title'   => 'Mentor Portal',
			'post_name'    => 'mentor',
			'post_content' => '[enp_mentor]',
		),
		array(
			'post_title'   => 'Student Portal',
			'post_name'    => 'student',
			'post_content' => '[enp_student]',
		),
		array(
			'post_title'   => 'Partner with Us',
			'post_name'    => 'partner-with-us',
			'post_content' => '[enp_partner_form]',
		),
	);

	foreach ( $pages as $page ) {
		$existing = get_page_by_path( $page['post_name'] );
		if ( $existing ) {
			continue;
		}
		wp_insert_post( array(
			'post_type'    => 'page',
			'post_title'   => $page['post_title'],
			'post_name'    => $page['post_name'],
			'post_content' => $page['post_content'],
			'post_status'  => 'publish',
		) );
	}
}
