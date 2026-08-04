<?php
/**
 * Template part for Category & Archive & Search pages in Child Theme.
 * Left: Smart Auto Extracted Image / Video Poster
 * Right: Title, Meta, Excerpt, Read More Button
 */

$post_id       = get_the_ID();
$permalink     = esc_url( get_permalink() );
$thumb_url     = esc_url( chinacongress_get_first_image_url( $post_id ) );
$title         = get_the_title();
$date_str      = esc_html( get_the_date() );
$categories    = get_the_category();
$category_name = ! empty( $categories[0] ) ? esc_html( $categories[0]->name ) : '';
$excerpt       = chinacongress_get_clean_excerpt( 140, $post_id );
?>

<article id="post-<?php the_ID(); ?>" <?php post_class('category-post-card'); ?>>
	<!-- 左侧：自动抓取的智能封面图片/视频海报 -->
	<div class="category-post-thumb-wrap">
		<?php if ( ! empty( $category_name ) ) : ?>
			<span class="category-badge"><?php echo $category_name; ?></span>
		<?php endif; ?>
		<a href="<?php echo $permalink; ?>" class="category-post-thumb-link">
			<img src="<?php echo $thumb_url; ?>" alt="<?php echo esc_attr( $title ); ?>" class="category-post-thumb-img" />
		</a>
	</div>

	<!-- 右侧：标题、发布时间、摘要及阅读全文按钮 -->
	<div class="category-post-content-wrap">
		<div>
			<div class="category-post-meta">
				<span><i class="fa fa-calendar"></i> <?php echo $date_str; ?></span>
			</div>
			
			<h5 class="category-post-title">
				<a href="<?php echo $permalink; ?>" rel="bookmark"><?php echo esc_html( $title ); ?></a>
			</h5>
			
			<div class="category-post-excerpt">
				<?php echo esc_html( $excerpt ); ?>
			</div>
		</div>

		<div>
			<a href="<?php echo $permalink; ?>" class="category-read-more-btn">
				<?php esc_html_e( '阅读全文', 'avril-child' ); ?> <i class="fa fa-angle-right"></i>
			</a>
		</div>
	</div>
</article>
