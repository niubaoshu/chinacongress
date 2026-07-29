<?php 
if ( ! function_exists( 'avril_home_blog' ) ) :
	function avril_home_blog() {
	$hs_blog					= get_theme_mod('hs_blog','1');
	$avril_blog_title			= get_theme_mod('blog_title');
	$blog_subtitle				= get_theme_mod('blog_subtitle');
	$blog_description			= get_theme_mod('blog_description');
	$blog_display_num			= get_theme_mod('blog_display_num','3');
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
				$avril_blog_args = array( 'post_type' => 'post', 'posts_per_page' => $blog_display_num,'post__not_in'=>get_option("sticky_posts")) ; 	
					$avril_wp_query = new WP_Query($avril_blog_args);
					if($avril_wp_query)
					{	
					while($avril_wp_query->have_posts()):$avril_wp_query->the_post(); ?>
					<div class="av-column-4 av-md-column-6 mb-4 mb-av-0">
						<article class="post-items">
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
									
									?>
									
									<?php
								?> 
								
							</div>
						</article>
					</div>
				<?php 
					endwhile; 
					}
					wp_reset_postdata();
				?>
            </div>
        </div>
    </section>
<?php } } endif; 
	if ( function_exists( 'avril_home_blog' ) ) {
		$avril_section_priority = apply_filters( 'avril_section_priority', 15, 'avril_home_blog' );
		add_action( 'avril_sections', 'avril_home_blog', absint( $avril_section_priority ) );
	}