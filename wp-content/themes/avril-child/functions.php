<?php
/**
 * ==============================================================================
 * Avril Child Theme 功能扩展与核心业务逻辑 (functions.php)
 * ==============================================================================
 * 本文件包含了 Avril 子主题的所有核心扩展功能，包括：
 * 1. 样式与脚本加载 (Parent/Child CSS, FontAwesome 4.6.3 兜底, Customizer 控件脚本)
 * 2. 核心 API 异步同步 (大陆院选民人数 & 最新 5 位选民走马灯，包含 5 分钟 WP-Cron)
 * 3. 首页核心板块重写 (双选民登记卡片、推荐内容、最新发布)
 * 4. 智能媒体提取引擎 (文章第一张图/YouTube封面/video poster 自动抓取)
 * 5. 全网 Open Graph / Twitter Cards 社交分享元数据自动生成
 * 6. 全站 URL 相对化自动清洗 (防止域名硬编码导致迁站失效)
 * 7. Clever Fox 插件与父主题兼容性修复 (Theme Mods 继承保底与控制项修复)
 * ==============================================================================
 */

/**
 * 1. 加载父主题样式、子主题样式及 FontAwesome 4.6.3 本地矢量图标兜底库
 */
add_action( 'wp_enqueue_scripts', 'avril_child_enqueue_styles', 99 );
function avril_child_enqueue_styles() {
    // 加载父主题主样式表
    wp_enqueue_style( 'avril-parent-style', get_template_directory_uri() . '/style.css' );
    // 加载子主题主样式表（使用文件修改时间作为版本号，允许浏览器/CDN有效缓存）
    $child_css_file = get_stylesheet_directory() . '/style.css';
    $child_css_ver  = file_exists( $child_css_file ) ? filemtime( $child_css_file ) : wp_get_theme()->get( 'Version' );
    wp_enqueue_style( 'avril-child-style', get_stylesheet_directory_uri() . '/style.css', array( 'avril-parent-style' ), $child_css_ver );
    // 加载子主题自带的永久 FontAwesome 4.6.3 字体图标库（解决 CDN 丢失问题）
    wp_enqueue_style( 'avril-child-fontawesome', get_stylesheet_directory_uri() . '/assets/css/fonts/font-awesome/css/font-awesome.min.css', array(), '4.6.3' );
}

/**
 * 2. 引入子主题安全覆盖模板片段 (Section Blog & Section Features)
 */
require_once get_stylesheet_directory() . '/template-parts/sections/section-blog.php';
require_once get_stylesheet_directory() . '/template-parts/sections/section-features.php';

/**
 * 3. 强制全站主搜索结果按照发布时间倒序 (Date DESC) 排列
 *
 * @param WP_Query $query 当前查询对象
 */
function chinacongress_sort_search_by_date( $query ) {
    if ( ! is_admin() && $query->is_main_query() && $query->is_search() ) {
        $query->set( 'orderby', 'date' );
        $query->set( 'order', 'DESC' );
    }
}
add_action( 'pre_get_posts', 'chinacongress_sort_search_by_date' );

/**
 * 4. 替换 Customizer 控件 JS 脚本，解除父主题限制，允许设置最多 50 个轮播图/推荐项
 */
function avril_child_customizer_control_scripts() {
    wp_dequeue_script( 'avril_customizer-repeater-script' );
    $repeater_js_file = get_stylesheet_directory() . '/js/customizer_repeater.js';
    $repeater_js_ver  = file_exists( $repeater_js_file ) ? filemtime( $repeater_js_file ) : wp_get_theme()->get( 'Version' );
    wp_enqueue_script(
        'avril-child-customizer-repeater-script',
        get_stylesheet_directory_uri() . '/js/customizer_repeater.js',
        array( 'jquery', 'jquery-ui-draggable', 'wp-color-picker' ),
        $repeater_js_ver,
        true
    );
}
add_action( 'customize_controls_enqueue_scripts', 'avril_child_customizer_control_scripts', 99 );

/**
 * 5. 在后台 Customizer (外观 - 自定义) 的 CTA 板块注册“大陆院选民登记人数”独立设置项
 *
 * @param WP_Customize_Manager $wp_customize 自定义管理器对象
 */
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
add_action( 'customize_register', 'avril_child_customize_register' );

