<?php
// PHP CLI 数据库一键导入与相对 URL 全量转换脚本
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

// 自动全量将本站点绝对 URL 转换为无协议/无域名的纯相对路径 (保留二级域名如 reg.chinacongress.net)
$domains = array(
    'https://chinacongress.net',
    'http://chinacongress.net',
    'https://www.chinacongress.net',
    'http://www.chinacongress.net',
    'http://localhost'
);

foreach ( $domains as $domain ) {
    $d_esc = $mysqli->real_escape_string( $domain );
    
    // 普通路径剥离域名 (只针对 wp_posts 和 wp_postmeta)
    $mysqli->query( "UPDATE wp_posts SET post_content = REPLACE(post_content, '{$d_esc}', '')" );
    $mysqli->query( "UPDATE wp_posts SET guid = REPLACE(guid, '{$d_esc}', '')" );
    $mysqli->query( "UPDATE wp_postmeta SET meta_value = REPLACE(meta_value, '{$d_esc}', '')" );

    // JSON / Escaped 字符串剥离域名
    $d_slash = str_replace( '/', '\\/', $domain );
    $d_slash_esc = $mysqli->real_escape_string( $d_slash );
    $mysqli->query( "UPDATE wp_posts SET post_content = REPLACE(post_content, '{$d_slash_esc}', '')" );
    $mysqli->query( "UPDATE wp_postmeta SET meta_value = REPLACE(meta_value, '{$d_slash_esc}', '')" );
}

// 修复 wp_options 中序列化字符串长度并保持设置完整
$res_opt = $mysqli->query( "SELECT option_name, option_value FROM wp_options WHERE option_name LIKE 'theme_mods_%' OR option_name LIKE 'sidebars_%'" );
if ( $res_opt ) {
    while ( $row_opt = $res_opt->fetch_assoc() ) {
        $opt_val = $row_opt['option_value'];
        $fixed_val = preg_replace_callback( '!s:(\d+):"(.*?)";!s', function( $m ) {
            return 's:' . strlen( $m[2] ) . ':"' . $m[2] . '"';
        }, $opt_val );
        
        $opt_name = $mysqli->real_escape_string( $row_opt['option_name'] );
        $fixed_esc = $mysqli->real_escape_string( $fixed_val );
        $mysqli->query( "UPDATE wp_options SET option_value='{$fixed_esc}' WHERE option_name='{$opt_name}'" );
    }
}

// 同步 theme_mods_avril 给子主题 theme_mods_avril-child
$res_avril = $mysqli->query( "SELECT option_value FROM wp_options WHERE option_name='theme_mods_avril'" );
if ( $res_avril && $row_avril = $res_avril->fetch_assoc() ) {
    $avril_esc = $mysqli->real_escape_string( $row_avril['option_value'] );
    $mysqli->query( "INSERT INTO wp_options (option_name, option_value, autoload) VALUES ('theme_mods_avril-child', '{$avril_esc}', 'yes') ON DUPLICATE KEY UPDATE option_value='{$avril_esc}'" );
}

$mysqli->query( "UPDATE wp_options SET option_value='http://localhost' WHERE option_name IN ('siteurl', 'home')" );
$mysqli->query( 'SET FOREIGN_KEY_CHECKS = 1' );

echo "✅ 数据库全量导入及相对 URL 转换完成！\n";
