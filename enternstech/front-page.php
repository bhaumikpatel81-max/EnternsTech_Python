<?php
/**
 * Front Page Template — clean PHP/HTML, no Design Canvas / React bundle
 *
 * @package EnternsTech
 */

// ══════════════════════════════════════════════════════════════════════════════
// EDITABLE CONTENT — edit these arrays to update what the site shows
// ══════════════════════════════════════════════════════════════════════════════

$et_placements = array(
	array( 'role' => 'Data Scientist',       'weeks' => 11, 'company' => 'Infosys',   'initials' => 'DS' ),
	array( 'role' => 'Java Developer',        'weeks' =>  9, 'company' => 'TCS',       'initials' => 'JD' ),
	array( 'role' => 'DevOps Engineer',       'weeks' => 13, 'company' => 'Wipro',     'initials' => 'DO' ),
	array( 'role' => 'Business Analyst',      'weeks' => 15, 'company' => 'Accenture', 'initials' => 'BA' ),
	array( 'role' => 'Cybersecurity Analyst', 'weeks' => 12, 'company' => 'HCL',       'initials' => 'CA' ),
	array( 'role' => 'Full Stack Developer',  'weeks' => 10, 'company' => 'Capgemini', 'initials' => 'FS' ),
);

$et_journey = array(
	array( 'num' => '01', 'title' => 'Learn',       'desc' => 'Hands-on training in your chosen technology track with live use-case projects.',  'delay' => 0 ),
	array( 'num' => '02', 'title' => 'Build',        'desc' => 'Create a portfolio of real-world projects that employers actually want to see.',    'delay' => 80 ),
	array( 'num' => '03', 'title' => 'Market',       'desc' => 'We optimise your resume and LinkedIn, then market you to 150+ active recruiters.', 'delay' => 160 ),
	array( 'num' => '04', 'title' => 'Interview',    'desc' => 'Mock interviews, coaching sessions, and guided prep for every round.',              'delay' => 240 ),
	array( 'num' => '05', 'title' => 'Get Hired',    'desc' => 'Land your role — then keep growing with post-placement support.',                  'delay' => 320 ),
);

$et_tracks = array(
	array(
		'cat'   => 'AI, ML & Data Science',
		'tag'   => 'Trending',
		'roles' => array( 'Data Scientist', 'ML Engineer', 'AI Research Analyst', 'Data Analyst', 'Business Intelligence Analyst', 'NLP Engineer', 'Computer Vision Engineer' ),
	),
	array(
		'cat'   => 'Software Engineering',
		'tag'   => 'High Demand',
		'roles' => array( 'Java Developer', 'Full Stack Developer', 'React Developer', 'Node.js Engineer', 'Python Developer', 'Backend Engineer', 'API Developer' ),
	),
	array(
		'cat'   => 'Cloud & DevOps',
		'tag'   => 'Fast-Growing',
		'roles' => array( 'DevOps Engineer', 'Cloud Architect', 'AWS Solutions Architect', 'Azure Engineer', 'GCP Engineer', 'Site Reliability Engineer', 'Infrastructure Engineer' ),
	),
	array(
		'cat'   => 'QA, Security & Enterprise',
		'tag'   => 'Evergreen',
		'roles' => array( 'QA Automation Engineer', 'Cybersecurity Analyst', 'Business Analyst', 'Project Manager', 'Scrum Master', 'SAP Consultant', 'Salesforce Developer' ),
	),
	array(
		'cat'   => 'Data Platforms & Analytics',
		'tag'   => 'Expanding',
		'roles' => array( 'Data Engineer', 'ETL Developer', 'Snowflake Engineer', 'Databricks Engineer', 'Power BI Developer', 'Tableau Analyst', 'Database Administrator' ),
	),
);

$et_services = array(
	array( 't' => 'Career Guidance',      'd' => 'Personalised roadmap built for your background, track, and target market.' ),
	array( 't' => 'Resume Crafting',      'd' => 'ATS-ready resume with strong verbs, metrics, and keyword optimisation.' ),
	array( 't' => 'Live Use-Cases',       'd' => 'Real company-like projects that build portfolio depth recruiters respect.' ),
	array( 't' => 'Resume Marketing',     'd' => 'Active outreach to 150+ recruiters across US, Canada, and India networks.' ),
	array( 't' => 'Interview Prep',       'd' => 'Technical mock interviews, HR coaching, and company-specific prep.' ),
	array( 't' => 'Documentation Support','d' => 'Work-auth documentation guidance for OPT, CPT, and H-1B transitions.' ),
);

$et_values = array(
	array( 't' => 'Professional & Ethical', 'd' => 'We operate with full transparency — no hidden fees, no inflated promises.' ),
	array( 't' => 'Results-Driven',         'd' => 'Every decision we make is tied back to one metric: your placement.' ),
	array( 't' => 'Personalised Attention', 'd' => 'Small cohorts mean every candidate gets dedicated recruiter focus.' ),
);

$et_steps = array(
	array( 'n' => '01', 't' => 'Enrolment & Assessment',    'd' => 'We map your background to the right track and build a personalised timeline.' ),
	array( 'n' => '02', 't' => 'Practical Know-How',        'd' => 'Live use-case training, code reviews, and expert-led technical sessions.' ),
	array( 'n' => '03', 't' => 'Portfolio Creation',        'd' => 'You deliver real deliverables — dashboards, APIs, pipelines — for your GitHub.' ),
	array( 'n' => '04', 't' => 'Resume Building',           'd' => 'Keyword-optimised, ATS-ready resume written by career specialists.' ),
	array( 'n' => '05', 't' => 'Resume Marketing',          'd' => 'We push your profile to 150+ active US and Canada recruiters.' ),
	array( 'n' => '06', 't' => 'Interview Preparation',     'd' => 'Mock technical and HR rounds, plus offer negotiation coaching.' ),
	array( 'n' => '07', 't' => 'Job Facilitation',          'd' => 'Interview scheduling, offer support, and onboarding guidance.' ),
);

$et_outcomes = array(
	array( 'role' => 'Data Scientist',        'placed' => 'Placed in 11 weeks', 'co' => 'Infosys',   'track' => 'AI, ML & Data Science',         'plan' => 'Elite' ),
	array( 'role' => 'Java Developer',         'placed' => 'Placed in 9 weeks',  'co' => 'TCS',       'track' => 'Software Engineering',           'plan' => 'Basic' ),
	array( 'role' => 'DevOps Engineer',        'placed' => 'Placed in 13 weeks', 'co' => 'Wipro',     'track' => 'Cloud & DevOps',                 'plan' => 'Elite' ),
	array( 'role' => 'Business Analyst',       'placed' => 'Placed in 15 weeks', 'co' => 'Accenture', 'track' => 'QA, Security & Enterprise',      'plan' => 'Premium' ),
	array( 'role' => 'Cybersecurity Analyst',  'placed' => 'Placed in 12 weeks', 'co' => 'HCL',       'track' => 'QA, Security & Enterprise',      'plan' => 'Elite' ),
	array( 'role' => 'Full Stack Developer',   'placed' => 'Placed in 10 weeks', 'co' => 'Capgemini', 'track' => 'Software Engineering',           'plan' => 'Premium' ),
);

$et_plans = array(
	array(
		'id'        => 'basic',
		'name'      => 'Basic Plan',
		'tagline'   => 'Get market-ready with core career fundamentals',
		'badge'     => '',
		'featured'  => false,
		'priceIntl' => '$2,500',
		'priceDom'  => '₹1,50,000',
		'noteIntl'  => 'Initial non-refundable fee',
		'noteDom'   => 'Initial non-refundable fee',
		'features'  => array(
			'OPT / CPT Friendly Programme',
			'Dedicated Recruiter Connection',
			'Resume Review & Build',
			'LinkedIn Profile Optimisation',
			'Career Coaching (3 sessions)',
			'Mock Interviews (2 rounds)',
			'US Job Applications Support',
		),
	),
	array(
		'id'        => 'elite',
		'name'      => 'Elite Plan',
		'tagline'   => 'Full-stack placement with priority recruiter access',
		'badge'     => 'Most Popular',
		'featured'  => true,
		'priceIntl' => '$4,000',
		'priceDom'  => '₹2,50,000',
		'noteIntl'  => 'Initial non-refundable fee',
		'noteDom'   => 'Initial non-refundable fee',
		'features'  => array(
			'Everything in Basic Plan',
			'Live Technical Brush-up with Expert',
			'Advanced Portfolio Creation',
			'Unlimited Mock Interviews',
			'Priority Recruiter Network (150+)',
			'LinkedIn & GitHub Optimisation',
			'Interview Scheduling Support',
			'Bi-weekly Progress Check-ins',
		),
	),
	array(
		'id'        => 'premium',
		'name'      => 'Premium Plan',
		'tagline'   => 'End-to-end placement with long-term career support',
		'badge'     => '',
		'featured'  => false,
		'priceIntl' => '$5,500',
		'priceDom'  => '₹3,50,000',
		'noteIntl'  => 'or $13,000 flat package',
		'noteDom'   => 'or ₹8,50,000 flat package',
		'features'  => array(
			'Everything in Elite Plan',
			'Guaranteed Interview Scheduling',
			'12-month Post-placement Support',
			'Salary Negotiation Coaching',
			'International Job Board Access',
			'Dedicated Account Manager',
		),
	),
);

