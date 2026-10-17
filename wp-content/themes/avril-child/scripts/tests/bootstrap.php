<?php
/**
 * 测试 bootstrap：从 functions.php 实时提取被测代码。
 *
 * 目的：测试文件里不快照被测函数的副本，每次运行时都从最新的
 * functions.php 提取，保证测的永远是当前代码，避免测试与实现脱节。
 *
 * 约定（提取规则依赖它）：被测函数 / 闭包的结束大括号必须顶格（column 0）。
 * 目前 functions.php 里相关代码都满足这一点；若将来改动破坏了该格式，
 * 对应测试会明确报错退出，而不是静默测错。
 */

/**
 * 按行提取命名函数源码（含 function 行到顶格 } 行）。
 *
 * @param string $src  functions.php 全文
 * @param string $name 函数名
 * @return string|false 函数源码，找不到返回 false
 */
function cc_test_extract_function( $src, $name ) {
	$lines = explode( "\n", $src );
	$out   = array();
	$in    = false;
	foreach ( $lines as $line ) {
		if ( ! $in && preg_match( '/^function\s+' . preg_quote( $name, '/' ) . '\s*\(/', $line ) ) {
			$in = true;
		}
		if ( $in ) {
			$out[] = $line;
			if ( '}' === $line ) {
				break;
			}
		}
	}
	return $in ? implode( "\n", $out ) : false;
}

/**
 * 提取 login_errors 的 add_filter 整条语句源码（含匿名闭包）。
 *
 * @param string $src functions.php 全文
 * @return string|false 语句源码，找不到返回 false
 */
function cc_test_extract_login_errors_stmt( $src ) {
	$lines = explode( "\n", $src );
	$out   = array();
	$in    = false;
	foreach ( $lines as $line ) {
		if ( ! $in && preg_match( "/^add_filter\(\s*'login_errors'/", $line ) ) {
			$in = true;
		}
		if ( $in ) {
			$out[] = $line;
			if ( preg_match( '/^\}\s*\);$/', $line ) ) {
				break;
			}
		}
	}
	return $in ? implode( "\n", $out ) : false;
}

/**
 * 读取被测的 functions.php 全文。
 */
function cc_test_functions_php() {
	$file = dirname( __DIR__ ) . '/../functions.php';
	$src  = @file_get_contents( $file );
	if ( false === $src ) {
		fwrite( STDERR, "读不到 {$file}\n" );
		exit( 2 );
	}
	return $src;
}

/**
 * 把提取到的函数源码载入当前进程（经由临时文件 require，避免 eval）。
 */
function cc_test_load_code( $code ) {
	$tmp = tempnam( sys_get_temp_dir(), 'cc_test_' ) . '.php';
	file_put_contents( $tmp, "<?php\n" . $code );
	require $tmp;
	@unlink( $tmp );
}

/**
 * 断言小助手。
 */
function cc_check( &$pass, &$fail, $name, $cond ) {
	if ( $cond ) {
		$pass++;
		echo "PASS: {$name}\n";
	} else {
		$fail++;
		echo "FAIL: {$name}\n";
	}
}
