#!/usr/bin/env bash
# ==============================================================================
# 纯洁全新安装与全自动一键复原脚本 (Fresh Install & Data Restorer)
#
# 用法：
#   1. 指定备份文件恢复:
#      ./restore_full_mirror_localhost.sh <uploads上传包.tar.gz> <数据库备份.sql.gz|db_dump.sql>
#   2. 未输入参数时，自动读取本地最新备份目录恢复:
#      ./restore_full_mirror_localhost.sh
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CHILD_THEME_SRC="$(cd "${SCRIPT_DIR}/.." && pwd)"
LOCAL_WEB_ROOT="/srv/http/my_site_name"
BACKUP_BASE_DIR="/home/niubaoshu/work/chinacongress/chinacongress_data_backups"
TEMP_DIR="/tmp/wp_fresh_restore"

UPLOADS_FILE="${1:-}"
DB_FILE="${2:-}"

echo "=========================================="
echo "🚀 开始执行【纯洁全新安装 + 指定/最新数据复原】流程..."
echo "=========================================="

# 0. 参数判定与本地最新备份自动寻找逻辑
if [ -z "${UPLOADS_FILE}" ] || [ -z "${DB_FILE}" ]; then
    echo "ℹ️ 未指定备份文件，正在寻找本地最新备份目录 (${BACKUP_BASE_DIR}) ..."
    
    LATEST_BACKUP_DIR="$(find "${BACKUP_BASE_DIR}" -mindepth 1 -maxdepth 1 -type d | sort -r | head -n 1)"
    
    if [ -z "${LATEST_BACKUP_DIR}" ] || [ ! -d "${LATEST_BACKUP_DIR}" ]; then
        echo "⚠️ 本地未找到备份目录，正在从线上拉取最新数据..."
        bash "${SCRIPT_DIR}/sync_user_data_from_remote.sh"
        SKIP_LOCAL_RESTORE=true
    else
        echo "📂 找到最新本地备份目录: ${LATEST_BACKUP_DIR}"
        UPLOADS_FILE="$(find "${LATEST_BACKUP_DIR}" -name "uploads_*.tar.gz" -o -name "uploads*.tar.gz" | head -n 1)"
        DB_FILE="$(find "${LATEST_BACKUP_DIR}" -name "*.sql.gz" -o -name "*.sql" | head -n 1)"
        SKIP_LOCAL_RESTORE=false
    fi
else
    SKIP_LOCAL_RESTORE=false
fi

if [ "${SKIP_LOCAL_RESTORE:-false}" = "false" ]; then
    if [ ! -f "${UPLOADS_FILE}" ]; then
        echo "❌ 媒体上传包文件不存在: ${UPLOADS_FILE}"
        exit 1
    fi

    if [ ! -f "${DB_FILE}" ]; then
        echo "❌ 数据库 SQL 备份文件不存在: ${DB_FILE}"
        exit 1
    fi
    echo "📦 媒体上传包: ${UPLOADS_FILE}"
    echo "🗄️ 数据库 SQL 文件: ${DB_FILE}"
fi

mkdir -p "${LOCAL_WEB_ROOT}" "${TEMP_DIR}"

# 1. 全新从官方源下载并解压最新的纯洁 WordPress 核心
echo "1. 正在从 WordPress 官方源全新下载并安装纯洁 WordPress 核心..."
curl -sL "https://cn.wordpress.org/latest-zh_CN.tar.gz" | tar -xz -C "${LOCAL_WEB_ROOT}" --strip-components=1
echo "✅ 官方 WordPress 核心全新安装完成。"

# 2. 全新从官方主题库下载安装 Avril 父主题
echo "2. 正在从 WordPress 官方主题库全新下载并安装 Avril 父主题..."
mkdir -p "${LOCAL_WEB_ROOT}/wp-content/themes"
curl -sL "https://downloads.wordpress.org/theme/avril.zip" -o "${TEMP_DIR}/avril.zip"
unzip -q -o "${TEMP_DIR}/avril.zip" -d "${LOCAL_WEB_ROOT}/wp-content/themes/"
echo "✅ 官方 Avril 父主题全新安装完成。"

# 3. 全新从官方插件库下载安装 Clever Fox 官方组件插件并补充子主题支持
echo "3. 正在从 WordPress 官方插件库全新下载并安装 Clever Fox 插件..."
mkdir -p "${LOCAL_WEB_ROOT}/wp-content/plugins"
curl -sL "https://downloads.wordpress.org/plugin/clever-fox.zip" -o "${TEMP_DIR}/clever-fox.zip"
unzip -q -o "${TEMP_DIR}/clever-fox.zip" -d "${LOCAL_WEB_ROOT}/wp-content/plugins/"

if [ -f "${LOCAL_WEB_ROOT}/wp-content/plugins/clever-fox/clever-fox.php" ]; then
    sed -i "s/'Avril' == \$cleverfox_theme->name/'Avril' == \$cleverfox_theme->name || 'Avril' == \$cleverfox_theme->template || 'avril' == \$cleverfox_theme->template/g" "${LOCAL_WEB_ROOT}/wp-content/plugins/clever-fox/clever-fox.php"
fi
echo "✅ 官方 Clever Fox 插件全新安装及子主题兼容配置完成。"

