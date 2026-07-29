#!/usr/bin/env bash
# ==============================================================================
# 从线上拉取【纯用户数据】至 localhost 脚本
# 说明：仅拉取数据库内容（文章、配置、数据表）和 wp-content/uploads/ 媒体库文件
# 绝对不拉取或覆盖任何第三方的 Theme/Plugin 代码，也绝对不改动二次开发 Git 代码。
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REMOTE_HOST="Chinacongress"
REMOTE_PATH="/var/www/chinacongress"
LOCAL_WEB_PATH="/srv/http/my_site_name"

echo "=========================================="
echo "🔄 开始从线上拉取【纯用户数据】同步到 localhost..."
echo "=========================================="

# 1. 增量同步线上媒体资源文件 wp-content/uploads/
echo "1. 正在增量同步线上媒体资源库 (wp-content/uploads/) ..."
mkdir -p "${LOCAL_WEB_PATH}/wp-content/uploads"
rsync -avz --no-o --no-g \
  "${REMOTE_HOST}:${REMOTE_PATH}/wp-content/uploads/" \
  "${LOCAL_WEB_PATH}/wp-content/uploads/"
echo "✅ 媒体文件增量同步完成。"

# 2. 导出线上 MySQL 数据库并通过 Web 管道免密导入本地 DB
echo "2. 正在通过 SSH 导出线上数据库并一键导入本地 localhost 数据库..."
ssh "${REMOTE_HOST}" "mysqldump -uchinacongress -p*** chinacongress" > "${LOCAL_WEB_PATH}/dump.sql"

if [ -s "${LOCAL_WEB_PATH}/dump.sql" ]; then
    chmod 666 "${LOCAL_WEB_PATH}/dump.sql"
    
    # 写入全量相对 URL 修正 Web 导入中转脚本
    cat << 'EOF' > "${LOCAL_WEB_PATH}/import_data.php"
<?php
$dump_file = __DIR__ . '/dump.sql';
if ( ! file_exists( $dump_file ) || filesize( $dump_file ) === 0 ) {
    die( "❌ 待导入的 SQL 文件不存在或为空！\n" );
}

$mysqli = new mysqli( 'localhost', 'chinacongress', '', 'chinacongress' );
if ( $mysqli->connect_error ) {
    $mysqli = new mysqli( 'localhost', 'root', '', 'chinacongress' );
}

if ( $mysqli->connect_error ) {
    die( "❌ 数据库连接失败: " . $mysqli->connect_error . "\n" );
}

$mysqli->set_charset( 'utf8mb4' );
$mysqli->query( 'SET FOREIGN_KEY_CHECKS = 0' );

$sql_content = file_get_contents( $dump_file );
$queries = explode( ";\n", $sql_content );
$count = 0;
foreach ( $queries as $query ) {
    $q = trim( $query );
    if ( ! empty( $q ) ) {
        if ( $mysqli->query( $q ) ) {
            $count++;
        }
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
    
    // 普通路径剥离域名
    $mysqli->query( "UPDATE wp_options SET option_value = REPLACE(option_value, '{$d_esc}', '')" );
    $mysqli->query( "UPDATE wp_posts SET post_content = REPLACE(post_content, '{$d_esc}', '')" );
    $mysqli->query( "UPDATE wp_posts SET guid = REPLACE(guid, '{$d_esc}', '')" );
    $mysqli->query( "UPDATE wp_postmeta SET meta_value = REPLACE(meta_value, '{$d_esc}', '')" );

    // JSON / Serialized 字符串剥离域名
    $d_slash = str_replace( '/', '\\/', $domain );
    $d_slash_esc = $mysqli->real_escape_string( $d_slash );
    $mysqli->query( "UPDATE wp_options SET option_value = REPLACE(option_value, '{$d_slash_esc}', '')" );
    $mysqli->query( "UPDATE wp_posts SET post_content = REPLACE(post_content, '{$d_slash_esc}', '')" );
    $mysqli->query( "UPDATE wp_postmeta SET meta_value = REPLACE(meta_value, '{$d_slash_esc}', '')" );
}

$mysqli->query( "UPDATE wp_options SET option_value='http://localhost' WHERE option_name IN ('siteurl', 'home')" );
$mysqli->query( 'SET FOREIGN_KEY_CHECKS = 1' );

@unlink( $dump_file );
@unlink( __FILE__ );

echo "SUCCESS_IMPORTED_" . $count . "_QUERIES";
EOF

    echo "正在将数据库一键导入本地 MySQL (chinacongress) ..."
    curl -s "http://localhost/import_data.php"
    echo ""
    echo "✅ 线上数据库导入及全量相对 URL 修正完成。"
else
    echo "❌ 线上数据库导出失败，请检查连接。"
    exit 1
fi

echo "------------------------------------------"
echo "🎉 线上【纯用户数据】成功同步至 localhost！"
echo "=========================================="
