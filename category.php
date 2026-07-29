<?php
/**
 * The template for displaying Category pages in Child Theme.
 * Clean Left-Image Right-Text Card Layout without extra header text/tips.
 */

get_header();
?>
<section id="post-section" class="post-section av-py-default blog-page">
	<div class="av-container">
		<div class="av-columns-area wow fadeInUp">
			<div id="av-primary-content" class="<?php echo esc_attr( avril_post_layout() ); ?> wow fadeInUp">
			
				<?php if ( have_posts() ) : ?>
					<?php
					while ( have_posts() ) : the_post();
						get_template_part( 'template-parts/content/content', 'search' ); 
					endwhile;

					the_posts_pagination( array(
						'prev_text' => '<i class="fa fa-angle-double-left"></i>',
						'next_text' => '<i class="fa fa-angle-double-right"></i>',
					) );
					?>
					
				<?php else : ?>
					<?php get_template_part( 'template-parts/content/content', 'none' ); ?>
				<?php endif; ?>

			</div>
			<?php get_sidebar(); ?>
		</div>
	</div>
</section> 
<?php get_footer(); ?>
