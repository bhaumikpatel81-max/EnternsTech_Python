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
	// No caching — always serve the latest file
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'Cache-Control: no-cache, no-store, must-revalidate' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );

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
	// The DC bundle references closeAdmin/openAdmin as global functions during
	// template evaluation before the component initialises its own methods.
	$inject .= '<script>window.closeAdmin=function(){};window.openAdmin=function(){};</script>';

	// ── Mobile + cycling fixes injected before </head> ─────────────────────────
	$inject .= '
<style>
/* Hide the "Unpacking…" status text — users don't need to see this */
#__bundler_loading{display:none!important;}
/* Dark background during asset decompression so there's no beige flash */
html,body{background:#05080F!important;}
#__bundler_thumbnail{background:#05080F!important;}
/* ── Cross-browser & mobile fixes ─────────────────────────── */
html,body{overflow-x:hidden!important;max-width:100vw!important;}
@supports not (backdrop-filter:blur(1px)){
  .et-floatcard{background:rgba(12,20,38,.96)!important;}
}
@media(max-width:900px){
  /* Prevent any element from exceeding viewport */
  *{max-width:100vw;box-sizing:border-box;}
  /* Tracks split-panel: stack vertically */
  div[style*="grid-template-columns:1fr 1fr"],
  div[style*="grid-template-columns: 1fr 1fr"]{
    grid-template-columns:1fr!important;
  }
  /* Journey 5-col grid → 2 cols */
  div[style*="grid-template-columns:repeat(5"]{
    grid-template-columns:repeat(2,1fr)!important;
  }
  /* Features/cards 3-col → 1 col */
  div[style*="grid-template-columns:repeat(3"]{
    grid-template-columns:1fr!important;
  }
  /* Pricing 3-col → 1 col */
  div[style*="repeat(3,1fr)"]{
    grid-template-columns:1fr!important;
  }
  /* Hide orbital ring on small screens */
  div[style*="width:300px"][style*="height:300px"]{display:none!important;}
  /* Reposition float cards */
  .et-floatcard[data-depth="1.5"]{
    position:relative!important;top:auto!important;right:auto!important;
    margin:0 0 16px!important;display:inline-block!important;
  }
  .et-floatcard[data-depth="2.4"]{display:none!important;}
  /* Marquee strip: smaller text */
  div[style*="etMarquee"] span{font-size:16px!important;}
  /* Nav: hide middle links, keep logo + CTA */
  nav a[style*="color:#9FB1CE"]{display:none!important;}
}
@media(max-width:480px){
  /* Full-width buttons */
  a[style*="padding:15px 28px"]{
    width:100%!important;justify-content:center!important;display:flex!important;
  }
  /* Overflow-safe tables */
  table{display:block;overflow-x:auto;width:100%;}
  /* Sticky nav padding */
  div[style*="position:fixed"][style*="z-index:1000"]{padding:10px 16px!important;}
}
/* iOS tap highlight & zoom fixes */
*{-webkit-tap-highlight-color:transparent;}
input,button,select,textarea{font-size:16px!important;}
/* Firefox scrollbar */
*{scrollbar-width:thin;scrollbar-color:#22D3EE #0C1426;}
</style>';

	$html = preg_replace( '#</head>#i', $inject . '</head>', $html, 1 );

	// ── Orbit cycling script — waits for Design Canvas to render ───────────────
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
  var idx=0;

  function findEl(){
    /* Try by ID first (if Design Canvas preserved it) */
    var el=document.getElementById("et-placed-role");
    if(el) return el;
    /* Fallback: find by text content */
    var divs=document.querySelectorAll("div");
    for(var i=0;i<divs.length;i++){
      var t=divs[i].textContent.trim();
      if((t.indexOf("Data Scientist")>-1||t.indexOf("Java Developer")>-1)
          && t.indexOf("weeks")>-1
          && divs[i].children.length===0){
        return divs[i];
      }
    }
    return null;
  }

  function startCycling(el){
    setInterval(function(){
      el.style.transition="opacity 0.45s ease";
      el.style.opacity="0";
      setTimeout(function(){
        idx=(idx+1)%placements.length;
        el.textContent=placements[idx];
        el.style.opacity="1";
      },450);
    },3200);
  }

  /* Poll until element exists (Design Canvas renders async) */
  var attempts=0;
  var poll=setInterval(function(){
    var el=findEl();
    if(el){clearInterval(poll);startCycling(el);}
    if(++attempts>40){clearInterval(poll);} /* Give up after 20s */
  },500);
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
