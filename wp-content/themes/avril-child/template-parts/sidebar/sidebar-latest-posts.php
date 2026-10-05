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

// 固定展示 4 篇最新发布文章（不再读取数据库配置）
$latest_args = array(
	'post_type'      => 'post',
	'post_status'    => 'publish',
	'posts_per_page' => 4,
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
			get_template_part( 'template-parts/content/card', 'post', array( 'mode' => 'side' ) );
		endwhile;
		?>
	</div>
</aside>
<?php
endif;
wp_reset_postdata();
