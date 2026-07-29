<?php
// Enqueue Parent Theme Style, Child Theme Style & Permanent FontAwesome Backup
add_action( 'wp_enqueue_scripts', 'avril_child_enqueue_styles', 99 );
function avril_child_enqueue_styles() {
    wp_enqueue_style( 'avril-parent-style', get_template_directory_uri() . '/style.css' );
    wp_enqueue_style( 'avril-child-style', get_stylesheet_directory_uri() . '/style.css', array( 'avril-parent-style' ), time() );
    wp_enqueue_style( 'avril-child-fontawesome', get_stylesheet_directory_uri() . '/assets/css/fonts/font-awesome/css/font-awesome.min.css', array(), '4.6.3' );
}

// Load Child Theme Section Blog & Features Override so parent function is safely overridden
require_once get_stylesheet_directory() . '/template-parts/sections/section-blog.php';
require_once get_stylesheet_directory() . '/template-parts/sections/section-features.php';

// Search Sort by Date DESC
function chinacongress_sort_search_by_date( $query ) {
    if ( ! is_admin() && $query->is_main_query() && $query->is_search() ) {
        $query->set( 'orderby', 'date' );
        $query->set( 'order', 'DESC' );
    }
}
add_action( 'pre_get_posts', 'chinacongress_sort_search_by_date' );

// Enqueue Customizer JS for 50 Sliders Limit in Customizer
function avril_child_customizer_control_scripts() {
    wp_dequeue_script( 'avril_customizer-repeater-script' );
    wp_enqueue_script(
        'avril-child-customizer-repeater-script',
        get_stylesheet_directory_uri() . '/js/customizer_repeater.js',
        array( 'jquery', 'jquery-ui-draggable', 'wp-color-picker' ),
        time(),
        true
    );
}
add_action( 'customize_controls_enqueue_scripts', 'avril_child_customizer_control_scripts', 99 );

// Register dedicated input field for Mainland Voter Count in Customizer Call to Action Section
function avril_child_customize_register( $wp_customize ) {
    $wp_customize->add_setting( 'mainland_voter_count', array(
        'default'           => '180',
        'capability'        => 'edit_theme_options',
        'sanitize_callback' => 'sanitize_text_field',
    ) );

    $wp_customize->add_control( 'mainland_voter_count', array(
        'label'       => __( '大陆院选民登记人数', 'avril-child' ),
        'description' => __( '请在此处输入最新的大陆院选民登记数字', 'avril-child' ),
        'section'     => 'cta_setting',
        'type'        => 'text',
        'priority'    => 15,
    ) );
}
add_action( 'customize_register', 'avril_child_customize_register', 99 );

// Dual Voter Registration Boxes (CTA Section) editable via Customizer
function avril_lite_cta() {
	$avril_hs_cta            = get_theme_mod('hs_cta','1');	
	$avril_cta_title         = get_theme_mod('cta_title', __('海外院选民登记人数： ', 'clever-fox'));
	$renshu_overseas_val     = get_theme_mod('cta_description', '425');
	$renshu_overseas         = is_numeric(trim($renshu_overseas_val)) ? (int)trim($renshu_overseas_val) : 425;

	$renshu_mainland_val     = get_theme_mod('mainland_voter_count', '180');
	$renshu_mainland         = is_numeric(trim($renshu_mainland_val)) ? (int)trim($renshu_mainland_val) : 180;

	$avril_cta_btn_lbl1      = get_theme_mod('cta_btn_lbl1', __('选民登记', 'clever-fox'));
	$avril_cta_btn_link1     = get_theme_mod('cta_btn_link1', 'https://reg.chinacongress.net/');

	if($avril_hs_cta == '1') {	
	?>	
	 <!-- 1. 海外院选民登记框 -->
	 <section id="cta-section-overseas" class="cta-section cta-shadow-one av-mb-default home-cta">
        <div class="av-container">
            <div class="av-columns-area">
                <div class="av-column-12">
                    <div class="cta-wrapper">
                        <div class="cta-content">
							<?php if ( ! empty( $avril_cta_title ) ) : ?>
								<h4><?php echo wp_kses_post($avril_cta_title); ?>
									<span id="number_overseas"><?php echo esc_html($renshu_overseas); ?></span>
								</h4>
							<?php endif; ?>
                        </div>
                        <div class="cta-btn-wrap text-av-right text-center">
							<?php if ( ! empty( $avril_cta_btn_lbl1 ) ) : ?>
								<a href="<?php echo esc_url($avril_cta_btn_link1); ?>" class="av-btn av-btn-primary" target="_blank"><?php echo esc_html($avril_cta_btn_lbl1); ?></a>
							<?php endif;?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

	 <!-- 2. 大陆院选民登记框 -->
	 <section id="cta-section-mainland" class="cta-section cta-shadow-one av-mb-default home-cta">
        <div class="av-container">
            <div class="av-columns-area">
                <div class="av-column-12">
                    <div class="cta-wrapper">
                        <div class="cta-content">
							<h4>大陆院选民登记人数： 
								<span id="number_mainland"><?php echo esc_html($renshu_mainland); ?></span>
							</h4>
                        </div>
                        <div class="cta-btn-wrap text-av-right text-center">
							<a href="https://reg.congresscenter.org/" class="av-btn av-btn-primary" target="_blank">选民登记</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

	<script>
	(function() {
		let duration = 1000;
		
		function animateNumber(element, start, end) {
			let startTime = null;
			function step(timestamp) {
				if (!startTime) startTime = timestamp;
				let progress = timestamp - startTime;
				let percent = Math.min(progress / duration, 1);
				let current = Math.floor(start + (end - start) * percent);
				element.innerText = current;
				if (percent < 1) {
					requestAnimationFrame(step);
				}
			}
			requestAnimationFrame(step);
		}

		function setupObserver(elemId, endVal) {
			let animated = false;
			let observer = new IntersectionObserver(entries => {
				entries.forEach(entry => {
					if (entry.isIntersecting && !animated) {
						animated = true;
						animateNumber(entry.target, 100, endVal);
					}
				});
			}, { threshold: 0.5 });

			let elem = document.getElementById(elemId);
			if (elem) observer.observe(elem);
		}

		document.addEventListener('DOMContentLoaded', function() {
			setupObserver("number_overseas", <?php echo $renshu_overseas; ?>);
			setupObserver("number_mainland", <?php echo $renshu_mainland; ?>);
		});
		setupObserver("number_overseas", <?php echo $renshu_overseas; ?>);
		setupObserver("number_mainland", <?php echo $renshu_mainland; ?>);
	})();
	</script>
	<?php	
	}
}

