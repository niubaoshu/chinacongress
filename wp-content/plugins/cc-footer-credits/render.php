<?php
/**
 * cc-footer-credits — 前台 HTML 輸出。
 *
 * 只用到 esc_html／esc_attr／esc_url 三個 WP 函式，所以 tests/test-render.php
 * 能拿替身直接跑。外層屬性由呼叫端算好（get_block_wrapper_attributes()）再傳進來。
 */

declare( strict_types = 1 );

// 直接用瀏覽器打這支檔案時停住（CLI 測試與 WP 載入不受影響）。
if ( 'cli' !== PHP_SAPI && ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param array<string, string|array<int, string>>                       $credits            角色鍵 => 人員 ID（或 ID 陣列＝多人）。
 * @param bool                                                           $show_icons         是否輸出圖標區。
 * @param array<string, array{name:string,url:string,title:string}>      $people             人員 ID => 資料。
 * @param array<string, string>                                          $roles              角色鍵 => 顯示標籤（順序＝顯示順序）。
 * @param array<int, array{img:string,link:string,tooltip:string}>       $icons              圖標。
 * @param string                                                         $wrapper_attributes 外層 div 的屬性字串。
 */
function cc_credits_render(
	array $credits,
	bool $show_icons,
	array $people,
	array $roles,
	array $icons,
	string $wrapper_attributes
): string {
	$icons_html = $show_icons ? cc_credits_render_icons( $icons ) : '';
	$lines      = cc_credits_render_lines( $credits, $people, $roles );

	// 兩區都沒東西就什麼都不輸出 —— 不留空殼 div。
	if ( '' === $icons_html && ! $lines ) {
		return '';
	}

	$out = '<div ' . $wrapper_attributes . '>';
	if ( '' !== $icons_html ) {
		$out .= "\n" . $icons_html;
	}
	if ( $lines ) {
		$out .= "\n" . '<div class="cc-credits__names">'
			. "\n" . implode( "\n", $lines )
			. "\n" . '</div>';
	}

	return $out . "\n" . '</div>';
}

/**
 * 圖標區。數量不寫死，依名單庫行數決定。
 *
 * @param array<int, array{img:string,link:string,tooltip:string}> $icons
 */
function cc_credits_render_icons( array $icons ): string {
	$parts = array();

	foreach ( $icons as $icon ) {
		$img = isset( $icon['img'] ) ? (string) $icon['img'] : '';
		if ( '' === $img ) {
			continue;
		}

		// 圖標是 span 的 CSS 背景圖：網址是資料、只能 inline，其餘樣式都在 style.css。
		$span = '<span class="cc-credits__icon" style="background-image:url('
			. cc_credits_css_url( $img ) . ')"></span>';

		$link = isset( $icon['link'] ) ? (string) $icon['link'] : '';
		if ( '' === $link ) {
			$parts[] = $span;
			continue;
		}

		$attributes = 'href="' . esc_url( $link ) . '"';

		// tooltip 同時給 title（與現況一致）與 aria-label（純圖連結對讀屏軟體本來全空白）。
		$tooltip = isset( $icon['tooltip'] ) ? (string) $icon['tooltip'] : '';
		if ( '' !== $tooltip ) {
			$attributes .= ' title="' . esc_attr( $tooltip ) . '"'
				. ' aria-label="' . esc_attr( $tooltip ) . '"';
		}

		$parts[] = '<a ' . $attributes . ' rel="noopener noreferrer" target="_blank">' . $span . '</a>';
	}

	if ( ! $parts ) {
		return '';
	}

	return '<div class="cc-credits__icons">' . "\n" . implode( "\n", $parts ) . "\n" . '</div>';
}

/**
 * 給 CSS 語境用的網址。
 *
 * esc_url() 守的是 HTML 屬性邊界（它把 " < > { } 刪掉），但白名單**放行 ( ) ; :**
 * —— 在 style="background-image:url(…)" 裡，那幾個字元足以關掉 url()、追加任意
 * CSS 宣告（例如 position:fixed 蓋滿整個視窗）。而 KSES／safecss_filter_attr()
 * 只管存檔時的 post_content，管不到動態區塊 render_callback 吐出來的東西。
 *
 * 今天能寫圖標名單的只有 manage_options（那個角色本來就有 unfiltered_html，
 * 所以不構成權限越界），但 spec 9.2 明說這道閘門日後可能放寬到編輯 ——
 * 放寬的那天這裡就是編輯可打的洞。百分號編碼是合法網址編碼，圖片照樣載得到。
 */
function cc_credits_css_url( string $url ): string {
	return str_replace(
		array( '(', ')', ';', "'", '"' ),
		array( '%28', '%29', '%3B', '%27', '%22' ),
		esc_url( $url )
	);
}

/**
 * 署名行。順序照角色清單，不照 credits 的鍵順序。
 *
 * 值可以是單一 ID 字串（編輯器產生）或 ID 陣列（子項目 B 遷移的多人署名，
 * 例如文章 2184 有兩個编辑）——多人用「、」分隔，與那 46 篇 JS 渲染的慣例一致。
 *
 * @param array<string, string|array<int, string>>                  $credits
 * @param array<string, array{name:string,url:string,title:string}> $people
 * @param array<string, string>                                     $roles
 * @return array<int, string>
 */
function cc_credits_render_lines( array $credits, array $people, array $roles ): array {
	$lines = array();

	foreach ( $roles as $role_key => $label ) {
		// 角色鍵不在角色清單的殘留資料，在這個迴圈裡自然被跳過。
		$ids   = isset( $credits[ $role_key ] ) ? $credits[ $role_key ] : array();
		$ids   = is_array( $ids ) ? $ids : array( $ids );
		$names = array();

		foreach ( $ids as $person_id ) {
			// 非純量（巢狀陣列等髒資料）跳過，別讓它被硬轉成 "Array" 印出來。
			if ( ! is_scalar( $person_id ) ) {
				continue;
			}
			$person_id = trim( (string) $person_id );
			if ( '' !== $person_id ) {
				$names[] = cc_credits_render_name( $person_id, $people );
			}
		}

		if ( ! $names ) {
			continue;
		}

		$lines[] = '<div class="cc-credits__line">'
			. '<span class="cc-credits__label">' . esc_html( (string) $label ) . '</span>'
			. implode( '、', $names )
			. '</div>';
	}

	return $lines;
}

/**
 * 單一人名。名單庫查不到就輸出 ID 原字串、不掛連結 —— 不靜默丟棄。
 *
 * @param array<string, array{name:string,url:string,title:string}> $people
 */
function cc_credits_render_name( string $person_id, array $people ): string {
	if ( ! isset( $people[ $person_id ] ) ) {
		return '<span class="cc-credits__name">' . esc_html( $person_id ) . '</span>';
	}

	$person = $people[ $person_id ];
	$name   = isset( $person['name'] ) && '' !== $person['name'] ? (string) $person['name'] : $person_id;
	$url    = isset( $person['url'] ) ? (string) $person['url'] : '';
	$title  = isset( $person['title'] ) ? (string) $person['title'] : '';

	$title_attribute = '' !== $title ? ' title="' . esc_attr( $title ) . '"' : '';

	if ( '' === $url ) {
		return '<span class="cc-credits__name"' . $title_attribute . '>' . esc_html( $name ) . '</span>';
	}

	return '<a class="cc-credits__name" href="' . esc_url( $url ) . '"' . $title_attribute
		. ' rel="noopener noreferrer" target="_blank">' . esc_html( $name ) . '</a>';
}
