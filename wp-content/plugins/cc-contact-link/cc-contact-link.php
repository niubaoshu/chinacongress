<?php
/**
 * Plugin Name: 中國議會 — 联系链接卡片
 * Description: 古腾堡动态区块，在后台预设邮箱或 Telegram 联系卡片，文章内一键选用、单点维护全站同步。
 * Version:     1.1.0
 * Author:      中國議會 網絡部
 * Requires at least: 6.1
 * Requires PHP: 7.4
 * License:     GPL-2.0-or-later
 * Update URI:  false
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CC_CONTACT_LINK_OPTION', 'cc_contact_link_presets' );
define( 'CC_CONTACT_LINK_SETTINGS_GROUP', 'cc_contact_link' );
define( 'CC_CONTACT_LINK_SETTINGS_SLUG', 'cc-contact-link' );

/**
 * 解析后台预设名单库。
 * 格式：ID | 类型(email/telegram) | 显示文案 | 地址
 *
 * @return array<string, array{id:string,type:string,label:string,value:string}>
 */
function cc_contact_link_presets(): array {
	$raw     = (string) get_option( CC_CONTACT_LINK_OPTION, '' );
	$lines   = preg_split( '/\r\n|\r|\n/', $raw ) ?: array();
	$presets = array();

	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( '' === $line || 0 === strpos( $line, '#' ) ) {
			continue;
		}
		$parts = array_map( 'trim', explode( '|', $line, 4 ) );
		if ( count( $parts ) < 4 ) {
			continue;
		}

		$id    = sanitize_key( $parts[0] );
		$type  = ( 'telegram' === strtolower( $parts[1] ) ) ? 'telegram' : 'email';
		$label = sanitize_text_field( $parts[2] );
		$value = ( 'telegram' === $type ) ? esc_url_raw( cc_contact_link_normalize_telegram( $parts[3] ) ) : sanitize_email( $parts[3] );

		if ( '' === $id || '' === $value ) {
			continue;
		}

		if ( '' === $label ) {
			$label = ( 'telegram' === $type ) ? 'Telegram：' : 'Email：';
		}

		$presets[ $id ] = array(
			'id'    => $id,
			'type'  => $type,
			'label' => $label,
			'value' => $value,
		);
	}

	return $presets;
}

/**
 * 将 @username / t.me/username / 完整 URL 统一规范化为 https://t.me/...。
 */
function cc_contact_link_normalize_telegram( string $raw ): string {
	$val = trim( $raw );
	if ( '' === $val ) {
		return '';
	}
	if ( preg_match( '#^https?://#i', $val ) ) {
		return $val;
	}
	if ( 0 === stripos( $val, 't.me/' ) ) {
		return 'https://' . $val;
	}
	return 'https://t.me/' . ltrim( $val, '@/ ' );
}

add_action( 'init', static function () {
	register_block_type( __DIR__ );
} );

add_action( 'enqueue_block_editor_assets', static function () {
	$data = array(
		'presets'     => cc_contact_link_presets(),
		'settingsUrl' => admin_url( 'options-general.php?page=' . CC_CONTACT_LINK_SETTINGS_SLUG ),
	);
	wp_add_inline_script(
		'cc-contact-link-editor-script',
		'window.ccContactLinks = ' . wp_json_encode( $data ) . ';',
		'before'
	);
} );

add_action( 'admin_init', static function () {
	register_setting(
		CC_CONTACT_LINK_SETTINGS_GROUP,
		CC_CONTACT_LINK_OPTION,
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_textarea_field',
			'default'           => '',
		)
	);
} );

add_action( 'admin_menu', static function () {
	add_options_page(
		'联系链接预设',
		'联系链接预设',
		'manage_options',
		CC_CONTACT_LINK_SETTINGS_SLUG,
		'cc_contact_link_render_settings_page'
	);
} );

/**
 * 渲染后台「设定 → 联系链接预设」管理页。
 */
function cc_contact_link_render_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$value = (string) get_option( CC_CONTACT_LINK_OPTION, '' );
	?>
	<div class="wrap">
		<h1>联系链接预设</h1>
		<p>每行一条，用 <code>|</code> 分隔四个字段：<code>ID | 类型(email 或 telegram) | 显示文案 | 地址</code>，以 <code>#</code> 开头的行为注释。</p>
		<p>示例：<br>
			<code>submission_email | email | Email： | submissions@chinacongress.press</code><br>
			<code>submit_bot | telegram | Telegram 投稿机器人： | https://t.me/CC_SubmitBot</code>
		</p>
		<form method="post" action="options.php">
			<?php settings_fields( CC_CONTACT_LINK_SETTINGS_GROUP ); ?>
			<textarea name="<?php echo esc_attr( CC_CONTACT_LINK_OPTION ); ?>" rows="10" class="large-text code"><?php echo esc_textarea( $value ); ?></textarea>
			<?php submit_button( '保存预设' ); ?>
		</form>
	</div>
	<?php
}
