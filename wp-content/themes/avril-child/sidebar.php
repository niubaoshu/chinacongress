<?php
/**
 * The sidebar containing the main widget area.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package Avril_Child
 */

if ( ! is_active_sidebar( 'avril-sidebar-primary' ) && ! ( is_single() || is_archive() ) ) {
	return;
}
?>
<div id="av-secondary-content" class="av-column-4 mb-6 mb-av-0 wow fadeInUp">
	<section class="sidebar">
		<?php dynamic_sidebar( 'avril-sidebar-primary' ); ?>
		<?php if ( is_single() || is_archive() ) : ?>
			<?php get_template_part( 'template-parts/sidebar/sidebar', 'latest-posts' ); ?>
		<?php endif; ?>
	</section>
</div>
