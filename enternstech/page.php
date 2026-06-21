<?php
/**
 * Page Template
 *
 * @package EnternsTech
 */
get_header();
?>
<main id="main" class="site-main" style="max-width:900px;margin:0 auto;padding:3rem 2rem;">
	<?php while ( have_posts() ) : the_post(); ?>
		<article id="page-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header" style="margin-bottom:2rem;">
				<?php the_title( '<h1 style="font-size:2.25rem;color:#22D3EE;">', '</h1>' ); ?>
			</header>
			<div class="entry-content" style="color:#D1D5DB;line-height:1.8;">
				<?php the_content(); ?>
			</div>
		</article>
	<?php endwhile; ?>
</main>
<?php
get_footer();