// ==============================================================================
// 核心 API 自动化数据同步与 WP-Cron 后台任务机制
// ==============================================================================

/**
 * 注册自定义 5 分钟 (300 秒) WP-Cron 定时任务时间间隔
 *
 * @param array $schedules 已存在的 Cron 时间间隔数组
 * @return array 增加 5 分钟间隔后的数组
 */
function chinacongress_add_five_minute_cron_interval( $schedules ) {
	$schedules['every_five_minutes'] = array(
		'interval' => 300,
		'display'  => __( 'Every 5 Minutes', 'avril-child' ),
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'chinacongress_add_five_minute_cron_interval' );

/**
 * 自动从远程 API (registration_count.json) 同步大陆院选民登记总人数并写入 wp_options 数据库
 */
function chinacongress_sync_mainland_voter_count() {
	$response = wp_remote_get( 'https://reg.congresscenter.org/api/public/registration_count.json', array(
		'timeout'   => 5,
		'sslverify' => false,
	) );

	if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( is_array( $data ) && isset( $data['total'] ) && is_numeric( $data['total'] ) ) {
			$total = (int) $data['total'];
			if ( $total > 0 ) {
				set_theme_mod( 'mainland_voter_count', (string) $total );
			}
		}
	}
}

/**
 * 从远程 API (latest_members.json) 获取大陆院最新登记选民列表 (返回前 5 位选民，带 300 秒 Transient 缓存)
 *
 * @param bool $force 是否强制忽略缓存向远程 API 发起全新请求
 * @return array 包含选民省份与 display_name 的数组
 */
function chinacongress_get_latest_mainland_members( $force = false ) {
	$members = false;
	if ( ! $force ) {
		$members = get_transient( 'chinacongress_latest_mainland_members' );
	}

	if ( false === $members ) {
		$response = wp_remote_get( 'https://reg.congresscenter.org/api/public/latest_members.json', array(
			'timeout'   => 5,
			'sslverify' => false,
		) );

		$members = array();
		if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );
			if ( is_array( $data ) && isset( $data['members'] ) && is_array( $data['members'] ) ) {
				$members = array_slice( $data['members'], 0, 5 );
			}
		}

		// 远程 API 离线时的默认安全兜底数据
		if ( empty( $members ) ) {
			$members = array(
				array( 'province' => '江蘇', 'display_name' => '***7E6' ),
				array( 'province' => '廣東', 'display_name' => '***X9K' ),
				array( 'province' => '北京', 'display_name' => '***JT4' ),
				array( 'province' => '北京', 'display_name' => '***JWP' ),
				array( 'province' => '湖南', 'display_name' => '***FRQ' ),
			);
		}

		set_transient( 'chinacongress_latest_mainland_members', $members, 300 );
	}
	return $members;
}

/**
 * 从远程 API (https://api.fdcusa.org/?token=8d9f3b7c2e6a) 自动同步海外院选民登记总人数与最新选民列表
 *
 * @param bool $force 是否强制忽略 Transient 缓存向远程 API 发起全新请求
 * @return array 包含选民居住地 (residence) 与 姓名 (name) 的数组
 */
function chinacongress_sync_overseas_voter_data( $force = false ) {
	$members = false;
	if ( ! $force ) {
		$members = get_transient( 'chinacongress_latest_overseas_members' );
	}

	if ( false === $members ) {
		$response = wp_remote_get( 'https://api.fdcusa.org/?token=8d9f3b7c2e6a', array(
			'timeout'   => 5,
			'sslverify' => false,
		) );

		$members = array();
		if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( is_array( $data ) && ! empty( $data['success'] ) ) {
				// 1. 同步选民总人数并写入 wp_options 数据库 (theme_mod: cta_description)
				if ( isset( $data['total'] ) && is_numeric( $data['total'] ) ) {
					$total = (int) $data['total'];
					if ( $total > 0 ) {
						set_theme_mod( 'cta_description', (string) $total );
					}
				}

				// 2. 提取前 5 位选民作为走马灯数据
				if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
					$raw_list = array_slice( $data['data'], 0, 5 );
					foreach ( $raw_list as $item ) {
						$residence = ! empty( $item['residence'] ) ? trim( $item['residence'] ) : '海外';
						$name      = ! empty( $item['name'] ) ? trim( $item['name'] ) : '***';
						$members[] = array(
							'residence' => $residence,
							'name'      => $name,
						);
					}
				}
			}
		}

		// API 离线时的默认安全兜底数据
		if ( empty( $members ) ) {
			$members = array(
				array( 'residence' => '德国', 'name' => 'Z**' ),
				array( 'residence' => '德国', 'name' => 'L**' ),
				array( 'residence' => '其他', 'name' => '蒋**' ),
			);
		}

		set_transient( 'chinacongress_latest_overseas_members', $members, 300 );
	}
	return $members;
}

