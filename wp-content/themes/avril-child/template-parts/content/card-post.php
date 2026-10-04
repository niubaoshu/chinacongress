<?php
/**
 * 通用文章卡片模板组件 (Unified Post Card Component)
 *
 * 场景模式 ($args['mode'])：
 * - 'archive' (默认): 分类列表、标签、归档、搜索结果（横向响应式图文卡片）
 * - 'home': 首页“最新发布”网格卡片
 * - 'side': 侧边栏“最新发布”紧凑卡片
 */

$mode           = ! empty( $args['mode'] ) ? $args['mode'] : 'archive';
$excerpt_len    = ! empty( $args['excerpt_length'] ) ? (int) $args['excerpt_length'] : ( 'side' === $mode ? 60 : ( 'home' === $mode ? 110 : 140 ) );
$post_id        = get_the_ID();
$permalink      = esc_url( get_permalink() );
$title          = get_the_title();
$categories     = get_the_category();
$category_name  = ! empty( $categories[0] ) ? esc_html( $categories[0]->name ) : '';
$thumb_url      = esc_url( chinacongress_get_first_image_url( $post_id ) );
$date_str       = esc_html( get_the_date() );
$excerpt        = esc_html( chinacongress_get_clean_excerpt( $excerpt_len, $post_id ) );

// 标题级别语义化
$title_tag = 'h5';
if ( 'home' === $mode ) {
	$title_tag = 'h4';
} elseif ( 'side' === $mode ) {
	$title_tag = 'h6';
}

// 组合卡片类名（同时包含通用 BEM 类与历史兼容别名，确保零副作用）
$card_classes = array( 'cc-post-card', 'cc-post-card--' . $mode );
if ( 'home' === $mode ) {
	$card_classes[] = 'home-blog-card';
} elseif ( 'side' === $mode ) {
	$card_classes[] = 'sidebar-blog-card';
} else {
	$card_classes[] = 'category-post-card';
}
?>

<article id="post-<?php the_ID(); ?>" class="<?php echo esc_attr( implode( ' ', $card_classes ) ); ?>">
	<!-- 缩略图与分类徽章 -->
	<div class="cc-card-thumb-wrap <?php echo ( 'home' === $mode ) ? 'home-blog-thumb-wrap' : ( ( 'side' === $mode ) ? 'sidebar-blog-thumb-wrap' : 'category-post-thumb-wrap' ); ?>">
		<?php if ( ! empty( $category_name ) ) : ?>
			<span class="category-badge"><?php echo $category_name; ?></span>
		<?php endif; ?>
		<a href="<?php echo $permalink; ?>" class="cc-card-thumb-link <?php echo ( 'home' === $mode ) ? 'home-blog-thumb-link' : ( ( 'side' === $mode ) ? 'sidebar-blog-thumb-link' : 'category-post-thumb-link' ); ?>" aria-label="<?php echo esc_attr( $title ); ?>">
			<img src="<?php echo $thumb_url; ?>" alt="<?php echo esc_attr( $title ); ?>" class="cc-card-thumb-img <?php echo ( 'home' === $mode ) ? 'home-blog-thumb-img' : ( ( 'side' === $mode ) ? 'sidebar-blog-thumb-img' : 'category-post-thumb-img' ); ?>" loading="lazy" />
		</a>
	</div>

	<!-- 标题、发布日期、摘要及阅读全文按钮 -->
	<div class="cc-card-content <?php echo ( 'home' === $mode ) ? 'home-blog-content' : ( ( 'side' === $mode ) ? 'sidebar-blog-content' : 'category-post-content-wrap' ); ?>">
		<div>
			<div class="cc-card-meta <?php echo ( 'home' === $mode ) ? 'home-blog-meta' : ( ( 'side' === $mode ) ? 'sidebar-blog-meta' : 'category-post-meta' ); ?>">
				<span><i class="fa fa-calendar"></i> <?php echo $date_str; ?></span>
			</div>
			
			<<?php echo $title_tag; ?> class="cc-card-title <?php echo ( 'home' === $mode ) ? 'home-blog-title' : ( ( 'side' === $mode ) ? 'sidebar-blog-title' : 'category-post-title' ); ?>">
				<a href="<?php echo $permalink; ?>" rel="bookmark"><?php echo esc_html( $title ); ?></a>
			</<?php echo $title_tag; ?>>
			
			<?php if ( ! empty( $excerpt ) ) : ?>
				<div class="cc-card-excerpt <?php echo ( 'home' === $mode ) ? 'home-blog-excerpt' : ( ( 'side' === $mode ) ? 'sidebar-blog-excerpt' : 'category-post-excerpt' ); ?>">
					<?php echo $excerpt; ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="<?php echo ( 'side' === $mode ) ? 'sidebar-blog-action' : ''; ?>">
			<a href="<?php echo $permalink; ?>" class="<?php echo ( 'side' === $mode ) ? 'sidebar-blog-read-more' : 'category-read-more-btn'; ?>">
				<?php esc_html_e( '阅读全文', 'avril-child' ); ?> <i class="fa fa-angle-right"></i>
			</a>
		</div>
	</div>
</article>
