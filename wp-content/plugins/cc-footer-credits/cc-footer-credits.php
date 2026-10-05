<?php
/**
 * Plugin Name: 中國議會 — 文章署名（含圖標）
 * Description: 每篇文章獨立署名的動態區塊。人名只能從後台名單庫選，圖標與人員資料單一來源、改一處全站生效。
 * Version:     1.0.3
 * Author:      中國議會 網絡部
 * Requires at least: 6.1
 * Requires PHP: 7.4
 * License:     GPL-2.0-or-later
 * Update URI:  false
 *
 * 設計 spec：docs/superpowers/specs/2026-07-30-cc-footer-credits-design.md
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CC_CREDITS_VERSION', '1.0.0' );
define( 'CC_CREDITS_OPTION_PEOPLE', 'cc_credits_people' );
define( 'CC_CREDITS_OPTION_ROLES', 'cc_credits_roles' );
define( 'CC_CREDITS_OPTION_ICONS', 'cc_credits_icons' );
define( 'CC_CREDITS_SETTINGS_GROUP', 'cc_credits' );
define( 'CC_CREDITS_SETTINGS_SLUG', 'cc-credits' );

require_once __DIR__ . '/parse.php';
require_once __DIR__ . '/render.php';

/**
 * 每個名單庫的欄數與逐欄清理函式。
 *
 * ⚠️ 網址欄絕對不可以走 sanitize_text_field／sanitize_textarea_field ——
 * 它們內部的 _sanitize_text_fields() 會刪掉所有 %xx 百分號編碼，本站附件檔名
 * 大量是中文，從瀏覽器位址欄複製來的網址會被靜默截斷。網址一律 esc_url_raw。
 *
 * @return array{columns:int, sanitizers:array<int, callable>}
 */
function cc_credits_schema( string $option ): array {
	switch ( $option ) {
		case CC_CREDITS_OPTION_PEOPLE:
			// ID|姓名|網址|職務
			return array(
				'columns'    => 4,
				'sanitizers' => array( 'sanitize_key', 'sanitize_text_field', 'esc_url_raw', 'sanitize_text_field' ),
			);
		case CC_CREDITS_OPTION_ROLES:
			// 角色鍵|顯示標籤
			return array(
				'columns'    => 2,
				'sanitizers' => array( 'sanitize_key', 'sanitize_text_field' ),
			);
		case CC_CREDITS_OPTION_ICONS:
			// 圖片網址|連結網址|tooltip
			return array(
				'columns'    => 3,
				'sanitizers' => array( 'esc_url_raw', 'esc_url_raw', 'sanitize_text_field' ),
			);
	}

	throw new InvalidArgumentException( 'unknown option: ' . $option );
}

/**
 * 讀名單庫：解析 ＋ 再清一次（舊資料可能早於清理規則）。
 *
 * @return array<int, array<int, string>>
 */
function cc_credits_rows( string $option ): array {
	$schema = cc_credits_schema( $option );
	$rows   = cc_credits_parse_lines( (string) get_option( $option, '' ), $schema['columns'] );

	return cc_credits_apply_sanitizers( $rows, $schema['sanitizers'] );
}

/**
 * 人員：ID => { name, url, title }。
 *
 * @return array<string, array{name:string,url:string,title:string}>
 */
function cc_credits_people(): array {
	$people = array();

	foreach ( cc_credits_rows( CC_CREDITS_OPTION_PEOPLE ) as $row ) {
		if ( '' === $row[0] ) {
			continue;
		}
		$people[ $row[0] ] = array(
			'name'  => '' !== $row[1] ? $row[1] : $row[0],
			'url'   => $row[2],
			'title' => $row[3],
		);
	}

	return $people;
}

/**
 * 角色：角色鍵 => 顯示標籤。陣列順序＝前台顯示順序。
 *
 * @return array<string, string>
 */
function cc_credits_roles(): array {
	$roles = array();

	foreach ( cc_credits_rows( CC_CREDITS_OPTION_ROLES ) as $row ) {
		if ( '' === $row[0] ) {
			continue;
		}
		$roles[ $row[0] ] = $row[1];
	}

	return $roles;
}

/**
 * 圖標：依名單庫行數，數量不寫死。
 *
 * @return array<int, array{img:string,link:string,tooltip:string}>
 */
function cc_credits_icons(): array {
	$icons = array();

	foreach ( cc_credits_rows( CC_CREDITS_OPTION_ICONS ) as $row ) {
		if ( '' === $row[0] ) {
			continue;
		}
		$icons[] = array(
			'img'     => $row[0],
			'link'    => $row[1],
			'tooltip' => $row[2],
		);
	}

	return $icons;
}

