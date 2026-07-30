<?php 
if ( ! function_exists( 'avril_lite_features' ) ) :
	function avril_lite_features() {
	$avril_features_title        = get_theme_mod('feature_title', __('Technology from tomorrow','clever-fox'));
	$avril_features_subtitle     = get_theme_mod('feature_subtitle', __('推荐内容','avril-child'));
	$avril_features_description  = get_theme_mod('feature_description');
	$avril_features_contents     = get_theme_mod('features_contents', function_exists('avril_get_features_default') ? avril_get_features_default() : '');
	$avril_hs_feature            = get_theme_mod('hs_feature','1');

if ( $avril_hs_feature == '1' ) {	
?>
 <section id="features-section" class="features-section bg-primary av-py-default home-features">
        <div class="av-container">
			<?php if ( ! empty( $avril_features_title ) || ! empty( $avril_features_subtitle ) || ! empty( $avril_features_description ) ) { ?> 
				<div class="av-columns-area">
					<div class="av-column-12">
						<div class="heading-default heading-white wow fadeInUp">
						   <?php if ( ! empty( $avril_features_title ) ) : ?>
								<span class='ttl'><?php echo wp_kses_post($avril_features_title); ?></span>
							<?php endif; ?>
						   <?php if ( ! empty( $avril_features_subtitle ) ) : ?>		
								<h3><?php echo wp_kses_post($avril_features_subtitle); ?></h3>    
							<?php endif; ?>	                   
							<?php if ( ! empty( $avril_features_description ) ) : ?>		
								<p><?php echo wp_kses_post($avril_features_description); ?></p>    
							<?php endif; ?>
						</div>
					</div>
				</div>
			<?php } ?>	
            <div class="av-columns-area features-area wow fadeInUp">
				<?php
				if ( ! empty( $avril_features_contents ) ) {
					$avril_features_contents = json_decode( $avril_features_contents );
					if ( is_array( $avril_features_contents ) ) {
					foreach ( $avril_features_contents as $avril_feature_item ) {
						$avril_repeater_title = ! empty( $avril_feature_item->title ) ? apply_filters( 'avril_translate_single_string', $avril_feature_item->title, 'feature section' ) : '';
						$avril_repeater_text  = ! empty( $avril_feature_item->text ) ? apply_filters( 'avril_translate_single_string', $avril_feature_item->text, 'feature section' ) : '';
						$avril_repeater_image = ! empty( $avril_feature_item->image_url ) ? apply_filters( 'avril_translate_single_string', $avril_feature_item->image_url, 'feature section' ) : '';
						$avril_repeater_link  = ! empty( $avril_feature_item->link ) ? apply_filters( 'avril_translate_single_string', $avril_feature_item->link, 'feature section' ) : '#';

						// 自动清洗链接：若配置中包含了本站线上绝对域名，自动剥离转换为相对路径
						if ( ! empty( $avril_repeater_link ) && $avril_repeater_link !== '#' ) {
							$avril_repeater_link = preg_replace( '#^https?://(www\.)?chinacongress\.net#i', '', $avril_repeater_link );
						}

						// 智能获取图片：优先 Customizer 自定义图片 -> 自动抓取关联文章的第一张图/视频海报 -> 规则 Logo 兜底
						$img_url = '';
						if ( ! empty( $avril_repeater_image ) ) {
							$img_url = $avril_repeater_image;
						} elseif ( ! empty( $avril_repeater_link ) && $avril_repeater_link !== '#' ) {
							$full_link = ( 0 === strpos( $avril_repeater_link, '/' ) ) ? home_url( $avril_repeater_link ) : $avril_repeater_link;
							$post_id   = url_to_postid( $full_link );
							if ( $post_id ) {
								$img_url = chinacongress_get_first_image_url( $post_id );
							}
						}

						if ( empty( $img_url ) ) {
							$img_url = '/wp-content/uploads/2026/03/logo-e1768719012316-1536x413-1-150x150.jpg';
						}
				?>
					<div class="av-column-4 av-md-column-6 mb-6">
						<div class="feature-card-item">
							<div class="feature-card-thumb">
								<a href="<?php echo esc_url( $avril_repeater_link ); ?>">
									<img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $avril_repeater_title ); ?>" class="feature-card-img" />
								</a>
							</div>
							<div class="feature-card-content">
								<div>
									<?php if ( ! empty( $avril_repeater_title ) ) : ?>
										<h5 class="feature-card-title"><a href="<?php echo esc_url( $avril_repeater_link ); ?>"><?php echo esc_html( $avril_repeater_title ); ?></a></h5>
									<?php endif; ?>
									<?php if ( ! empty( $avril_repeater_text ) ) : ?>
										<p class="feature-card-text"><?php echo esc_html( $avril_repeater_text ); ?></p>
									<?php endif; ?>
								</div>
								<div>
									<a href="<?php echo esc_url( $avril_repeater_link ); ?>" class="category-read-more-btn mt-2">
										查看详情 <i class="fa fa-angle-right"></i>
									</a>
								</div>
							</div>
						</div>
					</div>
				<?php }}} ?>
            </div>
        </div>
    </section>
	
<?php	
	}} endif; 
	if ( function_exists( 'avril_lite_features' ) ) {
		$cleverfox_section_priority = apply_filters( 'avril_section_priority', 14, 'avril_lite_features' );
		add_action( 'avril_sections', 'avril_lite_features', absint( $cleverfox_section_priority ) );
	}
