<?php
/**
 * Psychometric scoring engine — unit tests.
 *
 * Run:  php enterns-portal/tests/psy-scorer-test.php
 * (No WP, no DB. Tests pure math in ENP_Psy_Scorer static helpers via
 *  a thin test harness defined below.)
 *
 * Tests use known-input → known-output patterns per the Scoring_Config sheet:
 *   Likert index  = (sum - min) / (max - min) * 100
 *   Reverse       = 6 − raw
 *   Sec6 norm     = (sum - 3) / (n×5 - 3) × 100
 *   Bands         = 80+ Strong | 60+ Solid | 40+ Mixed | <40 Watch
 *   Reasoning     = correct/6; 5-6 strong | 3-4 adequate | ≤2 gap
 *   Sec2          = A% ≥65 → Analytical | ≤35 → People | else Balanced
 *   Sec3 learning = ≥75 self-directed | 50–74 capable | <50 structured
 */

// ── Minimal test harness ───────────────────────────────────────────────────

$failures = 0;
$passes   = 0;

function assert_eq($label, $expected, $actual): void {
    global $failures, $passes;
    // Float comparison with epsilon.
    if (is_float($expected) || is_float($actual)) {
        $ok = abs((float)$expected - (float)$actual) < 0.01;
    } else {
        $ok = ($expected === $actual);
    }
    if ($ok) {
        echo "  PASS  {$label}\n";
        $passes++;
    } else {
        $exp_str = var_export($expected, true);
        $act_str = var_export($actual, true);
        echo "  FAIL  {$label}\n        expected: {$exp_str}\n        got:      {$act_str}\n";
        $failures++;
    }
}

// ── Stub: load scorer without WP ──────────────────────────────────────────

// Replace WP functions used only at runtime (not in the static scoring helpers).
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($v) { return json_encode($v); }
}
if (!defined('ABSPATH')) { define('ABSPATH', '/'); }

// Load the scorer (only uses static math, no WP DB at test time).
require_once __DIR__ . '/../includes/psy-scorer.php';

// ── Expose private methods via reflection ─────────────────────────────────

function call_private(string $method, array $args = []) {
    $ref    = new ReflectionClass('ENP_Psy_Scorer');
    $method = $ref->getMethod($method);
    $method->setAccessible(true);
    return $method->invokeArgs(null, $args);
}

// ── Tests ─────────────────────────────────────────────────────────────────

echo "\n=== Likert index ===\n";

// n=1, raw=1 → (1-1)/(5-1)*100 = 0
assert_eq('likert_index n=1 raw=1', 0.0, call_private('likert_index', [1, 1]));
// n=1, raw=5 → (5-1)/(5-1)*100 = 100
assert_eq('likert_index n=1 raw=5', 100.0, call_private('likert_index', [5, 1]));
// n=1, raw=3 → (3-1)/(5-1)*100 = 50
assert_eq('likert_index n=1 raw=3', 50.0, call_private('likert_index', [3, 1]));
// n=4, all-5 → (20-4)/(20-4)*100 = 100
assert_eq('likert_index n=4 all-5', 100.0, call_private('likert_index', [20, 4]));
// n=4, all-1 → (4-4)/(20-4)*100 = 0
assert_eq('likert_index n=4 all-1', 0.0, call_private('likert_index', [4, 4]));
// n=4, mixed 3+4+2+5=14 → (14-4)/(20-4)*100 = 62.5
assert_eq('likert_index n=4 mixed', 62.5, call_private('likert_index', [14, 4]));

echo "\n=== Reverse scoring ===\n";
// reverse Y, raw=1 → 6-1=5
assert_eq('reverse raw=1', 5, call_private('apply_reverse', [1, 'Y']));
// reverse Y, raw=5 → 6-5=1
assert_eq('reverse raw=5', 1, call_private('apply_reverse', [5, 'Y']));
// reverse Y, raw=3 → 3
assert_eq('reverse raw=3', 3, call_private('apply_reverse', [3, 'Y']));
// reverse N, raw=2 → 2 (unchanged)
assert_eq('no reverse N',  2, call_private('apply_reverse', [2, 'N']));
assert_eq('no reverse empty', 4, call_private('apply_reverse', [4, '']));

echo "\n=== Band labels ===\n";
assert_eq('band 80',   'Strong', ENP_Psy_Scorer::band_label(80.0));
assert_eq('band 100',  'Strong', ENP_Psy_Scorer::band_label(100.0));
assert_eq('band 79.9', 'Solid',  ENP_Psy_Scorer::band_label(79.9));
assert_eq('band 60',   'Solid',  ENP_Psy_Scorer::band_label(60.0));
assert_eq('band 59.9', 'Mixed',  ENP_Psy_Scorer::band_label(59.9));
assert_eq('band 40',   'Mixed',  ENP_Psy_Scorer::band_label(40.0));
assert_eq('band 39.9', 'Watch',  ENP_Psy_Scorer::band_label(39.9));
assert_eq('band 0',    'Watch',  ENP_Psy_Scorer::band_label(0.0));