$et_combos = array(
	array(
		'id'        => 'accelerator',
		'name'      => 'Career Accelerator Combo',
		'includes'  => 'Elite + Premium Plans',
		'priceIntl' => '$8,500',
		'priceDom'  => '₹5,50,000',
		'desc'      => 'The complete end-to-end programme — includes all Elite features plus long-term post-placement support at a bundled rate.',
	),
	array(
		'id'        => 'starter',
		'name'      => 'Career Starter Combo',
		'includes'  => 'Basic + Elite Plans',
		'priceIntl' => '$5,800',
		'priceDom'  => '₹3,75,000',
		'desc'      => 'Start with career fundamentals and unlock Elite placement power — perfect for candidates building from the ground up.',
	),
);

$et_comparison = array(
	array(
		'dept' => 'Technical Department',
		'rows' => array(
			array( 'feat' => 'Live Technical Brush-up with Expert', 'basic' => false,        'elite' => true,        'premium' => true ),
			array( 'feat' => 'Live Use-Case Projects',              'basic' => false,        'elite' => true,        'premium' => true ),
			array( 'feat' => 'Code Review & Mentoring',             'basic' => false,        'elite' => true,        'premium' => true ),
			array( 'feat' => 'Cloud / AI Platform Access',          'basic' => false,        'elite' => false,       'premium' => true ),
		),
	),
	array(
		'dept' => 'Career Development',
		'rows' => array(
			array( 'feat' => 'Resume Build & Optimisation',         'basic' => 'Standard',   'elite' => 'Advanced',  'premium' => 'Advanced' ),
			array( 'feat' => 'LinkedIn & GitHub Revamp',            'basic' => 'Basic',      'elite' => 'Advanced',  'premium' => 'Advanced' ),
			array( 'feat' => 'Mock Interview Sessions',             'basic' => '2 Rounds',   'elite' => 'Unlimited', 'premium' => 'Unlimited' ),
			array( 'feat' => 'Career Coaching Sessions',            'basic' => '3 Sessions', 'elite' => '6 Sessions','premium' => '12 Sessions' ),
		),
	),
	array(
		'dept' => 'Placement Support',
		'rows' => array(
			array( 'feat' => 'Active Recruiter Network',            'basic' => 'Standard',   'elite' => '150+',      'premium' => '200+' ),
			array( 'feat' => 'Job Application Support',             'basic' => 'Basic',      'elite' => 'Priority',  'premium' => 'Dedicated' ),
			array( 'feat' => 'Interview Scheduling',                'basic' => false,        'elite' => 'Limited',   'premium' => 'Full' ),
			array( 'feat' => 'Post-placement Support',              'basic' => false,        'elite' => '3 months',  'premium' => '12 months' ),
		),
	),
);

$et_faqs = array(
	array(
		'q' => 'Is this programme OPT / CPT / H-1B friendly?',
		'a' => 'Yes. We have years of experience supporting candidates on OPT, CPT, STEM OPT extension, and H-1B. Our team guides you on documentation and targeting employers who sponsor visa status.',
	),
	array(
		'q' => 'How long does it typically take to get placed?',
		'a' => 'Average placement time is 9–15 weeks from the start of active marketing — faster for candidates on Elite or Premium plans with priority recruiter access. Some candidates land offers in as little as 6 weeks.',
	),
	array(
		'q' => 'What technology tracks are available?',
		'a' => 'We place candidates in AI/ML & Data Science, Software Engineering, Cloud & DevOps, QA / Security / Enterprise roles, and Data Platforms & Analytics. If your target role is not listed, contact us — our recruiter network is broad.',
	),
	array(
		'q' => 'What if I am not placed within the programme period?',
		'a' => 'We continue supporting you. Premium plan members receive 12-month post-placement support. Elite and Basic candidates can extend their recruiter marketing on a case-by-case basis. We are committed until you are hired.',
	),
);

$et_marquee = array(
	'Recruiter Network', 'Hiring Partners', 'Live Use-Cases', 'Dedicated Recruiters',
	'US · Canada · India', 'Resume Marketing', 'OPT/CPT Friendly', 'Mock Interviews',
	'Automation Tools', '6,825+ Placed',
);

// ══════════════════════════════════════════════════════════════════════════════
// PHP SETUP
// ══════════════════════════════════════════════════════════════════════════════

while ( ob_get_level() ) ob_end_clean();

$logo_url = get_template_directory_uri() . '/assets/enterns-logo-motion.png';
$et_js_data = wp_json_encode( array(
	'placements' => $et_placements,
	'tracks'     => array_values( array_map( function ( $t ) {
		return array( 'cat' => $t['cat'], 'tag' => $t['tag'], 'roles' => $t['roles'] );
	}, $et_tracks ) ),
) );

