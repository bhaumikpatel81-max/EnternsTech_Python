<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
	<style>
		*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
		:root {
			--cyan:  #22D3EE;
			--blue:  #3BA4FF;
			--bg:    #05080F;
			--surf:  #0C1426;
			--text:  #ECF2FF;
			--muted: #6B7280;
			--bdr:   rgba(34,211,238,0.12);
		}
		body {
			background: var(--bg);
			color: var(--text);
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
			line-height: 1.6;
		}
		a { color: var(--cyan); text-decoration: none; }
		a:hover { color: var(--blue); }
		.site-header {
			position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
			display: flex; align-items: center; gap: 1rem;
			padding: 0.875rem 2rem;
			background: rgba(5,8,15,0.92);
			backdrop-filter: blur(12px);
			border-bottom: 1px solid var(--bdr);
		}
		.site-logo { color: var(--cyan); font-size: 1.25rem; font-weight: 700; }
		.site-logo a { color: inherit; }
		.site-main { padding-top: 72px; min-height: 100vh; }
	</style>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="site-header">
	<?php if ( has_custom_logo() ) : ?>
		<?php the_custom_logo(); ?>
	<?php else : ?>
		<p class="site-logo">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
		</p>
	<?php endif; ?>

	<?php if ( has_nav_menu( 'primary' ) ) : ?>
		<nav aria-label="<?php esc_attr_e( 'Primary', 'enternstech' ); ?>">
			<?php wp_nav_menu( array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'primary-menu',
				'items_wrap'     => '<ul id="%1$s" class="%2$s" style="display:flex;list-style:none;gap:2rem;margin-left:auto;">%3$s</ul>',
			) ); ?>
		</nav>
	<?php endif; ?>
</header>
