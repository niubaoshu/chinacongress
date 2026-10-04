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

// 全站通用默认兜底封面图片路径
if ( ! defined( 'CHINACONGRESS_FALLBACK_IMAGE' ) ) {
	define( 'CHINACONGRESS_FALLBACK_IMAGE', '/wp-content/uploads/2026/01/logo-1024x480.jpg' );
}

// ==============================================================================
// 1. 样式与脚本加载
// ==============================================================================

/**
 * 1. 加载父主题样式、子主题样式及 FontAwesome 4.6.3 本地矢量图标兜底库
 */
add_action( 'wp_enqueue_scripts', 'avril_child_enqueue_styles', 20 );
function avril_child_enqueue_styles() {
    // 1. 加载父主题主样式表 (确保排在子主题样式之前)
    wp_enqueue_style( 'avril-parent-style', get_template_directory_uri() . '/style.css' );

    // 2. 将父主题已自动注册的子主题 style.css ('avril-style') 关联依赖并赋予动态时间戳版本号，彻底消除重复发起的第二次 HTTP 请求
    $child_css_file = get_stylesheet_directory() . '/style.css';
    $child_css_ver  = file_exists( $child_css_file ) ? filemtime( $child_css_file ) : wp_get_theme()->get( 'Version' );
    if ( isset( wp_styles()->registered['avril-style'] ) ) {
        if ( ! in_array( 'avril-parent-style', wp_styles()->registered['avril-style']->deps, true ) ) {
            wp_styles()->registered['avril-style']->deps[] = 'avril-parent-style';
        }
        wp_styles()->registered['avril-style']->ver = $child_css_ver;
    }

    // 3. 加载子主题自带的永久 FontAwesome 4.6.3 字体图标库（解决 CDN 丢失问题）
    wp_enqueue_style( 'avril-child-fontawesome', get_stylesheet_directory_uri() . '/assets/css/fonts/font-awesome/css/font-awesome.min.css', array(), '4.6.3' );
}

// 彻底移除父主题加载的外部 Google 字体 (Poppins)
add_action( 'init', function() {
    remove_action( 'wp_enqueue_scripts', 'avril_scripts_styles' );
} );


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
 * 远程 JSON 请求通用辅助函数
 */
function chinacongress_fetch_json( $url ) {
	$response = wp_remote_get( $url, array( 'timeout' => 5, 'sslverify' => true ) );
	if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
		return json_decode( wp_remote_retrieve_body( $response ), true );
	}
	return null;
}

/**
 * 自动从远程 API 同步大陆院选民登记总人数
 */
function chinacongress_sync_mainland_voter_count() {
	$data = chinacongress_fetch_json( 'https://reg.congresscenter.org/api/public/registration_count.json' );
	if ( is_array( $data ) && ! empty( $data['total'] ) && is_numeric( $data['total'] ) ) {
		set_theme_mod( 'mainland_voter_count', (string) (int) $data['total'] );
	}
}

/**
 * 从远程 API 获取大陆院最新登记选民列表 (返回前 5 位选民，带 1 天 Transient 缓存)
 */
function chinacongress_get_latest_mainland_members( $force = false ) {
	$members = $force ? false : get_transient( 'chinacongress_latest_mainland_members' );
	if ( false === $members ) {
		$data    = chinacongress_fetch_json( 'https://reg.congresscenter.org/api/public/latest_members.json' );
		$members = ( is_array( $data ) && ! empty( $data['members'] ) && is_array( $data['members'] ) )
			? array_slice( $data['members'], 0, 5 )
			: array();
		if ( ! empty( $members ) ) {
			set_transient( 'chinacongress_latest_mainland_members', $members, DAY_IN_SECONDS );
		}
	}
	return is_array( $members ) ? $members : array();
}

/**
 * 从远程 API 自动同步海外院选民登记总人数与最新选民列表 (供 WP-Cron 执行)
 */
function chinacongress_sync_overseas_voter_data() {
	$data = chinacongress_fetch_json( 'https://api.fdcusa.org/index.php?token=8d9f3b7c2e6a' );
	if ( is_array( $data ) && ! empty( $data['success'] ) ) {
		if ( ! empty( $data['total'] ) && is_numeric( $data['total'] ) ) {
			set_theme_mod( 'cta_description', (string) (int) $data['total'] );
		}
		if ( ! empty( $data['data'] ) && is_array( $data['data'] ) ) {
			$members = array_map( function( $item ) {
				return array(
					'residence' => ! empty( $item['residence'] ) ? trim( $item['residence'] ) : '海外',
					'name'      => ! empty( $item['name'] ) ? trim( $item['name'] ) : '***',
				);
			}, array_slice( $data['data'], 0, 5 ) );
			if ( ! empty( $members ) ) {
				set_transient( 'chinacongress_latest_overseas_members', $members, 300 );
			}
		}
	}
}

/**
 * 获取海外院最新注册选民列表 (前台只读，从 Transient 缓存或兜底获取)
 */
function chinacongress_get_latest_overseas_members() {
	$members = get_transient( 'chinacongress_latest_overseas_members' );
	return ( ! empty( $members ) && is_array( $members ) ) ? $members : array();
}