// ---------------------------------------------------------------------------
// 設定頁：設定 → 文章署名名單
// ---------------------------------------------------------------------------

// 掛 init 而非 admin_init：REST 請求不跑 admin_init，掛錯 /wp/v2/settings 就看不到這三個 option。
add_action( 'init', 'cc_credits_register_settings' );

function cc_credits_register_settings(): void {
	foreach ( array( CC_CREDITS_OPTION_PEOPLE, CC_CREDITS_OPTION_ROLES, CC_CREDITS_OPTION_ICONS ) as $option ) {
		register_setting(
			CC_CREDITS_SETTINGS_GROUP,
			$option,
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'cc_credits_sanitize_option',
				// 開給 REST：GET/POST /wp-json/wp/v2/settings（需 manage_options）。
				// 寫入仍過 sanitize_option_* 同一套清理。
				'show_in_rest'      => true,
			)
		);
	}
}

/**
 * 存檔清理。哪個選項在存檔由 current_filter() 決定（sanitize_option_{$option}）。
 *
 * @param mixed $value
 */
function cc_credits_sanitize_option( $value ): string {
	$option = (string) preg_replace( '/^sanitize_option_/', '', (string) current_filter() );

	try {
		$schema = cc_credits_schema( $option );
	} catch ( InvalidArgumentException $e ) {
		// 認不出是哪個選項就原值奉還 —— 回空字串等於把整份名單庫清空，
		// 那比「這一次沒清理」嚴重得多（輸出端本來就一律轉義）。
		return (string) $value;
	}

	return cc_credits_sanitize_text( (string) $value, $schema['columns'], $schema['sanitizers'] );
}

add_action( 'admin_menu', 'cc_credits_register_settings_page' );

function cc_credits_register_settings_page(): void {
	add_options_page(
		'文章署名名單',
		'文章署名名單',
		'manage_options',
		CC_CREDITS_SETTINGS_SLUG,
		'cc_credits_render_settings_page'
	);
}

function cc_credits_render_settings_page(): void {
	// add_options_page 已經擋過一層，這裡再擋一次，免得日後這個 callback 被別處呼叫。
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '權限不足。' );
	}

	$fields = array(
		array(
			'option'      => CC_CREDITS_OPTION_PEOPLE,
			'label'       => '人員',
			'format'      => 'ID|姓名|網址|職務',
			'description' => 'ID 是主鍵，只能用小寫英數與 - _（用姓名拼音即可，例 luozhifei）。區塊只存 ID，所以姓名／網址／職務隨時可改，舊文章會跟著改。網址留空＝只出純文字不掛連結；職務留空＝不出 tooltip。',
			'placeholder' => "luozhifei|罗志飞|https://x.com/zhifeiluofree|传播部\nyangjia|杨佳|https://x.com/jbcchin|网页编辑",
		),
		array(
			'option'      => CC_CREDITS_OPTION_ROLES,
			'label'       => '角色',
			'format'      => '角色鍵|顯示標籤',
			'description' => '這裡的行順序＝文章上的顯示順序。標籤含冒號會原樣輸出。增減角色只要改這一欄，不用改程式。',
			'placeholder' => "author|作者：\neditor|编辑：\nuploader|上传：",
		),
		array(
			'option'      => CC_CREDITS_OPTION_ICONS,
			'label'       => '圖標',
			'format'      => '圖片網址|連結網址|滑過去顯示的文字',
			'description' => '可用相對路徑（/wp-content/...）。連結留空＝只顯示圖不可點。數量不限四個，依行數決定。Telegram 邀請連結被封時，改這裡一行就全站生效。',
			'placeholder' => '/wp-content/uploads/2026/07/图片-13.png|https://t.me/xxxx|电报群： 中国议会 关注组',
		),
	);
	?>
	<div class="wrap">
		<h1>文章署名名單</h1>
		<p>
			文章裡的「文章署名（含圖標）」區塊，選單內容來自這裡。<strong>每行一筆、欄位用 <code>|</code> 分隔</strong>；
			空行與 <code>#</code> 開頭的行是註解，會原樣留著。
		</p>
		<form method="post" action="options.php">
			<?php settings_fields( CC_CREDITS_SETTINGS_GROUP ); ?>
			<table class="form-table" role="presentation">
				<?php foreach ( $fields as $field ) : ?>
					<tr>
						<th scope="row">
							<label for="<?php echo esc_attr( $field['option'] ); ?>">
								<?php echo esc_html( $field['label'] ); ?>
							</label>
						</th>
						<td>
							<p><code><?php echo esc_html( $field['format'] ); ?></code></p>
							<textarea
								id="<?php echo esc_attr( $field['option'] ); ?>"
								name="<?php echo esc_attr( $field['option'] ); ?>"
								class="large-text code"
								rows="8"
								spellcheck="false"
								placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"
							><?php echo esc_textarea( (string) get_option( $field['option'], '' ) ); ?></textarea>
							<p class="description"><?php echo esc_html( $field['description'] ); ?></p>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

