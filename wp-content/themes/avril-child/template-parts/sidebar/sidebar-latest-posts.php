<?php
/**
 * Template part for displaying Latest Posts in the Single Post Sidebar
 *
 * @package Avril_Child
 */

// 排除当前正在浏览的文章（仅在文章详情页时）及置顶文章
$current_post_id = is_single() ? get_the_ID() : 0;
$sticky_posts    = get_option( 'sticky_posts' );
$exclude_ids     = array_filter( array_merge( (array) $current_post_id, (array) $sticky_posts ) );

// 读取后台 Customizer 设置的文章篇数（带大于0防呆兜底）
$blog_display_num = get_theme_mod( 'blog_display_num', '3' );
$posts_per_page   = absint( $blog_display_num ) > 0 ? absint( $blog_display_num ) : 3;

$latest_args = array(
	'post_type'      => 'post',
	'post_status'    => 'publish',
	'posts_per_page' => $posts_per_page,
	'post__not_in'   => $exclude_ids,
	'no_found_rows'  => true,
);

$latest_query = new WP_Query( $latest_args );

if ( $latest_query->have_posts() ) :
?>
<aside id="sidebar-latest-posts" class="widget widget_sidebar_latest_posts">
	<h5 class="widget-title">
		<span class="widget-title-text"><?php esc_html_e( '最新发布', 'avril-child' ); ?></span>
	</h5>
	<div class="sidebar-latest-posts-list">
		<?php
		while ( $latest_query->have_posts() ) :
			$latest_query->the_post();
			$categories = get_the_category();
			$post_url   = esc_url( get_permalink() );
			$post_title = esc_html( get_the_title() );
			$thumb_url  = esc_url( chinacongress_get_first_image_url( get_the_ID() ) );
			$post_date  = esc_html( get_the_date() );
			$excerpt    = esc_html( chinacongress_get_clean_excerpt( 60 ) );
		?>
		<article class="sidebar-blog-card">
			<div class="sidebar-blog-thumb-wrap">
				<?php if ( ! empty( $categories[0] ) ) : ?>
					<span class="category-badge"><?php echo esc_html( $categories[0]->name ); ?></span>
				<?php endif; ?>
				<a href="<?php echo $post_url; ?>" class="sidebar-blog-thumb-link" aria-label="<?php echo $post_title; ?>">
					<img src="<?php echo $thumb_url; ?>" alt="<?php echo $post_title; ?>" class="sidebar-blog-thumb-img" loading="lazy" />
				</a>
			</div>
			<div class="sidebar-blog-content">
				<div class="sidebar-blog-meta">
					<span><i class="fa fa-calendar"></i> <?php echo $post_date; ?></span>
				</div>
				<h6 class="sidebar-blog-title">
					<a href="<?php echo $post_url; ?>" rel="bookmark"><?php echo $post_title; ?></a>
				</h6>
				<?php if ( ! empty( $excerpt ) ) : ?>
					<p class="sidebar-blog-excerpt"><?php echo $excerpt; ?></p>
				<?php endif; ?>
				<div class="sidebar-blog-action">
					<a href="<?php echo $post_url; ?>" class="sidebar-blog-read-more">
						<?php _e( '阅读全文', 'avril-child' ); ?> <i class="fa fa-angle-right"></i>
					</a>
				</div>
			</div>
		</article>
		<?php endwhile; ?>
	</div>
</aside>
<?php
endif;
wp_reset_postdata();