/**
 * 挂载 WP-Cron 定时任务
 */
function chinacongress_schedule_cron_sync() {
	if ( ! wp_next_scheduled( 'chinacongress_cron_sync_api_data_event' ) ) {
		wp_schedule_event( time(), 'daily', 'chinacongress_cron_sync_api_data_event' );
	}
	if ( ! wp_next_scheduled( 'chinacongress_cron_sync_overseas_event' ) ) {
		wp_schedule_event( time(), 'every_five_minutes', 'chinacongress_cron_sync_overseas_event' );
	}
}
add_action( 'init', 'chinacongress_schedule_cron_sync' );

function chinacongress_execute_cron_sync() {
	chinacongress_sync_mainland_voter_count();
	chinacongress_get_latest_mainland_members( true );
}
add_action( 'chinacongress_cron_sync_api_data_event', 'chinacongress_execute_cron_sync' );
add_action( 'chinacongress_cron_sync_overseas_event', 'chinacongress_sync_overseas_voter_data' );

/**
 * 双选民注册卡片板块 (CTA Section)
 */
function avril_lite_cta() {
	if ( get_theme_mod( 'hs_cta', '1' ) != '1' ) {
		return;
	}

	$overseas_title = str_replace( '选民登记人数', '选民注册人数', get_theme_mod( 'cta_title', __( '海外院选民注册人数： ', 'clever-fox' ) ) );
	$overseas_count = (int) trim( get_theme_mod( 'cta_description', '425' ) );
	$mainland_count = (int) trim( get_theme_mod( 'mainland_voter_count', '180' ) );

	$cta_boxes = array(
		array(
			'id'          => 'overseas',
			'title'       => $overseas_title,
			'count'       => $overseas_count,
			'btn_lbl'     => get_theme_mod( 'cta_btn_lbl1', __( '选民登记', 'clever-fox' ) ),
			'btn_link'    => get_theme_mod( 'cta_btn_link1', 'https://reg.chinacongress.net/' ),
			'ticker_lbl'  => '最新注册选民：',
			'members'     => chinacongress_get_latest_overseas_members(),
			'loc_key'     => 'residence',
			'name_key'    => 'name',
		),
		array(
			'id'          => 'mainland',
			'title'       => '大陆院选民注册人数： ',
			'count'       => $mainland_count,
			'btn_lbl'     => '选民登记',
			'btn_link'    => 'https://reg.congresscenter.org/',
			'ticker_lbl'  => '近期新增：',
			'members'     => chinacongress_get_latest_mainland_members(),
			'loc_key'     => 'province',
			'name_key'    => 'display_name',
		),
	);

	foreach ( $cta_boxes as $box ) :
	?>
	<section id="cta-section-<?php echo esc_attr( $box['id'] ); ?>" class="cta-section cta-shadow-one av-mb-default home-cta">
		<div class="av-container">
			<div class="av-columns-area">
				<div class="av-column-12">
					<div class="cta-wrapper">
						<div class="cta-content">
							<h4><?php echo wp_kses_post( $box['title'] ); ?><span id="number_<?php echo esc_attr( $box['id'] ); ?>"><?php echo esc_html( $box['count'] ); ?></span></h4>
						</div>

						<?php if ( ! empty( $box['members'] ) ) : ?>
						<div class="cta-content <?php echo esc_attr( $box['id'] ); ?>-members-container">
							<h4 style="margin: 0; display: flex; align-items: center; gap: 8px;">
								<span style="white-space: nowrap;"><?php echo esc_html( $box['ticker_lbl'] ); ?></span>
								<span class="<?php echo esc_attr( $box['id'] ); ?>-members-ticker" id="<?php echo esc_attr( $box['id'] ); ?>_members_ticker" style="display: inline-block; min-width: 140px; height: 36px; overflow: hidden; position: relative; vertical-align: middle;">
									<ul class="<?php echo esc_attr( $box['id'] ); ?>-members-list" style="list-style: none; margin: 0; padding: 0; position: absolute; top: 0; left: 0; width: 100%; transition: top 0.4s ease-in-out, opacity 0.3s ease, transform 0.3s ease;">
										<?php foreach ( $box['members'] as $m ) : ?>
											<li style="height: 36px; line-height: 36px; font-size: inherit; font-weight: inherit; color: inherit; white-space: nowrap;">
												<span><?php echo esc_html( $m[ $box['loc_key'] ] ); ?></span>
												<span style="margin-left: 6px;"><?php echo esc_html( $m[ $box['name_key'] ] ); ?></span>
											</li>
										<?php endforeach; ?>
									</ul>
								</span>
							</h4>
						</div>
						<?php endif; ?>

						<div class="cta-btn-wrap text-av-right text-center">
							<a href="<?php echo esc_url( $box['btn_link'] ); ?>" class="av-btn av-btn-primary" target="_blank"><?php echo esc_html( $box['btn_lbl'] ); ?></a>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>
	<?php endforeach; ?>

	<script>
	(function() {
		function animate(el, end) {
			let start = 100, t0 = null, dur = 1000;
			function step(t) {
				if (!t0) t0 = t;
				let p = Math.min((t - t0) / dur, 1);
				el.innerText = Math.floor(start + (end - start) * p);
				if (p < 1) requestAnimationFrame(step);
			}
			requestAnimationFrame(step);
		}
		function observe(id, val) {
			let el = document.getElementById(id);
			if (!el) return;
			let io = new IntersectionObserver((entries, obs) => {
				if (entries[0].isIntersecting) {
					obs.disconnect();
					animate(el, val);
				}
			}, { threshold: 0.5 });
			io.observe(el);
		}
		function initTicker(tickerId, listClass) {
			let ticker = document.getElementById(tickerId);
			if (!ticker) return;
			let list = ticker.querySelector('.' + listClass);
			if (!list || list.children.length <= 1) return;
			let idx = 0, hover = false;
			ticker.onmouseenter = () => hover = true;
			ticker.onmouseleave = () => hover = false;
			setInterval(() => {
				if (hover) return;
				list.style.opacity = '0';
				list.style.transform = 'translateY(-3px)';
				setTimeout(() => {
					idx = (idx + 1) % list.children.length;
					list.style.top = -(idx * (list.children[0].offsetHeight || 36)) + 'px';
					list.style.transform = 'translateY(3px)';
					setTimeout(() => { list.style.opacity = '1'; list.style.transform = 'translateY(0)'; }, 50);
				}, 250);
			}, 3500);
		}
		observe("number_overseas", <?php echo $overseas_count; ?>);
		observe("number_mainland", <?php echo $mainland_count; ?>);
		initTicker('mainland_members_ticker', 'mainland-members-list');
		initTicker('overseas_members_ticker', 'overseas-members-list');
	})();
	</script>
	<?php
}