// ==============================================================================
// 动态智能提取文章第一张图 / 嵌入视频封面 / 规则兜底 & 全网社交分享 OG 卡片自动输出
// ==============================================================================

function chinacongress_get_first_image_url( $post_id = null ) {
    if ( ! $post_id ) {
        $post_id = get_the_ID();
    }

    // 1. 优先检查：是否手动设置了特色图片 (Featured Image)
    if ( has_post_thumbnail( $post_id ) ) {
        $thumb_id = get_post_thumbnail_id( $post_id );
        $img_src  = wp_get_attachment_image_src( $thumb_id, 'full' );
        if ( ! empty( $img_src[0] ) ) {
            return $img_src[0];
        }
    }

    $post = get_post( $post_id );
    if ( $post && ! empty( $post->post_content ) ) {
        // 2. 次级检查：用正则表达式在文章正文中搜寻第一张 <img src="..."> 图片
        preg_match_all( '/<img.+?src=[\'"]([^\'"]+)[\'"].*?>/i', $post->post_content, $matches );
        if ( ! empty( $matches[1][0] ) ) {
            return $matches[1][0];
        }

        // 3. 视频检查：若无静态图片，检查是否嵌入了 YouTube 视频，自动提取 YouTube 1280x720 高清封面海报
        if ( preg_match( '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i', $post->post_content, $yt_matches ) ) {
            if ( ! empty( $yt_matches[1] ) ) {
                return 'https://img.youtube.com/vi/' . $yt_matches[1] . '/maxresdefault.jpg';
            }
        }

        // 4. 视频检查：若有 <video poster="..."> 海报封面
        if ( preg_match( '/<video.+?poster=[\'"]([^\'"]+)[\'"].*?>/i', $post->post_content, $v_matches ) ) {
            if ( ! empty( $v_matches[1] ) ) {
                return $v_matches[1];
            }
        }
    }

    // 5. 兜底保护：若正文无图无视频，自动返回中国议会官方 Banner Logo
    return 'https://chinacongress.net/wp-content/uploads/2026/03/logo-e1768719012316-1536x413-1-150x150.jpg';
}

// 过滤 post_thumbnail_html，使前台列表无特色图片时自动展示正文第一张图/视频封面
function chinacongress_auto_first_image_html( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
    if ( ! empty( $html ) ) {
        return $html;
    }
    
    $first_img_url = chinacongress_get_first_image_url( $post_id );
    if ( $first_img_url ) {
        return sprintf(
            '<img src="%s" class="attachment-full size-full wp-post-image auto-first-img" alt="%s" />',
            esc_url( $first_img_url ),
            esc_attr( get_the_title( $post_id ) )
        );
    }
    
    return $html;
}
add_filter( 'post_thumbnail_html', 'chinacongress_auto_first_image_html', 10, 5 );

// 自动在 <head> 输出符合全网社交平台标准的 Open Graph & Twitter Cards 宽屏大图元数据
function chinacongress_add_social_og_tags() {
    if ( is_single() || is_page() ) {
        global $post;
        $title       = esc_attr( get_the_title() );
        $url         = esc_url( get_permalink() );
        $image_url   = esc_url( chinacongress_get_first_image_url( $post->ID ) );
        $raw_desc    = wp_strip_all_tags( $post->post_content );
        $description = esc_attr( mb_strimwidth( preg_replace( '/\s+/', ' ', $raw_desc ), 0, 120, '...' ) );

        echo "\n<!-- ChinaCongress Social Open Graph & Twitter Cards -->\n";
        echo '<meta property="og:type" content="article" />' . "\n";
        echo '<meta property="og:title" content="' . $title . '" />' . "\n";
        echo '<meta property="og:description" content="' . $description . '" />' . "\n";
        echo '<meta property="og:url" content="' . $url . '" />' . "\n";
        echo '<meta property="og:image" content="' . $image_url . '" />' . "\n";
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        echo '<meta name="twitter:title" content="' . $title . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . $description . '" />' . "\n";
        echo '<meta name="twitter:image" content="' . $image_url . '" />' . "\n";
        echo "<!-- End Social Meta Tags -->\n\n";
    }
}
add_action( 'wp_head', 'chinacongress_add_social_og_tags', 5 );

// 过滤清理分类与归档标题，移除系统多余的前缀（如“分类：”、“Category Archives:”）
add_filter( 'get_the_archive_title', function ( $title ) {
    if ( is_category() ) {
        $title = single_cat_title( '', false );
    } elseif ( is_tag() ) {
        $title = single_tag_title( '', false );
    } elseif ( is_author() ) {
        $title = get_the_author();
    } elseif ( is_post_type_archive() ) {
        $title = post_type_archive_title( '', false );
    } elseif ( is_tax() ) {
        $title = single_term_title( '', false );
    }
    return $title;
} );

