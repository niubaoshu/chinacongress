<?php
/**
 * The template for displaying Category pages in Child Theme.
 * Guaranteed Left-Image Right-Text Card Layout.
 */

get_header();
?>
<section id="post-section" class="post-section av-py-default blog-page">
	<div class="av-container">
		<div class="av-columns-area wow fadeInUp">
			<div id="av-primary-content" class="<?php echo esc_attr( avril_post_layout() ); ?> wow fadeInUp">
			
				<?php if ( have_posts() ) : ?>
					<header class="page-header mb-6">
						<?php
						the_archive_title( '<h1 class="page-title" style="font-size: 24px; font-weight: 700; color: #1d2327; margin-bottom: 20px; border-left: 4px solid #3a75a1; padding-left: 12px;">', '</h1>' );
						the_archive_description( '<div class="archive-description mb-4" style="font-size: 15px; color: #6c757d;">', '</div>' );
						?>
					</header>

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