// ==============================================================================
// 动态智能提取文章第一张图 / 嵌入视频封面 / 规则兜底 & 全网社交分享 OG 卡片自动输出
// ==============================================================================

/**
 * 智能提取文本或链接中的 11 位 YouTube 视频 ID
 */
function chinacongress_extract_youtube_id( $text ) {
	if ( empty( $text ) ) {
		return '';
	}
	if ( preg_match( '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/i', $text, $matches ) ) {
		return $matches[1];
	}
	return '';
}

// 封装统一的纯文本摘要提取工具函数 (带运行期内存缓存，剥离短代码与 HTML 标签、多余换行缩紧、中文截断)
function chinacongress_get_clean_excerpt( $length = 140, $post_id = null ) {
	static $excerpt_cache = array();
	$post_id = $post_id ?: get_the_ID();
	if ( ! $post_id ) {
		return '';
	}
	$cache_key = $post_id . '_' . $length;
	if ( isset( $excerpt_cache[ $cache_key ] ) ) {
		return $excerpt_cache[ $cache_key ];
	}
	$post = get_post( $post_id );
	if ( ! $post || empty( $post->post_content ) ) {
		return '';
	}
	$raw_content = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
	$clean_text  = preg_replace( '/\s+/', ' ', $raw_content );
	$excerpt     = mb_strimwidth( trim( $clean_text ), 0, $length, '...' );
	$excerpt_cache[ $cache_key ] = $excerpt;
	return $excerpt;
}

/**
 * 规范化媒体 URL：自动补齐绝对域名，并对中文路径段进行安全编码
 */
function chinacongress_normalize_media_url( $url ) {
	if ( empty( $url ) ) {
		return '';
	}
	if ( ! preg_match( '~^(?:f|ht)tps?://~i', $url ) ) {
		$url = site_url( '/' . ltrim( $url, '/' ) );
	}
	$parts = parse_url( $url );
	if ( ! empty( $parts['path'] ) ) {
		$segments = array_map( function( $seg ) {
			return rawurlencode( rawurldecode( $seg ) );
		}, explode( '/', $parts['path'] ) );
		$parts['path'] = implode( '/', $segments );
		$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : '';
		$host   = isset( $parts['host'] ) ? $parts['host'] : '';
		$port   = isset( $parts['port'] ) ? ':' . $parts['port'] : '';
		$query  = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
		$url    = $scheme . $host . $port . $parts['path'] . $query;
	}
	return $url;
}

function chinacongress_get_first_image_url( $post_id = null ) {
	static $image_cache = array();
	$post_id = $post_id ?: get_the_ID();
	if ( ! $post_id ) {
		return '';
	}
	if ( isset( $image_cache[ $post_id ] ) ) {
		return $image_cache[ $post_id ];
	}
	$url = '';

	// 1. 优先特色图片
	if ( has_post_thumbnail( $post_id ) ) {
		$img_src = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'full' );
		if ( ! empty( $img_src[0] ) ) {
			$url = $img_src[0];
		}
	}

	// 2. 正文第一张图 / YouTube 封面 / video poster
	if ( empty( $url ) ) {
		$post = get_post( $post_id );
		if ( $post && ! empty( $post->post_content ) ) {
			if ( preg_match( '/<img.+?src=[\'"]([^\'"]+)[\'"].*?>/i', $post->post_content, $m ) ) {
				$url = $m[1];
			} elseif ( $yt_id = chinacongress_extract_youtube_id( $post->post_content ) ) {
				$url = 'https://img.youtube.com/vi/' . $yt_id . '/hqdefault.jpg';
			} elseif ( preg_match( '/<video.+?poster=[\'"]([^\'"]+)[\'"].*?>/i', $post->post_content, $v_m ) ) {
				$url = $v_m[1];
			}
		}
	}

	// 3. 兜底官方横版 Banner Logo
	if ( empty( $url ) ) {
		$url = CHINACONGRESS_FALLBACK_IMAGE;
	}

	$normalized = chinacongress_normalize_media_url( $url );
	$image_cache[ $post_id ] = $normalized;
	return $normalized;
}

