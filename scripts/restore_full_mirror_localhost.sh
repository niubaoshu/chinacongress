#!/usr/bin/env bash
# ==============================================================================
# 全自动一键完美复原 localhost 本地镜像脚本
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOCAL_WEB_ROOT="/srv/http/my_site_name"
REMOTE_HOST="Chinacongress"
REMOTE_PATH="/var/www/chinacongress"

echo "=========================================="
echo "🚀 开始全自动跑通【一键完美复原 localhost】流程..."
echo "=========================================="

mkdir -p "${LOCAL_WEB_ROOT}"

# 1. 从线上同步第三方标准框架代码 (WordPress 核心 + Avril 父主题 + 插件)
echo "1. 正在同步线上第三方框架代码 (WordPress Core + Avril 父主题 + 插件)..."
rsync -avz --no-o --no-g --omit-dir-times \
  --exclude='wp-content/uploads' \
  --exclude='wp-content/themes/avril-child' \
  --exclude='wp-config.php' \
  "${REMOTE_HOST}:${REMOTE_PATH}/" \
  "${LOCAL_WEB_ROOT}/"

# 2. 构造本地 wp-config.php 文件
echo "2. 正在生成本地 localhost 数据库配置文件 wp-config.php ..."
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

# 3. 从线上同步纯用户数据与媒体库
echo "3. 正在导入线上纯用户数据与媒体文件库..."
bash "${SCRIPT_DIR}/sync_user_data_from_remote.sh"

# 4. 从 Git 仓库同步部署二次开发代码 (avril-child)
echo "4. 正在部署二次开发 Git 仓库代码 (avril-child)..."
bash "${SCRIPT_DIR}/sync_custom_code.sh" localhost

echo "------------------------------------------"
echo "🎉 【全自动完美复原】流程 100% 成功完成！"
echo "🌐 请直接在浏览器中访问: http://localhost/"
echo "=========================================="
