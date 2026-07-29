<?php
// PHP CLI 数据库一键导入与域名全量修正脚本
$wp_config = '/srv/http/my_site_name/wp-config.php';
if ( ! file_exists( $wp_config ) ) {
    $wp_config = '/home/niubaoshu/work/chinacongress/chinacongress.net/public_html/wp-config.php';
}

require_once $wp_config;

$dump_file = isset( $argv[1] ) ? $argv[1] : '/tmp/remote_db_dump.sql';

if ( ! file_exists( $dump_file ) || filesize( $dump_file ) === 0 ) {
    die( "❌ 待导入的 SQL 文件不存在或为空！\n" );
}

$mysqli = new mysqli( DB_HOST, DB_USER, DB_PASSWORD, DB_NAME );
if ( $mysqli->connect_error ) {
    die( "❌ 数据库连接失败: " . $mysqli->connect_error . "\n" );
}

$mysqli->set_charset( 'utf8mb4' );
$mysqli->query( 'SET FOREIGN_KEY_CHECKS = 0' );

$sql_content = file_get_contents( $dump_file );
$queries = explode( ";\n", $sql_content );
foreach ( $queries as $query ) {
    $q = trim( $query );
    if ( ! empty( $q ) ) {
        $mysqli->query( $q );
    }
}

// 自动全量修正本地域名与协议 URL (处理普通 URL 及 JSON/Serialized 转义 URL)
$domains = array(
    'https://chinacongress.net',
    'http://chinacongress.net',
    'https://www.chinacongress.net',
    'http://www.chinacongress.net',
    'https:\/\/chinacongress.net',
    'http:\/\/chinacongress.net',
    'https:\/\/www.chinacongress.net',
    'http:\/\/www.chinacongress.net'
);

foreach ( $domains as $domain ) {
    $target = ( strpos( $domain, '\\' ) !== false ) ? 'http:\/\/localhost' : 'http://localhost';
    $d_esc = $mysqli->real_escape_string( $domain );
    $t_esc = $mysqli->real_escape_string( $target );

    $mysqli->query( "UPDATE wp_options SET option_value = REPLACE(option_value, '{$d_esc}', '{$t_esc}')" );
    $mysqli->query( "UPDATE wp_posts SET post_content = REPLACE(post_content, '{$d_esc}', '{$t_esc}')" );
    $mysqli->query( "UPDATE wp_posts SET guid = REPLACE(guid, '{$d_esc}', '{$t_esc}')" );
    $mysqli->query( "UPDATE wp_postmeta SET meta_value = REPLACE(meta_value, '{$d_esc}', '{$t_esc}')" );
}

$mysqli->query( "UPDATE wp_options SET option_value='http://localhost' WHERE option_name IN ('siteurl', 'home')" );
$mysqli->query( 'SET FOREIGN_KEY_CHECKS = 1' );

echo "✅ 数据库全量数据及本地域名修正导入成功！\n";