/**
 * 调度挂载 5 分钟 WP-Cron 后台异步任务事件
 */
function chinacongress_schedule_cron_sync() {
	if ( ! wp_next_scheduled( 'chinacongress_cron_sync_api_data_event' ) ) {
		wp_schedule_event( time(), 'every_five_minutes', 'chinacongress_cron_sync_api_data_event' );
	}
}
add_action( 'init', 'chinacongress_schedule_cron_sync' );

/**
 * WP-Cron 定时任务执行体：静默同步选民总数与强刷最新选民列表 (包含大陆院与海外院)
 */
function chinacongress_execute_cron_sync() {
	chinacongress_sync_mainland_voter_count();
	chinacongress_get_latest_mainland_members( true );
	chinacongress_sync_overseas_voter_data( true );
}
add_action( 'chinacongress_cron_sync_api_data_event', 'chinacongress_execute_cron_sync' );

// Dual Voter Registration Boxes (CTA Section) editable via Customizer
function avril_lite_cta() {
	$avril_hs_cta            = get_theme_mod('hs_cta','1');	
	$avril_cta_title         = get_theme_mod('cta_title', __('海外院选民注册人数： ', 'clever-fox'));
	if ( false !== strpos( $avril_cta_title, '选民登记人数' ) ) {
		$avril_cta_title = str_replace( '选民登记人数', '选民注册人数', $avril_cta_title );
		set_theme_mod( 'cta_title', $avril_cta_title );
	}

	// 优先同步/拉取最新海外选民数据（如果 Transient 缓存更新则可获取最新数据）
	$latest_overseas_members = chinacongress_sync_overseas_voter_data();

	$renshu_overseas_val     = get_theme_mod('cta_description', '425');
	$renshu_overseas         = is_numeric(trim($renshu_overseas_val)) ? (int)trim($renshu_overseas_val) : 425;

	$renshu_mainland_val     = get_theme_mod('mainland_voter_count', '180');
	$renshu_mainland         = is_numeric(trim($renshu_mainland_val)) ? (int)trim($renshu_mainland_val) : 180;

	$avril_cta_btn_lbl1      = get_theme_mod('cta_btn_lbl1', __('选民登记', 'clever-fox'));
	$avril_cta_btn_link1     = get_theme_mod('cta_btn_link1', 'https://reg.chinacongress.net/');

	if($avril_hs_cta == '1') {	
	?>	
	 <!-- 1. 海外院选民注册框 -->
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

						<?php
						if ( ! empty( $latest_overseas_members ) ) :
						?>
						<!-- 海外院最新注册选民 滚动走马灯 (样式完全匹配左侧 h4 标题，自适应响应式) -->
						<div class="cta-content overseas-members-container">
							<h4 style="margin: 0; display: flex; align-items: center; gap: 8px;">
								<span style="white-space: nowrap;">最新注册选民：</span>
								<span class="overseas-members-ticker" id="overseas_members_ticker" style="display: inline-block; min-width: 140px; height: 36px; overflow: hidden; position: relative; vertical-align: middle;">
									<ul class="overseas-members-list" style="list-style: none; margin: 0; padding: 0; position: absolute; top: 0; left: 0; width: 100%; transition: top 0.4s ease-in-out, opacity 0.3s ease, transform 0.3s ease;">
										<?php foreach ( $latest_overseas_members as $member ) : ?>
											<li style="height: 36px; line-height: 36px; font-size: inherit; font-weight: inherit; color: inherit; white-space: nowrap;">
												<span><?php echo esc_html( $member['residence'] ); ?></span>
												<span style="margin-left: 6px;"><?php echo esc_html( $member['name'] ); ?></span>
											</li>
										<?php endforeach; ?>
									</ul>
								</span>
							</h4>
						</div>
						<?php endif; ?>

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

	 <!-- 2. 大陆院选民注册框 -->
	 <section id="cta-section-mainland" class="cta-section cta-shadow-one av-mb-default home-cta">
        <div class="av-container">
            <div class="av-columns-area">
                <div class="av-column-12">
                    <div class="cta-wrapper">
                        <div class="cta-content">
							<h4>大陆院选民注册人数： 
								<span id="number_mainland"><?php echo esc_html($renshu_mainland); ?></span>
							</h4>
                        </div>

						<?php
						$latest_members = chinacongress_get_latest_mainland_members();
						if ( ! empty( $latest_members ) ) :
						?>
						<!-- 最新注册选民 滚动走马灯 (样式完全匹配左侧 h4 标题，自适应响应式) -->
						<div class="cta-content mainland-members-container">
							<h4 style="margin: 0; display: flex; align-items: center; gap: 8px;">
								<span style="white-space: nowrap;">最新注册选民：</span>
								<span class="mainland-members-ticker" id="mainland_members_ticker" style="display: inline-block; min-width: 140px; height: 36px; overflow: hidden; position: relative; vertical-align: middle;">
									<ul class="mainland-members-list" style="list-style: none; margin: 0; padding: 0; position: absolute; top: 0; left: 0; width: 100%; transition: top 0.4s ease-in-out, opacity 0.3s ease, transform 0.3s ease;">
										<?php foreach ( $latest_members as $member ) : ?>
											<li style="height: 36px; line-height: 36px; font-size: inherit; font-weight: inherit; color: inherit; white-space: nowrap;">
												<span><?php echo esc_html( $member['province'] ); ?></span>
												<span style="margin-left: 6px;"><?php echo esc_html( $member['display_name'] ); ?></span>
											</li>
										<?php endforeach; ?>
									</ul>
								</span>
							</h4>
						</div>
						<?php endif; ?>

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

		function runObservers() {
			setupObserver("number_overseas", <?php echo $renshu_overseas; ?>);
			setupObserver("number_mainland", <?php echo $renshu_mainland; ?>);
		}
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', runObservers);
		} else {
			runObservers();
		}

		// 选民平滑渐变（Fade & Slide）无缝轮播通用初始化函数
		function initTicker(tickerId, listClass) {
			let ticker = document.getElementById(tickerId);
			if (!ticker) return;
			let list = ticker.querySelector('.' + listClass);
			if (!list) return;
			let items = list.querySelectorAll('li');
			if (items.length <= 1) return;

			let currentIndex = 0;
			let isHovered = false;

			ticker.addEventListener('mouseenter', function() { isHovered = true; });
			ticker.addEventListener('mouseleave', function() { isHovered = false; });

			setInterval(function() {
				if (isHovered) return;

				list.style.opacity = '0';
				list.style.transform = 'translateY(-3px)';

				setTimeout(function() {
					currentIndex = (currentIndex + 1) % items.length;
					let itemHeight = items[0].offsetHeight || 36;
					list.style.top = -(currentIndex * itemHeight) + 'px';
					list.style.transform = 'translateY(3px)';

					setTimeout(function() {
						list.style.opacity = '1';
						list.style.transform = 'translateY(0)';
					}, 50);
				}, 250);
			}, 3500);
		}

		initTicker('mainland_members_ticker', 'mainland-members-list');
		initTicker('overseas_members_ticker', 'overseas-members-list');
	})();
	</script>
	<?php	
	}
}

