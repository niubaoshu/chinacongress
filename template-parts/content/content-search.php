<?php
/**
 * Template part for displaying results in search pages in Child Theme.
 */

?>
<article id="post-<?php the_ID(); ?>" <?php post_class('post-items mb-6'); ?>>
	<figure class="post-image">
	   <a href="<?php esc_url(get_permalink()); ?>" class="post-hover">
			<?php if ( has_post_thumbnail() ) { the_post_thumbnail(); } ?>
		</a>
		<div class="post-meta imu">
			<span class="post-list">
			   <ul class="post-categories"><li><a href="<?php esc_url(get_permalink()); ?>"><?php the_category(' '); ?></a></li></ul>
			</span>
		</div>
	</figure>
	<div class="post-content">
		<div class="post-meta up">
			<span class="posted-on">
			   <a href="<?php echo esc_url(get_month_link(get_post_time('Y'),get_post_time('m'))); ?>"><?php echo esc_html(get_the_date()); ?></a>
			</span>
		</div>
	   <?php     
			if ( is_single() ) :
			
			the_title('<h5 class="post-title">', '</h5>' );
			
			else:
			
			the_title( sprintf( '<h5 class="post-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h5>' );
			
			endif; 
			
			$raw_content = wp_strip_all_tags( get_the_content() );
			$clean_text = preg_replace( '/\s+/', ' ', $raw_content );
			$excerpt = mb_strimwidth( trim( $clean_text ), 0, 120, '...' );
			echo '<p>' . esc_html( $excerpt ) . '</p>';
			echo '<div class="read-more mt-3"><a href="' . esc_url( get_permalink() ) . '" class="btn-theme">' . esc_html__( 'Read More', 'avril' ) . '</a></div>';
		?> 
	</div>
</article>
