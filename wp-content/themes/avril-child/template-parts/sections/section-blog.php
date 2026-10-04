<?php 
if ( ! function_exists( 'avril_home_blog' ) ) :
	function avril_home_blog() {
	$hs_blog					= get_theme_mod('hs_blog','1');
	$avril_blog_title			= get_theme_mod('blog_title', __('最新发布', 'avril-child'));
	$blog_subtitle				= get_theme_mod('blog_subtitle');
	$blog_description			= get_theme_mod('blog_description');
	$blog_display_num			= get_theme_mod('blog_display_num','2');
if($hs_blog == '1') {	
?>
 <section id="post-section" class="post-section post-shadow av-py-default home-blog">
        <div class="av-container">
            <div class="av-columns-area">
                <div class="av-column-12">
                    <div class="heading-default wow fadeInUp">
                        <?php if ( ! empty( $avril_blog_title ) ) : ?>
							<span class='ttl'><?php echo esc_html($avril_blog_title); ?></span>
						<?php endif; ?>
					   <?php if ( ! empty( $blog_subtitle ) ) : ?>		
							<h3><?php echo wp_kses_post($blog_subtitle); ?></h3>    
						<?php endif; ?>	                   
						<?php if ( ! empty( $blog_description ) ) : ?>		
							<p><?php echo esc_html($blog_description); ?></p>
						<?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="av-columns-area wow fadeInUp">
				<?php 	
				$posts_per_page = absint( $blog_display_num ) > 0 ? absint( $blog_display_num ) : 2;
				$sticky_posts   = get_option( 'sticky_posts' );
				$avril_blog_args = array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'posts_per_page' => $posts_per_page,
					'post__not_in'   => ! empty( $sticky_posts ) && is_array( $sticky_posts ) ? $sticky_posts : array(),
					'no_found_rows'  => true,
				); 	
				$avril_wp_query = new WP_Query( $avril_blog_args );
				if ( $avril_wp_query && $avril_wp_query->have_posts() ) :
					while ( $avril_wp_query->have_posts() ) :
						$avril_wp_query->the_post();
					?>
					<div class="av-column-6 av-md-column-6 mb-4">
						<?php get_template_part( 'template-parts/content/card', 'post', array( 'mode' => 'home' ) ); ?>
					</div>
				<?php 
					endwhile; 
				endif;
				wp_reset_postdata();
				?>
            </div>
        </div>
    </section>
<?php } } endif;