// ==============================================================================
// 动态智能提取文章第一张图 / 嵌入视频封面 / 规则兜底 & 全网社交分享 OG 卡片自动输出
// ==============================================================================

// 封装统一的纯文本摘要提取工具函数 (剥离 HTML 标签、多余换行缩紧、中文截断)
function chinacongress_get_clean_excerpt( $length = 140, $post_id = null ) {
    $post = get_post( $post_id );
    if ( ! $post || empty( $post->post_content ) ) {
        return '';
    }
    $raw_content = wp_strip_all_tags( $post->post_content );
    $clean_text  = preg_replace( '/\s+/', ' ', $raw_content );
    return mb_strimwidth( trim( $clean_text ), 0, $length, '...' );
}

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
    return '/wp-content/uploads/2026/03/logo-e1768719012316-1536x413-1-150x150.jpg';
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

// 提升首页轮播图播放速度 50% (播放间隔从 9 秒缩短至 4.5 秒，平滑过渡缩短至 0.5 秒)
function chinacongress_slider_speed_boost() {
    if ( is_front_page() || is_home() ) {
        ?>
        <script id="chinacongress-slider-speed">
        jQuery(document).ready(function($) {
            setTimeout(function() {
                var $slider = $('.main-slider');
                if ($slider.length && typeof $slider.owlCarousel === 'function') {
                    $slider.trigger('destroy.owl.carousel');
                    $slider.owlCarousel({
                        rtl: $("html").attr("dir") == 'rtl' ? true : false,
                        items: 1,
                        loop: true,
                        dots: true,
                        nav: true,
                        navText: ['<i class="fa fa-arrow-left"></i>', '<i class="fa fa-arrow-right"></i>'],
                        autoHeight: $("body").hasClass("aera-theme") || $("body").hasClass("avail-theme")|| $("body").hasClass("evion-theme") ? true : false,
                        autoplay: true,
                        autoplayTimeout: 4500,
                        animateIn: $("body").hasClass("aera-theme") ? false : 'pulse',
                        animateOut: $("body").hasClass("aera-theme") ? false : 'fadeOut',
                        smartSpeed: 500
                    });
                }
            }, 300);
        });
        </script>
        <?php
    }
}
add_action( 'wp_footer', 'chinacongress_slider_speed_boost', 99 );