# 4. 生成本地 wp-config.php 配置文件
echo "4. 正在生成本地 localhost 数据库配置文件 wp-config.php ..."
cat << 'EOF' > "${LOCAL_WEB_ROOT}/wp-config.php"
<?php
define( 'DB_NAME', 'chinacongress' );
define( 'DB_USER', 'chinacongress' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

define( 'AUTH_KEY',         'eyPPyg+c*|wamN_`ubaY]o9@#aE|{je,Iy)3)K#yKhV|1wv0]A@W/&[69@5? Q)l' );
define( 'SECURE_AUTH_KEY',  'w)P]TL^X^a-z!zG#7j:1f.eD<6-#L_!`3oOfMMm{hNs(TO%bWy/|&/Ov[hv%,>y2' );
define( 'LOGGED_IN_KEY',    'Df 7mk/,lT: N(Gt8+0EO6Q]`HO 28Bw{R[ZD}2?|(3vtZ8._58L?JC[Y5iH@81j' );
define( 'NONCE_KEY',        'ynsw]lrQNt[ScIBCt?}Zr}04WbsOHjUO?V!{d}[q4J~brMCK3dwk_~;iEKL9{_!7' );
define( 'AUTH_SALT',        'z8/?4iuE)`LMB)[>$sc{%!9}<4Q({nJ8q`;Jk2:q.&Q<M8/fVlT>P%R+:&w@5s0$' );
define( 'SECURE_SALT',      '8MxYA2hvJ]&R&? Ql$UJ24hpXRPzj^~IWE1WppXaX/#mdh#>eASpfYa|=sB.ddQD' );
define( 'LOGGED_IN_SALT',   '$4|5G];6/&Ib99)dtqVs)PhQ0I&TeM6B|0_1wUGxT]w1!eH!pA|@dnj^ -?U88JU' );
define( 'NONCE_SALT',       ' FT>s+ICiA0%mr|1.[+Q:!O%<ZCG2sQ/Wx,,G045L003R~&}Hk~X3nxOxoFtuH^I' );

$table_prefix = 'wp_';
define( 'WP_DEBUG', false );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
EOF
echo "✅ wp-config.php 配置生成完成。"

# 5. 恢复媒体文件与数据库文件
if [ "${SKIP_LOCAL_RESTORE:-false}" = "false" ]; then
    echo "5.1 正在解压导入媒体上传库 wp-content/uploads/ ..."
    mkdir -p "${LOCAL_WEB_ROOT}/wp-content/uploads"
    tar -xzf "${UPLOADS_FILE}" -C "${LOCAL_WEB_ROOT}/wp-content/uploads/" --strip-components=1 || tar -xzf "${UPLOADS_FILE}" -C "${LOCAL_WEB_ROOT}/wp-content/"
    echo "✅ 媒体文件解压导入完成。"

    echo "5.2 正在准备数据库 SQL 文件并解压处理..."
    if [[ "${DB_FILE}" == *.gz ]]; then
        gunzip -c "${DB_FILE}" > "${LOCAL_WEB_ROOT}/dump.sql"
    else
        cp "${DB_FILE}" "${LOCAL_WEB_ROOT}/dump.sql"
    fi
    chmod 666 "${LOCAL_WEB_ROOT}/dump.sql"

    # 生成全量域名修正免密 Web 导入管道脚本
    cat << 'EOF' > "${LOCAL_WEB_ROOT}/import_data.php"
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

@unlink( $dump_file );
@unlink( __FILE__ );

echo "SUCCESS_IMPORTED_" . $count . "_QUERIES";
EOF

    echo "正在将数据库一键导入本地 MySQL (chinacongress) ..."
    curl -s "http://localhost/import_data.php"
    echo ""
    echo "✅ 本地数据库导入及本地域名全量修正完成。"
fi

# 6. 从本地 Git 仓库部署二次开发代码 (avril-child)
echo "6. 正在部署二次开发 Git 仓库代码 (avril-child)..."
bash "${SCRIPT_DIR}/sync_custom_code.sh" localhost

# 7. 修正数据库 Customizer 配置兼容性
php -r "
require '${LOCAL_WEB_ROOT}/wp-config.php';
\$m = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
\$r = \$m->query(\"SELECT option_value FROM wp_options WHERE option_name='theme_mods_avril'\")->fetch_assoc();
if (!empty(\$r['option_value'])) {
    \$mods = unserialize(\$r['option_value']);
    \$mods['hs_slider'] = '1';
    \$mods['hs_service'] = '1';
    \$mods['hs_cta'] = '1';
    \$mods['hs_feature'] = '1';
    \$mods['hs_blog'] = '1';
    \$val = \$m->real_escape_string(serialize(\$mods));
    \$m->query(\"UPDATE wp_options SET option_value='\" . \$val . \"' WHERE option_name IN ('theme_mods_avril', 'theme_mods_avril-child')\");
    \$m->query(\"INSERT INTO wp_options (option_name, option_value, autoload) VALUES ('theme_mods_avril-child', '\" . \$val . \"', 'yes') ON DUPLICATE KEY UPDATE option_value='\" . \$val . \"'\");
}
"

# 清理临时目录
rm -rf "${TEMP_DIR}"

echo "------------------------------------------"
echo "🎉 【全新官方框架 + 数据复原】100% 成功完成！"
echo "🌐 请直接在浏览器中访问: http://localhost/"
echo "=========================================="