// Helper: render a comparison table cell
function et_cmp_cell( $val ) {
	if ( $val === true )  return '<span style="color:#5BE89A;font-size:1.1rem;">✓</span>';
	if ( $val === false ) return '<span style="color:rgba(255,255,255,.2);">—</span>';
	return '<span style="font-size:.82rem;color:#9FB1CE;">' . esc_html( $val ) . '</span>';
}

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="Enterns Tech — from learning to employment. Career training, resume marketing, and recruiter access for IT professionals across the US, Canada, and India.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<?php wp_head(); ?>
<style>
:root{--cyan:#22D3EE;--blue:#3BA4FF;--bg:#05080F;--surf:#0C1426;--surf2:#0F1B34;--text:#ECF2FF;--muted:#6B7280;--green:#5BE89A;--border:rgba(255,255,255,.07);}
*,*::before,*::after{box-sizing:border-box;-webkit-font-smoothing:antialiased;margin:0;padding:0;}
html,body{background:var(--bg);color:var(--text);font-family:Inter,sans-serif;overflow-x:hidden;scroll-behavior:smooth;}
::selection{background:rgba(34,211,238,.22);color:#fff;}
img{max-width:100%;height:auto;}
a{color:inherit;text-decoration:none;}
button,input,select,textarea{font-family:inherit;font-size:16px;}
input,select,textarea{background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:12px 16px;width:100%;outline:none;transition:border-color .2s;}
input:focus,select:focus,textarea:focus{border-color:rgba(34,211,238,.5);}
/* keyframes */
@keyframes etSpin  {to{transform:rotate(360deg)}}
@keyframes etFloat {0%,100%{transform:translateY(0)}50%{transform:translateY(-16px)}}
@keyframes etFloat2{0%,100%{transform:translateY(0)}50%{transform:translateY(12px)}}
@keyframes etPulse {0%,100%{opacity:.35;transform:scale(1)}50%{opacity:.9;transform:scale(1.07)}}
@keyframes etDrift {0%{transform:translate(0,0)}50%{transform:translate(44px,-32px)}100%{transform:translate(0,0)}}
@keyframes etDrift2{0%{transform:translate(0,0)}50%{transform:translate(-52px,28px)}100%{transform:translate(0,0)}}
@keyframes etMarquee{to{transform:translateX(-50%)}}
@keyframes etGlow  {0%,100%{opacity:.45}50%{opacity:1}}
@keyframes etBlink {0%,100%{opacity:1}50%{opacity:.15}}
@keyframes etOrbit {from{transform:rotate(0deg) translateX(74px) rotate(0deg)}to{transform:rotate(360deg) translateX(74px) rotate(-360deg)}}
@keyframes etOrbit2{from{transform:rotate(0deg) translateX(54px) rotate(0deg)}to{transform:rotate(-360deg) translateX(54px) rotate(360deg)}}
/* reveal */
[data-reveal]{opacity:0;transform:translateY(28px);filter:blur(5px);transition:opacity .9s cubic-bezier(.16,1,.3,1),transform .9s cubic-bezier(.16,1,.3,1),filter .9s cubic-bezier(.16,1,.3,1);}
@media(prefers-reduced-motion:reduce){[data-reveal]{opacity:1!important;transform:none!important;filter:none!important;}*{animation-duration:.001ms!important;animation-iteration-count:1!important;transition-duration:.001ms!important;}}
/* floatcards */
.et-floatcard{position:absolute;z-index:4;transition:transform .22s ease-out;}
@media(max-width:980px){.et-floatcard{display:none!important;}}
/* ── nav ── */
#et-nav{position:fixed;top:0;left:0;right:0;z-index:100;display:flex;align-items:center;justify-content:space-between;padding:0 5vw;height:66px;transition:background .3s,backdrop-filter .3s,border-color .3s;}
#et-nav.et-nav--solid{background:rgba(5,8,15,.9);backdrop-filter:blur(20px);border-bottom:1px solid var(--border);}
.et-nav-logo{display:flex;align-items:center;gap:10px;font-family:'Space Grotesk',sans-serif;font-weight:700;font-size:1.1rem;letter-spacing:.01em;}
.et-nav-logo img{height:32px;width:auto;}
#et-nav-menu{display:flex;align-items:center;gap:2rem;}
#et-nav-menu a{font-size:.9rem;color:#9FB1CE;transition:color .2s;cursor:pointer;}
#et-nav-menu a:hover{color:var(--cyan);}
.et-nav-cta{background:rgba(34,211,238,.1);border:1px solid rgba(34,211,238,.35);color:var(--cyan)!important;border-radius:8px;padding:8px 18px;font-size:.85rem!important;font-weight:600;}
.et-nav-cta:hover{background:rgba(34,211,238,.18)!important;}
#et-nav-toggle{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:6px;}
#et-nav-toggle span{display:block;width:22px;height:2px;background:var(--text);border-radius:2px;}
@media(max-width:860px){#et-nav-menu{display:none;position:fixed;top:66px;left:0;right:0;flex-direction:column;gap:0;background:rgba(5,8,15,.97);padding:1rem 0;border-bottom:1px solid var(--border);}#et-nav-menu a{padding:14px 5vw;border-bottom:1px solid var(--border);width:100%;}#et-nav-toggle{display:flex;}}
/* ── pointer + cursor ── */
#et-pointer{position:fixed;width:480px;height:480px;border-radius:50%;background:radial-gradient(circle,rgba(34,211,238,.07) 0%,transparent 70%);pointer-events:none;z-index:0;top:0;left:0;will-change:transform;}
#et-cursor{position:fixed;width:10px;height:10px;border-radius:50%;background:var(--cyan);pointer-events:none;z-index:9999;top:0;left:0;will-change:transform;mix-blend-mode:screen;transition:background .2s;}
.et-ambient{position:fixed;border-radius:50%;filter:blur(100px);pointer-events:none;z-index:0;opacity:.6;}
/* ── hero ── */
#et-hero{position:relative;min-height:100vh;display:flex;align-items:center;padding:100px 5vw 60px;overflow:hidden;}
#et-hero-canvas{position:absolute;inset:0;width:100%;height:100%;}
#et-hero-scene{position:relative;z-index:2;width:100%;max-width:1200px;margin:0 auto;display:grid;grid-template-columns:1fr 1fr;gap:4rem;align-items:center;}
.et-hero-text{display:flex;flex-direction:column;gap:1.5rem;}
.et-hero-badge{display:inline-flex;align-items:center;gap:8px;background:rgba(34,211,238,.09);border:1px solid rgba(34,211,238,.25);border-radius:100px;padding:6px 16px;font-size:.8rem;font-weight:600;color:var(--cyan);width:fit-content;}
.et-hero-badge-dot{width:6px;height:6px;border-radius:50%;background:var(--cyan);animation:etBlink 1.4s ease-in-out infinite;}
.et-hero-h1{font-family:'Space Grotesk',sans-serif;font-size:clamp(2.2rem,4.5vw,3.6rem);font-weight:800;line-height:1.1;letter-spacing:-.02em;}
.et-hero-h1 span{color:var(--cyan);}
.et-hero-sub{color:#9FB1CE;font-size:1.05rem;line-height:1.65;max-width:480px;}
.et-hero-btns{display:flex;gap:1rem;flex-wrap:wrap;}
.et-btn-primary{display:inline-flex;align-items:center;gap:8px;background:var(--cyan);color:#05080F;border:none;border-radius:10px;padding:14px 28px;font-weight:700;font-size:.95rem;cursor:pointer;transition:opacity .2s,transform .15s;}
.et-btn-primary:hover{opacity:.88;transform:translateY(-1px);}
.et-btn-outline{display:inline-flex;align-items:center;gap:8px;background:transparent;color:var(--text);border:1px solid rgba(255,255,255,.18);border-radius:10px;padding:14px 28px;font-weight:600;font-size:.95rem;cursor:pointer;transition:border-color .2s,background .2s;}
.et-btn-outline:hover{border-color:rgba(34,211,238,.4);background:rgba(34,211,238,.06);}
.et-hero-stats{display:flex;gap:2rem;padding-top:.5rem;flex-wrap:wrap;}
.et-hero-stat{display:flex;flex-direction:column;gap:2px;}
.et-hero-stat strong{font-family:'Space Grotesk',sans-serif;font-size:1.3rem;font-weight:700;color:var(--cyan);}
.et-hero-stat span{font-size:.78rem;color:#6B7280;}
/* orbital card (right side) */
.et-orbital-wrap{position:relative;height:420px;display:flex;align-items:center;justify-content:center;}
.et-orbital-ring{position:relative;width:200px;height:200px;}
.et-orbital-ring svg{position:absolute;inset:0;}
.et-orbital-center{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;}
.et-orbital-logo{width:60px;height:60px;border-radius:50%;background:var(--surf2);border:1px solid rgba(34,211,238,.3);display:flex;align-items:center;justify-content:center;font-family:'Space Grotesk',sans-serif;font-weight:800;font-size:1rem;color:var(--cyan);}
/* floatcard styles */
.et-fc{background:rgba(12,20,38,.88);border:1px solid rgba(34,211,238,.18);border-radius:14px;padding:14px 18px;backdrop-filter:blur(16px);box-shadow:0 16px 48px rgba(0,0,0,.4);min-width:160px;}
.et-fc-label{font-size:.7rem;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px;}
.et-fc-value{font-size:.88rem;font-weight:600;color:var(--text);transition:opacity .4s;}
.et-fc-big{font-size:1.4rem;font-weight:700;font-family:'Space Grotesk',sans-serif;color:var(--cyan);}
/* ── section commons ── */
section{padding:80px 5vw;}
.et-section-label{font-size:.78rem;text-transform:uppercase;letter-spacing:.12em;color:var(--cyan);font-weight:600;margin-bottom:.75rem;}
.et-section-h2{font-family:'Space Grotesk',sans-serif;font-size:clamp(1.8rem,3.5vw,2.8rem);font-weight:700;line-height:1.2;margin-bottom:1rem;}
.et-section-h2 span{color:var(--cyan);}
.et-section-sub{color:#9FB1CE;font-size:1rem;line-height:1.65;max-width:640px;margin-bottom:3rem;}
.et-center{text-align:center;}
.et-center .et-section-sub{margin-left:auto;margin-right:auto;}
/* ── marquee ── */
.et-marquee-wrap{background:rgba(34,211,238,.04);border-top:1px solid rgba(34,211,238,.12);border-bottom:1px solid rgba(34,211,238,.12);padding:16px 0;overflow:hidden;}
.et-marquee-track{display:flex;white-space:nowrap;animation:etMarquee 22s linear infinite;}
.et-marquee-track span{display:inline-flex;align-items:center;gap:1.8rem;font-size:.82rem;font-weight:600;color:#9FB1CE;padding:0 2rem;}
.et-marquee-track span::before{content:'';display:inline-block;width:4px;height:4px;border-radius:50%;background:var(--cyan);}
/* ── journey ── */
.et-journey-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:1.5rem;}
@media(max-width:1100px){.et-journey-grid{grid-template-columns:repeat(3,1fr);}}
@media(max-width:700px){.et-journey-grid{grid-template-columns:1fr 1fr;}}
.et-journey-card{background:var(--surf);border:1px solid var(--border);border-radius:16px;padding:28px 24px;transition:border-color .25s;}
.et-journey-card:hover{border-color:rgba(34,211,238,.3);}
.et-journey-num{font-family:'Space Grotesk',sans-serif;font-size:2.2rem;font-weight:800;color:rgba(34,211,238,.18);margin-bottom:.75rem;}
.et-journey-title{font-family:'Space Grotesk',sans-serif;font-weight:700;font-size:1.05rem;color:var(--cyan);margin-bottom:.5rem;}
.et-journey-desc{font-size:.84rem;color:#9FB1CE;line-height:1.6;}
/* ── tracks ── */
#tracks{background:var(--surf2);}
.et-tracks-layout{display:grid;grid-template-columns:220px 1fr;gap:2rem;}
@media(max-width:760px){.et-tracks-layout{grid-template-columns:1fr;}}
.et-track-tabs{display:flex;flex-direction:column;gap:.5rem;}
@media(max-width:760px){.et-track-tabs{flex-direction:row;flex-wrap:wrap;}}
.et-track-tab{background:rgba(255,255,255,.025);border:1px solid var(--border);border-radius:10px;padding:12px 16px;cursor:pointer;text-align:left;color:#9FB1CE;font-size:.85rem;font-weight:500;transition:all .2s;line-height:1.4;}
.et-track-panel-roles{display:flex;flex-wrap:wrap;gap:.6rem;margin-top:1.25rem;}
.et-track-role{background:rgba(34,211,238,.07);border:1px solid rgba(34,211,238,.18);border-radius:100px;padding:6px 14px;font-size:.8rem;color:var(--cyan);}
/* ── services ── */
.et-services-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem;}
@media(max-width:900px){.et-services-grid{grid-template-columns:1fr 1fr;}}
@media(max-width:560px){.et-services-grid{grid-template-columns:1fr;}}
.et-service-card{background:var(--surf);border:1px solid var(--border);border-radius:16px;padding:28px 24px;data-tilt3d;transition:border-color .25s,transform .28s;}
.et-service-card:hover{border-color:rgba(34,211,238,.28);}
.et-service-icon{width:40px;height:40px;border-radius:10px;background:rgba(34,211,238,.1);display:flex;align-items:center;justify-content:center;margin-bottom:1rem;color:var(--cyan);font-size:1.2rem;}
.et-service-title{font-family:'Space Grotesk',sans-serif;font-weight:700;margin-bottom:.5rem;}
.et-service-desc{font-size:.84rem;color:#9FB1CE;line-height:1.6;}
/* ── why/metrics ── */
#why{background:var(--surf2);}
.et-why-grid{display:grid;grid-template-columns:1fr 1fr;gap:4rem;align-items:start;}
@media(max-width:760px){.et-why-grid{grid-template-columns:1fr;gap:2rem;}}
.et-value-list{display:flex;flex-direction:column;gap:1.5rem;}
.et-value-item{display:flex;gap:1rem;align-items:flex-start;}
.et-value-dot{width:8px;height:8px;border-radius:50%;background:var(--cyan);margin-top:6px;flex-shrink:0;}
.et-value-title{font-weight:700;margin-bottom:.3rem;}
.et-value-desc{font-size:.85rem;color:#9FB1CE;line-height:1.6;}
.et-metrics-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;}
.et-metric-card{background:var(--surf);border:1px solid var(--border);border-radius:16px;padding:28px 24px;}
.et-metric-num{font-family:'Space Grotesk',sans-serif;font-size:2.6rem;font-weight:800;color:var(--cyan);margin-bottom:.3rem;}
.et-metric-label{font-size:.82rem;color:#9FB1CE;}
/* ── roadmap ── */
.et-roadmap{position:relative;display:flex;flex-direction:column;gap:0;}
.et-step{display:grid;grid-template-columns:56px 1fr;gap:1.5rem;align-items:start;position:relative;padding-bottom:1.8rem;}
.et-step:not(:last-child)::before{content:'';position:absolute;left:27px;top:56px;bottom:0;width:1px;background:linear-gradient(to bottom,rgba(34,211,238,.3),transparent);}
.et-step-num{width:56px;height:56px;border-radius:50%;background:rgba(34,211,238,.1);border:1px solid rgba(34,211,238,.3);display:flex;align-items:center;justify-content:center;font-family:'Space Grotesk',sans-serif;font-weight:800;font-size:.9rem;color:var(--cyan);flex-shrink:0;}
.et-step-title{font-weight:700;font-family:'Space Grotesk',sans-serif;margin-bottom:.35rem;}
.et-step-desc{font-size:.85rem;color:#9FB1CE;line-height:1.6;}
/* ── success ── */
#success{background:var(--surf2);}
.et-success-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem;margin-bottom:4rem;}
@media(max-width:900px){.et-success-grid{grid-template-columns:1fr 1fr;}}
@media(max-width:560px){.et-success-grid{grid-template-columns:1fr;}}
.et-outcome-card{background:var(--surf);border:1px solid var(--border);border-radius:16px;padding:24px;transition:border-color .25s;}
.et-outcome-card:hover{border-color:rgba(34,211,238,.3);}
.et-outcome-role{font-weight:700;font-family:'Space Grotesk',sans-serif;font-size:1.05rem;margin-bottom:.3rem;}
.et-outcome-placed{font-size:.82rem;color:var(--green);font-weight:600;margin-bottom:.6rem;}
.et-outcome-meta{font-size:.78rem;color:var(--muted);display:flex;gap:.8rem;flex-wrap:wrap;}
.et-net-wrap{max-width:500px;margin:0 auto;}
#et-net-canvas{width:100%;height:360px;display:block;}
/* ── pricing ── */
.et-audience-btns{display:flex;gap:1.5rem;margin-bottom:2.5rem;flex-wrap:wrap;}
.et-aud-btn{flex:1;min-width:220px;max-width:320px;cursor:pointer;text-align:left;padding:22px 26px;border-radius:18px;background:rgba(255,255,255,.025);border:1.5px solid rgba(255,255,255,.1);transition:all .3s;}
.et-aud-icon{font-size:1.4rem;margin-bottom:.6rem;}
.et-aud-title{font-weight:700;font-family:'Space Grotesk',sans-serif;margin-bottom:.2rem;}
.et-aud-sub{font-size:.8rem;color:#9FB1CE;}
#et-no-audience{background:rgba(255,255,255,.025);border:1px dashed var(--border);border-radius:16px;padding:40px;text-align:center;color:#9FB1CE;}
#et-plans-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem;margin-bottom:3rem;}
#et-combos-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;}
@media(max-width:960px){#et-plans-grid{grid-template-columns:1fr 1fr;}}
@media(max-width:620px){#et-plans-grid{grid-template-columns:1fr;}#et-combos-grid{grid-template-columns:1fr;}}
.et-plan-card{background:var(--surf);border:1.5px solid var(--border);border-radius:20px;padding:32px 28px;position:relative;display:flex;flex-direction:column;gap:1rem;transition:border-color .3s;}
.et-plan-card.et-plan-featured{border-color:rgba(34,211,238,.45);background:linear-gradient(160deg,rgba(34,211,238,.07),rgba(12,20,38,1));}
.et-plan-badge{display:inline-block;background:var(--cyan);color:#05080F;font-size:.72rem;font-weight:700;border-radius:100px;padding:3px 12px;margin-bottom:.25rem;width:fit-content;}
.et-plan-name{font-family:'Space Grotesk',sans-serif;font-weight:700;font-size:1.25rem;}
.et-plan-tagline{font-size:.82rem;color:#9FB1CE;line-height:1.5;}
.et-plan-price{font-family:'Space Grotesk',sans-serif;font-size:2.4rem;font-weight:800;color:var(--cyan);}
.et-plan-note{font-size:.75rem;color:var(--muted);margin-top:-4px;}
.et-plan-features{list-style:none;display:flex;flex-direction:column;gap:.55rem;padding:0;margin:0;flex:1;}
.et-plan-features li{font-size:.84rem;color:#9FB1CE;display:flex;gap:.6rem;align-items:flex-start;line-height:1.4;}
.et-plan-features li::before{content:'✓';color:var(--green);flex-shrink:0;font-weight:700;}
.et-plan-btn{margin-top:auto;background:rgba(34,211,238,.12);border:1px solid rgba(34,211,238,.35);color:var(--cyan);border-radius:10px;padding:12px 20px;font-weight:700;cursor:pointer;width:100%;transition:background .2s;}
.et-plan-btn:hover{background:rgba(34,211,238,.22);}
.et-plan-featured .et-plan-btn{background:var(--cyan);color:#05080F;}
.et-plan-featured .et-plan-btn:hover{opacity:.88;}
.et-combo-card{background:var(--surf);border:1px solid var(--border);border-radius:18px;padding:28px 24px;transition:border-color .25s;}
.et-combo-card:hover{border-color:rgba(34,211,238,.3);}
.et-combo-includes{font-size:.75rem;color:var(--cyan);font-weight:600;text-transform:uppercase;letter-spacing:.08em;margin-bottom:.5rem;}
.et-combo-name{font-family:'Space Grotesk',sans-serif;font-weight:700;font-size:1.1rem;margin-bottom:.5rem;}
.et-combo-desc{font-size:.82rem;color:#9FB1CE;line-height:1.55;margin-bottom:1rem;}
/* comparison table */
.et-cmp-wrap{overflow-x:auto;margin-top:3rem;}
.et-cmp-table{width:100%;border-collapse:collapse;min-width:580px;}
.et-cmp-table th,.et-cmp-table td{padding:12px 16px;text-align:center;border-bottom:1px solid var(--border);}
.et-cmp-table th{font-family:'Space Grotesk',sans-serif;font-weight:700;font-size:.85rem;color:var(--cyan);}
.et-cmp-table td:first-child{text-align:left;color:#9FB1CE;font-size:.82rem;}
.et-dept-row td{background:rgba(34,211,238,.04);font-weight:700;font-size:.8rem;color:var(--muted);text-transform:uppercase;letter-spacing:.07em;}
/* ── faq ── */
#faq{background:var(--surf2);}
.et-faq-list{max-width:780px;margin:0 auto;display:flex;flex-direction:column;gap:.75rem;}
.et-faq-item{background:var(--surf);border:1px solid var(--border);border-radius:14px;overflow:hidden;}
.et-faq-btn{width:100%;display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:20px 24px;cursor:pointer;background:none;border:none;color:var(--text);font-weight:600;font-size:.9rem;text-align:left;}
.et-faq-sign{font-size:1.3rem;color:var(--cyan);flex-shrink:0;}
.et-faq-body{padding:0 24px 20px;font-size:.85rem;color:#9FB1CE;line-height:1.7;}
/* ── referral ── */
.et-referral-card{background:linear-gradient(135deg,rgba(34,211,238,.08),rgba(59,164,255,.04));border:1px solid rgba(34,211,238,.2);border-radius:24px;padding:48px;text-align:center;max-width:720px;margin:0 auto;}
/* ── contact ── */
#contact{background:var(--surf2);}
.et-contact-layout{display:grid;grid-template-columns:1fr 1fr;gap:4rem;align-items:start;}
@media(max-width:760px){.et-contact-layout{grid-template-columns:1fr;}}
.et-contact-form{display:flex;flex-direction:column;gap:1rem;}
.et-contact-info{display:flex;flex-direction:column;gap:1.5rem;}
.et-contact-item{display:flex;gap:1rem;align-items:flex-start;}
.et-contact-item-icon{width:40px;height:40px;border-radius:10px;background:rgba(34,211,238,.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1.1rem;}
/* ── logo concepts ── */
.et-logos-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:1rem;}
@media(max-width:860px){.et-logos-grid{grid-template-columns:repeat(3,1fr);}}
@media(max-width:520px){.et-logos-grid{grid-template-columns:repeat(2,1fr);}}
.et-logo-concept{background:var(--surf);border:1px solid var(--border);border-radius:14px;aspect-ratio:1;display:flex;align-items:center;justify-content:center;font-family:'Space Grotesk',sans-serif;font-size:1.5rem;font-weight:800;}
/* ── footer ── */
footer{background:rgba(0,0,0,.6);border-top:1px solid var(--border);padding:60px 5vw 32px;}
.et-footer-grid{display:grid;grid-template-columns:2fr 1fr 1fr;gap:3rem;margin-bottom:3rem;}
@media(max-width:760px){.et-footer-grid{grid-template-columns:1fr;gap:2rem;}}
.et-footer-brand p{font-size:.84rem;color:#9FB1CE;line-height:1.7;margin-top:.75rem;max-width:320px;}
.et-footer-col h4{font-weight:700;margin-bottom:1rem;font-size:.9rem;}
.et-footer-col ul{list-style:none;display:flex;flex-direction:column;gap:.6rem;}
.et-footer-col ul li a,.et-footer-col ul li span{font-size:.84rem;color:#9FB1CE;cursor:pointer;transition:color .2s;}
.et-footer-col ul li a:hover,.et-footer-col ul li span:hover{color:var(--cyan);}
.et-footer-bottom{border-top:1px solid var(--border);padding-top:1.5rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem;font-size:.78rem;color:var(--muted);}
/* ── modals ── */
.et-modal-overlay{position:fixed;inset:0;z-index:1000;display:flex;align-items:center;justify-content:center;padding:1.5rem;background:rgba(0,0,0,.75);backdrop-filter:blur(8px);}
.et-modal-box{background:var(--surf);border:1px solid rgba(34,211,238,.2);border-radius:24px;padding:40px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;position:relative;}
.et-modal-close{position:absolute;top:18px;right:20px;background:none;border:none;color:var(--muted);font-size:1.5rem;cursor:pointer;line-height:1;}
.et-modal-close:hover{color:var(--text);}
.et-form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
@media(max-width:480px){.et-form-row{grid-template-columns:1fr;}}
.et-form-group{display:flex;flex-direction:column;gap:.4rem;}
.et-form-group label{font-size:.8rem;color:#9FB1CE;font-weight:500;}
</style>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<!-- ambient glow -->
<div id="et-pointer" aria-hidden="true"></div>
<div id="et-cursor" aria-hidden="true"></div>
<div class="et-ambient" style="width:600px;height:600px;background:rgba(34,211,238,.04);top:-200px;left:-150px;animation:etDrift 22s ease-in-out infinite;" aria-hidden="true"></div>
<div class="et-ambient" style="width:500px;height:500px;background:rgba(59,164,255,.04);bottom:-150px;right:-100px;animation:etDrift2 28s ease-in-out infinite;" aria-hidden="true"></div>

<!-- ═══════════════════ NAV ═══════════════════ -->
<nav id="et-nav" role="navigation" aria-label="Main navigation">
  <div class="et-nav-logo">
    <img src="<?php echo esc_url( $logo_url ); ?>" alt="Enterns Tech logo" width="32" height="32">
    <span>EnternsTech</span>
  </div>
  <button id="et-nav-toggle" aria-label="Toggle navigation" aria-expanded="false" aria-controls="et-nav-menu">
    <span></span><span></span><span></span>
  </button>
  <div id="et-nav-menu" role="menubar">
    <a data-scroll-to="journey" role="menuitem">Journey</a>
    <a data-scroll-to="tracks"  role="menuitem">Tracks</a>
    <a data-scroll-to="success" role="menuitem">Success</a>
    <a data-scroll-to="pricing" role="menuitem">Pricing</a>
    <a data-scroll-to="contact" role="menuitem" class="et-nav-cta" data-magnetic>Book a call</a>
  </div>
</nav>

<!-- ═══════════════════ HERO ═══════════════════ -->
<header id="et-hero">
  <canvas id="et-hero-canvas" aria-hidden="true"></canvas>

  <div id="et-hero-scene">
    <!-- left: text -->
    <div class="et-hero-text">
      <div class="et-hero-badge">
        <span class="et-hero-badge-dot"></span>
        <span>6,825+ IT Professionals Placed</span>
      </div>

      <h1 class="et-hero-h1">
        From learning<br>to <span>employment.</span>
      </h1>

      <p class="et-hero-sub">
        End-to-end career placement for IT professionals. We train, build your portfolio, market your resume to 150&plus; recruiters, and keep going until you're hired.
      </p>

      <div class="et-hero-btns">
        <button class="et-btn-primary" data-scroll-to="pricing" data-magnetic>Explore Plans &#8594;</button>
        <button class="et-btn-outline" data-scroll-to="contact">Book a free call</button>
      </div>

      <div class="et-hero-stats">
        <div class="et-hero-stat">
          <strong data-count data-target="6825" data-suffix="+">6825+</strong>
          <span>Placed</span>
        </div>
        <div class="et-hero-stat">
          <strong data-count data-target="150" data-suffix="+">150+</strong>
          <span>Recruiters</span>
        </div>
        <div class="et-hero-stat">
          <strong data-count data-target="98" data-suffix="%">98%</strong>
          <span>Satisfaction</span>
        </div>
        <div class="et-hero-stat">
          <strong style="color:var(--cyan)">OPT/CPT</strong>
          <span>Friendly</span>
        </div>
      </div>
    </div>

    <!-- right: orbital visualization -->
    <div class="et-orbital-wrap">
      <div class="et-orbital-ring">
        <!-- outer ring -->
        <svg viewBox="0 0 200 200" style="width:200px;height:200px;animation:etSpin 10s linear infinite;" aria-hidden="true">
          <ellipse cx="100" cy="100" rx="88" ry="34" fill="none" stroke="rgba(34,211,238,.28)" stroke-width="1.5"/>
        </svg>
        <!-- mid ring -->
        <svg viewBox="0 0 200 200" style="position:absolute;inset:0;width:200px;height:200px;animation:etSpin 14s linear infinite reverse;" aria-hidden="true">
          <ellipse cx="100" cy="100" rx="88" ry="34" fill="none" stroke="rgba(59,164,255,.18)" stroke-width="1" transform="rotate(60 100 100)"/>
        </svg>
        <!-- inner ring -->
        <svg viewBox="0 0 200 200" style="position:absolute;inset:0;width:200px;height:200px;animation:etSpin 20s linear infinite;" aria-hidden="true">
          <ellipse cx="100" cy="100" rx="60" ry="24" fill="none" stroke="rgba(91,233,255,.14)" stroke-width="1" transform="rotate(120 100 100)"/>
        </svg>
        <!-- orbit dots -->
        <div style="position:absolute;inset:0;animation:etSpin 10s linear infinite;" aria-hidden="true">
          <div style="position:absolute;top:50%;left:50%;width:8px;height:8px;background:var(--cyan);border-radius:50%;transform:translate(-50%,-50%) translate(88px,0);box-shadow:0 0 8px var(--cyan);"></div>
        </div>
        <div style="position:absolute;inset:0;animation:etSpin 14s linear infinite reverse;" aria-hidden="true">
          <div style="position:absolute;top:50%;left:50%;width:6px;height:6px;background:var(--blue);border-radius:50%;transform:translate(-50%,-50%) translate(70px,0);box-shadow:0 0 6px var(--blue);"></div>
        </div>
        <!-- center logo -->
        <div class="et-orbital-center">
          <div class="et-orbital-logo">et</div>
        </div>
      </div>

      <!-- floatcard 1: recently placed (cycling) -->
      <div class="et-floatcard et-fc" data-depth="1.5" style="top:15%;left:-100px;animation:etFloat 5s ease-in-out infinite;">
        <div class="et-fc-label">Recently placed</div>
        <div id="et-placed-role" class="et-fc-value">
          <?php echo esc_html( $et_placements[0]['role'] . ' · ' . $et_placements[0]['weeks'] . ' weeks' ); ?>
        </div>
      </div>

      <!-- floatcard 2: avg time stat -->
      <div class="et-floatcard et-fc" data-depth="2.4" style="bottom:10%;right:-90px;animation:etFloat2 6s ease-in-out infinite;" aria-hidden="true">
        <div class="et-fc-big">9–15 wks</div>
        <div class="et-fc-label">avg. time to offer</div>
      </div>
    </div>
  </div>
</header>

<!-- ═══════════════════ MARQUEE ═══════════════════ -->
<div class="et-marquee-wrap" aria-hidden="true">
  <div class="et-marquee-track">
    <?php foreach ( array_merge( $et_marquee, $et_marquee ) as $m ) : ?>
      <span><?php echo esc_html( $m ); ?></span>
    <?php endforeach; ?>
  </div>
</div>

<!-- ═══════════════════ CAREER JOURNEY ═══════════════════ -->
<section id="journey">
  <div class="et-center" data-reveal>
    <div class="et-section-label">How it works</div>
    <h2 class="et-section-h2">The <span>5-step</span> career journey</h2>
    <p class="et-section-sub">From day one to your first offer — here is exactly what we do.</p>
  </div>
  <div class="et-journey-grid">
    <?php foreach ( $et_journey as $j ) : ?>
      <div class="et-journey-card" data-reveal data-delay="<?php echo (int) $j['delay']; ?>">
        <div class="et-journey-num"><?php echo esc_html( $j['num'] ); ?></div>
        <div class="et-journey-title"><?php echo esc_html( $j['title'] ); ?></div>
        <p class="et-journey-desc"><?php echo esc_html( $j['desc'] ); ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ═══════════════════ TECHNOLOGY TRACKS ═══════════════════ -->
<section id="tracks">
  <div class="et-center" data-reveal>
    <div class="et-section-label">Specialisations</div>
    <h2 class="et-section-h2">Technology <span>tracks</span></h2>
    <p class="et-section-sub">Choose the track that matches your background. We train and market candidates across five in-demand domains.</p>
  </div>
  <div class="et-tracks-layout">
    <div class="et-track-tabs" role="tablist">
      <?php foreach ( $et_tracks as $i => $track ) : ?>
        <button class="et-track-tab" data-track-tab="<?php echo $i; ?>" role="tab" aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>">
          <?php echo esc_html( $track['cat'] ); ?>
          <br><small style="opacity:.55;font-size:.72rem;"><?php echo esc_html( $track['tag'] ); ?></small>
        </button>
      <?php endforeach; ?>
    </div>
    <div>
      <?php foreach ( $et_tracks as $i => $track ) : ?>
        <div data-track-panel="<?php echo $i; ?>" <?php echo $i > 0 ? 'style="display:none"' : ''; ?>>
          <div class="et-section-label" style="margin-bottom:.5rem;"><?php echo esc_html( $track['tag'] ); ?></div>
          <h3 style="font-family:'Space Grotesk',sans-serif;font-size:1.5rem;font-weight:700;margin-bottom:.4rem;"><?php echo esc_html( $track['cat'] ); ?></h3>
          <p style="font-size:.84rem;color:#9FB1CE;margin-bottom:1rem;">Roles we train &amp; place for in this track:</p>
          <div class="et-track-panel-roles">
            <?php foreach ( $track['roles'] as $role ) : ?>
              <span class="et-track-role"><?php echo esc_html( $role ); ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══════════════════ SERVICES ═══════════════════ -->
<section id="services">
  <div class="et-center" data-reveal>
    <div class="et-section-label">What we do</div>
    <h2 class="et-section-h2">Every service you need to <span>get hired</span></h2>
    <p class="et-section-sub">We handle every step so you can focus entirely on performing in interviews.</p>
  </div>
  <div class="et-services-grid">
    <?php foreach ( $et_services as $i => $svc ) : ?>
      <div class="et-service-card" data-reveal data-delay="<?php echo $i * 70; ?>" data-tilt3d>
        <div class="et-service-icon">&#9733;</div>
        <div class="et-service-title"><?php echo esc_html( $svc['t'] ); ?></div>
        <p class="et-service-desc"><?php echo esc_html( $svc['d'] ); ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ═══════════════════ WHY + METRICS ═══════════════════ -->
<section id="why">
  <div class="et-why-grid">
    <div data-reveal>
      <div class="et-section-label">Why Enterns Tech</div>
      <h2 class="et-section-h2">We operate on <span>principles</span>, not promises</h2>
      <p style="color:#9FB1CE;font-size:.95rem;line-height:1.7;margin-bottom:2rem;">
        Thousands of IT professionals have used our network. Here is what makes us different.
      </p>
      <div class="et-value-list">
        <?php foreach ( $et_values as $v ) : ?>
          <div class="et-value-item">
            <div class="et-value-dot"></div>
            <div>
              <div class="et-value-title"><?php echo esc_html( $v['t'] ); ?></div>
              <p class="et-value-desc"><?php echo esc_html( $v['d'] ); ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="et-metrics-grid" data-reveal data-delay="120">
      <div class="et-metric-card">
        <div class="et-metric-num" data-count data-target="6825" data-suffix="+">6825+</div>
        <div class="et-metric-label">Professionals Placed</div>
      </div>
      <div class="et-metric-card">
        <div class="et-metric-num" data-count data-target="150" data-suffix="+">150+</div>
        <div class="et-metric-label">Active Recruiters</div>
      </div>
      <div class="et-metric-card">
        <div class="et-metric-num" data-count data-target="98" data-suffix="%">98%</div>
        <div class="et-metric-label">Client Satisfaction</div>
      </div>
      <div class="et-metric-card">
        <div class="et-metric-num" style="font-size:1.8rem;">US · CA · IN</div>
        <div class="et-metric-label">Markets Covered</div>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════════ ROADMAP ═══════════════════ -->
<section id="roadmap">
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:4rem;align-items:start;">
    <div data-reveal>
      <div class="et-section-label">The process</div>
      <h2 class="et-section-h2">Your <span>7-step</span> roadmap to employment</h2>
      <p style="color:#9FB1CE;font-size:.9rem;line-height:1.65;">
        From day one to your first offer — every milestone is defined, measured, and delivered.
      </p>
    </div>
    <div class="et-roadmap">
      <?php foreach ( $et_steps as $idx => $step ) : ?>
        <div class="et-step" data-reveal data-delay="<?php echo $idx * 60; ?>">
          <div class="et-step-num"><?php echo esc_html( $step['n'] ); ?></div>
          <div>
            <div class="et-step-title"><?php echo esc_html( $step['t'] ); ?></div>
            <p class="et-step-desc"><?php echo esc_html( $step['d'] ); ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══════════════════ SUCCESS + NETWORK ═══════════════════ -->
<section id="success">
  <div class="et-center" data-reveal>
    <div class="et-section-label">Outcomes</div>
    <h2 class="et-section-h2">Recent <span>placement successes</span></h2>
    <p class="et-section-sub">Real candidates, real companies, real timelines.</p>
  </div>
  <div class="et-success-grid">
    <?php foreach ( $et_outcomes as $i => $o ) : ?>
      <div class="et-outcome-card" data-reveal data-delay="<?php echo $i * 60; ?>">
        <div class="et-outcome-role"><?php echo esc_html( $o['role'] ); ?></div>
        <div class="et-outcome-placed"><?php echo esc_html( $o['placed'] ); ?></div>
        <div class="et-outcome-meta">
          <span><?php echo esc_html( $o['co'] ); ?></span>
          <span>·</span>
          <span><?php echo esc_html( $o['track'] ); ?></span>
          <span>·</span>
          <span><?php echo esc_html( $o['plan'] ); ?> Plan</span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="et-net-wrap" data-reveal>
    <h3 style="font-family:'Space Grotesk',sans-serif;text-align:center;margin-bottom:1.5rem;font-size:1.1rem;">Our active recruiter network</h3>
    <canvas id="et-net-canvas" aria-label="Recruiter network animation"></canvas>
  </div>
</section>

<!-- ═══════════════════ PRICING ═══════════════════ -->
<section id="pricing">
  <div class="et-center" data-reveal>
    <div class="et-section-label">Investment</div>
    <h2 class="et-section-h2">Transparent <span>pricing</span></h2>
    <p class="et-section-sub">Choose your market first — we show the right pricing for your region.</p>
  </div>

  <!-- audience selector -->
  <div class="et-audience-btns">
    <div class="et-aud-btn" id="et-pick-intl" style="cursor:pointer;" role="button" tabindex="0" aria-label="Select International pricing">
      <div class="et-aud-icon">&#127758;</div>
      <div class="et-aud-title">International</div>
      <div class="et-aud-sub">US, Canada &amp; other markets — prices in USD</div>
    </div>
    <div class="et-aud-btn" id="et-pick-dom" style="cursor:pointer;" role="button" tabindex="0" aria-label="Select Domestic pricing">
      <div class="et-aud-icon">&#127470;&#127475;</div>
      <div class="et-aud-title">Domestic (India)</div>
      <div class="et-aud-sub">India market — prices in INR</div>
    </div>
  </div>

  <!-- prompt before selection -->
  <div id="et-no-audience">
    <p style="font-size:.95rem;">Select your market above to see pricing.</p>
  </div>

  <!-- plan cards (hidden until audience chosen) -->
  <div id="et-plans-grid" style="display:none">
    <?php foreach ( $et_plans as $plan ) : ?>
      <div class="et-plan-card <?php echo $plan['featured'] ? 'et-plan-featured' : ''; ?>">
        <?php if ( $plan['badge'] ) : ?>
          <div class="et-plan-badge"><?php echo esc_html( $plan['badge'] ); ?></div>
        <?php endif; ?>
        <div class="et-plan-name"><?php echo esc_html( $plan['name'] ); ?></div>
        <p class="et-plan-tagline"><?php echo esc_html( $plan['tagline'] ); ?></p>

        <div data-price-intl>
          <div class="et-plan-price"><?php echo esc_html( $plan['priceIntl'] ); ?></div>
          <div class="et-plan-note"><?php echo esc_html( $plan['noteIntl'] ); ?></div>
        </div>
        <div data-price-dom style="display:none">
          <div class="et-plan-price"><?php echo esc_html( $plan['priceDom'] ); ?></div>
          <div class="et-plan-note"><?php echo esc_html( $plan['noteDom'] ); ?></div>
        </div>

        <ul class="et-plan-features">
          <?php foreach ( $plan['features'] as $feat ) : ?>
            <li><?php echo esc_html( $feat ); ?></li>
          <?php endforeach; ?>
        </ul>

        <button class="et-plan-btn" data-plan-btn
          data-plan-id="<?php echo esc_attr( $plan['id'] ); ?>"
          data-plan-name="<?php echo esc_attr( $plan['name'] ); ?>"
          data-price-intl="<?php echo esc_attr( $plan['priceIntl'] ); ?>"
          data-price-dom="<?php echo esc_attr( $plan['priceDom'] ); ?>"
          data-price="<?php echo esc_attr( $plan['priceIntl'] ); ?>">
          Enrol now &rarr;
        </button>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- combo offers (hidden until audience chosen) -->
  <div id="et-combos-grid" style="display:none">
    <?php foreach ( $et_combos as $combo ) : ?>
      <div class="et-combo-card">
        <div class="et-combo-includes"><?php echo esc_html( $combo['includes'] ); ?></div>
        <div class="et-combo-name"><?php echo esc_html( $combo['name'] ); ?></div>
        <p class="et-combo-desc"><?php echo esc_html( $combo['desc'] ); ?></p>
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;">
          <div>
            <div data-price-intl style="font-family:'Space Grotesk',sans-serif;font-size:1.6rem;font-weight:800;color:var(--cyan);"><?php echo esc_html( $combo['priceIntl'] ); ?></div>
            <div data-price-dom style="font-family:'Space Grotesk',sans-serif;font-size:1.6rem;font-weight:800;color:var(--cyan);display:none;"><?php echo esc_html( $combo['priceDom'] ); ?></div>
          </div>
          <button class="et-plan-btn" data-combo-btn style="width:auto;padding:10px 20px;"
            data-plan-id="<?php echo esc_attr( $combo['id'] ); ?>"
            data-plan-name="<?php echo esc_attr( $combo['name'] ); ?>"
            data-price-intl="<?php echo esc_attr( $combo['priceIntl'] ); ?>"
            data-price-dom="<?php echo esc_attr( $combo['priceDom'] ); ?>"
            data-price="<?php echo esc_attr( $combo['priceIntl'] ); ?>">
            Enrol now
          </button>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- comparison table -->
  <div class="et-cmp-wrap" data-reveal>
    <h3 style="font-family:'Space Grotesk',sans-serif;font-size:1.2rem;font-weight:700;margin-bottom:1rem;">Plan comparison</h3>
    <table class="et-cmp-table">
      <thead>
        <tr>
          <th style="text-align:left">Feature</th>
          <th>Basic</th>
          <th>Elite</th>
          <th>Premium</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $et_comparison as $dept ) : ?>
          <tr class="et-dept-row">
            <td colspan="4"><?php echo esc_html( $dept['dept'] ); ?></td>
          </tr>
          <?php foreach ( $dept['rows'] as $row ) : ?>
            <tr>
              <td><?php echo esc_html( $row['feat'] ); ?></td>
              <td><?php echo et_cmp_cell( $row['basic'] ); ?></td>
              <td><?php echo et_cmp_cell( $row['elite'] ); ?></td>
              <td><?php echo et_cmp_cell( $row['premium'] ); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<!-- ═══════════════════ FAQ ═══════════════════ -->
<section id="faq">
  <div class="et-center" data-reveal>
    <div class="et-section-label">FAQ</div>
    <h2 class="et-section-h2">Common <span>questions</span></h2>
  </div>
  <div class="et-faq-list">
    <?php foreach ( $et_faqs as $faq ) : ?>
      <div class="et-faq-item">
        <button class="et-faq-btn" data-faq-btn>
          <?php echo esc_html( $faq['q'] ); ?>
          <span class="et-faq-sign" data-faq-sign>+</span>
        </button>
        <div class="et-faq-body" data-faq-body style="display:none">
          <?php echo esc_html( $faq['a'] ); ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ═══════════════════ REFERRAL ═══════════════════ -->
<section id="referral">
  <div class="et-referral-card" data-reveal>
    <div class="et-section-label">Refer &amp; earn</div>
    <h2 class="et-section-h2" style="margin-bottom:1rem;">Know someone job-hunting?<br><span>Refer them — earn a reward.</span></h2>
    <p style="color:#9FB1CE;font-size:.9rem;line-height:1.65;max-width:480px;margin:0 auto 2rem;">
      Every successful referral earns you a cash reward. Contact us with the details of the person you are referring and we will handle the rest.
    </p>
    <button class="et-btn-primary" data-scroll-to="contact" data-magnetic>
      Refer a friend &rarr;
    </button>
  </div>
</section>

<!-- ═══════════════════ CONTACT ═══════════════════ -->
<section id="contact">
  <div class="et-center" data-reveal>
    <div class="et-section-label">Get in touch</div>
    <h2 class="et-section-h2">Book a <span>free consultation</span></h2>
    <p class="et-section-sub">No pressure, no obligation — just a conversation about where you are and where you want to go.</p>
  </div>
  <div class="et-contact-layout">
    <div>
      <form id="et-contact-form" class="et-contact-form" novalidate>
        <div class="et-form-row">
          <div class="et-form-group">
            <label for="et-cf-name">Full name *</label>
            <input id="et-cf-name" type="text" name="name" placeholder="Jane Smith" required>
          </div>
          <div class="et-form-group">
            <label for="et-cf-email">Email address *</label>
            <input id="et-cf-email" type="email" name="email" placeholder="jane@example.com" required>
          </div>
        </div>
        <div class="et-form-row">
          <div class="et-form-group">
            <label for="et-cf-phone">Phone / WhatsApp</label>
            <input id="et-cf-phone" type="tel" name="phone" placeholder="+1 555 000 0000">
          </div>
          <div class="et-form-group">
            <label for="et-cf-plan">Interested plan</label>
            <select id="et-cf-plan" name="plan">
              <option value="">— select —</option>
              <option>Basic Plan</option>
              <option>Elite Plan</option>
              <option>Premium Plan</option>
              <option>Career Accelerator Combo</option>
              <option>Career Starter Combo</option>
              <option>Not sure yet</option>
            </select>
          </div>
        </div>
        <div class="et-form-group">
          <label for="et-cf-msg">Message (optional)</label>
          <textarea id="et-cf-msg" name="message" rows="4" placeholder="Tell us about your background and goals…"></textarea>
        </div>
        <div id="et-contact-err" style="display:none;color:#FF8A80;font-size:.82rem;"></div>
        <button type="submit" class="et-btn-primary" data-magnetic style="align-self:flex-start;">Send message &rarr;</button>
      </form>
      <div id="et-contact-success" style="display:none;padding:32px;background:rgba(91,234,154,.06);border:1px solid rgba(91,234,154,.25);border-radius:16px;text-align:center;">
        <div style="font-size:2rem;margin-bottom:.75rem;">&#10003;</div>
        <div style="font-weight:700;margin-bottom:.4rem;">Message received!</div>
        <p style="font-size:.85rem;color:#9FB1CE;">We will be in touch within 1 business day.</p>
      </div>
    </div>
    <div class="et-contact-info">
      <div class="et-contact-item">
        <div class="et-contact-item-icon">&#9993;</div>
        <div>
          <div style="font-weight:600;margin-bottom:.2rem;">Email us</div>
          <a href="mailto:info@enternstech.com" style="color:#9FB1CE;font-size:.85rem;">info@enternstech.com</a>
        </div>
      </div>
      <div class="et-contact-item">
        <div class="et-contact-item-icon">&#127758;</div>
        <div>
          <div style="font-weight:600;margin-bottom:.2rem;">Markets served</div>
          <p style="color:#9FB1CE;font-size:.85rem;line-height:1.55;">United States · Canada · India<br>OPT, CPT &amp; H-1B support available</p>
        </div>
      </div>
      <div class="et-contact-item">
        <div class="et-contact-item-icon">&#128203;</div>
        <div>
          <div style="font-weight:600;margin-bottom:.2rem;">Partner with us</div>
          <p style="color:#9FB1CE;font-size:.85rem;line-height:1.55;">Recruiting firms, training companies, and referral partners are welcome.</p>
          <button class="et-btn-outline" data-open-partner style="margin-top:.75rem;padding:10px 18px;font-size:.82rem;">Become a partner</button>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════════ FOOTER ═══════════════════ -->
<footer>
  <div class="et-footer-grid">
    <div class="et-footer-brand">
      <div class="et-nav-logo">
        <img src="<?php echo esc_url( $logo_url ); ?>" alt="Enterns Tech" width="28" height="28">
        <span>EnternsTech</span>
      </div>
      <p>From learning to employment — end-to-end IT career placement across the US, Canada, and India.</p>
    </div>
    <div class="et-footer-col">
      <h4>Company</h4>
      <ul>
        <li><a data-scroll-to="journey">Journey</a></li>
        <li><a data-scroll-to="tracks">Tracks</a></li>
        <li><a data-scroll-to="services">Services</a></li>
        <li><a data-scroll-to="success">Success Stories</a></li>
        <li><a data-scroll-to="pricing">Pricing</a></li>
      </ul>
    </div>
    <div class="et-footer-col">
      <h4>Connect</h4>
      <ul>
        <li><a href="mailto:info@enternstech.com">info@enternstech.com</a></li>
        <li><span data-open-partner>Partner with Us</span></li>
        <li><span data-open-admin style="color:var(--muted);font-size:.78rem;cursor:pointer;">Admin login</span></li>
      </ul>
    </div>
  </div>
  <div class="et-footer-bottom">
    <span>&copy; <?php echo date( 'Y' ); ?> EnternsTech. All rights reserved.</span>
    <span style="color:rgba(255,255,255,.15)">OPT · CPT · H-1B Friendly</span>
  </div>
</footer>

<!-- ═══════════════════ PARTNER MODAL ═══════════════════ -->
<div id="et-partner-modal" class="et-modal-overlay" style="display:none" role="dialog" aria-modal="true" aria-label="Partner with us">
  <div class="et-modal-box">
    <button class="et-modal-close" data-close-partner aria-label="Close">&times;</button>
    <div class="et-section-label" style="margin-bottom:.5rem;">Partnership</div>
    <h2 style="font-family:'Space Grotesk',sans-serif;font-size:1.5rem;font-weight:700;margin-bottom:.5rem;">Partner with Enterns Tech</h2>
    <p style="font-size:.84rem;color:#9FB1CE;margin-bottom:1.5rem;">Tell us about your organisation and how we can work together.</p>

    <form id="et-partner-form" class="et-contact-form" novalidate>
      <div class="et-form-row">
        <div class="et-form-group">
          <label>Contact name *</label>
          <input type="text" name="contact" placeholder="Jane Smith" required>
        </div>
        <div class="et-form-group">
          <label>Company name</label>
          <input type="text" name="company" placeholder="Acme Corp">
        </div>
      </div>
      <div class="et-form-row">
        <div class="et-form-group">
          <label>Email address *</label>
          <input type="email" name="email" placeholder="jane@acme.com" required>
        </div>
        <div class="et-form-group">
          <label>Phone / WhatsApp</label>
          <input type="tel" name="phone" placeholder="+1 555 000 0000">
        </div>
      </div>
      <div class="et-form-group">
        <label>Partner type *</label>
        <select name="ptype" required>
          <option value="">— select —</option>
          <option>Recruiting / Staffing Firm</option>
          <option>Training / Bootcamp</option>
          <option>University / College</option>
          <option>Corporate HR</option>
          <option>Individual Referral Partner</option>
          <option>Other</option>
        </select>
      </div>
      <div class="et-form-row">
        <div class="et-form-group">
          <label>Website (optional)</label>
          <input type="url" name="website" placeholder="https://acme.com">
        </div>
        <div class="et-form-group">
          <label>Country</label>
          <input type="text" name="country" placeholder="United States">
        </div>
      </div>
      <div class="et-form-group">
        <label>Message (optional)</label>
        <textarea name="message" rows="3" placeholder="Tell us how you'd like to collaborate…"></textarea>
      </div>
      <div id="et-partner-err" style="display:none;color:#FF8A80;font-size:.82rem;"></div>
      <button type="submit" class="et-btn-primary">Send partnership request &rarr;</button>
    </form>
    <div id="et-partner-success" style="display:none;padding:32px;text-align:center;">
      <div style="font-size:2rem;margin-bottom:.75rem;color:var(--green);">&#10003;</div>
      <div style="font-weight:700;">Request received!</div>
      <p style="font-size:.85rem;color:#9FB1CE;margin-top:.4rem;">We will review your application and reach out within 2 business days.</p>
    </div>
  </div>
</div>

<!-- ═══════════════════ ENROL MODAL ═══════════════════ -->
<div id="et-enrol-modal" class="et-modal-overlay" style="display:none" role="dialog" aria-modal="true" aria-label="Enrol">
  <div class="et-modal-box">
    <button id="et-enrol-close" class="et-modal-close" aria-label="Close">&times;</button>
    <div class="et-section-label" style="margin-bottom:.5rem;">Enrolment</div>
    <h2 style="font-family:'Space Grotesk',sans-serif;font-size:1.4rem;font-weight:700;margin-bottom:.25rem;" id="et-enrol-plan">Selected Plan</h2>
    <div style="font-family:'Space Grotesk',sans-serif;font-size:2rem;font-weight:800;color:var(--cyan);margin-bottom:1.5rem;" id="et-enrol-price"></div>

    <!-- Razorpay payment form — shown when gateway is configured -->
    <div id="et-enrol-form" style="<?php echo ( function_exists( 'enp_razorpay_configured' ) && enp_razorpay_configured() ) ? '' : 'display:none'; ?>">
      <div class="et-form-group" style="margin-bottom:1rem">
        <label for="et-enrol-email" style="display:block;font-size:.8rem;color:#9FB1CE;font-weight:500;margin-bottom:.4rem">Email address *</label>
        <input id="et-enrol-email" type="email" placeholder="your@email.com" autocomplete="email">
      </div>
      <div id="et-enrol-pay-err" style="display:none;color:#FF8A80;font-size:.82rem;margin-bottom:.75rem"></div>
      <button id="et-rzp-pay-btn" class="et-btn-primary" style="width:100%;justify-content:center">Pay with Razorpay &rarr;</button>
    </div>

    <!-- Fallback when Razorpay not configured -->
    <div id="et-enrol-fallback" style="<?php echo ( function_exists( 'enp_razorpay_configured' ) && enp_razorpay_configured() ) ? 'display:none' : ''; ?>">
      <p style="font-size:.85rem;color:#9FB1CE;margin-bottom:1.25rem;">
        To complete your enrolment, send us a message and our team will guide you through the payment process within 1 business day.
      </p>
      <button class="et-btn-primary" onclick="document.getElementById('et-enrol-modal').style.display='none';document.body.style.overflow='';document.getElementById('et-cf-name')&&document.getElementById('et-cf-name').focus();window.scrollTo({top:document.getElementById('contact').getBoundingClientRect().top+window.scrollY-80,behavior:'smooth'});">
        Contact us to enrol &rarr;
      </button>
    </div>

    <!-- Success state — shown after payment verified -->
    <div id="et-enrol-success" style="display:none;padding:24px;text-align:center">
      <div style="font-size:2.5rem;color:var(--green);margin-bottom:.5rem">&#10003;</div>
      <div style="font-weight:700;font-family:'Space Grotesk',sans-serif;font-size:1.1rem;margin-bottom:.4rem">Payment Confirmed!</div>
      <p style="font-size:.85rem;color:#9FB1CE;margin-bottom:1.25rem">Check your email for the set-password link to access your student portal.</p>
      <a id="et-enrol-portal-link" href="/student/" class="et-btn-primary" style="display:inline-flex;justify-content:center">Go to Student Portal &rarr;</a>
    </div>
  </div>
</div>

<!-- ═══════════════════ ADMIN MODAL ═══════════════════ -->
<div id="et-admin-modal" class="et-modal-overlay" style="display:none" role="dialog" aria-modal="true" aria-labelledby="et-admin-title">
  <div class="et-modal-box">
    <button class="et-modal-close" data-close-admin aria-label="Close">&times;</button>
    <div class="et-section-label" style="margin-bottom:.5rem;">Internal access</div>
    <h2 id="et-admin-title" style="font-family:'Space Grotesk',sans-serif;font-size:1.4rem;font-weight:700;margin-bottom:.75rem;">Admin Portal</h2>
    <p style="font-size:.85rem;color:#9FB1CE;line-height:1.6;margin-bottom:1.5rem;">
      Access the admin portal to manage revenue entries, pricing, and site content.
      Authentication is required once you reach the portal.
    </p>
    <a href="/admin-portal/" class="et-btn-primary" style="width:100%;justify-content:center;">Go to Admin Portal &rarr;</a>
    <button class="et-btn-outline" data-close-admin style="width:100%;margin-top:.75rem;justify-content:center;">Cancel</button>
  </div>
</div>

<!-- ═══════════════════ SCRIPTS ═══════════════════ -->
<script>
window.ET_DATA = <?php echo $et_js_data; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
</script>
<script>
window.ENP_DATA = <?php echo wp_json_encode( array(
	'ajax_url'       => admin_url( 'admin-ajax.php' ),
	'nonce'          => wp_create_nonce( 'enp_razorpay' ),
	'rzp_configured' => function_exists( 'enp_razorpay_configured' ) && enp_razorpay_configured(),
) ); ?>;
</script>
<?php if ( function_exists( 'enp_razorpay_configured' ) && enp_razorpay_configured() ) : ?>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html>
<?php exit; ?>