// ==============================================================================
// 自动全站正文路径相对化：入库自动清洗 & 前台动态显示双重保险
// ==============================================================================

// 1. 全站正文路径相对化：保存入库与前台展示统一通过单一定义函数清洗本站绝对域名
function chinacongress_make_content_relative( $content ) {
    if ( empty( $content ) ) {
        return $content;
    }
    return preg_replace( '#https?://(www\.)?chinacongress\.net/#i', '/', $content );
}
add_filter( 'content_save_pre', 'chinacongress_make_content_relative', 99 );
add_filter( 'the_content', 'chinacongress_make_content_relative', 99 );


// ==============================================================================
// 兼容性修补、Customizer 配置继承与 Hook 替代逻辑
// ==============================================================================

// 1. 极重要修补：修复 Clever Fox 插件因判断 $theme->name === 'Avril' 导致子主题 (Avril Child) 下轮播图与核心组件丢失的 Bug
function chinacongress_ensure_cleverfox_avril_loaded() {
    if ( defined( 'CLEVERFOX_PLUGIN_DIR' ) ) {
        if ( ! function_exists( 'cleverfox_avril_frontpage_sections' ) ) {
            $avril_file = CLEVERFOX_PLUGIN_DIR . 'inc/avril/avril.php';
            if ( file_exists( $avril_file ) ) {
                require_once $avril_file;
            }
        }
    }
}
add_action( 'plugins_loaded', 'chinacongress_ensure_cleverfox_avril_loaded', 1 );

// 2. Customizer 配置继承保底：解决 Clever Fox 升级后校验子主题配置导致轮播图/组件丢失的问题
function chinacongress_theme_mods_fallback( $mods ) {
    $parent_mods = get_option( 'theme_mods_avril' );
    if ( is_array( $parent_mods ) ) {
        if ( ! is_array( $mods ) ) {
            $mods = array();
        }
        foreach ( $parent_mods as $key => $val ) {
            if ( ! isset( $mods[ $key ] ) || empty( $mods[ $key ] ) ) {
                $mods[ $key ] = $val;
            }
        }
        $mods['hs_slider']  = '1';
        $mods['hs_feature'] = '1';
        $mods['hs_service'] = '1';
        $mods['hs_blog']    = '1';
    }
    return $mods;
}
add_filter( 'option_theme_mods_avril-child', 'chinacongress_theme_mods_fallback', 99 );

