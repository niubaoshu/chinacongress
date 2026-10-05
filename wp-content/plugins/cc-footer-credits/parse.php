<?php
/**
 * cc-footer-credits — 名單庫解析與分欄清理（純函式，不依賴 WordPress）
 *
 * 這個檔刻意不碰任何 WP 函式，所以 tests/test-parse.php 能用 php CLI 直接跑。
 * 清理函式由呼叫端（cc-footer-credits.php）注入。
 */

declare( strict_types = 1 );

// 直接用瀏覽器打這支檔案時停住（CLI 測試與 WP 載入不受影響）。
if ( 'cli' !== PHP_SAPI && ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 把一行切成固定欄數：以 | 分欄、逐欄 trim、不足補空字串、超過的丟棄。
 *
 * @return array<int, string>
 */
function cc_credits_split_fields( string $line, int $columns ): array {
	$fields = array_map( 'trim', explode( '|', $line ) );
	$fields = array_slice( $fields, 0, $columns );

	return array_pad( $fields, $columns, '' );
}

/**
 * 把一段 textarea 內容切成每行一筆、每筆固定欄數的二維陣列。
 *
 * @param string $text    原始 textarea 內容。
 * @param int    $columns 欄數；不足補空字串，超過的丟棄。
 * @return array<int, array<int, string>>
 */
function cc_credits_parse_lines( string $text, int $columns ): array {
	$rows = array();

	foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line ) {
		$line = trim( $line );

		// 空行與 # 註解行（含縮排的）直接略過。
		if ( '' === $line || 0 === strpos( $line, '#' ) ) {
			continue;
		}

		$fields = cc_credits_split_fields( $line, $columns );

		// 第一欄是主鍵（人員 ID／角色鍵／圖片網址），空的話這行沒有意義。
		if ( '' === $fields[0] ) {
			continue;
		}

		$rows[] = $fields;
	}

	return $rows;
}

/**
 * 逐欄套用清理函式。
 *
 * @param array<int, array<int, string>> $rows       cc_credits_parse_lines() 的輸出。
 * @param array<int, callable>           $sanitizers 每欄一個清理函式，索引對應欄位。
 * @return array<int, array<int, string>>
 * @throws InvalidArgumentException 清理函式沒蓋滿所有欄位時。
 */
function cc_credits_apply_sanitizers( array $rows, array $sanitizers ): array {
	$out = array();

	foreach ( $rows as $row ) {
		$clean = array();
		foreach ( $row as $i => $value ) {
			// 缺清理函式就吵，不靜默放行 —— 未清理的欄位會一路寫進資料庫。
			if ( ! isset( $sanitizers[ $i ] ) ) {
				throw new InvalidArgumentException(
					sprintf( '第 %d 欄沒有指定清理函式', $i )
				);
			}
			$clean[ $i ] = (string) call_user_func( $sanitizers[ $i ], $value );
		}
		$out[] = $clean;
	}

	return $out;
}

/**
 * 存檔用：逐行清理，但保留使用者寫的註解行與空行。
 *
 * @param array<int, callable> $sanitizers 每欄一個清理函式。
 * @throws InvalidArgumentException 清理函式沒蓋滿所有欄位時。
 */
function cc_credits_sanitize_text( string $text, int $columns, array $sanitizers ): string {
	$out = array();

	foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line ) {
		$line = trim( $line );

		// 註解行與空行原樣留著 —— 存檔時吃掉使用者寫的註解是會被發現的資料遺失。
		if ( '' === $line || 0 === strpos( $line, '#' ) ) {
			$out[] = $line;
			continue;
		}

		$clean = cc_credits_apply_sanitizers(
			array( cc_credits_split_fields( $line, $columns ) ),
			$sanitizers
		);
		$out[] = implode( '|', $clean[0] );
	}

	return implode( "\n", $out );
}