echo "\n=== Learning band (Sec3) ===\n";
assert_eq('learning ≥75',  'self-directed',              ENP_Psy_Scorer::learning_band(75.0));
assert_eq('learning 74',   'capable-with-support',       ENP_Psy_Scorer::learning_band(74.9));
assert_eq('learning 50',   'capable-with-support',       ENP_Psy_Scorer::learning_band(50.0));
assert_eq('learning 49',   'needs-structured-onboarding', ENP_Psy_Scorer::learning_band(49.0));
assert_eq('learning null', 'unknown',                    ENP_Psy_Scorer::learning_band(null));

echo "\n=== Sec6 trait normalisation ===\n";
// 3 items × 1–5 scale. min=3, max=15.
// All-5: (15-3)/(15-3)*100 = 100
// All-1: (3-3)/(15-3)*100 = 0
// Mixed 4+3+5=12: (12-3)/(15-3)*100 = 75

// We test via score_sec6 indirectly by constructing mock item_map + resp_map.
$sec6_items = [
    ['item_id'=>'T1','section'=>6,'type'=>'likert','reverse_scored'=>'N','trait_or_cluster'=>'C'],
    ['item_id'=>'T2','section'=>6,'type'=>'likert','reverse_scored'=>'N','trait_or_cluster'=>'C'],
    ['item_id'=>'T3','section'=>6,'type'=>'likert','reverse_scored'=>'N','trait_or_cluster'=>'C'],
];
$item_map = ['T1'=>$sec6_items[0],'T2'=>$sec6_items[1],'T3'=>$sec6_items[2]];

// All-5 → expect 100
$resp_map_all5 = ['T1'=>'5','T2'=>'5','T3'=>'5'];
$res = call_private('score_sec6', [['T1','T2','T3'], $item_map, $resp_map_all5]);
assert_eq('sec6 all-5 C', 100.0, $res['C']);

// All-1 → expect 0
$resp_map_all1 = ['T1'=>'1','T2'=>'1','T3'=>'1'];
$res = call_private('score_sec6', [['T1','T2','T3'], $item_map, $resp_map_all1]);
assert_eq('sec6 all-1 C', 0.0, $res['C']);

// 4+3+5=12 → (12-3)/(15-3)*100 = 75
$resp_map_mixed = ['T1'=>'4','T2'=>'3','T3'=>'5'];
$res = call_private('score_sec6', [['T1','T2','T3'], $item_map, $resp_map_mixed]);
assert_eq('sec6 mixed C', 75.0, $res['C']);

// With reverse: raw=1 reversed → 5; raw=5 reversed → 1; raw=3 = 3. sum=9 → (9-3)/12*100=50
$sec6_rev = [
    ['item_id'=>'R1','section'=>6,'type'=>'likert','reverse_scored'=>'Y','trait_or_cluster'=>'E'],
    ['item_id'=>'R2','section'=>6,'type'=>'likert','reverse_scored'=>'N','trait_or_cluster'=>'E'],
    ['item_id'=>'R3','section'=>6,'type'=>'likert','reverse_scored'=>'N','trait_or_cluster'=>'E'],
];
$item_map2 = ['R1'=>$sec6_rev[0],'R2'=>$sec6_rev[1],'R3'=>$sec6_rev[2]];
$resp_map_rev = ['R1'=>'1','R2'=>'5','R3'=>'3']; // reversed: 5; plain: 5,3 → sum=13 → (13-3)/12*100=83.33
$res2 = call_private('score_sec6', [['R1','R2','R3'], $item_map2, $resp_map_rev]);
assert_eq('sec6 with reverse E', 83.33, $res2['E']);

echo "\n=== Sec7 reasoning ===\n";
$s7_items = [
    ['item_id'=>'S7A','section'=>7,'type'=>'mcq','reverse_scored'=>'','trait_or_cluster'=>'REASONING','question_text'=>'Q1','correct'=>'20%'],
    ['item_id'=>'S7B','section'=>7,'type'=>'mcq','reverse_scored'=>'','trait_or_cluster'=>'REASONING','question_text'=>'Q2','correct'=>'1,380'],
    ['item_id'=>'S7C','section'=>7,'type'=>'mcq','reverse_scored'=>'','trait_or_cluster'=>'REASONING','question_text'=>'Q3','correct'=>'True'],
    ['item_id'=>'S7D','section'=>7,'type'=>'mcq','reverse_scored'=>'','trait_or_cluster'=>'REASONING','question_text'=>'Q4','correct'=>'B'],
    ['item_id'=>'S7E','section'=>7,'type'=>'mcq','reverse_scored'=>'','trait_or_cluster'=>'REASONING','question_text'=>'Q5','correct'=>'C'],
    ['item_id'=>'S7F','section'=>7,'type'=>'mcq','reverse_scored'=>'','trait_or_cluster'=>'REASONING','question_text'=>'Q6','correct'=>'D'],
];
$imap7 = [];
foreach ($s7_items as $i) { $imap7[$i['item_id']] = $i; }