// 3. 重写顶栏 (Above Header) 逻辑：在子主题中强行将“法律顾问 / Counsel”绑定跳转至创世律师事务所 (https://chuangshilaw.com/)
function chinacongress_above_header_override() {
    remove_action( 'avril_above_header', 'avril_above_header' );
    add_action( 'avril_above_header', 'chinacongress_above_header_custom' );
}
add_action( 'init', 'chinacongress_above_header_override' );

function chinacongress_above_header_custom() {
    $avril_hide_show_social_icon = get_theme_mod( 'hide_show_social_icon', '1' ); 
    $avril_social_icons          = get_theme_mod( 'social_icons', function_exists( 'avril_get_social_icon_default' ) ? avril_get_social_icon_default() : '' );
    ?>
    <!--===// Start: Header Above ===-->
    <div id="above-header" class="header-above-info d-av-block d-none wow fadeInDown">
        <div class="header-widget">
            <div class="av-container">
                <div class="av-columns-area">
                    <div class="av-column-5">
                        <div class="widget-left text-av-left text-center">
                            <?php if ( $avril_hide_show_social_icon == '1' ) : ?>
                                <aside class="widget widget_social_widget">
                                    <ul>
                                        <?php
                                        $avril_social_icons = json_decode( $avril_social_icons );
                                        if ( ! empty( $avril_social_icons ) && is_array( $avril_social_icons ) ) {
                                            foreach ( $avril_social_icons as $avril_social_item ) {    
                                                $avril_repeater_social_icon = ! empty( $avril_social_item->icon_value ) ? apply_filters( 'avril_translate_single_string', $avril_social_item->icon_value, 'Header section' ) : ''; 
                                                $avril_repeater_social_link = ! empty( $avril_social_item->link ) ? apply_filters( 'avril_translate_single_string', $avril_social_item->link, 'Header section' ) : '';
                                                ?>
                                                <li><a href="<?php echo esc_url( $avril_repeater_social_link ); ?>"><i class="fa <?php echo esc_attr( $avril_repeater_social_icon ); ?>"></i></a></li>
                                            <?php }
                                        } ?>
                                    </ul>
                                </aside>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="av-column-7">
                        <div class="widget-right text-av-right text-center"> 
                            <?php 
                            $avril_hide_show_cntct_details = get_theme_mod( 'hide_show_cntct_details', '1' ); 
                            $avril_tlh_contct_icon         = get_theme_mod( 'tlh_contct_icon', 'fa-book' );   
                            $avril_tlh_contact_title       = get_theme_mod( 'tlh_contact_title', '法律顾问' ); 
                            $avril_tlh_contact_sbtitle     = get_theme_mod( 'tlh_contact_sbtitle', 'Counsel' ); 
                            ?>
                            <?php if ( $avril_hide_show_cntct_details == '1' ) : ?>
                                <aside class="widget widget-contact wgt-1">
                                    <div class="contact-area">
                                        <div class="contact-icon">
                                           <i class="fa <?php echo esc_attr( $avril_tlh_contct_icon ); ?>"></i>
                                        </div>
                                        <a href="https://chuangshilaw.com/" target="_blank" class="contact-info">
                                            <span class="text"><?php echo esc_html( $avril_tlh_contact_title ); ?></span>
                                            <span class="title"><?php echo esc_html( $avril_tlh_contact_sbtitle ); ?></span>
                                        </a>
                                    </div>
                                </aside>
                            <?php endif; ?>
                            <?php 
                            $avril_hide_show_email_details = get_theme_mod( 'hide_show_email_details', '1' );
                            $avril_tlh_email_icon          = get_theme_mod( 'tlh_email_icon', 'fa-envelope-o' );   
                            $avril_tlh_email_title         = get_theme_mod( 'tlh_email_title', __( 'Email Us', 'clever-fox' ) ); 
                            $avril_tlh_email_sbtitle       = get_theme_mod( 'tlh_email_sbtitle', 'info@chinacongress.net' ); 
                            ?>  
                            <?php if ( $avril_hide_show_email_details == '1' ) : ?>
                                <aside class="widget widget-contact wgt-2">
                                    <div class="contact-area">
                                        <div class="contact-icon">
                                            <i class="fa <?php echo esc_attr( $avril_tlh_email_icon ); ?>"></i>
                                        </div>
                                        <a href="mailto:<?php echo esc_html( $avril_tlh_email_sbtitle ); ?>" class="contact-info">
                                            <span class="text"><?php echo esc_html( $avril_tlh_email_title ); ?></span>
                                            <span class="title"><?php echo esc_html( $avril_tlh_email_sbtitle ); ?></span>
                                        </a>
                                    </div>
                                </aside>
                            <?php endif; ?> 
                            <?php 
                            $avril_hide_show_mbl_details = get_theme_mod( 'hide_show_mbl_details', '1' );   
                            $avril_tlh_mobile_icon       = get_theme_mod( 'tlh_mobile_icon', 'fa-usd' );
                            $avril_tlh_mobile_title      = get_theme_mod( 'tlh_mobile_title', 'Zelle 捐助' ); 
                            $avril_tlh_mobile_sbtitle    = get_theme_mod( 'tlh_mobile_sbtitle', 'chinacongress' ); 
                            ?>
                            <?php if ( $avril_hide_show_mbl_details == '1' ) : ?>
                                <aside class="widget widget-contact wgt-3">
                                    <div class="contact-area">
                                        <div class="contact-icon">
                                            <i class="fa <?php echo esc_attr( $avril_tlh_mobile_icon ); ?>"></i>
                                        </div>
                                        <a href="javascript:void(0)" class="contact-info">
                                            <span class="text"><?php echo esc_html( $avril_tlh_mobile_title ); ?></span>
                                            <span class="title"><?php echo esc_html( $avril_tlh_mobile_sbtitle ); ?></span>
                                        </a>
                                    </div>
                                </aside>
                            <?php endif; ?> 
                        </div>  
                    </div>
                </div>
            </div>
        </div>
    </div>  
    <!--===// End: Header Top ===-->
    <?php
}


