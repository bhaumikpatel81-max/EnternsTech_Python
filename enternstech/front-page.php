<?php
/**
 * Front Page Template
 *
 * Serves the Design Canvas bundled site as the homepage.
 *
 * @package EnternsTech
 */

while ( ob_get_level() ) {
	ob_end_clean();
}

$bundled = get_template_directory() . '/static/index.html';

if ( file_exists( $bundled ) ) {
	// No caching — bypass browser cache, WordPress cache, and LiteSpeed server cache.
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'Cache-Control: no-cache, no-store, must-revalidate' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );
	header( 'X-LiteSpeed-Cache-Control: no-cache' ); // Bluehost/LiteSpeed bypass

	$html = file_get_contents( $bundled );

	// ── PayPal config ──────────────────────────────────────────────────────────
	$client  = defined( 'ENTERNSTECH_PAYPAL_CLIENT' ) ? ENTERNSTECH_PAYPAL_CLIENT : '';
	$env     = function_exists( 'enternstech_paypal_env' ) ? enternstech_paypal_env() : 'sandbox';
	$create  = esc_url_raw( rest_url( 'enternstech/v1/paypal/create' ) );
	$capture = esc_url_raw( rest_url( 'enternstech/v1/paypal/capture' ) );

	$inject = '<script>window.ENTERNSTECH_PAYPAL=' . wp_json_encode( array(
		'clientId'   => $client,
		'env'        => $env,
		'createUrl'  => $create,
		'captureUrl' => $capture,
	) ) . ';</script>';

	if ( $client ) {
		$inject .= '<script src="https://www.paypal.com/sdk/js?client-id=' . rawurlencode( $client )
			. '&currency=USD" data-namespace="enternsPayPal"></script>';
	}

	// ── Stub Design-Canvas component methods called as globals ────────────────
	$inject .= '<script>window.closeAdmin=function(){};window.openAdmin=function(){};</script>';

	// ── Base CSS: loading screen, dark bg, cross-browser basics ───────────────
	$inject .= '
<style>
#__bundler_loading{display:none!important;}
html,body{background:#05080F!important;overflow-x:hidden!important;}
#__bundler_thumbnail{background:#05080F!important;}
*{-webkit-tap-highlight-color:transparent;box-sizing:border-box;}
input,button,select,textarea{font-size:16px!important;}
@supports not (backdrop-filter:blur(1px)){
  [style*="backdrop-filter"]{background:rgba(12,20,38,.96)!important;}
}
</style>';

	$html = preg_replace( '#</head>#i', $inject . '</head>', $html, 1 );

	// ── Cycling + mobile fixes — runs after Design Canvas renders ─────────────
	$cycling_script = '
<script>
(function(){
  var placements=[
    "Data Scientist · 11 weeks",
    "Java Developer · 9 weeks",
    "DevOps Engineer · 13 weeks",
    "Business Analyst · 15 weeks",
    "Cybersecurity Analyst · 12 weeks",
    "Full Stack Developer · 10 weeks"
  ];
  var idx=0,cycleStarted=false,mobileFixed=false;

  /* ── Find the placement badge text node ── */
  function findPlacementEl(){
    var el=document.getElementById("et-placed-role");
    if(el&&el.children.length===0) return el;
    var all=document.querySelectorAll("span,div,p");
    for(var i=0;i<all.length;i++){
      var t=all[i].textContent.trim();
      if(all[i].children.length===0&&t.indexOf("weeks")>-1&&
         (t.indexOf("Scientist")>-1||t.indexOf("Developer")>-1||
          t.indexOf("Engineer")>-1||t.indexOf("Analyst")>-1)){
        return all[i];
      }
    }
    return null;
  }

  /* ── Cycle the placement text ── */
  function startCycling(el){
    if(cycleStarted)return;cycleStarted=true;
    setInterval(function(){
      el.style.transition="opacity 0.4s";el.style.opacity="0";
      setTimeout(function(){idx=(idx+1)%placements.length;el.textContent=placements[idx];el.style.opacity="1";},420);
    },3200);
  }

  /* ── Mobile layout fixes applied via JS (more reliable than CSS attr selectors) ── */
  function applyMobileFix(){
    if(mobileFixed||window.innerWidth>900)return;mobileFixed=true;
    var divs=document.querySelectorAll("div");
    for(var i=0;i<divs.length;i++){
      var s=divs[i].style;
      if(!s)continue;
      var gc=s.gridTemplateColumns||"";
      /* Stack any 2-column split layout */
      if(gc.indexOf("1fr 1fr")>-1||gc.indexOf("1fr 2fr")>-1||gc.indexOf("2fr 1fr")>-1||
         gc.indexOf("1fr 3fr")>-1||gc.indexOf("3fr 1fr")>-1){
        s.gridTemplateColumns="1fr";s.gap="32px";
      }
      /* 3-col → 1 col on mobile */
      if(gc.indexOf("repeat(3")>-1){s.gridTemplateColumns="1fr";}
      /* 5-col journey → 2 col */
      if(gc.indexOf("repeat(5")>-1){s.gridTemplateColumns="repeat(2,1fr)";}
      /* 4-col → 2 col */
      if(gc.indexOf("repeat(4")>-1){s.gridTemplateColumns="repeat(2,1fr)";}
      /* Clamp any element wider than viewport */
      var w=s.width||"";
      if(w&&parseInt(w)>window.innerWidth&&w.indexOf("%")===-1&&w.indexOf("vw")===-1){
        s.width="100%";s.maxWidth="100vw";
      }
      /* Remove fixed positioning that causes overflow */
      var pos=s.position||"";
      var right=s.right||"";
      if(pos==="absolute"&&right&&parseInt(right)<0){s.right="0";}
    }
    /* Force body to not overflow */
    document.body.style.overflowX="hidden";
    document.documentElement.style.overflowX="hidden";
  }

  /* ── Poll until DC renders (checks every 400ms, gives up at 25s) ── */
  var attempts=0;
  var poll=setInterval(function(){
    attempts++;
    var el=findPlacementEl();
    if(el) startCycling(el);
    applyMobileFix();
    /* Check if DC finished rendering (thumbnail hidden = done) */
    var thumb=document.getElementById("__bundler_thumbnail");
    var dcDone=!thumb||thumb.style.display==="none"||getComputedStyle(thumb).display==="none";
    if(dcDone&&el){clearInterval(poll);return;}
    if(attempts>62){clearInterval(poll);}
  },400);
})();
</script>';

	$html = str_replace( '</body>', $cycling_script . '</body>', $html );

	// Gzip the output so the 2.39 MB bundle transfers as ~500 KB.
	if ( function_exists( 'ob_gzhandler' ) && ! ini_get( 'zlib.output_compression' ) ) {
		ob_start( 'ob_gzhandler' );
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		ob_end_flush();
	} else {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
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
