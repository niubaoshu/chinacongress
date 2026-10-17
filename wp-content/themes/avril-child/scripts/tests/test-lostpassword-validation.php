<?php
/**
 * 找回密码双重校验测试（6.2 chinacongress_lostpassword_require_username_and_email）
 *
 * 方法：桩函数隔离测试。被测函数运行时从 functions.php 实时提取；
 * WordPress 核心函数（get_user_by / is_email / wp_unslash / __）用桩函数模拟。
 *
 * 运行：~/workspace/.php/php test-lostpassword-validation.php
 * 全部通过返回 0，有失败返回 1。
 */

require __DIR__ . '/bootstrap.php';

// ---- WordPress 桩 ----
function add_action( $h, $f, $p = 10, $a = 1 ) {}
function wp_unslash( $v ) { return is_string( $v ) ? stripslashes( $v ) : $v; }
function is_email( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); }
function __( $s, $d = null ) { return $s; }

// 桩用户库：登录名 => [ID, email]
$CC_TEST_USERS = array(
	'alice' => array( 'ID' => 10, 'email' => 'alice@example.com' ),
);
function get_user_by( $field, $val ) {
	global $CC_TEST_USERS;
	foreach ( $CC_TEST_USERS as $login => $u ) {
		if ( ( 'login' === $field && $login === $val ) || ( 'email' === $field && $u['email'] === $val ) ) {
			return (object) array( 'ID' => $u['ID'] );
		}
	}
	return false;
}
class CC_Test_Errors {
	public $codes = array();
	public function add( $c, $m ) { $this->codes[] = $c; }
	public function has_errors() { return ! empty( $this->codes ); }
}

// ---- 载入被测函数（实时提取） ----
$code = cc_test_extract_function( cc_test_functions_php(), 'chinacongress_lostpassword_require_username_and_email' );
if ( false === $code ) {
	fwrite( STDERR, "提取被测函数失败（函数改名或结束括号不再顶格？）\n" );
	exit( 2 );
}
cc_test_load_code( $code );

// ---- 场景 ----
$pass = 0;
$fail = 0;
function cc_run_case( $post ) {
	$_POST = $post;
	$e     = new CC_Test_Errors();
	chinacongress_lostpassword_require_username_and_email( $e, false );
	return $e;
}

$e = cc_run_case( array( 'user_login' => 'alice', 'user_email' => 'alice@example.com' ) );
cc_check( $pass, $fail, '用户名与邮箱匹配 → 放行', ! $e->has_errors() );

$e = cc_run_case( array( 'user_login' => 'alice', 'user_email' => 'bob@example.com' ) );
cc_check( $pass, $fail, '邮箱不匹配 → 拦截', in_array( 'mismatched_username_email', $e->codes, true ) );

$e = cc_run_case( array( 'user_login' => 'alice' ) );
cc_check( $pass, $fail, '省略 user_email 字段 → 拦截（绕过已堵）', in_array( 'empty_username_email', $e->codes, true ) );

$e = cc_run_case( array( 'user_login' => '', 'user_email' => '' ) );
cc_check( $pass, $fail, '空字段 → 拦截', $e->has_errors() );

$e = cc_run_case( array( 'user_login' => 'alice@example.com', 'user_email' => 'alice@example.com' ) );
cc_check( $pass, $fail, '用户名栏填邮箱 → 拦截', in_array( 'invalid_username_email', $e->codes, true ) );

$e = cc_run_case( array() );
cc_check( $pass, $fail, '纯编程调用（无 user_login）→ 跳过不介入', ! $e->has_errors() );

$e = cc_run_case( array( 'user_login' => 'nobody', 'user_email' => 'nobody@example.com' ) );
cc_check( $pass, $fail, '未知用户 → 拦截（提示不泄露账号存在性）', in_array( 'mismatched_username_email', $e->codes, true ) );

echo "== {$pass} passed, {$fail} failed ==\n";
exit( $fail ? 1 : 0 );
