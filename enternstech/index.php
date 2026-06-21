<?php
/**
 * Main Index / Blog Template
 *
 * @package EnternsTech
 */
get_header();
?>
<main id="main" class="site-main" style="max-width:900px;margin:0 auto;padding:3rem 2rem;">
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<article id="post-<?php the_ID(); ?>" <?php post_class( 'entry' ); ?> style="margin-bottom:3rem;padding-bottom:3rem;border-bottom:1px solid rgba(34,211,238,0.1);">
				<header class="entry-header" style="margin-bottom:1rem;">
					<h2 class="entry-title" style="font-size:1.75rem;color:#22D3EE;">
						<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
					</h2>
					<div class="entry-meta" style="color:#6B7280;font-size:0.875rem;margin-top:0.4rem;">
						<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
							<?php echo esc_html( get_the_date() ); ?>
						</time>
						&middot; <?php echo esc_html( get_the_author() ); ?>
					</div>
				</header>
				<div class="entry-content" style="color:#D1D5DB;line-height:1.8;">
					<?php the_excerpt(); ?>
				</div>
				<a href="<?php the_permalink(); ?>" style="display:inline-block;margin-top:1rem;padding:0.5rem 1.25rem;border:1px solid #22D3EE;color:#22D3EE;border-radius:6px;font-size:0.875rem;">
					<?php esc_html_e( 'Read more', 'enternstech' ); ?> &rarr;
				</a>
			</article>
		<?php endwhile; ?>

		<div class="pagination" style="display:flex;gap:1rem;justify-content:center;margin-top:2rem;">
			<?php the_posts_navigation(); ?>
		</div>
	<?php else : ?>
		<p style="color:#6B7280;"><?php esc_html_e( 'No posts found.', 'enternstech' ); ?></p>
	<?php endif; ?>
</main>
<?php
get_footer();