/**
 * 修正「推荐内容」版位在 Customizer 儲存時遺失連結與封面圖的問題。（2026-07-31）
 *
 * 根因：clever-fox 註冊 features_contents 控制項時只開了 icon / title / text 三個旗標，
 * link 與 image 兩個旗標為 false，控制項不 render 對應的輸入框（實測渲染 HTML：
 * customizer-repeater-link-control 出現 0 次、custom-media-url 出現 0 次）。
 * 但 customizer_repeater.js:149,154 仍無條件讀取這兩個欄位，取到 undefined，
 * 而 JSON.stringify 會直接省略值為 undefined 的屬性 —— 於是只要有人在 Customizer
 * 存一次「推荐内容」，六張卡的 link 與 image_url 就全部從 theme_mods 消失，
 * 首頁卡片連結一律變成 #、縮圖全部掉到 logo 兜底。
 *
 * 修法：在 clever-fox 註冊之後（priority 100）移除該控制項並以相同 setting
 * 重新註冊，補開 link 與 image 兩個旗標。不動外掛與父主題檔案。
 */
function chinacongress_fix_features_repeater_controls( $wp_customize ) {

	if ( ! class_exists( 'AVRIL_Repeater' ) ) {
		return;
	}

	// 控制項必須已由 clever-fox 註冊過，否則不介入。
	if ( ! $wp_customize->get_control( 'features_contents' ) ) {
		return;
	}

	$wp_customize->remove_control( 'features_contents' );

	$wp_customize->add_control(
		new AVRIL_Repeater(
			$wp_customize,
			'features_contents',
			array(
				'label'                             => esc_html__( 'Features', 'clever-fox' ),
				'section'                           => 'feature_setting',
				'add_field_label'                   => esc_html__( 'Add New Feature', 'clever-fox' ),
				'item_name'                         => esc_html__( 'Feature', 'clever-fox' ),
				'customizer_repeater_icon_control'  => true,
				'customizer_repeater_title_control' => true,
				'customizer_repeater_text_control'  => true,
				'customizer_repeater_link_control'  => true,
				'customizer_repeater_image_control' => true,
			)
		)
	);
}
add_action( 'customize_register', 'chinacongress_fix_features_repeater_controls', 100 );

