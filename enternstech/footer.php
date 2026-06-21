<footer class="site-footer" style="background:#05080F;border-top:1px solid rgba(34,211,238,0.1);padding:2.5rem 2rem;text-align:center;color:#6B7280;font-size:0.875rem;">
	<?php if ( is_active_sidebar( 'footer-1' ) ) : ?>
		<div style="max-width:1200px;margin:0 auto 2rem;">
			<?php dynamic_sidebar( 'footer-1' ); ?>
		</div>
	<?php endif; ?>

	<?php if ( has_nav_menu( 'footer' ) ) : ?>
		<nav style="margin-bottom:1.5rem;" aria-label="<?php esc_attr_e( 'Footer', 'enternstech' ); ?>">
			<?php wp_nav_menu( array(
				'theme_location' => 'footer',
				'container'      => false,
				'menu_class'     => 'footer-menu',
				'items_wrap'     => '<ul id="%1$s" class="%2$s" style="display:flex;flex-wrap:wrap;justify-content:center;list-style:none;gap:1.5rem;">%3$s</ul>',
			) ); ?>
		</nav>
	<?php endif; ?>

	<p style="margin-bottom:0.5rem;">
		&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="color:#22D3EE;"><?php bloginfo( 'name' ); ?></a>.
		<?php esc_html_e( 'All rights reserved.', 'enternstech' ); ?>
	</p>
	<p>
		<a href="mailto:info@enternstech.com" style="color:#22D3EE;">info@enternstech.com</a>
	</p>
	<?php wp_footer(); ?>
</footer>
</body>
</html>