// ---------------------------------------------------------------------------
// 區塊
// ---------------------------------------------------------------------------

add_action( 'init', 'cc_credits_register_block' );

function cc_credits_register_block(): void {
	$url = plugin_dir_url( __FILE__ );

	wp_register_script(
		'cc-footer-credits-editor',
		$url . 'editor.js',
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components' ),
		cc_credits_asset_version( 'editor.js' ),
		true
	);

	wp_register_style( 'cc-footer-credits', $url . 'style.css', array(), cc_credits_asset_version( 'style.css' ) );
	wp_register_style(
		'cc-footer-credits-editor',
		$url . 'editor.css',
		array( 'cc-footer-credits' ),
		cc_credits_asset_version( 'editor.css' )
	);

	// 名單庫送進編輯器供下拉與預覽使用。內容本來就公開印在文章署名上，可接受。
	wp_add_inline_script(
		'cc-footer-credits-editor',
		'window.ccFooterCredits = ' . wp_json_encode(
			array(
				'people'      => cc_credits_people(),
				'roles'       => cc_credits_roles(),
				'icons'       => cc_credits_icons(),
				'settingsUrl' => admin_url( 'options-general.php?page=' . CC_CREDITS_SETTINGS_SLUG ),
			)
		) . ';',
		'before'
	);

	register_block_type(
		'cc/footer-credits',
		array(
			'api_version'     => 3,
			'title'           => '文章署名（含圖標）',
			'description'     => '這篇文章的署名與社群圖標。人名從後台名單庫選。',
			'category'        => 'text',
			'icon'            => 'groups',
			'attributes'      => array(
				'credits'   => array(
					'type'    => 'object',
					'default' => array(),
				),
				'showIcons' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				// 純編輯器狀態（按過「產生署名」沒有），前台不看它；
				// 在這裡宣告只為了兩邊的屬性定義一致。
				'configured' => array(
					'type'    => 'boolean',
					'default' => false,
				),
			),
			'supports'        => array(
				'html'      => false,
				'multiple'  => true,
				'className' => true,
			),
			'editor_script'   => 'cc-footer-credits-editor',
			'style'           => 'cc-footer-credits',
			'editor_style'    => 'cc-footer-credits-editor',
			'render_callback' => 'cc_credits_render_block',
		)
	);
}

/**
 * 用檔案 mtime 當版本號：改完 scp 上去就自動破快取，不必記得改版本常數。
 */
function cc_credits_asset_version( string $file ): string {
	$path = __DIR__ . '/' . $file;
	$time = file_exists( $path ) ? filemtime( $path ) : false;

	return false !== $time ? (string) $time : CC_CREDITS_VERSION;
}

/**
 * 前台輸出。動態區塊：文章只存角色鍵與人員 ID，姓名／網址／職務／圖標改了全站一起變。
 *
 * @param array<string, mixed> $attributes
 */
function cc_credits_render_block( array $attributes ): string {
	$credits = isset( $attributes['credits'] ) && is_array( $attributes['credits'] )
		? $attributes['credits']
		: array();

	// 值收兩種形狀：純量＝單一人員 ID（編輯器產生）、純量陣列＝多人（子項目 B 遷移）。
	// 陣列裡的非純量元素（巢狀陣列等髒資料）丟掉；濾完全空的陣列整鍵不留。
	$clean = array();
	foreach ( $credits as $role_key => $person_id ) {
		if ( is_scalar( $person_id ) ) {
			$clean[ (string) $role_key ] = (string) $person_id;
			continue;
		}
		if ( ! is_array( $person_id ) ) {
			continue;
		}
		$ids = array();
		foreach ( $person_id as $entry ) {
			if ( is_scalar( $entry ) ) {
				$ids[] = (string) $entry;
			}
		}
		if ( $ids ) {
			$clean[ (string) $role_key ] = $ids;
		}
	}

	$show_icons = ! isset( $attributes['showIcons'] ) || (bool) $attributes['showIcons'];

	return cc_credits_render(
		$clean,
		$show_icons,
		cc_credits_people(),
		cc_credits_roles(),
		cc_credits_icons(),
		get_block_wrapper_attributes( array( 'class' => 'cc-credits' ) )
	);
}