// 过滤 post_thumbnail_html，使前台列表无特色图片时自动展示正文第一张图/视频封面
function chinacongress_auto_first_image_html( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
	if ( ! empty( $html ) ) {
		return $html;
	}
	$first_img_url = chinacongress_get_first_image_url( $post_id );
	return $first_img_url ? sprintf(
		'<img src="%s" class="attachment-full size-full wp-post-image auto-first-img" alt="%s" />',
		esc_url( $first_img_url ),
		esc_attr( get_the_title( $post_id ) )
	) : $html;
}
add_filter( 'post_thumbnail_html', 'chinacongress_auto_first_image_html', 10, 5 );

// 注册全站社交分享 1200x628 标准大图尺寸
add_action( 'after_setup_theme', function() {
	add_image_size( 'social-og', 1200, 628, true );
} );

/**
 * 获取符合社交平台 (X/Twitter, Facebook) 比例标准的 OG 图片数据 (包含 URL, Width, Height)
 */
function chinacongress_get_og_image_data( $post_id = null ) {
	$post_id = $post_id ?: get_the_ID();
	if ( ! $post_id ) {
		return array( 'url' => '', 'width' => 0, 'height' => 0 );
	}

	// 1. 优先读取 post_meta 缓存
	$cached = get_post_meta( $post_id, '_chinacongress_og_image_data', true );
	if ( is_array( $cached ) && ! empty( $cached['url'] ) ) {
		return $cached;
	}

	$img_url = '';
	$width   = 0;
	$height  = 0;

	if ( has_post_thumbnail( $post_id ) ) {
		$thumb_id = get_post_thumbnail_id( $post_id );
		$img_src  = wp_get_attachment_image_src( $thumb_id, 'social-og' ) ?: wp_get_attachment_image_src( $thumb_id, 'full' );
		if ( ! empty( $img_src[0] ) ) {
			$img_url = $img_src[0];
			$width   = ! empty( $img_src[1] ) ? (int) $img_src[1] : 0;
			$height  = ! empty( $img_src[2] ) ? (int) $img_src[2] : 0;
		}
	}
	if ( empty( $img_url ) ) {
		$img_url = chinacongress_get_first_image_url( $post_id );
	}

	// 仅当未从媒体库获取到宽高时，才尝试读取本地文件尺寸兜底
	if ( ( $width === 0 || $height === 0 ) && ! empty( $img_url ) ) {
		$upload_dir = wp_upload_dir();
		if ( false !== strpos( $img_url, $upload_dir['baseurl'] ) ) {
			$rel_path  = ltrim( str_replace( $upload_dir['baseurl'], '', $img_url ), '/' );
			$file_path = $upload_dir['basedir'] . '/' . rawurldecode( strtok( $rel_path, '?' ) );
			if ( file_exists( $file_path ) ) {
				$sz = @getimagesize( $file_path );
				if ( $sz && ! empty( $sz[0] ) && ! empty( $sz[1] ) ) {
					$width  = (int) $sz[0];
					$height = (int) $sz[1];
				}
			}
		}
	}

	$og_data = array(
		'url'    => $img_url,
		'width'  => $width,
		'height' => $height,
	);

	if ( ! empty( $img_url ) ) {
		update_post_meta( $post_id, '_chinacongress_og_image_data', $og_data );
	}

	return $og_data;
}

/**
 * 文章保存或更新时，后台预计算/预生成社交分享大图及缓存
 *
 * @param int     $post_id
 * @param WP_Post $post
 */
function chinacongress_pregenerate_og_image( $post_id, $post ) {
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
        return;
    }
    if ( ! is_object( $post ) || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
        return;
    }

    delete_post_meta( $post_id, '_chinacongress_og_image_data' );
    chinacongress_get_og_image_data( $post_id );
}
add_action( 'save_post', 'chinacongress_pregenerate_og_image', 10, 2 );

