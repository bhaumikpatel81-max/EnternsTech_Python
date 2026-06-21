<?php
/**
 * Single Post Template
 *
 * @package EnternsTech
 */
get_header();
?>
<main id="main" class="site-main" style="max-width:800px;margin:0 auto;padding:3rem 2rem;">
	<?php while ( have_posts() ) : the_post(); ?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header" style="margin-bottom:2rem;">
				<?php the_title( '<h1 style="font-size:2rem;color:#22D3EE;margin-bottom:0.5rem;">', '</h1>' ); ?>
				<div style="color:#6B7280;font-size:0.875rem;">
					<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
						<?php echo esc_html( get_the_date() ); ?>
					</time>
					&middot; <?php echo esc_html( get_the_author() ); ?>
					<?php if ( has_category() ) : ?>
						&middot; <?php the_category( ', ' ); ?>
					<?php endif; ?>
				</div>
			</header>

			<?php if ( has_post_thumbnail() ) : ?>
				<div style="margin-bottom:2rem;border-radius:12px;overflow:hidden;">
					<?php the_post_thumbnail( 'large', array( 'style' => 'width:100%;height:auto;display:block;' ) ); ?>
				</div>
			<?php endif; ?>

			<div class="entry-content" style="color:#D1D5DB;line-height:1.9;">
				<?php the_content(); ?>
			</div>

			<footer class="entry-footer" style="margin-top:2rem;padding-top:1.5rem;border-top:1px solid rgba(34,211,238,0.1);">
				<?php the_tags( '<div style="color:#6B7280;font-size:0.8rem;">Tags: ', ', ', '</div>' ); ?>
			</footer>
		</article>

		<nav style="display:flex;justify-content:space-between;margin-top:3rem;gap:1rem;">
			<div><?php previous_post_link( '<a style="color:#22D3EE;">&larr; %link</a>' ); ?></div>
			<div><?php next_post_link( '<a style="color:#22D3EE;">%link &rarr;</a>' ); ?></div>
		</nav>

		<?php if ( comments_open() || get_comments_number() ) : ?>
			<div style="margin-top:3rem;"><?php comments_template(); ?></div>
		<?php endif; ?>
	<?php endwhile; ?>
</main>
<?php
get_footer();
