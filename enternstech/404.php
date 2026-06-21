<?php
/**
 * 404 Template
 *
 * @package EnternsTech
 */
get_header();
?>
<main id="main" class="site-main" style="display:flex;align-items:center;justify-content:center;min-height:calc(100vh - 72px);">
	<div style="text-align:center;padding:2rem;max-width:480px;">
		<div style="font-size:7rem;font-weight:900;color:#22D3EE;line-height:1;margin-bottom:0.5rem;">404</div>
		<h1 style="font-size:1.5rem;color:#ECF2FF;margin-bottom:1rem;">
			<?php esc_html_e( 'Page Not Found', 'enternstech' ); ?>
		</h1>
		<p style="color:#6B7280;margin-bottom:2rem;">
			<?php esc_html_e( "The page you're looking for doesn't exist or has been moved.", 'enternstech' ); ?>
		</p>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"
		   style="display:inline-block;padding:0.75rem 2rem;background:#22D3EE;color:#05080F;font-weight:700;border-radius:10px;">
			<?php esc_html_e( '← Back to Home', 'enternstech' ); ?>
		</a>
	</div>
</main>
<?php
get_footer();