// 自动在 <head> 输出符合全网社交平台标准的 Open Graph & Twitter Cards 宽屏大图元数据
function chinacongress_add_social_og_tags() {
    if ( is_single() || is_page() ) {
        global $post;
        $title       = esc_attr( get_the_title() );
        $url         = esc_url( get_permalink() );
        $og_data     = chinacongress_get_og_image_data( $post->ID );
        $image_url   = esc_url( $og_data['url'] );
        $description = esc_attr( chinacongress_get_clean_excerpt( 120, $post->ID ) );

        echo "\n<!-- ChinaCongress Social Open Graph & Twitter Cards -->\n";
        echo '<meta property="og:type" content="article" />' . "\n";
        echo '<meta property="og:title" content="' . $title . '" />' . "\n";
        echo '<meta property="og:description" content="' . $description . '" />' . "\n";
        echo '<meta property="og:url" content="' . $url . '" />' . "\n";
        echo '<meta property="og:image" content="' . $image_url . '" />' . "\n";
        echo '<meta property="og:image:secure_url" content="' . $image_url . '" />' . "\n";
        if ( ! empty( $og_data['width'] ) && ! empty( $og_data['height'] ) ) {
            echo '<meta property="og:image:width" content="' . intval( $og_data['width'] ) . '" />' . "\n";
            echo '<meta property="og:image:height" content="' . intval( $og_data['height'] ) . '" />' . "\n";
        }
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

/**
 * 首页轮播图原生热拦截：在 Clever Fox 初始化前自动注入 dots: true 与 4.5 秒播放速度
 * 彻底消除先销毁后重建的暴力闪烁，同时完美呈现底部圆点指示器 (dots)
 */
function chinacongress_optimize_slider_init() {
    if ( is_front_page() || is_home() ) {
        $slider_patch = "
        (function($) {
            if (typeof $.fn.owlCarousel === 'function') {
                var _origOwl = $.fn.owlCarousel;
                $.fn.owlCarousel = function(options) {
                    if (this.hasClass('main-slider') && typeof options === 'object') {
                        options.dots = true;
                        options.autoplay = true;
                        options.autoplayTimeout = 4500;
                        options.smartSpeed = 500;
                    }
                    return _origOwl.apply(this, arguments);
                };
            }
        })(jQuery);";
        wp_add_inline_script( 'owl-carousel', $slider_patch, 'after' );
    }
}
add_action( 'wp_enqueue_scripts', 'chinacongress_optimize_slider_init', 999 );

// ==============================================================================
// 自动全站正文路径相对化：入库自动清洗 & 快速通道字符串替换
// ==============================================================================

// 1. 全站正文路径相对化：入库清洗与前台展示统一通过快速通道执行，避免无谓正则引擎开销
function chinacongress_make_content_relative( $content ) {
    if ( empty( $content ) || false === strpos( $content, 'chinacongress.net' ) ) {
        return $content;
    }
    return str_ireplace(
        array( 'https://chinacongress.net/', 'http://chinacongress.net/', 'https://www.chinacongress.net/', 'http://www.chinacongress.net/' ),
        '/',
        $content
    );
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
add_action( 'init', 'chinacongress_ensure_cleverfox_avril_loaded', 1 );

// 2. Customizer 配置继承保底：解决 Clever Fox 升级后校验子主题配置导致轮播图/组件丢失的问题
function chinacongress_theme_mods_fallback( $mods ) {
    $parent_mods = get_option( 'theme_mods_avril' );
    if ( is_array( $parent_mods ) ) {
        if ( ! is_array( $mods ) ) {
            $mods = array();
        }
        foreach ( $parent_mods as $key => $val ) {
            if ( ! isset( $mods[ $key ] ) ) {
                $mods[ $key ] = $val;
            }
        }
    }
    return $mods;
}
add_filter( 'option_theme_mods_avril-child', 'chinacongress_theme_mods_fallback', 99 );

// 3. 重写顶栏 (Above Header) 逻辑：在子主题中强行将“法律顾问 / Counsel”绑定跳转至创世律师事务所
function chinacongress_above_header_override() {
    remove_action( 'avril_above_header', 'avril_above_header' );
    add_action( 'avril_above_header', 'chinacongress_above_header_custom' );
}
add_action( 'wp_head', 'chinacongress_above_header_override', 1 );

function chinacongress_above_header_custom() {
    $avril_hide_show_social_icon = get_theme_mod( 'hide_show_social_icon', '1' ); 
    $avril_social_icons          = get_theme_mod( 'social_icons', function_exists( 'avril_get_social_icon_default' ) ? avril_get_social_icon_default() : '' );

    $contacts = array(
        array(
            'show'    => get_theme_mod( 'hide_show_cntct_details', '1' ),
            'class'   => 'wgt-1',
            'icon'    => get_theme_mod( 'tlh_contct_icon', 'fa-book' ),
            'title'   => get_theme_mod( 'tlh_contact_title', '法律顾问' ),
            'sub'     => get_theme_mod( 'tlh_contact_sbtitle', 'Counsel' ),
            'url'     => 'https://chuangshilaw.com/',
            'target'  => '_blank',
        ),
        array(
            'show'    => get_theme_mod( 'hide_show_email_details', '1' ),
            'class'   => 'wgt-2',
            'icon'    => get_theme_mod( 'tlh_email_icon', 'fa-envelope-o' ),
            'title'   => get_theme_mod( 'tlh_email_title', __( 'Email Us', 'clever-fox' ) ),
            'sub'     => get_theme_mod( 'tlh_email_sbtitle', 'info@chinacongress.net' ),
            'url'     => 'mailto:' . get_theme_mod( 'tlh_email_sbtitle', 'info@chinacongress.net' ),
            'target'  => '',
        ),
        array(
            'show'    => get_theme_mod( 'hide_show_mbl_details', '1' ),
            'class'   => 'wgt-3',
            'icon'    => get_theme_mod( 'tlh_mobile_icon', 'fa-usd' ),
            'title'   => get_theme_mod( 'tlh_mobile_title', 'Zelle 捐助' ),
            'sub'     => get_theme_mod( 'tlh_mobile_sbtitle', 'chinacongress' ),
            'url'     => '#',
            'target'  => '',
        ),
    );
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
                                        $icons_data = json_decode( $avril_social_icons );
                                        if ( ! empty( $icons_data ) && is_array( $icons_data ) ) {
                                             foreach ( $icons_data as $item ) {    
                                                $icon = ! empty( $item->icon_value ) ? apply_filters( 'avril_translate_single_string', $item->icon_value, 'Header section' ) : ''; 
                                                $link = ! empty( $item->link ) ? apply_filters( 'avril_translate_single_string', $item->link, 'Header section' ) : '';
                                                ?>
                                                <li><a href="<?php echo esc_url( $link ); ?>"><i class="fa <?php echo esc_attr( $icon ); ?>"></i></a></li>
                                            <?php }
                                        } ?>
                                    </ul>
                                </aside>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="av-column-7">
                        <div class="widget-right text-av-right text-center"> 
                            <?php foreach ( $contacts as $c ) : if ( $c['show'] == '1' ) : ?>
                                <aside class="widget widget-contact <?php echo esc_attr( $c['class'] ); ?>">
                                    <div class="contact-area">
                                        <div class="contact-icon"><i class="fa <?php echo esc_attr( $c['icon'] ); ?>"></i></div>
                                        <a href="<?php echo esc_url( $c['url'] ); ?>" <?php echo ( '#' === $c['url'] ) ? 'onclick="return false;"' : ''; ?> <?php echo $c['target'] ? 'target="' . esc_attr( $c['target'] ) . '"' : ''; ?> class="contact-info">
                                            <span class="text"><?php echo esc_html( $c['title'] ); ?></span>
                                            <span class="title"><?php echo esc_html( $c['sub'] ); ?></span>
                                        </a>
                                    </div>
                                </aside>
                            <?php endif; endforeach; ?>
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

// 允许管理员上传 SVG 矢量图资源
add_filter( 'upload_mimes', function ( $mimes ) {
    if ( current_user_can( 'manage_options' ) ) {
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

/**
 * 自动在文章中智能插入可直接播放的 YouTube 高清视频窗口
 * 规则：
 * 1. 100% 保留原本正文中的 <a type="youtube"> 文本超链接不变；
 * 2. 若文章正文中没有其他视频框，则将视频播放框插入到【文章正文最头部】；
 * 3. 若文章正文中已有视频框（<iframe> 或 <video>），则将视频播放框插入到【现有视频框正下方】。
 */
add_filter( 'the_content', 'chinacongress_auto_embed_youtube_players', 20 );
function chinacongress_auto_embed_youtube_players( $content ) {
	if ( is_admin() || empty( $content ) || ! is_singular( 'post' ) || false === stripos( $content, 'youtube' ) ) {
		return $content;
	}

	// 1. 搜寻正文中是否有 type="youtube" 的 <a> 链接标签
	if ( ! preg_match_all( '/<a\s+[^>]*?type=[\'"]?youtube[\'"]?[^>]*?>.*?<\/a>/i', $content, $matches, PREG_SET_ORDER ) ) {
		return $content;
	}

	// 2. 提取所有匹配到的 YouTube 视频 ID 并生成 16:9 响应式播放器 HTML 块
	$video_boxes = array();
	foreach ( $matches as $item ) {
		$full_a_tag = $item[0];
		if ( preg_match( '/href=[\'"]([^\'"]+)[\'"]/i', $full_a_tag, $href_match ) ) {
			$video_id = chinacongress_extract_youtube_id( $href_match[1] );
			if ( $video_id ) {
				$video_boxes[] = '<div class="cc-video-embed-wrap" style="position:relative; padding-bottom:56.25%; height:0; overflow:hidden; border-radius:10px; margin:20px 0; box-shadow:0 4px 15px rgba(0,0,0,0.1);">'
							   . '<iframe src="https://www.youtube.com/embed/' . esc_attr( $video_id ) . '" style="position:absolute; top:0; left:0; width:100%; height:100%; border:0;" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>'
							   . '</div>';
			}
		}
	}

	if ( empty( $video_boxes ) ) {
		return $content;
	}

	$combined_boxes = implode( "\n", $video_boxes );

	// 3. 判断文章正文中是否已经存在视频框 (<iframe> 标签或 <figure class="...embed..."> 容器)
	if ( preg_match( '/(<figure[^>]*class="[^"]*wp-block-embed[^"]*"[^>]*>.*?<\/figure>|<iframe[^>]*>.*?<\/iframe>|<video[^>]*>.*?<\/video>)/is', $content, $embed_match, PREG_OFFSET_CAPTURE ) ) {
		// 情况 A：已有视频框，插入到第一个/现有视频框的正下方
		$matched_str = $embed_match[0][0];
		$matched_pos = $embed_match[0][1];
		$insert_pos  = $matched_pos + strlen( $matched_str );

		return substr_replace( $content, "\n" . $combined_boxes . "\n", $insert_pos, 0 );
	}

	// 4. 通用智能检测全站所有文章最头部是否存在“副标题 / 大字号标题 / 导语 / 阶段章节标题”
	// 通用匹配规则：HTML 标题 (h1-h6)、各类副标题 div 块 (cc_title/head_title/cc_colon/chapter/section 等)、大字号段落及 style="font-size:..." 放大样式
	$subtitle_pattern = '/(<h[1-6][^>]*?>.*?<\/h[1-6]>|<div[^>]*class=[\'"][^\'"]*(?:cc_title|cc_colon|cc_author|head_title|cc_strong|subtitle|post-subtitle|sub-title|title|heading|chapter|section)[^\'"]*[\'"][^>]*?>.*?<\/div>|<p[^>]*class=[\'"][^\'"]*(?:has-large-font-size|has-huge-font-size|has-medium-font-size|subtitle|lead)[^\'"]*[\'"][^>]*?>.*?<\/p>|<p[^>]*style=[\'"][^\'"]*font-size[^\'"]*[\'"][^>]*?>.*?<\/p>|<div[^>]*style=[\'"][^\'"]*font-size[^\'"]*[\'"][^>]*?>.*?<\/div>)/is';

	if ( preg_match( $subtitle_pattern, $content, $sub_match, PREG_OFFSET_CAPTURE ) ) {
		$matched_str = $sub_match[0][0];
		$matched_pos = $sub_match[0][1];

		// 只有当副标题元素位于文章前半部分 (前 3000 字符内) 时才认定为文章头部的副标题
		if ( $matched_pos < 3000 ) {
			$insert_pos = $matched_pos + strlen( $matched_str );
			return substr_replace( $content, "\n" . $combined_boxes . "\n", $insert_pos, 0 );
		}
	}

	// 情况 C：无其他视频也无头部副标题，插入到【文章正文最头部】
	return $combined_boxes . "\n" . $content;
}

// ==============================================================================
// Cloudflare CDN 深度协同优化模块 (Edge Cache, Early Hints & Preconnect)
// ==============================================================================

/**
 * 提取首页首张轮播大图 (Hero Banner Image) URL
 */
function chinacongress_get_hero_image_url() {
	if ( ! is_front_page() && ! is_home() ) {
		return '';
	}
	$slider_mod = get_theme_mod( 'slider' );
	if ( empty( $slider_mod ) ) {
		return '';
	}
	$slider_items = json_decode( $slider_mod );
	if ( ! empty( $slider_items ) && is_array( $slider_items ) && ! empty( $slider_items[0]->image_url ) ) {
		return $slider_items[0]->image_url;
	}
	return '';
}

/**
 * 1. 智能注入 Cloudflare CDN 边缘缓存响应头 (Edge Cache-Control) 与 Early Hints Link 头
 * 仅对未登录的普通访客在公开前台 GET 页面输出 s-maxage，解放源站 PHP & MySQL 算力
 */
function chinacongress_cloudflare_edge_cache_headers() {
	if ( headers_sent() ) {
		return;
	}

	// 安全防御检查：排除后台、登录页、XML-RPC、REST 请求、搜索页、预览页面及非 GET 请求
	if ( is_admin() || is_user_logged_in() || is_search() || is_preview() || is_customize_preview() ) {
		return;
	}
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && ! in_array( $_SERVER['REQUEST_METHOD'], array( 'GET', 'HEAD' ), true ) ) {
		return;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return;
	}

	// 检查是否有 WordPress 用户认证 Cookie 或评论者 Cookie
	if ( ! empty( $_COOKIE ) ) {
		foreach ( $_COOKIE as $cookie_key => $cookie_val ) {
			if ( strpos( $cookie_key, 'wordpress_logged_in_' ) === 0 || strpos( $cookie_key, 'comment_author_' ) === 0 ) {
				return;
			}
		}
	}

	// 仅对普通访客在纯静态展示内容页面发出 Cloudflare 边缘缓存指令
	// s-maxage=3600: Cloudflare 边缘节点缓存该页面 1 小时 (源站 1 小时内 0 负载)
	// max-age=60: 访客本地浏览器仅缓存 1 分钟 (确保前台近实时感知更新)
	// stale-while-revalidate=600: 缓存过期时，Cloudflare 秒出旧副本并在后台静默异步刷新
	header( 'Cache-Control: public, max-age=60, s-maxage=3600, stale-while-revalidate=600' );

	// 2. HTTP 103 Early Hints 支持：提前向 Cloudflare 推送关键静态资产 Link 响应头 (与 wp_enqueue 版本参数对齐)
	$child_css_file = get_stylesheet_directory() . '/style.css';
	$child_css_ver  = file_exists( $child_css_file ) ? filemtime( $child_css_file ) : wp_get_theme()->get( 'Version' );
	$style_url      = add_query_arg( 'ver', $child_css_ver, get_stylesheet_directory_uri() . '/style.css' );
	$fa_url         = add_query_arg( 'ver', '4.6.3', get_stylesheet_directory_uri() . '/assets/css/fonts/font-awesome/css/font-awesome.min.css' );
	header( 'Link: <' . esc_url_raw( $style_url ) . '>; rel=preload; as=style', false );
	header( 'Link: <' . esc_url_raw( $fa_url ) . '>; rel=preload; as=style', false );

	// 首页首屏首张轮播大图 (Hero Banner Image) 注入 Early Link 响应头
	$hero_img = chinacongress_get_hero_image_url();
	if ( ! empty( $hero_img ) ) {
		header( 'Link: <' . esc_url_raw( $hero_img ) . '>; rel=preload; as=image', false );
	}
}
add_action( 'template_redirect', 'chinacongress_cloudflare_edge_cache_headers', 999 );

/**
 * 3. 页面头部注入核心外部资源 DNS 预解析与预连接 (DNS-Prefetch & Preconnect)
 * 加快 YouTube 视频封面/播放器以及静态字体的 TLS 握手速度，并预加载首屏大图
 */
function chinacongress_cloudflare_preconnect_tags() {
	echo "\n<!-- ChinaCongress Cloudflare Preconnect & DNS-Prefetch -->\n";
	echo '<link rel="dns-prefetch" href="//img.youtube.com">' . "\n";
	echo '<link rel="preconnect" href="https://img.youtube.com" crossorigin>' . "\n";
	echo '<link rel="dns-prefetch" href="//www.youtube.com">' . "\n";
	echo '<link rel="preconnect" href="https://www.youtube.com" crossorigin>' . "\n";

	// 首页首屏首张轮播大图 HTML 高优先级预加载
	$hero_img = chinacongress_get_hero_image_url();
	if ( ! empty( $hero_img ) ) {
		echo '<link rel="preload" as="image" href="' . esc_url( $hero_img ) . '" fetchpriority="high">' . "\n";
	}
	echo "<!-- End Cloudflare Preconnect -->\n";
}
add_action( 'wp_head', 'chinacongress_cloudflare_preconnect_tags', 1 );

/**
 * 4. 站内活动短视频支持 (Video Player for Activity Short Videos <15MB/<3min)
 * -----------------------------------------------------------------------------
 * 注册极简短代码 [cc_video src="..." caption="..."]，支持文章中插入短视频并可选显示说明文字。
 */
function chinacongress_shortcode_cc_video( $atts ) {
	$a = shortcode_atts( array(
		'src'     => '',
		'caption' => '',
	), $atts, 'cc_video' );

	if ( empty( $a['src'] ) ) {
		return '';
	}

	$src = esc_url( $a['src'] );
	$caption_html = '';
	if ( ! empty( $a['caption'] ) ) {
		$caption_html = '<div class="cc_video_caption">' . esc_html( $a['caption'] ) . '</div>';
	}

	return '<div class="cc_video_container"><video src="' . $src . '" controls playsinline preload="metadata"></video>' . $caption_html . '</div>';
}
add_shortcode( 'cc_video', 'chinacongress_shortcode_cc_video' );

/**
 * 全站短视频自动包装与互斥播放守护脚本
 * 无论使用短代码、自定义 HTML 还是原生视频区块，自动实现：
 * 1. 站内相对链接自动补齐为绝对路径；
 * 2. 多视频互斥播放（播放一个时自动暂停其他视频）；
 * 3. 自动识别 caption 属性渲染说明文字。
 */
function chinacongress_video_player_footer_script() {
	if ( is_admin() ) {
		return;
	}
	?>
	<script id="cc-video-player-init">
	(function() {
		function initVideos() {
			var videos = document.querySelectorAll('video');
			if (!videos || videos.length === 0) return;

			videos.forEach(function(video) {
				// 确保有基础控制条
				video.controls = true;
				video.playsInline = true;

				// 互斥播放：当一个视频播放时，暂停其他正在播放的视频
				if (!video.dataset.ccMutualInit) {
					video.dataset.ccMutualInit = "1";
					video.addEventListener('play', function() {
						document.querySelectorAll('video').forEach(function(other) {
							if (other !== video && !other.paused) {
								other.pause();
							}
						});
					});
				}

				// 自动包装与 caption 处理（若未包装）
				if (!video.parentElement.classList.contains('cc_video_container') && !video.parentElement.classList.contains('wp-block-video')) {
					var container = document.createElement('div');
					container.className = 'cc_video_container';
					video.parentNode.insertBefore(container, video);
					container.appendChild(video);

					var caption = video.getAttribute('caption');
					if (caption && !container.querySelector('.cc_video_caption')) {
						var capDiv = document.createElement('div');
						capDiv.className = 'cc_video_caption';
						capDiv.textContent = caption;
						container.appendChild(capDiv);
					}
				}
			});
		}

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', initVideos);
		} else {
			initVideos();
		}
	})();
	</script>
	<?php
}
add_action( 'wp_footer', 'chinacongress_video_player_footer_script', 99 );


