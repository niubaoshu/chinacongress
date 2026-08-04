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
				$avril_blog_args = array( 'post_type' => 'post', 'posts_per_page' => 2, 'post__not_in' => get_option("sticky_posts") ); 	
				$avril_wp_query = new WP_Query($avril_blog_args);
				if($avril_wp_query && $avril_wp_query->have_posts()) :
					while($avril_wp_query->have_posts()): $avril_wp_query->the_post(); 
						$categories = get_the_category();
					?>
					<div class="av-column-6 av-md-column-6 mb-4">
						<article class="home-blog-card">
							<div class="home-blog-thumb-wrap">
								<?php if ( ! empty( $categories[0] ) ) : ?>
									<span class="category-badge"><?php echo esc_html( $categories[0]->name ); ?></span>
								<?php endif; ?>
								<a href="<?php echo esc_url( get_permalink() ); ?>" class="home-blog-thumb-link">
									<img src="<?php echo esc_url( chinacongress_get_first_image_url( get_the_ID() ) ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" class="home-blog-thumb-img" />
								</a>
							</div>
							<div class="home-blog-content">
								<div>
									<div class="home-blog-meta">
										<span><i class="fa fa-calendar"></i> <?php echo esc_html( get_the_date() ); ?></span>
									</div>
									<h4 class="home-blog-title">
										<a href="<?php echo esc_url( get_permalink() ); ?>" rel="bookmark"><?php echo esc_html( get_the_title() ); ?></a>
									</h4>
									<p class="home-blog-excerpt">
										<?php echo esc_html( chinacongress_get_clean_excerpt( 110 ) ); ?>
									</p>
								</div>
								<div>
									<a href="<?php echo esc_url( get_permalink() ); ?>" class="category-read-more-btn mt-2">
										阅读全文 <i class="fa fa-angle-right"></i>
									</a>
								</div>
							</div>
						</article>
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
	if ( function_exists( 'avril_home_blog' ) ) {
		$avril_section_priority = apply_filters( 'avril_section_priority', 15, 'avril_home_blog' );
		add_action( 'avril_sections', 'avril_home_blog', absint( $avril_section_priority ) );
	}