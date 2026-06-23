<?php
/**
 * Enterns Tech — Admin Portal
 * Accessible only at /admin-portal/ — never linked from the main site.
 */

ob_start(); // buffer output so header() calls work after any early output

session_start();
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

// ── Config check ─────────────────────────────────────────────────────────────
if (!file_exists(__DIR__ . '/config.php')) {
    http_response_code(503);
    die(render_setup_page());
}
require_once __DIR__ . '/config.php';

// ── WordPress bootstrap ───────────────────────────────────────────────────────
// Loads wp_create_user(), get_password_reset_key(), enp_send_mail(), etc.
$_enp_wp = dirname(__DIR__) . '/wp-load.php';
if (!defined('ABSPATH') && file_exists($_enp_wp)) {
    require_once $_enp_wp;
}
unset($_enp_wp);

// ── DB connection (lazy) ──────────────────────────────────────────────────────
$pdo = null;
function get_db(): PDO {
    global $pdo;
    if ($pdo) return $pdo;
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        create_tables($pdo);
    } catch (PDOException $e) {
        die('<div style="font:14px monospace;padding:2rem;color:#f87171;background:#0a0e1a">DB Error: ' . htmlspecialchars($e->getMessage()) . '</div>');
    }
    return $pdo;
}

function create_tables(PDO $db): void {
    $p = DB_PREFIX;
    $db->exec("CREATE TABLE IF NOT EXISTS `{$p}et_transactions` (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        order_id   VARCHAR(60)     NOT NULL,
        plan       VARCHAR(120)    DEFAULT '',
        amount     DECIMAL(10,2)   NOT NULL,
        currency   VARCHAR(3)      DEFAULT 'USD',
        status     VARCHAR(20)     DEFAULT 'COMPLETED',
        payer_name VARCHAR(200)    DEFAULT '',
        payer_email VARCHAR(200)   DEFAULT '',
        created_at DATETIME        DEFAULT CURRENT_TIMESTAMP,
        INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $db->exec("CREATE TABLE IF NOT EXISTS `{$p}et_revenue_manual` (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        entry_date  DATE            NOT NULL,
        amount      DECIMAL(10,2)   NOT NULL,
        description VARCHAR(500)    DEFAULT '',
        category    VARCHAR(100)    DEFAULT 'Other',
        created_at  DATETIME        DEFAULT CURRENT_TIMESTAMP,
        INDEX (entry_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

// ── Actions ───────────────────────────────────────────────────────────────────
$action = $_POST['action'] ?? '';
$error  = '';

// Login
if ($action === 'login') {
    if (isset($_POST['password']) && $_POST['password'] === ADMIN_PASSWORD) {
        session_regenerate_id(true);
        $_SESSION['et_auth']    = true;
        $_SESSION['et_time']    = time();
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
    $error = 'Incorrect password. Please try again.';
    sleep(1);
}

// Logout
if ($action === 'logout') {
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Session timeout: 8 hours
if (!empty($_SESSION['et_auth']) && (time() - ($_SESSION['et_time'] ?? 0)) > 28800) {
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$logged_in = !empty($_SESSION['et_auth']);

// CSRF token for mentor admin actions — one per login session
if ($logged_in && empty($_SESSION['enp_csrf'])) {
    $_SESSION['enp_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $logged_in ? ($_SESSION['enp_csrf'] ?? '') : '';

// Add manual revenue
if ($logged_in && $action === 'add_revenue') {
    $db   = get_db();
    $stmt = $db->prepare('INSERT INTO `' . DB_PREFIX . 'et_revenue_manual` (entry_date, amount, description, category) VALUES (?, ?, ?, ?)');
    $stmt->execute([
        $_POST['entry_date'] ?? date('Y-m-d'),
        abs(floatval($_POST['amount'] ?? 0)),
        substr(strip_tags($_POST['description'] ?? ''), 0, 500),
        substr(strip_tags($_POST['category']    ?? 'Other'), 0, 100),
    ]);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?section=manual&added=1');
    exit;
}

// Delete manual revenue
if ($logged_in && $action === 'delete_revenue') {
    $db   = get_db();
    $stmt = $db->prepare('DELETE FROM `' . DB_PREFIX . 'et_revenue_manual` WHERE id = ?');
    $stmt->execute([intval($_POST['entry_id'] ?? 0)]);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?section=manual');
    exit;
}

// Delete PayPal transaction
if ($logged_in && $action === 'delete_transaction') {
    $db   = get_db();
    $stmt = $db->prepare('DELETE FROM `' . DB_PREFIX . 'et_transactions` WHERE id = ?');
    $stmt->execute([intval($_POST['tx_id'] ?? 0)]);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?section=transactions');
    exit;
}

// ── Mentor admin actions (WordPress required) ─────────────────────────────────

// Approve mentor: create WP user, link user_id, send set-password email
if ($logged_in && $action === 'approve_mentor') {
    if (!isset($_SESSION['enp_csrf']) || !hash_equals($_SESSION['enp_csrf'], $_POST['csrf'] ?? '')) {
        http_response_code(403); die('CSRF mismatch.');
    }
    if (!function_exists('wp_create_user')) {
        die('WordPress not loaded. Ensure /wp-load.php exists at ' . htmlspecialchars(dirname(__DIR__), ENT_QUOTES));
    }
    $mid = intval($_POST['mentor_id'] ?? 0);
    $db  = get_db(); $p = DB_PREFIX;
    $st  = $db->prepare("SELECT * FROM `{$p}enp_mentors` WHERE id = ? LIMIT 1");
    $st->execute([$mid]);
    $mentor = $st->fetch();
    if (!$mentor) { header('Location: ?section=applications&err=' . urlencode('Mentor not found.')); exit; }
    if ($mentor['status'] === 'approved') { header('Location: ?section=applications&err=' . urlencode('Already approved.')); exit; }

    // Find or create WP user
    $existing = get_user_by('email', $mentor['email']);
    if ($existing) {
        $wp_uid  = $existing->ID;
        $wp_user = $existing;
    } else {
        $parts   = explode(' ', strtolower(trim($mentor['full_name'])), 2);
        $base    = sanitize_user(implode('.', array_map('trim', $parts)), true);
        if (!$base) $base = sanitize_user(strstr($mentor['email'], '@', true), true);
        $login = $base; $n = 1;
        while (username_exists($login)) { $login = $base . $n++; }
        $wp_uid = wp_create_user($login, wp_generate_password(24, true, true), $mentor['email']);
        if (is_wp_error($wp_uid)) {
            header('Location: ?section=applications&err=' . urlencode($wp_uid->get_error_message())); exit;
        }
        $wp_user = new WP_User($wp_uid);
        wp_update_user([
            'ID'           => $wp_uid,
            'display_name' => $mentor['full_name'],
            'first_name'   => ucfirst($parts[0]),
            'last_name'    => isset($parts[1]) ? ucfirst(trim($parts[1])) : '',
        ]);
    }
    $wp_user->set_role('et_mentor');

    // Build set-password link using WP native reset-key flow
    $key       = get_password_reset_key($wp_user);
    $reset_url = !is_wp_error($key)
        ? add_query_arg(['action' => 'rp', 'key' => $key, 'login' => rawurlencode($wp_user->user_login)], wp_login_url())
        : '';

    // Link DB row to WP user
    $db->prepare("UPDATE `{$p}enp_mentors` SET status='approved', user_id=? WHERE id=?")->execute([$wp_uid, $mid]);

    // Send approval email with set-password link
    if (function_exists('enp_send_mail')) {
        $fname = esc_html(explode(' ', $mentor['full_name'])[0]);
        $body  = "<h2 style='color:#22D3EE;margin:0 0 16px'>Welcome, {$fname}!</h2>";
        $body .= "<p>Your mentor application has been <strong style='color:#4ade80'>approved</strong>.</p>";
        if ($reset_url) {
            $body .= "<p style='margin:24px 0'><a href='" . esc_url($reset_url) . "' style='background:#22D3EE;color:#05080F;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;display:inline-block'>Set Your Password</a></p>";
            $body .= "<p style='color:#94a3b8;font-size:13px'>This link expires in 24 hours. After that, use <em>Forgot Password</em> on the login page.</p>";
        }
        $body .= "<p style='color:#94a3b8;font-size:13px'>Mentor portal: <a href='" . esc_url(home_url('/mentor/')) . "' style='color:#22D3EE'>" . esc_html(home_url('/mentor/')) . "</a></p>";
        enp_send_mail($mentor['email'], 'Welcome to Enterns Tech — Set your password', $body, true);
    }
    header('Location: ?section=applications&approved=1'); exit;
}

// Reject mentor
if ($logged_in && $action === 'reject_mentor') {
    if (!isset($_SESSION['enp_csrf']) || !hash_equals($_SESSION['enp_csrf'], $_POST['csrf'] ?? '')) {
        http_response_code(403); die('CSRF mismatch.');
    }
    $mid        = intval($_POST['mentor_id'] ?? 0);
    $admin_note = substr(strip_tags(wp_unslash($_POST['admin_note'] ?? '')), 0, 1000);
    $db = get_db(); $p = DB_PREFIX;
    $db->prepare("UPDATE `{$p}enp_mentors` SET status='rejected', admin_note=? WHERE id=? AND status != 'approved'")->execute([$admin_note, $mid]);
    $st = $db->prepare("SELECT full_name, email FROM `{$p}enp_mentors` WHERE id=?");
    $st->execute([$mid]);
    $mentor = $st->fetch();
    if ($mentor && function_exists('enp_send_mail')) {
        $name  = esc_html($mentor['full_name']);
        $body  = "<h2 style='color:#22D3EE;margin:0 0 16px'>Application Update</h2>";
        $body .= "<p>Thank you for your interest in mentoring at Enterns Tech, {$name}.</p>";
        $body .= "<p>After reviewing your application, we are unable to move forward at this time.</p>";
        if ($admin_note) {
            $body .= "<p><strong>Feedback from our team:</strong><br>" . nl2br(esc_html($admin_note)) . "</p>";
        }
        $body .= "<p>You are welcome to apply again in the future. Thank you for your time.</p>";
        enp_send_mail($mentor['email'], 'Your Enterns Tech mentor application', $body, true);
    }
    header('Location: ?section=applications&rejected=1'); exit;
}

// Request more info
if ($logged_in && $action === 'request_info') {
    if (!isset($_SESSION['enp_csrf']) || !hash_equals($_SESSION['enp_csrf'], $_POST['csrf'] ?? '')) {
        http_response_code(403); die('CSRF mismatch.');
    }
    $mid        = intval($_POST['mentor_id'] ?? 0);
    $admin_note = substr(strip_tags(wp_unslash($_POST['admin_note'] ?? '')), 0, 1000);
    $db = get_db(); $p = DB_PREFIX;
    $db->prepare("UPDATE `{$p}enp_mentors` SET status='info_requested', admin_note=? WHERE id=?")->execute([$admin_note, $mid]);
    $st = $db->prepare("SELECT full_name, email FROM `{$p}enp_mentors` WHERE id=?");
    $st->execute([$mid]);
    $mentor = $st->fetch();
    if ($mentor && function_exists('enp_send_mail')) {
        $name  = esc_html($mentor['full_name']);
        $body  = "<h2 style='color:#22D3EE;margin:0 0 16px'>Additional Information Needed</h2>";
        $body .= "<p>Thank you for applying to Enterns Tech, {$name}.</p>";
        $body .= "<p>Before we can proceed, we need the following:</p>";
        $body .= "<blockquote style='border-left:3px solid #22D3EE;padding-left:1rem;margin:1rem 0;color:#94a3b8'>" . nl2br(esc_html($admin_note)) . "</blockquote>";
        $body .= "<p>Please reply to this email with the requested details.</p>";
        enp_send_mail($mentor['email'], 'Additional information needed for your Enterns Tech application', $body, true);
    }
    header('Location: ?section=applications&info_sent=1'); exit;
}

// Edit mentor: rate per session, available slots, custom fields JSON
if ($logged_in && $action === 'edit_mentor') {
    if (!isset($_SESSION['enp_csrf']) || !hash_equals($_SESSION['enp_csrf'], $_POST['csrf'] ?? '')) {
        http_response_code(403); die('CSRF mismatch.');
    }
    $mid       = intval($_POST['mentor_id'] ?? 0);
    $rate      = max(0.0, (float) ($_POST['rate_per_session'] ?? 0));
    $slots     = max(1, min(40, intval($_POST['available_slots'] ?? 1)));
    $raw_extra = function_exists('wp_unslash') ? wp_unslash($_POST['extra_fields'] ?? '{}') : ($_POST['extra_fields'] ?? '{}');
    $decoded   = json_decode($raw_extra, true);
    if (!is_array($decoded)) $decoded = [];
    $clean = [];
    foreach ($decoded as $k => $v) {
        $ck = substr(trim(strip_tags((string)$k)), 0, 100);
        $cv = substr(trim(strip_tags((string)$v)), 0, 500);
        if ($ck !== '') $clean[$ck] = $cv;
    }
    $db = get_db(); $p = DB_PREFIX;
    $db->prepare("UPDATE `{$p}enp_mentors` SET rate_per_session=?, available_slots=?, extra_fields=? WHERE id=? AND status='approved'")
       ->execute([$rate, $slots, json_encode($clean), $mid]);
    header('Location: ?section=mentors&updated=1'); exit;
}

// Manual mark student paid (CSRF-protected, runs enp_activate_student via WP)
if ($logged_in && $action === 'mark_student_paid') {
    if (!isset($_SESSION['enp_csrf']) || !hash_equals($_SESSION['enp_csrf'], $_POST['csrf'] ?? '')) {
        http_response_code(403); die('CSRF mismatch.');
    }
    if (!function_exists('enp_activate_student')) {
        header('Location: ?section=payments&err=' . urlencode('Plugin function not available. Is the enterns-portal plugin active?')); exit;
    }
    $email   = isset($_POST['email'])   ? filter_var(trim($_POST['email']),   FILTER_VALIDATE_EMAIL) : false;
    $plan_id = isset($_POST['plan_id']) ? trim(strip_tags($_POST['plan_id'])) : '';
    $amount  = isset($_POST['amount'])  ? max(0.0, (float) $_POST['amount'])  : 0.0;
    $valid_plans = ['basic', 'elite', 'premium', 'accelerator', 'starter'];

    if (!$email) {
        header('Location: ?section=payments&err=' . urlencode('Invalid email address.')); exit;
    }
    if (!in_array($plan_id, $valid_plans, true)) {
        header('Location: ?section=payments&err=' . urlencode('Invalid plan selected.')); exit;
    }
    if ($amount <= 0.0) {
        header('Location: ?section=payments&err=' . urlencode('Amount must be greater than zero.')); exit;
    }

    $db = get_db(); $p = DB_PREFIX;

    // Insert manual payment row; activation will flip it to 'paid'.
    $db->prepare("INSERT INTO `{$p}enp_payments` (email, plan_id, amount, currency, gateway, status) VALUES (?, ?, ?, 'INR', 'manual', 'created')")
       ->execute([$email, $plan_id, $amount]);
    $payment_id = (int) $db->lastInsertId();

    $result = enp_activate_student((string)$email, $plan_id, $payment_id);
    if (is_wp_error($result)) {
        header('Location: ?section=payments&err=' . urlencode($result->get_error_message())); exit;
    }
    header('Location: ?section=payments&paid=1'); exit;
}

// Admin: override student sessions_total
if ($logged_in && $action === 'set_student_sessions') {
    if (!isset($_SESSION['enp_csrf']) || !hash_equals($_SESSION['enp_csrf'], $_POST['csrf'] ?? '')) {
        http_response_code(403); die('CSRF mismatch.');
    }
    $sid  = intval($_POST['student_id']    ?? 0);
    $sess = max(1, min(100, intval($_POST['sessions_total'] ?? 1)));
    $db = get_db(); $p = DB_PREFIX;
    $db->prepare("UPDATE `{$p}enp_students` SET sessions_total=? WHERE id=?")->execute([$sess, $sid]);
    header('Location: ?section=students&updated_sessions=1'); exit;
}

// Admin: approve mentor change request
if ($logged_in && $action === 'approve_mentor_change') {
    if (!isset($_SESSION['enp_csrf']) || !hash_equals($_SESSION['enp_csrf'], $_POST['csrf'] ?? '')) {
        http_response_code(403); die('CSRF mismatch.');
    }
    $req_id = intval($_POST['request_id'] ?? 0);
    $db = get_db(); $p = DB_PREFIX;
    $req_st = $db->prepare("SELECT r.*, s.email AS student_email, s.full_name AS student_name, s.id AS sid
        FROM `{$p}enp_requests` r
        JOIN `{$p}enp_students` s ON r.student_id = s.id
        WHERE r.id = ? AND r.type = 'mentor_change' AND r.status = 'open' LIMIT 1");
    $req_st->execute([$req_id]);
    $req = $req_st->fetch();
    if (!$req) { header('Location: ?section=requests&err=' . urlencode('Request not found or already processed.')); exit; }

    $new_mentor_id = $req['mentor_id'] ? (int)$req['mentor_id'] : null;
    $new_mentor_name = null;
    if ($new_mentor_id) {
        $db->prepare("UPDATE `{$p}enp_students` SET mentor_id=? WHERE id=?")->execute([$new_mentor_id, $req['sid']]);
        $nm = $db->prepare("SELECT full_name FROM `{$p}enp_mentors` WHERE id=?");
        $nm->execute([$new_mentor_id]);
        $new_mentor_name = $nm->fetchColumn() ?: null;
    } else {
        $db->prepare("UPDATE `{$p}enp_students` SET mentor_id=NULL WHERE id=?")->execute([$req['sid']]);
    }
    $db->prepare("UPDATE `{$p}enp_requests` SET status='approved' WHERE id=?")->execute([$req_id]);

    if (function_exists('enp_send_mail')) {
        $sname = esc_html($req['student_name']);
        $body  = "<h2 style='color:#22D3EE;margin:0 0 16px'>Mentor Change Approved</h2>";
        $body .= "<p>Hi {$sname}, your mentor change request has been approved.</p>";
        if ($new_mentor_name) {
            $body .= "<p>Your new mentor is <strong>" . esc_html($new_mentor_name) . "</strong>.</p>";
        } else {
            $body .= "<p>Please log in to your student portal to select a new mentor.</p>";
        }
        $purl = function_exists('home_url') ? home_url('/student/') : '/student/';
        $body .= "<p style='margin-top:1.5rem'><a href='" . esc_url($purl) . "' style='background:#22D3EE;color:#05080F;padding:10px 24px;border-radius:8px;text-decoration:none;font-weight:700'>Go to Portal &rarr;</a></p>";
        enp_send_mail($req['student_email'], 'Mentor change approved — Enterns Tech', $body, true);
    }
    header('Location: ?section=requests&approved_change=1'); exit;
}

// ── Psychometric: generate assessment link ────────────────────────────────────
if ($logged_in && $action === 'generate_psy_link') {
    if (!isset($_SESSION['enp_csrf']) || !hash_equals($_SESSION['enp_csrf'], $_POST['csrf'] ?? '')) {
        http_response_code(403); die('CSRF mismatch.');
    }
    if (!function_exists('enp_psy_generate_token') || !function_exists('enp_psy_token_expiry')) {
        header('Location: ?section=assessments&err=' . urlencode('Psychometric module not loaded.')); exit;
    }
    $cand_name  = substr(strip_tags($_POST['candidate_name']  ?? ''), 0, 200);
    $cand_email = filter_var(trim($_POST['candidate_email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $cand_phone = substr(strip_tags($_POST['candidate_phone'] ?? ''), 0, 30);
    $region     = strtoupper(trim(strip_tags($_POST['region']          ?? 'UK')));
    $edu_level  = max(1, min(4, intval($_POST['education_level'] ?? 0)));
    $field      = strtoupper(trim(strip_tags($_POST['field']           ?? '')));
    $pay_ref    = substr(strip_tags($_POST['payment_ref']      ?? ''), 0, 200);

    $valid_regions = ['UK','US','CA','IN','AUTO'];
    $valid_fields  = ['IT','DATA_AI','BUSINESS','HR','FINANCE','MARKETING','INFRA','INFOSEC','HONORS'];

    if (!in_array($region, $valid_regions, true)) {
        header('Location: ?section=assessments&err=' . urlencode('Invalid region.')); exit;
    }
    if (!in_array($field, $valid_fields, true)) {
        header('Location: ?section=assessments&err=' . urlencode('Please select a field.')); exit;
    }
    if ($edu_level < 1) {
        header('Location: ?section=assessments&err=' . urlencode('Please select an education level.')); exit;
    }

    $db = get_db(); $p = DB_PREFIX;
    $defaulted     = 0;
    $region_source = 'admin';
    if ($region === 'AUTO') {
        $region = 'UK'; $region_source = 'auto_default'; $defaulted = 1;
    }

    $token   = enp_psy_generate_token();
    $expiry  = enp_psy_token_expiry();
    $created_by = function_exists('get_current_user_id') ? get_current_user_id() : null;

    $db->prepare("INSERT INTO `{$p}psy_assessments`
        (token, candidate_name, candidate_email, candidate_phone, region, region_source,
         education_level, field, created_by, payment_ref, status, expires_at, defaulted)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
       ->execute([$token, $cand_name, $cand_email ?: '', $cand_phone, $region, $region_source,
                  $edu_level, $field, $created_by, $pay_ref, 'pending', $expiry, $defaulted]);

    header('Location: ?section=assessments&generated=1&tok=' . urlencode($token)); exit;
}

// ── Psychometric: save Razorpay auto-trigger setting ─────────────────────────
if ($logged_in && $action === 'save_psy_rzp_toggle') {
    if (!isset($_SESSION['enp_csrf']) || !hash_equals($_SESSION['enp_csrf'], $_POST['csrf'] ?? '')) {
        http_response_code(403); die('CSRF mismatch.');
    }
    if (!function_exists('update_option')) {
        header('Location: ?section=assessments&tab=settings&err=' . urlencode('WordPress not loaded.')); exit;
    }
    $enabled = $_POST['rzp_plans'] ?? [];
    $valid   = ['basic','elite','premium','accelerator','starter'];
    $clean   = array_values(array_filter($enabled, fn($v) => in_array($v, $valid, true)));
    update_option('enp_psy_rzp_plans', $clean);
    header('Location: ?section=assessments&tab=settings&saved=1'); exit;
}

// ── Psychometric: save recommendation ────────────────────────────────────────
if ($logged_in && $action === 'save_psy_recommendation') {
    if (!isset($_SESSION['enp_csrf']) || !hash_equals($_SESSION['enp_csrf'], $_POST['csrf'] ?? '')) {
        http_response_code(403); die('CSRF mismatch.');
    }
    $ass_id = intval($_POST['assessment_id'] ?? 0);
    $rec    = substr(strip_tags($_POST['recommendation'] ?? ''), 0, 5000);
    $db = get_db(); $p = DB_PREFIX;
    $db->prepare("UPDATE `{$p}psy_scores` SET recommendation=? WHERE assessment_id=?")->execute([$rec, $ass_id]);
    header('Location: ?section=assessments&view=' . $ass_id . '&saved=1'); exit;
}

// Admin: deny mentor change request
if ($logged_in && $action === 'deny_mentor_change') {
    if (!isset($_SESSION['enp_csrf']) || !hash_equals($_SESSION['enp_csrf'], $_POST['csrf'] ?? '')) {
        http_response_code(403); die('CSRF mismatch.');
    }
    $req_id    = intval($_POST['request_id'] ?? 0);
    $admin_note = substr(strip_tags($_POST['admin_note'] ?? ''), 0, 500);
    $db = get_db(); $p = DB_PREFIX;
    $req_st = $db->prepare("SELECT r.*, s.email AS student_email, s.full_name AS student_name
        FROM `{$p}enp_requests` r
        JOIN `{$p}enp_students` s ON r.student_id = s.id
        WHERE r.id = ? AND r.type = 'mentor_change' AND r.status = 'open' LIMIT 1");
    $req_st->execute([$req_id]);
    $req = $req_st->fetch();
    if (!$req) { header('Location: ?section=requests&err=' . urlencode('Request not found.')); exit; }

    $db->prepare("UPDATE `{$p}enp_requests` SET status='denied', admin_note=? WHERE id=?")->execute([$admin_note, $req_id]);

    if (function_exists('enp_send_mail')) {
        $sname = esc_html($req['student_name']);
        $body  = "<h2 style='color:#22D3EE;margin:0 0 16px'>Mentor Change Update</h2>";
        $body .= "<p>Hi {$sname}, we could not approve your mentor change request at this time.</p>";
        if ($admin_note) {
            $body .= "<p><strong>Note:</strong><br>" . nl2br(esc_html($admin_note)) . "</p>";
        }
        $body .= "<p>Please contact us if you have questions.</p>";
        enp_send_mail($req['student_email'], 'Mentor change request update — Enterns Tech', $body, true);
    }
    header('Location: ?section=requests&denied_change=1'); exit;
}

// ── Dashboard data ────────────────────────────────────────────────────────────
$stats = [];
$monthly_labels  = [];
$monthly_paypal  = [];
$monthly_manual  = [];
$monthly_total   = [];
$transactions    = [];
$manual_entries  = [];
$section         = $_GET['section'] ?? 'overview';

if ($logged_in) {
    $db = get_db();
    $p  = DB_PREFIX;

    // Summary stats
    $stats['paypal_total']  = (float) $db->query("SELECT COALESCE(SUM(amount),0) FROM `{$p}et_transactions` WHERE status='COMPLETED'")->fetchColumn();
    $stats['manual_total']  = (float) $db->query("SELECT COALESCE(SUM(amount),0) FROM `{$p}et_revenue_manual`")->fetchColumn();
    $stats['grand_total']   = $stats['paypal_total'] + $stats['manual_total'];
    $stats['tx_count']      = (int)   $db->query("SELECT COUNT(*) FROM `{$p}et_transactions`")->fetchColumn();

    $this_m = (int) date('n');
    $this_y = (int) date('Y');
    $pp_m   = (float) $db->query("SELECT COALESCE(SUM(amount),0) FROM `{$p}et_transactions` WHERE MONTH(created_at)={$this_m} AND YEAR(created_at)={$this_y} AND status='COMPLETED'")->fetchColumn();
    $mn_m   = (float) $db->query("SELECT COALESCE(SUM(amount),0) FROM `{$p}et_revenue_manual` WHERE MONTH(entry_date)={$this_m} AND YEAR(entry_date)={$this_y}")->fetchColumn();
    $stats['this_month']    = $pp_m + $mn_m;

    // Monthly chart — last 12 months
    for ($i = 11; $i >= 0; $i--) {
        $ts  = strtotime("-{$i} months");
        $lbl = date('M Y', $ts);
        $m   = (int) date('n', $ts);
        $y   = (int) date('Y', $ts);

        $pp = (float) $db->query("SELECT COALESCE(SUM(amount),0) FROM `{$p}et_transactions` WHERE MONTH(created_at)={$m} AND YEAR(created_at)={$y} AND status='COMPLETED'")->fetchColumn();
        $mn = (float) $db->query("SELECT COALESCE(SUM(amount),0) FROM `{$p}et_revenue_manual` WHERE MONTH(entry_date)={$m} AND YEAR(entry_date)={$y}")->fetchColumn();

        $monthly_labels[] = $lbl;
        $monthly_paypal[] = $pp;
        $monthly_manual[] = $mn;
        $monthly_total[]  = $pp + $mn;
    }

    // Tables
    $transactions   = $db->query("SELECT * FROM `{$p}et_transactions` ORDER BY created_at DESC LIMIT 50")->fetchAll();
    $manual_entries = $db->query("SELECT * FROM `{$p}et_revenue_manual` ORDER BY entry_date DESC LIMIT 100")->fetchAll();

    // ── Portal stats (from enterns-portal plugin tables) ──────────────────────
    // Tables are created when the plugin activates; we guard with try/catch so
    // the admin portal still works before the plugin is installed.
    $portal = [
        'mentors_total'   => 0,
        'mentors_pending' => 0,
        'students_total'  => 0,
        'students_active' => 0,
        'sessions_total'  => 0,
        'payments_paid'   => 0.0,
    ];
    try {
        $portal['mentors_total']   = (int) $db->query("SELECT COUNT(*) FROM `{$p}enp_mentors`")->fetchColumn();
        $portal['mentors_pending'] = (int) $db->query("SELECT COUNT(*) FROM `{$p}enp_mentors` WHERE status='pending'")->fetchColumn();
        $portal['students_total']  = (int) $db->query("SELECT COUNT(*) FROM `{$p}enp_students`")->fetchColumn();
        $portal['students_active'] = (int) $db->query("SELECT COUNT(*) FROM `{$p}enp_students` WHERE status='active'")->fetchColumn();
        $portal['sessions_total']  = (int) $db->query("SELECT COUNT(*) FROM `{$p}enp_sessions`")->fetchColumn();
        $portal['payments_paid']   = (float) $db->query("SELECT COALESCE(SUM(amount),0) FROM `{$p}enp_payments` WHERE status='paid'")->fetchColumn();
        $portal['requests_open']   = (int) $db->query("SELECT COUNT(*) FROM `{$p}enp_requests` WHERE type='mentor_change' AND status='open'")->fetchColumn();
    } catch (PDOException $e) {
        // Plugin tables not yet created — portal data will show zeros.
    }

    // Mentor applications list for Applications tab.
    $applications = [];
    try {
        $applications = $db->query("SELECT * FROM `{$p}enp_mentors` ORDER BY created_at DESC LIMIT 100")->fetchAll();
    } catch (PDOException $e) {}

    // Student list for Students tab.
    $portal_students = [];
    try {
        $portal_students = $db->query("SELECT s.*, m.full_name AS mentor_name FROM `{$p}enp_students` s LEFT JOIN `{$p}enp_mentors` m ON s.mentor_id = m.id ORDER BY s.created_at DESC LIMIT 100")->fetchAll();
    } catch (PDOException $e) {}

    // Mentor change requests for Requests tab.
    $portal_requests = [];
    try {
        $portal_requests = $db->query("
            SELECT r.*,
                   s.full_name  AS student_name,  s.email AS student_email,
                   cm.full_name AS current_mentor_name,
                   rm.full_name AS requested_mentor_name
            FROM `{$p}enp_requests` r
            LEFT JOIN `{$p}enp_students` s  ON r.student_id = s.id
            LEFT JOIN `{$p}enp_mentors`  cm ON s.mentor_id  = cm.id
            LEFT JOIN `{$p}enp_mentors`  rm ON r.mentor_id  = rm.id
            WHERE r.type = 'mentor_change'
            ORDER BY r.created_at DESC LIMIT 100
        ")->fetchAll();
    } catch (PDOException $e) {}

    // ── Psychometric assessments ───────────────────────────────────────────
    $psy_assessments  = [];
    $psy_counts       = ['total'=>0,'pending'=>0,'in_progress'=>0,'submitted'=>0];
    $psy_result_row   = null;
    $psy_score_row    = null;
    $psy_responses_oa = [];
    $psy_view_id      = isset($_GET['view']) ? intval($_GET['view']) : 0;
    $psy_tab          = $_GET['tab'] ?? 'list';

    try {
        $psy_counts['total']       = (int)$db->query("SELECT COUNT(*) FROM `{$p}psy_assessments`")->fetchColumn();
        $psy_counts['pending']     = (int)$db->query("SELECT COUNT(*) FROM `{$p}psy_assessments` WHERE status='pending'")->fetchColumn();
        $psy_counts['in_progress'] = (int)$db->query("SELECT COUNT(*) FROM `{$p}psy_assessments` WHERE status='in_progress'")->fetchColumn();
        $psy_counts['submitted']   = (int)$db->query("SELECT COUNT(*) FROM `{$p}psy_assessments` WHERE status='submitted'")->fetchColumn();

        $portal['psy_total']     = $psy_counts['total'];
        $portal['psy_submitted'] = $psy_counts['submitted'];

        $psy_assessments = $db->query("SELECT * FROM `{$p}psy_assessments` ORDER BY created_at DESC LIMIT 200")->fetchAll();

        if ($psy_view_id > 0 && $section === 'assessments') {
            $psy_result_row = $db->prepare("SELECT * FROM `{$p}psy_assessments` WHERE id=? LIMIT 1");
            $psy_result_row->execute([$psy_view_id]);
            $psy_result_row = $psy_result_row->fetch();

            if ($psy_result_row) {
                $psy_score_stmt = $db->prepare("SELECT * FROM `{$p}psy_scores` WHERE assessment_id=? LIMIT 1");
                $psy_score_stmt->execute([$psy_view_id]);
                $psy_score_row = $psy_score_stmt->fetch();
            }
        }
    } catch (PDOException $e) {}
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function fmt_money(float $v): string { return '$' . number_format($v, 2); }
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function active_section(string $s): string {
    global $section;
    return $section === $s ? 'active' : '';
}

function render_setup_page(): string {
    return <<<HTML
<!doctype html><html><head><meta charset="UTF-8"><title>Admin Setup</title>
<style>body{background:#0a0e1a;color:#e5e7eb;font-family:system-ui;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
.box{background:#111827;border:1px solid #1f2937;border-radius:12px;padding:2rem;max-width:520px;width:90%}
h2{color:#22D3EE;margin-top:0}pre{background:#0a0e1a;padding:1rem;border-radius:8px;overflow-x:auto;font-size:13px;color:#a3e635}
</style></head><body><div class="box">
<h2>⚙️ Admin Portal Setup</h2>
<p>Create the file <strong>admin-portal/config.php</strong> on the server (or deploy it) with the following content:</p>
<pre>&lt;?php
define('ADMIN_PASSWORD', 'your-secret-password');
define('DB_HOST',   'localhost');
define('DB_NAME',   'your_wp_database_name');
define('DB_USER',   'your_db_username');
define('DB_PASS',   'your_db_password');
define('DB_PREFIX', 'wp_');</pre>
<p>You can find the database details in your Bluehost cPanel → MySQL Databases, and in your WordPress <code>wp-config.php</code> file.</p>
<p>After uploading <code>config.php</code>, reload this page.</p>
</div></body></html>
HTML;
}

// ── HTML output ───────────────────────────────────────────────────────────────
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Enterns Tech — Admin</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg:       #0a0e1a;
      --surface:  #111827;
      --border:   #1f2937;
      --cyan:     #22D3EE;
      --cyan-dim: #0e7490;
      --text:     #f1f5f9;
      --muted:    #94a3b8;
      --green:    #4ade80;
      --red:      #f87171;
      --gold:     #fbbf24;
    }

    body { background: var(--bg); color: var(--text); font-family: system-ui, -apple-system, sans-serif; min-height: 100vh; }

    /* ── LOGIN PAGE ─────────────────────────────────────────────────────────── */
    .login-wrap { display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 1rem; }
    .login-card {
      background: var(--surface); border: 1px solid var(--border); border-radius: 16px;
      padding: 2.5rem 2rem; width: 100%; max-width: 380px; text-align: center;
      box-shadow: 0 0 40px rgba(34,211,238,.08);
    }
    .login-logo { font-size: 1.6rem; font-weight: 800; color: var(--cyan); margin-bottom: .25rem; letter-spacing: -1px; }
    .login-sub { color: var(--muted); font-size: .85rem; margin-bottom: 2rem; }
    .login-card label { display: block; text-align: left; font-size: .8rem; color: var(--muted); margin-bottom: .4rem; letter-spacing: .05em; text-transform: uppercase; }
    .login-card input[type=password] {
      width: 100%; padding: .75rem 1rem; background: var(--bg); border: 1px solid var(--border);
      border-radius: 8px; color: var(--text); font-size: 1rem; outline: none; transition: border .2s;
    }
    .login-card input[type=password]:focus { border-color: var(--cyan); }
    .btn-login {
      width: 100%; margin-top: 1.25rem; padding: .8rem; background: var(--cyan); color: #0a0e1a;
      font-weight: 700; font-size: 1rem; border: none; border-radius: 8px; cursor: pointer; transition: opacity .2s;
    }
    .btn-login:hover { opacity: .85; }
    .error-msg { background: rgba(248,113,113,.12); border: 1px solid rgba(248,113,113,.3); border-radius: 8px; color: var(--red); padding: .75rem 1rem; margin-bottom: 1.25rem; font-size: .9rem; }
    .lock-icon { font-size: 2.5rem; margin-bottom: 1rem; }

    /* ── DASHBOARD SHELL ────────────────────────────────────────────────────── */
    .dash { display: flex; flex-direction: column; min-height: 100vh; }

    /* Top bar */
    .topbar {
      background: var(--surface); border-bottom: 1px solid var(--border);
      padding: .75rem 1.5rem; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
    }
    .topbar-brand { font-weight: 800; color: var(--cyan); font-size: 1.1rem; letter-spacing: -0.5px; }
    .topbar-badge { background: rgba(34,211,238,.1); border: 1px solid var(--cyan-dim); color: var(--cyan); font-size: .7rem; padding: .15rem .5rem; border-radius: 99px; text-transform: uppercase; letter-spacing: .08em; }
    .topbar-spacer { flex: 1; }
    .topbar-time { color: var(--muted); font-size: .8rem; }
    .btn-logout { background: rgba(248,113,113,.12); border: 1px solid rgba(248,113,113,.3); color: var(--red); padding: .4rem .9rem; border-radius: 7px; font-size: .83rem; cursor: pointer; transition: background .2s; }
    .btn-logout:hover { background: rgba(248,113,113,.22); }

    /* Nav tabs */
    .nav-tabs { background: var(--surface); border-bottom: 1px solid var(--border); display: flex; gap: .25rem; padding: 0 1.5rem; overflow-x: auto; }
    .nav-tab { padding: .7rem 1.1rem; color: var(--muted); font-size: .88rem; text-decoration: none; border-bottom: 2px solid transparent; white-space: nowrap; transition: color .2s, border-color .2s; display: inline-flex; align-items: center; gap: .35rem; }
    .nav-tab:hover { color: var(--text); }
    .nav-tab.active { color: var(--cyan); border-bottom-color: var(--cyan); font-weight: 600; }
    .nav-badge { background: rgba(251,191,36,.2); color: var(--gold); border: 1px solid rgba(251,191,36,.35); border-radius: 99px; font-size: .68rem; font-weight: 700; padding: .1rem .45rem; }

    /* Main content */
    .main { flex: 1; padding: 1.75rem 1.5rem; max-width: 1200px; width: 100%; margin: 0 auto; }

    /* ── STAT CARDS ─────────────────────────────────────────────────────────── */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.75rem; }
    .stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 1.25rem 1.5rem; }
    .stat-label { font-size: .75rem; color: var(--muted); text-transform: uppercase; letter-spacing: .07em; margin-bottom: .5rem; }
    .stat-value { font-size: 1.75rem; font-weight: 800; line-height: 1; }
    .stat-value.cyan  { color: var(--cyan); }
    .stat-value.green { color: var(--green); }
    .stat-value.gold  { color: var(--gold); }
    .stat-sub { font-size: .75rem; color: var(--muted); margin-top: .5rem; }

    /* ── CHART ──────────────────────────────────────────────────────────────── */
    .chart-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.75rem; }
    .chart-title { font-size: .95rem; font-weight: 600; margin-bottom: 1rem; color: var(--text); }
    .chart-wrap { position: relative; height: 240px; }

    /* ── TABLES ─────────────────────────────────────────────────────────────── */
    .section-title { font-size: 1rem; font-weight: 700; margin-bottom: 1rem; color: var(--text); display: flex; align-items: center; gap: .5rem; }
    .section-title span { background: rgba(34,211,238,.1); color: var(--cyan); font-size: .72rem; padding: .2rem .55rem; border-radius: 99px; }
    .card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; margin-bottom: 1.75rem; }
    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: .86rem; }
    thead th { background: rgba(34,211,238,.06); color: var(--muted); font-size: .75rem; text-transform: uppercase; letter-spacing: .06em; padding: .75rem 1rem; text-align: left; white-space: nowrap; border-bottom: 1px solid var(--border); }
    tbody td { padding: .75rem 1rem; border-bottom: 1px solid rgba(31,41,55,.8); vertical-align: middle; }
    tbody tr:last-child td { border-bottom: none; }
    tbody tr:hover { background: rgba(255,255,255,.02); }
    .badge { display: inline-block; font-size: .7rem; padding: .2rem .55rem; border-radius: 99px; font-weight: 600; }
    .badge-green { background: rgba(74,222,128,.12); color: var(--green); border: 1px solid rgba(74,222,128,.3); }
    .badge-cyan  { background: rgba(34,211,238,.1);  color: var(--cyan);  border: 1px solid var(--cyan-dim); }
    .badge-gold  { background: rgba(251,191,36,.1);  color: var(--gold);  border: 1px solid rgba(251,191,36,.3); }
    .amount { font-weight: 700; color: var(--green); }
    .empty-row td { text-align: center; color: var(--muted); padding: 2rem; font-size: .9rem; }
    .btn-del { background: none; border: 1px solid rgba(248,113,113,.3); color: var(--red); font-size: .75rem; padding: .25rem .6rem; border-radius: 6px; cursor: pointer; transition: background .2s; }
    .btn-del:hover { background: rgba(248,113,113,.1); }

    /* ── ADD FORM ───────────────────────────────────────────────────────────── */
    .form-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.75rem; }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; }
    .form-group label { display: block; font-size: .75rem; color: var(--muted); text-transform: uppercase; letter-spacing: .07em; margin-bottom: .4rem; }
    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%; padding: .65rem .9rem; background: var(--bg); border: 1px solid var(--border);
      border-radius: 8px; color: var(--text); font-size: .9rem; outline: none; transition: border .2s; font-family: inherit;
    }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus { border-color: var(--cyan); }
    .form-group textarea { min-height: 70px; resize: vertical; }
    .form-group select option { background: var(--surface); }
    .btn-add { background: var(--cyan); color: #0a0e1a; font-weight: 700; font-size: .9rem; padding: .65rem 1.4rem; border: none; border-radius: 8px; cursor: pointer; transition: opacity .2s; margin-top: 1.5rem; }
    .btn-add:hover { opacity: .85; }
    .success-banner { background: rgba(74,222,128,.08); border: 1px solid rgba(74,222,128,.25); border-radius: 8px; color: var(--green); padding: .75rem 1rem; margin-bottom: 1.25rem; font-size: .9rem; }

    /* ── ACTION BUTTONS ─────────────────────────────────────────────────────── */
    .action-cell { display:flex;gap:.35rem;flex-wrap:wrap;align-items:center; }
    .btn-approve { background:rgba(74,222,128,.1);border:1px solid rgba(74,222,128,.3);color:var(--green);font-size:.75rem;padding:.3rem .7rem;border-radius:6px;cursor:pointer;transition:background .2s;white-space:nowrap; }
    .btn-approve:hover { background:rgba(74,222,128,.2); }
    .btn-warn { background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.3);color:var(--gold);font-size:.75rem;padding:.3rem .7rem;border-radius:6px;cursor:pointer;transition:background .2s;white-space:nowrap; }
    .btn-warn:hover { background:rgba(251,191,36,.2); }
    .btn-edit { background:rgba(34,211,238,.08);border:1px solid rgba(34,211,238,.25);color:var(--cyan);font-size:.75rem;padding:.3rem .7rem;border-radius:6px;cursor:pointer;transition:background .2s;white-space:nowrap; }
    .btn-edit:hover { background:rgba(34,211,238,.15); }
    .badge-red   { background:rgba(248,113,113,.12);color:var(--red);border:1px solid rgba(248,113,113,.3); }
    .badge-muted { background:rgba(148,163,184,.1);color:var(--muted);border:1px solid rgba(148,163,184,.2); }

    /* ── MENTOR PHOTO ────────────────────────────────────────────────────────── */
    .mentor-photo { width:34px;height:34px;border-radius:50%;object-fit:cover;border:1px solid var(--border);flex-shrink:0; }
    .mentor-photo-placeholder { width:34px;height:34px;border-radius:50%;background:rgba(34,211,238,.1);border:1px solid rgba(34,211,238,.2);display:inline-flex;align-items:center;justify-content:center;font-size:.75rem;color:var(--cyan);font-weight:700;flex-shrink:0; }

    /* ── MODAL ───────────────────────────────────────────────────────────────── */
    .enp-modal-overlay { display:none;position:fixed;inset:0;z-index:999;background:rgba(0,0,0,.65);align-items:center;justify-content:center; }
    .enp-modal-overlay.open { display:flex; }
    .enp-modal { background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:1.75rem 1.5rem;max-width:480px;width:90%;box-shadow:0 0 60px rgba(0,0,0,.5); }
    .enp-modal h3 { margin:0 0 .35rem;color:var(--text); }
    .enp-modal p  { color:var(--muted);font-size:.85rem;margin:0 0 .75rem; }
    .enp-modal-actions { display:flex;gap:.75rem;margin-top:1.25rem;justify-content:flex-end; }

    @media(max-width:600px) {
      .main { padding: 1rem; }
      .stat-value { font-size: 1.4rem; }
      .topbar { padding: .75rem 1rem; }
    }
  </style>
</head>
<body>

<?php if (!$logged_in): ?>
<!-- ═══════════════════ LOGIN PAGE ═══════════════════ -->
<div class="login-wrap">
  <div class="login-card">
    <div class="lock-icon">🔐</div>
    <div class="login-logo">Enterns Tech</div>
    <div class="login-sub">Admin Portal — Private Access Only</div>

    <?php if ($error): ?>
      <div class="error-msg"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <input type="hidden" name="action" value="login">
      <div style="margin-bottom:1rem">
        <label for="pwd">Admin Password</label>
        <input type="password" id="pwd" name="password" autofocus placeholder="Enter password">
      </div>
      <button class="btn-login" type="submit">Sign In</button>
    </form>

    <p style="color:var(--muted);font-size:.75rem;margin-top:1.5rem">
      This page is private. Not accessible from the main website.
    </p>
  </div>
</div>

<?php else: ?>
<!-- ═══════════════════ DASHBOARD ═══════════════════ -->
<div class="dash">

  <!-- Top bar -->
  <header class="topbar">
    <div class="topbar-brand">Enterns Tech</div>
    <div class="topbar-badge">Admin Portal</div>
    <div class="topbar-spacer"></div>
    <div class="topbar-time"><?= date('d M Y, H:i') ?></div>
    <form method="POST" style="display:inline">
      <input type="hidden" name="action" value="logout">
      <button class="btn-logout" type="submit">Sign Out</button>
    </form>
  </header>

  <!-- Nav tabs -->
  <nav class="nav-tabs">
    <a href="?section=overview"      class="nav-tab <?= active_section('overview') ?>">Overview</a>
    <a href="?section=applications"  class="nav-tab <?= active_section('applications') ?>">
      Applications<?php if (!empty($portal['mentors_pending'])): ?> <span class="nav-badge"><?= (int)$portal['mentors_pending'] ?></span><?php endif; ?>
    </a>
    <a href="?section=mentors"       class="nav-tab <?= active_section('mentors') ?>">Mentors</a>
    <a href="?section=students"      class="nav-tab <?= active_section('students') ?>">Students</a>
    <a href="?section=requests"      class="nav-tab <?= active_section('requests') ?>">
      Requests<?php if (!empty($portal['requests_open'])): ?> <span class="nav-badge"><?= (int)$portal['requests_open'] ?></span><?php endif; ?>
    </a>
    <a href="?section=assessments"   class="nav-tab <?= active_section('assessments') ?>">
      Assessments<?php if (!empty($portal['psy_submitted'])): ?> <span class="nav-badge"><?= (int)$portal['psy_submitted'] ?></span><?php endif; ?>
    </a>
    <a href="?section=sessions"      class="nav-tab <?= active_section('sessions') ?>">Sessions</a>
    <a href="?section=payments"      class="nav-tab <?= active_section('payments') ?>">Payments</a>
    <a href="?section=transactions"  class="nav-tab <?= active_section('transactions') ?>">Legacy Txns</a>
    <a href="?section=manual"        class="nav-tab <?= active_section('manual') ?>">Manual Revenue</a>
  </nav>

  <main class="main">

  <?php if ($section === 'overview'): ?>
  <!-- ── OVERVIEW ────────────────────────────────────────────── -->

    <!-- Portal stat cards -->
    <div class="stats-grid" style="margin-bottom:1rem;">
      <div class="stat-card" style="border-color:rgba(34,211,238,.25)">
        <div class="stat-label">Mentor Applications</div>
        <div class="stat-value cyan"><?= (int)$portal['mentors_total'] ?></div>
        <div class="stat-sub"><?= (int)$portal['mentors_pending'] ?> pending review</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Students</div>
        <div class="stat-value cyan"><?= (int)$portal['students_total'] ?></div>
        <div class="stat-sub"><?= (int)$portal['students_active'] ?> active</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Sessions</div>
        <div class="stat-value cyan"><?= (int)$portal['sessions_total'] ?></div>
        <div class="stat-sub">All time</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Portal Payments</div>
        <div class="stat-value green">&#8377;<?= number_format($portal['payments_paid'], 0) ?></div>
        <div class="stat-sub">Razorpay confirmed</div>
      </div>
    </div>

    <!-- Stat cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Total Revenue (All Time)</div>
        <div class="stat-value cyan"><?= fmt_money($stats['grand_total']) ?></div>
        <div class="stat-sub">PayPal + Manual combined</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">This Month</div>
        <div class="stat-value green"><?= fmt_money($stats['this_month']) ?></div>
        <div class="stat-sub"><?= date('F Y') ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">PayPal Revenue</div>
        <div class="stat-value cyan"><?= fmt_money($stats['paypal_total']) ?></div>
        <div class="stat-sub"><?= $stats['tx_count'] ?> payment(s)</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Manual Revenue</div>
        <div class="stat-value gold"><?= fmt_money($stats['manual_total']) ?></div>
        <div class="stat-sub">Manually entered</div>
      </div>
    </div>

    <!-- Revenue chart -->
    <div class="chart-card">
      <div class="chart-title">Monthly Revenue — Last 12 Months</div>
      <div class="chart-wrap">
        <canvas id="revenueChart"></canvas>
      </div>
    </div>

    <!-- Recent activity (last 5 PayPal + last 5 manual) -->
    <div class="section-title">Recent PayPal Transactions <span><?= count($transactions) ?> total</span></div>
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Date</th><th>Order ID</th><th>Plan</th><th>Amount</th><th>Status</th></tr></thead>
          <tbody>
          <?php if ($transactions): foreach (array_slice($transactions, 0, 5) as $t): ?>
            <tr>
              <td><?= h(date('d M Y', strtotime($t['created_at']))) ?></td>
              <td style="font-size:.78rem;color:var(--muted)"><?= h($t['order_id']) ?></td>
              <td><?= $t['plan'] ? h($t['plan']) : '<span style="color:var(--muted)">—</span>' ?></td>
              <td class="amount"><?= fmt_money((float)$t['amount']) ?></td>
              <td><span class="badge badge-green"><?= h($t['status']) ?></span></td>
            </tr>
          <?php endforeach; else: ?>
            <tr class="empty-row"><td colspan="5">No PayPal transactions recorded yet.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="section-title">Recent Manual Entries <span><?= count($manual_entries) ?> total</span></div>
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Date</th><th>Amount</th><th>Category</th><th>Description</th></tr></thead>
          <tbody>
          <?php if ($manual_entries): foreach (array_slice($manual_entries, 0, 5) as $m): ?>
            <tr>
              <td><?= h(date('d M Y', strtotime($m['entry_date']))) ?></td>
              <td class="amount"><?= fmt_money((float)$m['amount']) ?></td>
              <td><span class="badge badge-gold"><?= h($m['category']) ?></span></td>
              <td style="color:var(--muted)"><?= h($m['description']) ?></td>
            </tr>
          <?php endforeach; else: ?>
            <tr class="empty-row"><td colspan="4">No manual entries yet.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php elseif ($section === 'transactions'): ?>
  <!-- ── PAYPAL TRANSACTIONS ─────────────────────────────────── -->

    <div class="section-title">PayPal Transactions <span><?= count($transactions) ?> total</span></div>
    <p style="color:var(--muted);font-size:.85rem;margin-bottom:1.25rem">
      Transactions are logged automatically when a payment is captured on the website.
    </p>
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Date &amp; Time</th><th>Order ID</th><th>Plan</th><th>Payer</th><th>Amount</th><th>Currency</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php if ($transactions): foreach ($transactions as $t): ?>
            <tr>
              <td style="white-space:nowrap"><?= h(date('d M Y H:i', strtotime($t['created_at']))) ?></td>
              <td style="font-size:.76rem;color:var(--muted);font-family:monospace"><?= h($t['order_id']) ?></td>
              <td><?= $t['plan'] ? h($t['plan']) : '<span style="color:var(--muted)">—</span>' ?></td>
              <td style="font-size:.82rem"><?= h($t['payer_name'] ?: $t['payer_email'] ?: '—') ?></td>
              <td class="amount"><?= fmt_money((float)$t['amount']) ?></td>
              <td style="color:var(--muted)"><?= h($t['currency']) ?></td>
              <td><span class="badge badge-green"><?= h($t['status']) ?></span></td>
              <td>
                <form method="POST" onsubmit="return confirm('Delete this transaction record?')">
                  <input type="hidden" name="action" value="delete_transaction">
                  <input type="hidden" name="tx_id" value="<?= (int)$t['id'] ?>">
                  <button class="btn-del" type="submit">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr class="empty-row"><td colspan="8">No PayPal transactions recorded yet.<br><small>Transactions appear here once a customer completes payment.</small></td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php elseif ($section === 'manual'): ?>
  <!-- ── MANUAL REVENUE ─────────────────────────────────────── -->

    <?php if (isset($_GET['added'])): ?>
      <div class="success-banner">Entry added successfully.</div>
    <?php endif; ?>

    <div class="section-title">Add Manual Revenue Entry</div>
    <div class="form-card">
      <form method="POST">
        <input type="hidden" name="action" value="add_revenue">
        <div class="form-grid">
          <div class="form-group">
            <label>Date</label>
            <input type="date" name="entry_date" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="form-group">
            <label>Amount (₹ or $)</label>
            <input type="number" name="amount" step="0.01" min="0.01" placeholder="e.g. 5000" required>
          </div>
          <div class="form-group">
            <label>Category</label>
            <select name="category">
              <option>Training Fee</option>
              <option>Placement Fee</option>
              <option>Internship Fee</option>
              <option>Consultation</option>
              <option>Workshop</option>
              <option>Other</option>
            </select>
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label>Description</label>
            <textarea name="description" placeholder="e.g. John Doe — React Training batch, Jun 2026"></textarea>
          </div>
        </div>
        <button class="btn-add" type="submit">+ Add Entry</button>
      </form>
    </div>

    <div class="section-title">All Manual Entries <span><?= count($manual_entries) ?></span></div>
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Date</th><th>Amount</th><th>Category</th><th>Description</th><th>Logged At</th><th></th></tr></thead>
          <tbody>
          <?php if ($manual_entries): foreach ($manual_entries as $m): ?>
            <tr>
              <td style="white-space:nowrap"><?= h(date('d M Y', strtotime($m['entry_date']))) ?></td>
              <td class="amount"><?= fmt_money((float)$m['amount']) ?></td>
              <td><span class="badge badge-gold"><?= h($m['category']) ?></span></td>
              <td style="color:var(--muted);max-width:300px"><?= h($m['description']) ?></td>
              <td style="font-size:.78rem;color:var(--muted)"><?= h(date('d M Y H:i', strtotime($m['created_at']))) ?></td>
              <td>
                <form method="POST" onsubmit="return confirm('Delete this entry?')">
                  <input type="hidden" name="action" value="delete_revenue">
                  <input type="hidden" name="entry_id" value="<?= (int)$m['id'] ?>">
                  <button class="btn-del" type="submit">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr class="empty-row"><td colspan="6">No manual entries yet. Add one above.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php elseif ($section === 'applications'): ?>
  <!-- ── MENTOR APPLICATIONS ────────────────────────────────── -->

    <?php if (isset($_GET['approved'])): ?>
      <div class="success-banner">Mentor approved — set-password email sent.</div>
    <?php elseif (isset($_GET['rejected'])): ?>
      <div class="success-banner" style="background:rgba(248,113,113,.08);border-color:rgba(248,113,113,.25);color:var(--red)">Application rejected — applicant notified.</div>
    <?php elseif (isset($_GET['info_sent'])): ?>
      <div class="success-banner" style="background:rgba(251,191,36,.08);border-color:rgba(251,191,36,.25);color:var(--gold)">Information request sent to applicant.</div>
    <?php elseif (isset($_GET['err'])): ?>
      <div class="success-banner" style="background:rgba(248,113,113,.08);border-color:rgba(248,113,113,.25);color:var(--red)">Error: <?= h($_GET['err']) ?></div>
    <?php endif; ?>

    <div class="section-title">
      Mentor Applications <span><?= count($applications) ?> total</span>
      <?php if ($portal['mentors_pending'] > 0): ?>
        <span style="background:rgba(251,191,36,.15);color:var(--gold);border-color:rgba(251,191,36,.3);"><?= (int)$portal['mentors_pending'] ?> pending</span>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Photo</th><th>Name</th><th>Email</th><th>Phone</th><th>Tech Stack</th><th>Slots</th><th>Status</th><th>Applied</th><th>Actions</th></tr></thead>
          <tbody>
          <?php if ($applications): foreach ($applications as $a):
            $ap   = explode(' ', $a['full_name'], 2);
            $init = strtoupper(substr($ap[0], 0, 1) . (isset($ap[1]) ? substr($ap[1], 0, 1) : ''));
            $stc  = ['pending'=>'badge-gold','approved'=>'badge-green','rejected'=>'badge-red','info_requested'=>'badge-muted'][$a['status']] ?? 'badge-muted';
          ?>
            <tr>
              <td>
                <?php if (!empty($a['photo_url'])): ?>
                  <img src="<?= h($a['photo_url']) ?>" alt="" class="mentor-photo">
                <?php else: ?>
                  <div class="mentor-photo-placeholder"><?= h($init) ?></div>
                <?php endif; ?>
              </td>
              <td style="font-weight:600"><?= h($a['full_name']) ?></td>
              <td style="font-size:.82rem;color:var(--muted)"><?= h($a['email']) ?></td>
              <td style="font-size:.82rem;color:var(--muted)"><?= h($a['phone']) ?></td>
              <td style="font-size:.78rem;max-width:150px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= h($a['tech_stack'] ?? '') ?>"><?= h($a['tech_stack'] ?? '—') ?></td>
              <td style="text-align:center"><?= (int)$a['available_slots'] ?></td>
              <td>
                <span class="badge <?= $stc ?>"><?= h(str_replace('_', ' ', $a['status'])) ?></span>
                <?php if (!empty($a['admin_note'])): ?>
                  <div style="font-size:.72rem;color:var(--muted);margin-top:.25rem;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= h($a['admin_note']) ?>"><?= h($a['admin_note']) ?></div>
                <?php endif; ?>
              </td>
              <td style="font-size:.78rem;color:var(--muted);white-space:nowrap"><?= h(date('d M Y', strtotime($a['created_at']))) ?></td>
              <td>
                <?php if (in_array($a['status'], ['pending', 'info_requested'], true)): ?>
                <div class="action-cell">
                  <form method="POST" onsubmit="return confirm('Approve <?= h(str_replace("'", "\\'", $a['full_name'])) ?> and send a set-password email?')">
                    <input type="hidden" name="action"    value="approve_mentor">
                    <input type="hidden" name="mentor_id" value="<?= (int)$a['id'] ?>">
                    <input type="hidden" name="csrf"      value="<?= h($csrf) ?>">
                    <button class="btn-approve" type="submit">Approve</button>
                  </form>
                  <button class="btn-del"  data-action="reject_mentor" data-id="<?= (int)$a['id'] ?>" data-name="<?= h($a['full_name']) ?>">Reject</button>
                  <button class="btn-warn" data-action="request_info"  data-id="<?= (int)$a['id'] ?>" data-name="<?= h($a['full_name']) ?>">Ask Info</button>
                </div>
                <?php elseif ($a['status'] === 'approved'): ?>
                  <span style="color:var(--green);font-size:.8rem">&#10003; Approved</span>
                <?php else: ?>
                  <span style="color:var(--muted);font-size:.8rem">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr class="empty-row"><td colspan="9">No mentor applications yet. Share the <a href="<?= function_exists('home_url') ? h(home_url('/partner-with-us/')) : '/partner-with-us/' ?>" target="_blank" style="color:var(--cyan)">partner form ↗</a> to start receiving applications.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Reject / Ask Info modal -->
    <div id="enp-action-modal" class="enp-modal-overlay">
      <div class="enp-modal">
        <h3 id="enp-modal-title"></h3>
        <p  id="enp-modal-desc"></p>
        <div class="form-group">
          <label>Note</label>
          <textarea id="enp-modal-note" rows="4" placeholder="Enter your message to the applicant…"></textarea>
        </div>
        <div class="enp-modal-actions">
          <button class="btn-del" onclick="document.getElementById('enp-action-modal').classList.remove('open')">Cancel</button>
          <button class="btn-add" id="enp-modal-confirm">Confirm &amp; Send</button>
        </div>
      </div>
    </div>
    <form id="enp-action-form" method="POST" style="display:none">
      <input type="hidden" name="csrf"       value="<?= h($csrf) ?>">
      <input type="hidden" id="enp-fa"  name="action">
      <input type="hidden" id="enp-fid" name="mentor_id">
      <input type="hidden" id="enp-fn"  name="admin_note">
    </form>

  <?php elseif ($section === 'mentors'): ?>
  <!-- ── MENTORS ────────────────────────────────────────────── -->

    <?php if (isset($_GET['updated'])): ?>
      <div class="success-banner">Mentor profile updated.</div>
    <?php endif; ?>

    <div class="section-title">Approved Mentors <span><?= (int)$portal['mentors_total'] ?> total</span></div>
    <div class="card">
      <div class="table-wrap">
        <?php
        $all_mentors = [];
        try { $all_mentors = $db->query("SELECT * FROM `{$p}enp_mentors` WHERE status='approved' ORDER BY full_name")->fetchAll(); } catch (PDOException $e) {}
        ?>
        <table>
          <thead><tr><th>Photo</th><th>Name</th><th>Email</th><th>Tech Stack</th><th>Slots/wk</th><th>Rate</th><th>Custom Fields</th><th></th></tr></thead>
          <tbody>
          <?php if ($all_mentors): foreach ($all_mentors as $m):
            $mp     = explode(' ', $m['full_name'], 2);
            $minit  = strtoupper(substr($mp[0], 0, 1) . (isset($mp[1]) ? substr($mp[1], 0, 1) : ''));
            $mextra = $m['extra_fields'] ?: '{}';
            $marr   = json_decode($mextra, true) ?: [];
          ?>
            <tr>
              <td>
                <?php if (!empty($m['photo_url'])): ?>
                  <img src="<?= h($m['photo_url']) ?>" alt="" class="mentor-photo">
                <?php else: ?>
                  <div class="mentor-photo-placeholder"><?= h($minit) ?></div>
                <?php endif; ?>
              </td>
              <td style="font-weight:600"><?= h($m['full_name']) ?></td>
              <td style="font-size:.82rem;color:var(--muted)"><?= h($m['email']) ?></td>
              <td style="font-size:.78rem;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= h($m['tech_stack'] ?? '') ?>"><?= h($m['tech_stack'] ?? '—') ?></td>
              <td style="text-align:center"><?= (int)$m['available_slots'] ?></td>
              <td style="color:var(--green)">&#8377;<?= number_format((float)$m['rate_per_session'], 0) ?></td>
              <td style="font-size:.78rem;color:var(--muted)">
                <?php if ($marr): foreach ($marr as $ek => $ev): ?>
                  <div><strong><?= h((string)$ek) ?>:</strong> <?= h((string)$ev) ?></div>
                <?php endforeach; else: ?><span style="color:var(--muted)">—</span><?php endif; ?>
              </td>
              <td>
                <button class="btn-edit"
                  data-id="<?= (int)$m['id'] ?>"
                  data-name="<?= h($m['full_name']) ?>"
                  data-rate="<?= (float)$m['rate_per_session'] ?>"
                  data-slots="<?= (int)$m['available_slots'] ?>"
                  data-extra="<?= h($mextra) ?>">Edit</button>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr class="empty-row"><td colspan="8">No approved mentors yet. Approve applications in the Applications tab.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Edit mentor modal -->
    <div id="enp-edit-modal" class="enp-modal-overlay">
      <div class="enp-modal" style="max-width:520px">
        <h3 style="margin:0 0 .25rem">Edit Mentor</h3>
        <p id="enp-edit-mentor-name" style="color:var(--cyan);margin:0 0 1.25rem;font-size:.9rem"></p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.75rem">
          <div class="form-group"><label>Rate per Session (&#8377;)</label><input type="number" id="enp-edit-rate"  min="0" step="1" style="width:100%"></div>
          <div class="form-group"><label>Slots / Week</label>              <input type="number" id="enp-edit-slots" min="1" max="40" style="width:100%"></div>
        </div>
        <div class="form-group">
          <label>Custom Fields <small style="color:var(--muted);text-transform:none;letter-spacing:0;font-size:.72rem">(one &ldquo;Key: Value&rdquo; per line)</small></label>
          <textarea id="enp-edit-extra" rows="5" style="width:100%" placeholder="LinkedIn: https://linkedin.com/in/...&#10;Specialisation: Full Stack&#10;Experience: 5 years"></textarea>
        </div>
        <div class="enp-modal-actions">
          <button class="btn-del" onclick="document.getElementById('enp-edit-modal').classList.remove('open')">Cancel</button>
          <button class="btn-add" id="enp-edit-confirm">Save Changes</button>
        </div>
      </div>
    </div>
    <form id="enp-edit-form" method="POST" style="display:none">
      <input type="hidden" name="csrf"             value="<?= h($csrf) ?>">
      <input type="hidden" name="action"           value="edit_mentor">
      <input type="hidden" id="enp-eform-id"       name="mentor_id">
      <input type="hidden" id="enp-eform-rate"     name="rate_per_session">
      <input type="hidden" id="enp-eform-slots"    name="available_slots">
      <input type="hidden" id="enp-eform-extra"    name="extra_fields">
    </form>

  <?php elseif ($section === 'students'): ?>
  <!-- ── STUDENTS ───────────────────────────────────────────── -->

    <?php if (isset($_GET['updated_sessions'])): ?>
      <div class="success-banner">Sessions updated.</div>
    <?php endif; ?>

    <div class="section-title">Students <span><?= (int)$portal['students_total'] ?> total</span></div>
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Name</th><th>Email</th><th>Plan</th><th>Sessions (used/total)</th><th>Mentor</th><th>CV Redesign</th><th>Status</th><th>Enrolled</th><th></th></tr></thead>
          <tbody>
          <?php if ($portal_students): foreach ($portal_students as $s): ?>
            <tr>
              <td style="font-weight:600"><?= h($s['full_name'] ?: '—') ?></td>
              <td style="font-size:.8rem;color:var(--muted)"><?= h($s['email']) ?></td>
              <td><span class="badge badge-cyan"><?= h(strtoupper($s['plan_id'])) ?></span></td>
              <td>
                <?= (int)$s['sessions_used'] ?> / <?= (int)$s['sessions_total'] ?>
              </td>
              <td style="font-size:.82rem"><?= $s['mentor_name'] ? h($s['mentor_name']) : '<span style="color:var(--muted)">Unassigned</span>' ?></td>
              <td><span class="badge <?= $s['cv_redesign_status']==='done' ? 'badge-green' : 'badge-gold' ?>"><?= h($s['cv_redesign_status']) ?></span></td>
              <td><span class="badge <?= $s['status']==='active' ? 'badge-green' : 'badge-muted' ?>"><?= h($s['status']) ?></span></td>
              <td style="font-size:.78rem;color:var(--muted);white-space:nowrap"><?= h(date('d M Y', strtotime($s['created_at']))) ?></td>
              <td>
                <button class="btn-edit enp-set-sessions-btn"
                        data-id="<?= (int)$s['id'] ?>"
                        data-name="<?= h($s['full_name'] ?: $s['email']) ?>"
                        data-current="<?= (int)$s['sessions_total'] ?>">
                  Set Sessions
                </button>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr class="empty-row"><td colspan="9">No students enrolled yet.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Set sessions modal -->
    <div id="enp-sessions-modal" class="enp-modal-overlay">
      <div class="enp-modal" style="max-width:400px">
        <h3 style="margin:0 0 .25rem">Override Sessions</h3>
        <p id="enp-sessions-modal-name" style="color:var(--cyan);margin:0 0 1.25rem;font-size:.9rem"></p>
        <div class="form-group">
          <label>Total sessions to allocate</label>
          <input type="number" id="enp-sessions-input" min="1" max="100" style="width:100%">
        </div>
        <p style="color:var(--muted);font-size:.8rem;margin:.5rem 0 0">This overrides the plan default (4–8). You can set any value 1–100.</p>
        <div class="enp-modal-actions">
          <button class="btn-del" onclick="document.getElementById('enp-sessions-modal').classList.remove('open')">Cancel</button>
          <button class="btn-add" id="enp-sessions-confirm">Save</button>
        </div>
      </div>
    </div>
    <form id="enp-sessions-form" method="POST" style="display:none">
      <input type="hidden" name="action"         value="set_student_sessions">
      <input type="hidden" name="csrf"           value="<?= h($csrf) ?>">
      <input type="hidden" id="enp-sform-id"     name="student_id">
      <input type="hidden" id="enp-sform-sessions" name="sessions_total">
    </form>

  <?php elseif ($section === 'requests'): ?>
  <!-- ── MENTOR CHANGE REQUESTS ─────────────────────────────── -->

    <?php if (isset($_GET['approved_change'])): ?>
      <div class="success-banner">Mentor change approved — student notified.</div>
    <?php elseif (isset($_GET['denied_change'])): ?>
      <div class="success-banner" style="background:rgba(248,113,113,.08);border-color:rgba(248,113,113,.25);color:var(--red)">Request denied — student notified.</div>
    <?php elseif (isset($_GET['err'])): ?>
      <div class="success-banner" style="background:rgba(248,113,113,.08);border-color:rgba(248,113,113,.25);color:var(--red)">Error: <?= h($_GET['err']) ?></div>
    <?php endif; ?>

    <div class="section-title">
      Mentor Change Requests <span><?= count($portal_requests) ?> total</span>
      <?php if (!empty($portal['requests_open'])): ?>
        <span style="background:rgba(251,191,36,.15);color:var(--gold);border-color:rgba(251,191,36,.3);"><?= (int)$portal['requests_open'] ?> open</span>
      <?php endif; ?>
    </div>
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Student</th><th>Current Mentor</th><th>Requested Mentor</th><th>Reason</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
          <?php if ($portal_requests): foreach ($portal_requests as $req):
            $payload   = json_decode($req['payload'] ?? '{}', true);
            $reason    = $payload['reason'] ?? '—';
            $stc       = ['open'=>'badge-gold','approved'=>'badge-green','denied'=>'badge-red'][$req['status']] ?? 'badge-muted';
          ?>
            <tr>
              <td>
                <div style="font-weight:600"><?= h($req['student_name'] ?? '—') ?></div>
                <div style="font-size:.78rem;color:var(--muted)"><?= h($req['student_email'] ?? '') ?></div>
              </td>
              <td style="font-size:.85rem"><?= $req['current_mentor_name'] ? h($req['current_mentor_name']) : '<span style="color:var(--muted)">None</span>' ?></td>
              <td style="font-size:.85rem"><?= $req['requested_mentor_name'] ? h($req['requested_mentor_name']) : '<span style="color:var(--muted)">Any</span>' ?></td>
              <td style="font-size:.82rem;color:var(--muted);max-width:220px" title="<?= h($reason) ?>"><?= h(mb_substr($reason, 0, 100)) . (mb_strlen($reason) > 100 ? '…' : '') ?></td>
              <td style="font-size:.78rem;color:var(--muted);white-space:nowrap"><?= h(date('d M Y', strtotime($req['created_at']))) ?></td>
              <td><span class="badge <?= $stc ?>"><?= h($req['status']) ?></span>
                <?php if (!empty($req['admin_note'])): ?>
                  <div style="font-size:.72rem;color:var(--muted);margin-top:.25rem;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= h($req['admin_note']) ?>"><?= h($req['admin_note']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($req['status'] === 'open'): ?>
                <div class="action-cell">
                  <form method="POST" onsubmit="return confirm('Approve this mentor change?')">
                    <input type="hidden" name="action"     value="approve_mentor_change">
                    <input type="hidden" name="csrf"       value="<?= h($csrf) ?>">
                    <input type="hidden" name="request_id" value="<?= (int)$req['id'] ?>">
                    <button class="btn-approve" type="submit">Approve</button>
                  </form>
                  <button class="btn-del enp-deny-btn"
                          data-id="<?= (int)$req['id'] ?>"
                          data-name="<?= h($req['student_name'] ?? '') ?>">Deny</button>
                </div>
                <?php else: ?>
                  <span style="color:var(--muted);font-size:.8rem">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr class="empty-row"><td colspan="7">No mentor change requests yet.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Deny modal -->
    <div id="enp-deny-modal" class="enp-modal-overlay">
      <div class="enp-modal">
        <h3 id="enp-deny-title"></h3>
        <p style="color:var(--muted);font-size:.85rem">Optional note to send to the student:</p>
        <div class="form-group">
          <label>Note (optional)</label>
          <textarea id="enp-deny-note" rows="4" style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;color:var(--text);padding:.65rem .9rem;font-family:inherit;resize:vertical" placeholder="Explain the decision…"></textarea>
        </div>
        <div class="enp-modal-actions">
          <button class="btn-del" onclick="document.getElementById('enp-deny-modal').classList.remove('open')">Cancel</button>
          <button class="btn-add" id="enp-deny-confirm">Deny &amp; Notify</button>
        </div>
      </div>
    </div>
    <form id="enp-deny-form" method="POST" style="display:none">
      <input type="hidden" name="csrf"         value="<?= h($csrf) ?>">
      <input type="hidden" name="action"       value="deny_mentor_change">
      <input type="hidden" id="enp-dfid"       name="request_id">
      <input type="hidden" id="enp-dfnote"     name="admin_note">
    </form>

  <?php elseif ($section === 'assessments'): ?>
  <!-- ── PSYCHOMETRIC ASSESSMENTS ─────────────────────────── -->

  <?php if (isset($_GET['err'])): ?>
    <div class="success-banner" style="background:rgba(248,113,113,.08);border-color:rgba(248,113,113,.25);color:var(--red)">Error: <?= h($_GET['err']) ?></div>
  <?php endif; ?>

  <?php if (isset($_GET['saved'])): ?>
    <div class="success-banner">Saved.</div>
  <?php endif; ?>

  <!-- Sub-tabs -->
  <div style="display:flex;gap:.25rem;margin-bottom:1.5rem;border-bottom:1px solid var(--border);overflow-x:auto">
    <?php $at = $_GET['tab'] ?? 'list'; ?>
    <a href="?section=assessments&tab=list"     style="padding:.6rem 1rem;color:<?= $at==='list'     ?'var(--cyan)':'var(--muted)' ?>;border-bottom:2px solid <?= $at==='list'     ?'var(--cyan)':'transparent' ?>;white-space:nowrap;text-decoration:none;font-size:.88rem">Assessment List</a>
    <a href="?section=assessments&tab=generate" style="padding:.6rem 1rem;color:<?= $at==='generate' ?'var(--cyan)':'var(--muted)' ?>;border-bottom:2px solid <?= $at==='generate' ?'var(--cyan)':'transparent' ?>;white-space:nowrap;text-decoration:none;font-size:.88rem">Generate Link</a>
    <a href="?section=assessments&tab=settings" style="padding:.6rem 1rem;color:<?= $at==='settings' ?'var(--cyan)':'var(--muted)' ?>;border-bottom:2px solid <?= $at==='settings' ?'var(--cyan)':'transparent' ?>;white-space:nowrap;text-decoration:none;font-size:.88rem">Razorpay Toggle</a>
  </div>

  <?php if ($psy_view_id > 0 && $psy_result_row): ?>
  <!-- ── RESULT VIEW ────────────────────────────────────────── -->
  <?php
    $edu_map = [1=>'School-leaving',2=>'Diploma/Associate',3=>'Bachelor',4=>'Postgraduate'];
    $edu_lbl = $edu_map[(int)$psy_result_row['education_level']] ?? $psy_result_row['education_level'];
    $sc      = $psy_score_row;
    $band_color = function($v) {
        if ($v === null) return 'var(--muted)';
        $f = (float)$v;
        if ($f >= 80) return 'var(--green)'; if ($f >= 60) return 'var(--cyan)'; if ($f >= 40) return 'var(--gold)'; return 'var(--red)';
    };
    $band_label = function($v) {
        if ($v === null) return '—';
        $f = (float)$v;
        if ($f >= 80) return 'Strong'; if ($f >= 60) return 'Solid'; if ($f >= 40) return 'Mixed'; return 'Watch';
    };
    function psy_score_cell($v, $band_color, $band_label) {
        if ($v === null) return '<span style="color:var(--muted)">—</span>';
        return '<span style="color:' . $band_color($v) . ';font-weight:600">'
            . round((float)$v,1) . '</span>'
            . ' <span class="badge badge-muted" style="font-size:.7rem">' . $band_label($v) . '</span>';
    }
  ?>
  <div style="margin-bottom:1rem">
    <a href="?section=assessments&tab=list" style="color:var(--cyan);font-size:.85rem">&larr; Back to list</a>
  </div>

  <div class="stats-grid" style="margin-bottom:1.5rem">
    <div class="stat-card" style="border-color:rgba(34,211,238,.3)">
      <div class="stat-label">Candidate</div>
      <div style="font-weight:700;font-size:1.05rem;color:var(--text)"><?= h($psy_result_row['candidate_name'] ?: '—') ?></div>
      <div class="stat-sub"><?= h($psy_result_row['candidate_email']) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Context</div>
      <div style="font-size:.88rem;color:var(--cyan)"><?= h($psy_result_row['region']) ?> / <?= h($psy_result_row['field']) ?></div>
      <div class="stat-sub"><?= h($edu_lbl) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Status</div>
      <div><span class="badge <?= $psy_result_row['status']==='submitted'?'badge-green':($psy_result_row['status']==='in_progress'?'badge-gold':'badge-muted') ?>"><?= h($psy_result_row['status']) ?></span></div>
      <div class="stat-sub"><?= h(date('d M Y', strtotime($psy_result_row['created_at']))) ?></div>
    </div>
    <?php if ($sc): ?>
    <div class="stat-card" style="border-color:rgba(74,222,128,.3)">
      <div class="stat-label">Overall Band</div>
      <div style="font-weight:800;font-size:1.3rem;color:<?= $band_color($sc['strengths_index']) ?>"><?= h($sc['overall_band']) ?></div>
      <div class="stat-sub">Reasoning: <?= (int)($sc['reasoning_score']??0) ?>/6 (<?= h($sc['reasoning_band']??'—') ?>)</div>
    </div>
    <?php endif; ?>
  </div>

  <?php if (!$sc): ?>
    <div class="card" style="padding:2rem;text-align:center;color:var(--muted)">Scores not yet computed (assessment not submitted).</div>
  <?php else: ?>

  <!-- Scores grid -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1rem;margin-bottom:1.5rem">

    <!-- Strengths + clusters -->
    <div class="card" style="padding:1.25rem 1.5rem">
      <div class="section-title" style="margin-bottom:.75rem">Strengths Index</div>
      <div style="margin-bottom:.75rem"><?= psy_score_cell($sc['strengths_index'], $band_color, $band_label) ?></div>
      <?php $clusters = json_decode($sc['strengths_clusters'] ?? '{}', true) ?: []; ?>
      <?php if ($clusters): ?>
        <div style="font-size:.8rem;color:var(--muted);margin-bottom:.5rem;text-transform:uppercase;letter-spacing:.06em">Cluster breakdown</div>
        <?php foreach ($clusters as $cl => $v): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:.3rem 0;border-bottom:1px solid rgba(255,255,255,.04);font-size:.84rem">
            <span><?= h($cl) ?></span>
            <span style="color:<?= $band_color($v) ?>;font-weight:600"><?= round((float)$v,1) ?></span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Big Five -->
    <div class="card" style="padding:1.25rem 1.5rem">
      <div class="section-title" style="margin-bottom:.75rem">Big Five Personality</div>
      <?php
        $traits = ['trait_c'=>'Conscientiousness','trait_e'=>'Extraversion','trait_es'=>'Emotional Stability','trait_o'=>'Openness','trait_a'=>'Agreeableness'];
        foreach ($traits as $key => $label):
          $tv = $sc[$key] ?? null;
      ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:.4rem 0;border-bottom:1px solid rgba(255,255,255,.04);font-size:.84rem">
          <span><?= h($label) ?></span>
          <span><?= psy_score_cell($tv, $band_color, $band_label) ?></span>
        </div>
      <?php endforeach; ?>
      <!-- Big Five radar (simple CSS bar chart) -->
      <div style="margin-top:1rem">
        <?php foreach ($traits as $key => $label):
          $tv = $sc[$key] ?? null;
          $pct = $tv !== null ? max(2, round((float)$tv)) : 0;
        ?>
        <div style="margin-bottom:.35rem">
          <div style="font-size:.7rem;color:var(--muted);margin-bottom:.15rem"><?= h(explode(' ',$label)[0]) ?></div>
          <div style="height:6px;border-radius:3px;background:rgba(255,255,255,.06);overflow:hidden">
            <div style="height:100%;width:<?= $pct ?>%;background:var(--cyan);border-radius:3px;transition:width .4s"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Learning + Engagement + Reasoning -->
    <div class="card" style="padding:1.25rem 1.5rem">
      <div class="section-title" style="margin-bottom:.75rem">Other Indices</div>
      <div style="display:flex;flex-direction:column;gap:.6rem">
        <div style="display:flex;justify-content:space-between;font-size:.84rem">
          <span>Learning</span><?= psy_score_cell($sc['learning_index'], $band_color, $band_label) ?>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:.84rem">
          <span>Engagement</span><?= psy_score_cell($sc['engagement_index'], $band_color, $band_label) ?>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:.84rem">
          <span>Reasoning</span>
          <span style="color:var(--cyan);font-weight:600"><?= (int)($sc['reasoning_score']??0) ?>/6
            <span class="badge badge-muted" style="font-size:.7rem"><?= h($sc['reasoning_band']??'') ?></span>
          </span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:.84rem">
          <span>Preference</span>
          <span style="color:var(--text);font-size:.8rem"><?= h($sc['preference_profile']??'—') ?></span>
        </div>
      </div>
      <!-- Top motivators -->
      <?php $top3 = json_decode($sc['motivation_top3']??'[]',true) ?: []; ?>
      <?php if ($top3): ?>
        <div style="margin-top:1rem">
          <div style="font-size:.8rem;color:var(--muted);margin-bottom:.5rem;text-transform:uppercase;letter-spacing:.06em">Top Motivators</div>
          <?php foreach ($top3 as $i => $m): ?>
            <div style="display:flex;align-items:center;gap:.6rem;padding:.3rem 0;font-size:.84rem">
              <span style="min-width:20px;height:20px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:rgba(34,211,238,.12);color:var(--cyan);font-size:.7rem;font-weight:700"><?= $i+1 ?></span>
              <?= h($m) ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>

  <!-- Open responses -->
  <?php $open_resps = json_decode($sc['open_responses']??'[]',true) ?: []; ?>
  <?php if ($open_resps): ?>
  <div class="card" style="padding:1.25rem 1.5rem;margin-bottom:1rem">
    <div class="section-title" style="margin-bottom:1rem">Open Responses</div>
    <?php foreach ($open_resps as $resp): ?>
      <div style="margin-bottom:1.25rem;padding-bottom:1.25rem;border-bottom:1px solid var(--border)">
        <div style="font-size:.82rem;color:var(--muted);margin-bottom:.35rem"><?= h($resp['question']??'') ?></div>
        <div style="font-size:.9rem;line-height:1.6;white-space:pre-wrap"><?= h($resp['answer']??'—') ?></div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Editable recommendation -->
  <div class="card" style="padding:1.25rem 1.5rem;margin-bottom:1rem">
    <div class="section-title" style="margin-bottom:.75rem">Admin Recommendation</div>
    <form method="POST">
      <input type="hidden" name="action"        value="save_psy_recommendation">
      <input type="hidden" name="csrf"           value="<?= h($csrf) ?>">
      <input type="hidden" name="assessment_id"  value="<?= (int)$psy_view_id ?>">
      <textarea name="recommendation" rows="5"
        style="width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;
               color:var(--text);padding:.75rem 1rem;font-family:inherit;font-size:.9rem;
               line-height:1.5;resize:vertical;outline:none"
        placeholder="Internal recommendation / notes for this candidate…"><?= h($sc['recommendation']??'') ?></textarea>
      <div style="margin-top:.75rem">
        <button class="btn-add" type="submit">Save Recommendation</button>
      </div>
    </form>
  </div>

  <?php endif; // $sc ?>

  <?php elseif ($at === 'generate'): ?>
  <!-- ── GENERATE LINK ─────────────────────────────────────── -->

  <?php if (isset($_GET['generated']) && isset($_GET['tok'])): ?>
    <?php
      $gtok = preg_replace('/[^a-f0-9]/', '', $_GET['tok']);
      $gurl = '';
      if ($gtok && function_exists('enp_psy_assessment_url')) {
          $gurl = enp_psy_assessment_url($gtok);
      } elseif ($gtok && function_exists('home_url')) {
          $gurl = home_url('/psy-assessment/?t=' . $gtok);
      }
    ?>
    <div class="success-banner" style="margin-bottom:1.25rem">
      Link generated!
      <?php if ($gurl): ?>
        <div style="margin-top:.6rem;display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
          <input id="psy-gen-url" type="text" value="<?= h($gurl) ?>" readonly
            style="flex:1;min-width:260px;background:var(--bg);border:1px solid var(--border);
                   border-radius:7px;color:var(--text);padding:.5rem .75rem;font-size:.82rem;
                   font-family:monospace;outline:none">
          <button class="btn-add" onclick="navigator.clipboard.writeText(document.getElementById('psy-gen-url').value).then(()=>this.textContent='Copied!').catch(()=>{})" style="white-space:nowrap">Copy Link</button>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="section-title">Generate Psychometric Assessment Link</div>
  <p style="color:var(--muted);font-size:.85rem;margin-bottom:1.25rem">
    Creates a single-use expiring link for a candidate to complete the regional assessment. Link is valid for <?= defined('ENP_PSY_LINK_EXPIRY_DAYS') ? (int)ENP_PSY_LINK_EXPIRY_DAYS : 7 ?> days.
  </p>
  <div class="form-card">
    <form method="POST">
      <input type="hidden" name="action" value="generate_psy_link">
      <input type="hidden" name="csrf"   value="<?= h($csrf) ?>">
      <div class="form-grid">
        <div class="form-group">
          <label>Candidate Name</label>
          <input type="text" name="candidate_name" placeholder="Full name (optional at this stage)">
        </div>
        <div class="form-group">
          <label>Candidate Email</label>
          <input type="email" name="candidate_email" placeholder="candidate@example.com">
        </div>
        <div class="form-group">
          <label>Contact Number</label>
          <input type="text" name="candidate_phone" placeholder="+44 7700 000000">
        </div>
        <div class="form-group">
          <label>Region *</label>
          <select name="region" required>
            <option value="AUTO">Auto (default UK)</option>
            <option value="UK">UK</option>
            <option value="US">US</option>
            <option value="CA">CA</option>
            <option value="IN">IN</option>
          </select>
        </div>
        <div class="form-group">
          <label>Education Level *</label>
          <select name="education_level" required>
            <option value="">— select —</option>
            <option value="1">1 — School-leaving</option>
            <option value="2">2 — Diploma / Associate</option>
            <option value="3">3 — Bachelor</option>
            <option value="4">4 — Postgraduate</option>
          </select>
        </div>
        <div class="form-group">
          <label>Field *</label>
          <select name="field" required>
            <option value="">— select —</option>
            <option value="IT">IT</option>
            <option value="DATA_AI">Data Science &amp; AI</option>
            <option value="BUSINESS">Business</option>
            <option value="HR">HR</option>
            <option value="FINANCE">Finance</option>
            <option value="MARKETING">Marketing</option>
            <option value="INFRA">Infrastructure</option>
            <option value="INFOSEC">InfoSec</option>
            <option value="HONORS">Honors</option>
          </select>
        </div>
        <div class="form-group">
          <label>Payment Reference (optional)</label>
          <input type="text" name="payment_ref" placeholder="Razorpay order ID or invoice ref">
        </div>
      </div>
      <button class="btn-add" type="submit">Generate Link &rarr;</button>
    </form>
  </div>

  <?php elseif ($at === 'settings'): ?>
  <!-- ── RAZORPAY AUTO-TRIGGER TOGGLE ─────────────────────── -->

  <div class="section-title">Per-Product Razorpay Auto-Trigger</div>
  <p style="color:var(--muted);font-size:.85rem;margin-bottom:1.25rem">
    When enabled for a plan, a psychometric assessment link is automatically generated when a student is activated on that plan (Razorpay or manual). Requires WordPress to be loaded.
  </p>
  <?php
    $rzp_plans = function_exists('get_option') ? (array)get_option('enp_psy_rzp_plans', []) : [];
    $plan_opts = ['basic'=>'Basic','elite'=>'Elite','premium'=>'Premium','accelerator'=>'Career Accelerator','starter'=>'Career Starter'];
  ?>
  <div class="form-card">
    <form method="POST">
      <input type="hidden" name="action" value="save_psy_rzp_toggle">
      <input type="hidden" name="csrf"   value="<?= h($csrf) ?>">
      <div style="display:flex;flex-direction:column;gap:.6rem;margin-bottom:1.25rem">
        <?php foreach ($plan_opts as $pid => $plabel): ?>
          <label style="display:flex;align-items:center;gap:.65rem;font-size:.9rem;cursor:pointer">
            <input type="checkbox" name="rzp_plans[]" value="<?= h($pid) ?>"
              <?= in_array($pid, $rzp_plans, true) ? 'checked' : '' ?>
              style="width:16px;height:16px;accent-color:var(--cyan)">
            <?= h($plabel) ?>
          </label>
        <?php endforeach; ?>
      </div>
      <button class="btn-add" type="submit">Save Settings</button>
    </form>
  </div>

  <?php else: ?>
  <!-- ── LIST ──────────────────────────────────────────────── -->

  <!-- Stat cards -->
  <div class="stats-grid" style="margin-bottom:1.5rem">
    <div class="stat-card"><div class="stat-label">Total Assessments</div><div class="stat-value cyan"><?= (int)$psy_counts['total'] ?></div></div>
    <div class="stat-card"><div class="stat-label">Submitted</div><div class="stat-value green"><?= (int)$psy_counts['submitted'] ?></div></div>
    <div class="stat-card"><div class="stat-label">In Progress</div><div class="stat-value gold"><?= (int)$psy_counts['in_progress'] ?></div></div>
    <div class="stat-card"><div class="stat-label">Pending (not started)</div><div class="stat-value cyan"><?= (int)$psy_counts['pending'] ?></div></div>
  </div>

  <div class="section-title">All Assessments <span><?= (int)$psy_counts['total'] ?></span></div>
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Candidate</th><th>Region</th><th>Field</th><th>Level</th>
            <th>Status</th><th>Created</th><th>Expires</th><th>Link</th><th>Result</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($psy_assessments): foreach ($psy_assessments as $pa):
          $pst  = $pa['status'];
          $pstc = ['pending'=>'badge-muted','in_progress'=>'badge-gold','submitted'=>'badge-green'][$pst] ?? 'badge-muted';
          $expired = strtotime($pa['expires_at']) < time();
          $psy_url = '';
          if (function_exists('enp_psy_assessment_url')) $psy_url = enp_psy_assessment_url($pa['token']);
          elseif (function_exists('home_url')) $psy_url = home_url('/psy-assessment/?t=' . $pa['token']);
        ?>
          <tr>
            <td>
              <div style="font-weight:600;font-size:.88rem"><?= $pa['candidate_name'] ? h($pa['candidate_name']) : '<span style="color:var(--muted)">—</span>' ?></div>
              <div style="font-size:.76rem;color:var(--muted)"><?= h($pa['candidate_email']) ?></div>
            </td>
            <td style="font-size:.82rem"><?= h($pa['region']) ?><?= $pa['defaulted'] ? ' <span title="Auto-defaulted" style="color:var(--gold)">*</span>' : '' ?></td>
            <td style="font-size:.82rem"><?= h($pa['field']) ?></td>
            <td style="font-size:.82rem;text-align:center"><?= (int)$pa['education_level'] ?></td>
            <td><span class="badge <?= $pstc ?>"><?= h(str_replace('_',' ',$pst)) ?></span></td>
            <td style="font-size:.78rem;color:var(--muted);white-space:nowrap"><?= h(date('d M Y', strtotime($pa['created_at']))) ?></td>
            <td style="font-size:.78rem;<?= $expired ? 'color:var(--red)' : 'color:var(--muted)' ?>;white-space:nowrap">
              <?= h(date('d M Y', strtotime($pa['expires_at']))) ?><?= $expired ? ' (exp)' : '' ?>
            </td>
            <td>
              <?php if ($psy_url && $pst !== 'submitted'): ?>
                <button class="btn-edit psy-copy-btn" data-url="<?= h($psy_url) ?>" style="font-size:.72rem">Copy</button>
              <?php else: ?>
                <span style="color:var(--muted);font-size:.75rem">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($pst === 'submitted'): ?>
                <a href="?section=assessments&view=<?= (int)$pa['id'] ?>" class="btn-edit" style="font-size:.72rem;text-decoration:none">View</a>
              <?php else: ?>
                <span style="color:var(--muted);font-size:.75rem">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr class="empty-row"><td colspan="9">No assessments yet. Use the <a href="?section=assessments&tab=generate" style="color:var(--cyan)">Generate Link</a> tab to create the first one.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; // sub-tab ?>

  <?php elseif ($section === 'sessions'): ?>
  <!-- ── SESSIONS ───────────────────────────────────────────── -->

    <div class="section-title">Sessions <span><?= (int)$portal['sessions_total'] ?> total</span></div>
    <div class="card">
      <div class="table-wrap">
        <?php
        $all_sessions = [];
        try {
            $all_sessions = $db->query("
                SELECT ss.*, st.full_name AS student_name, m.full_name AS mentor_name
                FROM `{$p}enp_sessions` ss
                LEFT JOIN `{$p}enp_students` st ON ss.student_id = st.id
                LEFT JOIN `{$p}enp_mentors`  m  ON ss.mentor_id  = m.id
                ORDER BY ss.scheduled_at DESC LIMIT 100
            ")->fetchAll();
        } catch (PDOException $e) {}
        ?>
        <table>
          <thead><tr><th>Date</th><th>Student</th><th>Mentor</th><th>Duration</th><th>Rate</th><th>Status</th><th>Mentor Paid</th></tr></thead>
          <tbody>
          <?php if ($all_sessions): foreach ($all_sessions as $ss): ?>
            <tr>
              <td style="white-space:nowrap"><?= h(date('d M Y H:i', strtotime($ss['scheduled_at']))) ?></td>
              <td><?= h($ss['student_name'] ?? '—') ?></td>
              <td><?= h($ss['mentor_name']  ?? '—') ?></td>
              <td><?= (int)$ss['duration_min'] ?> min</td>
              <td style="color:var(--green)">&#8377;<?= number_format((float)$ss['rate_applied'], 0) ?></td>
              <td><span class="badge <?= $ss['status']==='completed' ? 'badge-green' : 'badge-cyan' ?>"><?= h($ss['status']) ?></span></td>
              <td><?= $ss['mentor_paid'] ? '<span class="badge badge-green">Yes</span>' : '<span style="color:var(--muted);font-size:.8rem;">No</span>' ?></td>
            </tr>
          <?php endforeach; else: ?>
            <tr class="empty-row"><td colspan="7">No sessions recorded yet.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php elseif ($section === 'payments'): ?>
  <!-- ── PORTAL PAYMENTS ────────────────────────────────────── -->

    <?php if (isset($_GET['paid'])): ?>
      <div class="success-banner">Student activated — set-password email sent (if new account).</div>
    <?php elseif (isset($_GET['err'])): ?>
      <div class="success-banner" style="background:rgba(248,113,113,.08);border-color:rgba(248,113,113,.25);color:var(--red)">Error: <?= h($_GET['err']) ?></div>
    <?php endif; ?>

    <!-- Manual mark-paid form -->
    <div class="section-title">Mark Student as Paid</div>
    <p style="color:var(--muted);font-size:.85rem;margin-bottom:1rem;">
      Use this to activate a student who paid offline, via bank transfer, or any method outside Razorpay.
      A set-password email is sent only if the student does not yet have a WordPress account.
    </p>
    <div class="form-card">
      <form method="POST">
        <input type="hidden" name="action" value="mark_student_paid">
        <input type="hidden" name="csrf"   value="<?= h($csrf) ?>">
        <div class="form-grid">
          <div class="form-group">
            <label>Student Email *</label>
            <input type="email" name="email" placeholder="student@example.com" required>
          </div>
          <div class="form-group">
            <label>Plan *</label>
            <select name="plan_id" required>
              <option value="">— select plan —</option>
              <option value="basic">Basic Plan — ₹1,50,000</option>
              <option value="elite">Elite Plan — ₹2,50,000</option>
              <option value="premium">Premium Plan — ₹3,50,000</option>
              <option value="accelerator">Career Accelerator Combo — ₹5,50,000</option>
              <option value="starter">Career Starter Combo — ₹3,75,000</option>
            </select>
          </div>
          <div class="form-group">
            <label>Amount Received (₹) *</label>
            <input type="number" name="amount" step="0.01" min="1" placeholder="e.g. 150000" required>
          </div>
        </div>
        <button class="btn-add" type="submit"
          onclick="return confirm('Activate this student and send a set-password email?')">
          Activate Student &rarr;
        </button>
      </form>
    </div>

    <!-- Payments table -->
    <?php
    $portal_payments = [];
    try {
        $portal_payments = $db->query(
            "SELECT p.*, s.full_name AS student_name
             FROM `{$p}enp_payments` p
             LEFT JOIN `{$p}enp_students` s ON p.student_id = s.id
             ORDER BY p.created_at DESC LIMIT 100"
        )->fetchAll();
    } catch (PDOException $e) {}
    ?>
    <div class="section-title">All Portal Payments <span><?= count($portal_payments) ?></span></div>
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Date</th><th>Email</th><th>Student</th><th>Plan</th>
              <th>Amount</th><th>Gateway</th><th>Ref / Payment ID</th><th>Status</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($portal_payments): foreach ($portal_payments as $pay): ?>
            <tr>
              <td style="white-space:nowrap"><?= h(date('d M Y', strtotime($pay['created_at']))) ?></td>
              <td style="font-size:.82rem"><?= h($pay['email']) ?></td>
              <td style="font-size:.82rem"><?= $pay['student_name'] ? h($pay['student_name']) : '<span style="color:var(--muted)">—</span>' ?></td>
              <td><span class="badge badge-cyan"><?= h(strtoupper($pay['plan_id'])) ?></span></td>
              <td class="amount">&#8377;<?= number_format((float)$pay['amount'], 0) ?></td>
              <td style="font-size:.8rem;color:var(--muted)"><?= h($pay['gateway']) ?></td>
              <td style="font-size:.76rem;color:var(--muted);font-family:monospace;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                <?= h($pay['gateway_payment_id'] ?: $pay['gateway_order_id'] ?: '—') ?>
              </td>
              <td>
                <span class="badge <?= $pay['status']==='paid' ? 'badge-green' : ($pay['status']==='created' ? 'badge-gold' : 'badge-red') ?>">
                  <?= h($pay['status']) ?>
                </span>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr class="empty-row"><td colspan="8">No portal payments yet. Use the form above to manually activate a student, or share the enrolment page for Razorpay payments.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php endif; ?>

  </main>
</div><!-- .dash -->

<script>
/* globals used by inline scripts */
var ajaxUrl    = <?= json_encode(function_exists('admin_url') ? admin_url('admin-ajax.php') : '') ?>;
var portalNonce = <?= json_encode(function_exists('wp_create_nonce') ? wp_create_nonce('enp_portal') : '') ?>;
</script>

<script>
(function(){
  if (document.getElementById('revenueChart') === null) return;

  const labels  = <?= json_encode($monthly_labels) ?>;
  const paypal  = <?= json_encode($monthly_paypal) ?>;
  const manual  = <?= json_encode($monthly_manual) ?>;

  new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
      labels,
      datasets: [
        { label: 'PayPal', data: paypal, backgroundColor: 'rgba(34,211,238,.7)', borderRadius: 4 },
        { label: 'Manual', data: manual, backgroundColor: 'rgba(251,191,36,.6)', borderRadius: 4 },
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { labels: { color: '#94a3b8', font: { size: 12 } } },
        tooltip: { callbacks: { label: ctx => ' $' + ctx.parsed.y.toFixed(2) } }
      },
      scales: {
        x: { stacked: true, ticks: { color: '#94a3b8', font: { size: 11 } }, grid: { color: '#1f2937' } },
        y: { stacked: true, ticks: { color: '#94a3b8', callback: v => '$' + v }, grid: { color: '#1f2937' } }
      }
    }
  });
})();
</script>

<script>
// ── Reject / Request-info action modal ───────────────────────────────────────
(function () {
  var modal  = document.getElementById('enp-action-modal');
  var btnCfm = document.getElementById('enp-modal-confirm');
  if (!modal || !btnCfm) return;

  document.querySelectorAll('[data-action]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var act  = btn.dataset.action;
      var id   = btn.dataset.id;
      var name = btn.dataset.name;
      var title = act === 'reject_mentor' ? 'Reject — ' + name : 'Request Info — ' + name;
      var desc  = act === 'reject_mentor'
        ? 'Optional feedback for the applicant (included in rejection email):'
        : 'What additional information is needed? (sent to applicant):';
      document.getElementById('enp-modal-title').textContent = title;
      document.getElementById('enp-modal-desc').textContent  = desc;
      document.getElementById('enp-modal-note').value = '';
      modal.classList.add('open');
      btnCfm.onclick = function () {
        var note = document.getElementById('enp-modal-note').value.trim();
        if (act === 'request_info' && !note) { alert('Please enter the information needed from the applicant.'); return; }
        document.getElementById('enp-fa').value  = act;
        document.getElementById('enp-fid').value = id;
        document.getElementById('enp-fn').value  = note;
        document.getElementById('enp-action-form').submit();
      };
    });
  });
  modal.addEventListener('click', function (e) { if (e.target === this) this.classList.remove('open'); });
})();

// ── Set student sessions modal ───────────────────────────────────────────────
(function () {
  var modal  = document.getElementById('enp-sessions-modal');
  var btnCfm = document.getElementById('enp-sessions-confirm');
  if (!modal || !btnCfm) return;
  var currentId = null;
  document.querySelectorAll('.enp-set-sessions-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      currentId = btn.dataset.id;
      document.getElementById('enp-sessions-modal-name').textContent = btn.dataset.name;
      document.getElementById('enp-sessions-input').value = btn.dataset.current || 4;
      modal.classList.add('open');
    });
  });
  btnCfm.addEventListener('click', function () {
    var val = parseInt(document.getElementById('enp-sessions-input').value, 10);
    if (!currentId || isNaN(val) || val < 1 || val > 100) { alert('Enter a number between 1 and 100.'); return; }
    document.getElementById('enp-sform-id').value       = currentId;
    document.getElementById('enp-sform-sessions').value = val;
    document.getElementById('enp-sessions-form').submit();
  });
  modal.addEventListener('click', function (e) { if (e.target === this) this.classList.remove('open'); });
})();

// ── Deny mentor change modal ─────────────────────────────────────────────────
(function () {
  var modal  = document.getElementById('enp-deny-modal');
  var btnCfm = document.getElementById('enp-deny-confirm');
  if (!modal || !btnCfm) return;
  document.querySelectorAll('.enp-deny-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('enp-deny-title').textContent = 'Deny — ' + btn.dataset.name;
      document.getElementById('enp-deny-note').value = '';
      document.getElementById('enp-dfid').value = btn.dataset.id;
      modal.classList.add('open');
    });
  });
  btnCfm.addEventListener('click', function () {
    document.getElementById('enp-dfnote').value = document.getElementById('enp-deny-note').value.trim();
    document.getElementById('enp-deny-form').submit();
  });
  modal.addEventListener('click', function (e) { if (e.target === this) this.classList.remove('open'); });
})();

// ── Edit mentor modal ────────────────────────────────────────────────────────
(function () {
  var modal  = document.getElementById('enp-edit-modal');
  var btnCfm = document.getElementById('enp-edit-confirm');
  if (!modal || !btnCfm) return;

  var editId = null;

  document.querySelectorAll('.btn-edit').forEach(function (btn) {
    btn.addEventListener('click', function () {
      editId = btn.dataset.id;
      document.getElementById('enp-edit-mentor-name').textContent = btn.dataset.name;
      document.getElementById('enp-edit-rate').value  = btn.dataset.rate;
      document.getElementById('enp-edit-slots').value = btn.dataset.slots;
      var lines = '';
      try {
        var obj = JSON.parse(btn.dataset.extra || '{}');
        lines = Object.entries(obj).map(function (e) { return e[0] + ': ' + e[1]; }).join('\n');
      } catch (ex) {}
      document.getElementById('enp-edit-extra').value = lines;
      modal.classList.add('open');
    });
  });

  btnCfm.addEventListener('click', function () {
    var lines = document.getElementById('enp-edit-extra').value.split('\n');
    var obj   = {};
    lines.forEach(function (line) {
      var idx = line.indexOf(':');
      if (idx > 0) {
        var k = line.substring(0, idx).trim();
        var v = line.substring(idx + 1).trim();
        if (k) obj[k] = v;
      }
    });
    document.getElementById('enp-eform-id').value    = editId;
    document.getElementById('enp-eform-rate').value  = document.getElementById('enp-edit-rate').value;
    document.getElementById('enp-eform-slots').value = document.getElementById('enp-edit-slots').value;
    document.getElementById('enp-eform-extra').value = JSON.stringify(obj);
    document.getElementById('enp-edit-form').submit();
  });

  modal.addEventListener('click', function (e) { if (e.target === this) this.classList.remove('open'); });
})();
</script>

<script>
/* ── Psychometric Assessments JS ──────────────────────────────────────── */
(function() {
  // Copy link buttons in the list view
  document.querySelectorAll('.psy-copy-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var url = this.getAttribute('data-url');
      if (!url) return;
      navigator.clipboard.writeText(url).then(function() {
        btn.textContent = 'Copied!';
        setTimeout(function() { btn.textContent = 'Copy'; }, 2500);
      }).catch(function() {
        // Fallback for older browsers
        var tmp = document.createElement('textarea');
        tmp.value = url;
        tmp.style.position = 'fixed';
        tmp.style.opacity = '0';
        document.body.appendChild(tmp);
        tmp.select();
        document.execCommand('copy');
        document.body.removeChild(tmp);
        btn.textContent = 'Copied!';
        setTimeout(function() { btn.textContent = 'Copy'; }, 2500);
      });
    });
  });

  // Email candidate button (sends assessment URL via AJAX)
  document.querySelectorAll('.psy-email-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var assessId = this.getAttribute('data-id');
      var origText = this.textContent;
      this.disabled = true;
      this.textContent = 'Sending…';
      var fd = new FormData();
      fd.append('action', 'enp_psy_email_link');
      fd.append('nonce',  portalNonce);
      fd.append('assessment_id', assessId);
      fetch(ajaxUrl, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
          btn.textContent = data.success ? 'Sent!' : 'Failed';
          btn.style.color = data.success ? 'var(--green)' : 'var(--red)';
          setTimeout(function() {
            btn.textContent = origText;
            btn.style.color = '';
            btn.disabled = false;
          }, 3000);
        })
        .catch(function() {
          btn.textContent = 'Error';
          btn.disabled = false;
        });
    });
  });

  // Big Five trait bar animation on result view
  var traitBars = document.querySelectorAll('.psy-trait-bar-fill');
  if (traitBars.length) {
    requestAnimationFrame(function() {
      traitBars.forEach(function(bar) {
        bar.style.transition = 'width .6s ease-out';
      });
    });
  }

  // Auto-dismiss success banners after 5s (assessments section)
  var banners = document.querySelectorAll('.success-banner');
  banners.forEach(function(b) {
    setTimeout(function() {
      b.style.transition = 'opacity .4s';
      b.style.opacity = '0';
      setTimeout(function() { b.style.display = 'none'; }, 450);
    }, 5000);
  });
})();
</script>

<?php endif; // logged_in ?>
</body>
</html>