// All correct → 6/6 → strong
$rmap_all_correct = ['S7A'=>'20%','S7B'=>'1,380','S7C'=>'True','S7D'=>'B','S7E'=>'C','S7F'=>'D'];
$r = call_private('score_sec7', [['S7A','S7B','S7C','S7D','S7E','S7F'], $imap7, $rmap_all_correct]);
assert_eq('sec7 6/6 score', 6, $r['score']);
assert_eq('sec7 6/6 band',  'strong', $r['band']);

// 4 correct → adequate
$rmap_4 = ['S7A'=>'20%','S7B'=>'1,380','S7C'=>'True','S7D'=>'B','S7E'=>'WRONG','S7F'=>'WRONG'];
$r = call_private('score_sec7', [['S7A','S7B','S7C','S7D','S7E','S7F'], $imap7, $rmap_4]);
assert_eq('sec7 4/6 score', 4, $r['score']);
assert_eq('sec7 4/6 band',  'adequate', $r['band']);

// 2 correct → gap
$rmap_2 = ['S7A'=>'20%','S7B'=>'1,380','S7C'=>'WRONG','S7D'=>'WRONG','S7E'=>'WRONG','S7F'=>'WRONG'];
$r = call_private('score_sec7', [['S7A','S7B','S7C','S7D','S7E','S7F'], $imap7, $rmap_2]);
assert_eq('sec7 2/6 score', 2, $r['score']);
assert_eq('sec7 2/6 band',  'gap', $r['band']);

// 0 correct → gap
$rmap_0 = ['S7A'=>'WRONG','S7B'=>'WRONG','S7C'=>'WRONG','S7D'=>'WRONG','S7E'=>'WRONG','S7F'=>'WRONG'];
$r = call_private('score_sec7', [['S7A','S7B','S7C','S7D','S7E','S7F'], $imap7, $rmap_0]);
assert_eq('sec7 0/6 score', 0, $r['score']);
assert_eq('sec7 0/6 band',  'gap', $r['band']);

// Case-insensitive match.
$rmap_case = ['S7A'=>'20%','S7B'=>'1,380','S7C'=>'true','S7D'=>'b','S7E'=>'c','S7F'=>'d'];
$r = call_private('score_sec7', [['S7A','S7B','S7C','S7D','S7E','S7F'], $imap7, $rmap_case]);
assert_eq('sec7 case-insensitive 6/6', 6, $r['score']);

echo "\n=== Sec2 preference profile ===\n";
// 10 items, all A → ≥65% A → Analytical
$rmap_10a = [];
for ($i=1; $i<=10; $i++) { $rmap_10a['P'.$i] = 'A'; }
$ids10 = array_keys($rmap_10a);
$p2 = call_private('score_sec2', [$ids10, $rmap_10a]);
assert_eq('sec2 all-A', 'Analytical / Data-oriented', $p2);

// 10 items, all B → ≤35% A → People
$rmap_10b = [];
for ($i=1; $i<=10; $i++) { $rmap_10b['P'.$i] = 'B'; }
$p2b = call_private('score_sec2', [$ids10, $rmap_10b]);
assert_eq('sec2 all-B', 'People-oriented / Collaborative', $p2b);

// 5A + 5B → 50% → Balanced
$rmap_50 = [];
for ($i=1; $i<=5; $i++)  { $rmap_50['P'.$i]    = 'A'; }
for ($i=6; $i<=10; $i++) { $rmap_50['P'.$i]    = 'B'; }
$p2m = call_private('score_sec2', [$ids10, $rmap_50]);
assert_eq('sec2 50/50', 'Balanced — analytical and people skills', $p2m);

echo "\n=== Sec4 motivation top-3 ===\n";
$s4_items = [];
for ($i=1; $i<=10; $i++) {
    $s4_items['M'.$i] = ['item_id'=>'M'.$i,'section'=>4,'type'=>'rank',
        'reverse_scored'=>'','trait_or_cluster'=>'MOTIVATION',
        'question_text'=>'Driver '.$i,'correct'=>''];
}
// Rank 1=M3, 2=M7, 3=M1 (others unranked / high rank)
$rmap_s4 = ['M1'=>'3','M2'=>'5','M3'=>'1','M4'=>'6','M5'=>'7','M6'=>'8','M7'=>'2','M8'=>'9','M9'=>'10','M10'=>'4'];
$top3 = call_private('score_sec4', [array_keys($rmap_s4), $s4_items, $rmap_s4]);
assert_eq('sec4 top3[0]', 'Driver 3', $top3[0]);
assert_eq('sec4 top3[1]', 'Driver 7', $top3[1]);
assert_eq('sec4 top3[2]', 'Driver 1', $top3[2]);
assert_eq('sec4 count=3', 3, count($top3));

echo "\n=== Summary ===\n";
$total = $passes + $failures;
echo "  {$passes}/{$total} passed";
if ($failures > 0) {
    echo " — {$failures} FAILED\n";
    exit(1);
} else {
    echo " — all green\n";
    exit(0);
}
