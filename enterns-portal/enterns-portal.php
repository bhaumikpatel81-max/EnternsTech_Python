<?php
/**
 * Plugin Name: Enterns Portal
 * Plugin URI:  https://enternstech.com
 * Description: Student, mentor, and admin portals for Enterns Tech.
 * Version:     1.6.0
 * Author:      Enterns Tech
 * Text Domain: enterns-portal
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ENP_VERSION',     '1.6.0' );
define( 'ENP_DIR',         plugin_dir_path( __FILE__ ) );
define( 'ENP_URL',         plugin_dir_url( __FILE__ ) );
define( 'ENP_PLUGIN_FILE', __FILE__ );

require_once ENP_DIR . 'includes/config.php';
require_once ENP_DIR . 'includes/install.php';
require_once ENP_DIR . 'includes/email.php';
require_once ENP_DIR . 'includes/roles.php';
require_once ENP_DIR . 'includes/privacy.php';
require_once ENP_DIR . 'includes/mentor-apply.php';
require_once ENP_DIR . 'includes/payments.php';
require_once ENP_DIR . 'includes/student.php';
require_once ENP_DIR . 'includes/shortcodes.php';
require_once ENP_DIR . 'includes/admin-settings.php';

// ── Psychometric module ────────────────────────────────────────────────────
require_once ENP_DIR . 'includes/psy-bank.php';
require_once ENP_DIR . 'includes/psy-install.php';
require_once ENP_DIR . 'includes/psy-resolver.php';
require_once ENP_DIR . 'includes/psy-scorer.php';
require_once ENP_DIR . 'includes/psy-ajax.php';
require_once ENP_DIR . 'includes/psy-shortcode.php';

register_activation_hook( __FILE__, 'enp_activate' );
register_deactivation_hook( __FILE__, 'enp_deactivate' );
