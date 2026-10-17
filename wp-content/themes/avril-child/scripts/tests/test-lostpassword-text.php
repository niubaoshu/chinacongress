<?php
/**
 * 找回密码页文案修正测试（6.3 chinacongress_fix_lostpassword_text）
 *
 * 验证：gettext 过滤器只在 lostpassword / retrievepassword 页面把
 * "Username or Email Address" → "用户名"、提示语里的"或"→"和"；
 * 登录页、其他域、其他文案一律不动。
 *
 * 运行：~/workspace/.php/php test-lostpassword-text.php
 */

require __DIR__ . '/bootstrap.php';

// ---- WordPress 桩 ----
function add_filter( $h, $f, $p = 10, $a = 3 ) {}

// ---- 载入被测函数（实时提取） ----
$code = cc_test_extract_function( cc_test_functions_php(), 'chinacongress_fix_lostpassword_text' );
if ( false === $code ) {
	fwrite( STDERR, "提取被测函数失败（函数改名或结束括号不再顶格？）\n" );
	exit( 2 );
}
cc_test_load_code( $code );

// ---- 场景 ----
$pass = 0;
$fail = 0;

$label = 'Username or Email Address';
$msg   = 'Please enter your username or email address. You will receive an email message with instructions on how to reset your password.';

$_REQUEST['action'] = 'lostpassword';
cc_check( $pass, $fail, '找回密码页：字段标签 → 用户名',
	chinacongress_fix_lostpassword_text( $label, $label, 'default' ) === '用户名' );
cc_check( $pass, $fail, '找回密码页：提示语"或"→"和"',
	chinacongress_fix_lostpassword_text( $msg, $msg, 'default' ) === '请输入您的用户名和邮箱地址。您会收到一封包含重设密码指引的邮件。' );

$_REQUEST['action'] = 'retrievepassword';
cc_check( $pass, $fail, 'retrievepassword 同样生效',
	chinacongress_fix_lostpassword_text( $label, $label, 'default' ) === '用户名' );

$_REQUEST['action'] = 'login';
cc_check( $pass, $fail, '登录页标签不动（仍允许二选一）',
	chinacongress_fix_lostpassword_text( $label, $label, 'default' ) === $label );

$_REQUEST['action'] = 'lostpassword';
cc_check( $pass, $fail, '非 default 域不动',
	chinacongress_fix_lostpassword_text( 'X', $label, 'avril-child' ) === 'X' );
cc_check( $pass, $fail, '其他文案不动',
	chinacongress_fix_lostpassword_text( 'Lost your password?', 'Lost your password?', 'default' ) === 'Lost your password?' );

unset( $_REQUEST['action'] );
cc_check( $pass, $fail, '无 action 参数不动',
	chinacongress_fix_lostpassword_text( $label, $label, 'default' ) === $label );

echo "== {$pass} passed, {$fail} failed ==\n";
exit( $fail ? 1 : 0 );