// 允許 SVG 上傳（僅限brook）
add_filter( 'upload_mimes', function ( $mimes ) {
    if ( get_current_user_id() === 12 ) {   // 換成實際 ID
        $mimes['svg'] = 'image/svg+xml';
    }
    return $mimes;
} );

// 修正 finfo 真實 MIME 比對（SVG 是純文字，finfo 常回報 text/plain）
add_filter( 'wp_check_filetype_and_ext', function ( $data, $file, $filename ) {
    if ( ! empty( $data['ext'] ) && ! empty( $data['type'] ) ) {
        return $data;
    }
    if ( 'svg' === strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) ) {
        $data['ext']  = 'svg';
        $data['type'] = 'image/svg+xml';
    }
    return $data;
}, 10, 3 );

// 媒體庫縮圖顯示
add_filter( 'wp_prepare_attachment_for_js', function ( $response, $attachment ) {
    if ( 'image/svg+xml' === $response['mime'] ) {
        $response['sizes'] = [
            'full' => [
                'url'         => $response['url'],
                'width'       => 150,
                'height'      => 150,
                'orientation' => 'portrait',
            ],
        ];
    }
    return $response;
}, 10, 2 );

/**
 * 允许文章 (Post) 开启原生“排序 (menu_order)”字段支持，并在后台快速编辑、编辑页及文章列表中提供直观设置
 */
// 1. 允许文章 (Post) 启用原生页面属性支持（开启文章编辑页右侧栏“排序”数值框与快速编辑原生的排序框）
add_action( 'init', function() {
	add_post_type_support( 'post', 'page-attributes' );
} );

// 2. 在文章后台列表中增加“排序号”列显示与排序支持
add_filter( 'manage_posts_columns', function( $columns ) {
	$columns['menu_order'] = '排序号';
	return $columns;
} );
add_action( 'manage_posts_custom_column', function( $column, $post_id ) {
	if ( 'menu_order' === $column ) {
		echo (int) get_post_field( 'menu_order', $post_id );
	}
}, 10, 2 );
add_filter( 'manage_edit-post_sortable_columns', function( $columns ) {
	$columns['menu_order'] = 'menu_order';
	return $columns;
} );

// 3. 在后台“快速编辑”的原生“排序”框旁注入说明提示文字 (纯 CSS 方案)
add_action( 'admin_head-edit.php', function() {
	global $current_screen;
	if ( $current_screen && 'post' === $current_screen->post_type ) {
		?>
		<style>
		.inline-edit-row label:has(input[name="menu_order"])::after {
			content: "（数字越小越靠前，支持负数如 -1，默认：0）";
			color: #666;
			font-size: 12px;
			font-weight: normal;
			margin-left: 8px;
			display: inline-block;
			vertical-align: middle;
		}
		</style>
		<?php
	}
} );

/**
 * 允许分类列表页 (Category Archive) 支持文章置顶 (Sticky Posts) 与自定义排序号 (menu_order)
 * 排序优先级：置顶文章优先 -> 排序号小到大 (ASC) -> 发布时间倒序 (DESC)
 *
 * @param string   $orderby 原始 SQL orderby 子句
 * @param WP_Query $query   当前 WP_Query 实例
 * @return string 修改后的 orderby 子句
 */
function chinacongress_sort_category_sticky_posts_first( $orderby, $query ) {
	if ( ! is_admin() && $query->is_main_query() && $query->is_category() ) {
		global $wpdb;
		$sticky = get_option( 'sticky_posts' );
		if ( ! empty( $sticky ) && is_array( $sticky ) ) {
			$sticky_ids = implode( ',', array_map( 'absint', $sticky ) );
			return "CASE WHEN {$wpdb->posts}.ID IN ($sticky_ids) THEN 0 ELSE 1 END, {$wpdb->posts}.menu_order ASC, " . $orderby;
		} else {
			return "{$wpdb->posts}.menu_order ASC, " . $orderby;
		}
	}
	return $orderby;
}
add_filter( 'posts_orderby', 'chinacongress_sort_category_sticky_posts_first', 10, 2 );

