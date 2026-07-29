#!/usr/bin/env bash
# ==============================================================================
# 纯洁全新安装与全自动一键复原脚本 (Fresh Install Restorer)
# 说明：
#   1. WordPress 核心、Avril 父主题及第三方插件全由官方源全新一键下载安装；
#   2. 绝不从服务器抓取任何第三方代码；
#   3. 仅从线上拉取纯用户数据 (数据库 SQL + wp-content/uploads/ 媒体包)；
#   4. 二次开发代码纯粹由本地 Git 仓库部署。
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CHILD_THEME_SRC="$(cd "${SCRIPT_DIR}/.." && pwd)"
LOCAL_WEB_ROOT="/srv/http/my_site_name"
TEMP_DIR="/tmp/wp_fresh_install"

echo "=========================================="
echo "🚀 开始执行【纯洁全新安装 + 纯数据复原】流程..."
echo "=========================================="

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

# 3. 全新从官方插件库下载安装 Clever Fox 官方组件插件
echo "3. 正在从 WordPress 官方插件库全新下载并安装 Clever Fox 插件..."
mkdir -p "${LOCAL_WEB_ROOT}/wp-content/plugins"
curl -sL "https://downloads.wordpress.org/plugin/clever-fox.zip" -o "${TEMP_DIR}/clever-fox.zip"
unzip -q -o "${TEMP_DIR}/clever-fox.zip" -d "${LOCAL_WEB_ROOT}/wp-content/plugins/"
echo "✅ 官方 Clever Fox 插件全新安装完成。"

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
define( 'SECURE_AUTH_SALT', '8MxYA2hvJ]&R&? Ql$UJ24hpXRPzj^~IWE1WppXaX/#mdh#>eASpfYa|=sB.ddQD' );
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

# 5. 从线上同步纯用户数据与媒体包
echo "5. 正在导入线上纯用户数据与 wp-content/uploads/ 媒体库..."
bash "${SCRIPT_DIR}/sync_user_data_from_remote.sh"

# 6. 从本地 Git 仓库部署二次开发代码 (avril-child)
echo "6. 正在部署二次开发 Git 仓库代码 (avril-child)..."
bash "${SCRIPT_DIR}/sync_custom_code.sh" localhost

# 清理临时下载包
rm -rf "${TEMP_DIR}"

echo "------------------------------------------"
echo "🎉 【全新官方框架 + 纯数据复原】100% 成功完成！"
echo "🌐 请直接在浏览器中访问: http://localhost/"
echo "=========================================="
