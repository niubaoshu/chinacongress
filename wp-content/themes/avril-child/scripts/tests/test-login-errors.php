<?php
/**
 * 登录错误提示分场景测试（第 3 节 login_errors 过滤器）
 *
 * 验证：找回密码页错误提示为"用户名或邮箱不对。"（不提密码）；
 * 登录页保持"用户名或密码错误。"；防用户名枚举的笼统提示意图不变。
 *
 * 运行：~/workspace/.php/php test-login-errors.php
 */

require __DIR__ . '/bootstrap.php';

// ---- WordPress 桩：捕获注册到 login_errors 的闭包 ----
$captured = null;
function add_filter( $h, $cb, $p = 10, $a = 1 ) {
	global $captured;
	if ( 'login_errors' === $h ) {
		$captured = $cb;
	}
}

// ---- 载入被测闭包（实时提取整条 add_filter 语句并执行，桩会捕获闭包） ----
$stmt = cc_test_extract_login_errors_stmt( cc_test_functions_php() );
if ( false === $stmt ) {
	fwrite( STDERR, "提取 login_errors 语句失败（代码结构变了？）\n" );
	exit( 2 );
}
eval( $stmt . ';' );
global $captured;
if ( ! is_callable( $captured ) ) {
	fwrite( STDERR, "未能捕获 login_errors 回调\n" );
	exit( 2 );
}

// ---- 场景 ----
$pass = 0;
$fail = 0;

$_REQUEST['action'] = 'lostpassword';
cc_check( $pass, $fail, '找回密码页：提示邮箱不提密码',
	$captured( 'whatever' ) === '用户名或邮箱不对。' );

$_REQUEST['action'] = 'retrievepassword';
cc_check( $pass, $fail, 'retrievepassword：提示邮箱不提密码',
	$captured( 'whatever' ) === '用户名或邮箱不对。' );

$_REQUEST['action'] = 'login';
cc_check( $pass, $fail, '登录页：保持原提示',
	$captured( 'whatever' ) === '用户名或密码错误。' );

unset( $_REQUEST['action'] );
cc_check( $pass, $fail, '无 action：保持原提示',
	$captured( 'whatever' ) === '用户名或密码错误。' );

echo "== {$pass} passed, {$fail} failed ==\n";
exit( $fail ? 1 : 0 );
