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
LOCAL_DB_NAME="${LOCAL_DB_NAME:-chinacongress}"
LOCAL_DB_USER="${LOCAL_DB_USER:-chinacongress}"
LOCAL_DB_PASS="${LOCAL_DB_PASS:-}"

echo "4. 正在生成本地 localhost 数据库配置文件 wp-config.php ..."
cat << 'EOF' > "${LOCAL_WEB_ROOT}/wp-config.php"
<?php
define( 'DB_NAME', '__DB_NAME__' );
define( 'DB_USER', '__DB_USER__' );
define( 'DB_PASSWORD', '__DB_PASS__' );
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
define( 'FS_METHOD', 'direct' );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
EOF

sed -i "s/__DB_NAME__/${LOCAL_DB_NAME}/g" "${LOCAL_WEB_ROOT}/wp-config.php"
sed -i "s/__DB_USER__/${LOCAL_DB_USER}/g" "${LOCAL_WEB_ROOT}/wp-config.php"
sed -i "s/__DB_PASS__/${LOCAL_DB_PASS}/g" "${LOCAL_WEB_ROOT}/wp-config.php"
echo "✅ wp-config.php 配置生成完成。"

# 5. 恢复媒体文件与数据库文件
if [ "${SKIP_LOCAL_RESTORE:-false}" = "false" ]; then
    echo "5.1 正在解压导入媒体上传库 wp-content/uploads/ ..."
    mkdir -p "${LOCAL_WEB_ROOT}/wp-content/uploads"
    tar -xzf "${UPLOADS_FILE}" -C "${LOCAL_WEB_ROOT}/wp-content/uploads/" --strip-components=1 || tar -xzf "${UPLOADS_FILE}" -C "${LOCAL_WEB_ROOT}/wp-content/"
    echo "✅ 媒体文件解压导入完成。"

    echo "5.2 正在自动创建本地数据库与用户授权 (${LOCAL_DB_USER})..."
    # 使用 sudo mariadb 或 root 免密创建数据库、创建用户并授权
    CREATE_DB_SQL="CREATE DATABASE IF NOT EXISTS \`${LOCAL_DB_NAME}\` DEFAULT CHARACTER SET utf8mb4; CREATE USER IF NOT EXISTS '${LOCAL_DB_USER}'@'localhost' IDENTIFIED BY '${LOCAL_DB_PASS}'; GRANT ALL PRIVILEGES ON \`${LOCAL_DB_NAME}\`.* TO '${LOCAL_DB_USER}'@'localhost'; FLUSH PRIVILEGES;"
    
    mariadb -u root -e "${CREATE_DB_SQL}" 2>/dev/null \
        || sudo mariadb -e "${CREATE_DB_SQL}" 2>/dev/null \
        || mariadb -u "${LOCAL_DB_USER}" -e "${CREATE_DB_SQL}" 2>/dev/null || true

    LOCAL_PASS_ARG=""
    if [ -n "${LOCAL_DB_PASS}" ]; then
        LOCAL_PASS_ARG="-p${LOCAL_DB_PASS}"
    fi

    # 定义 MariaDB 连接函数
    run_db() {
        mariadb -u "${LOCAL_DB_USER}" ${LOCAL_PASS_ARG} "${LOCAL_DB_NAME}" 2>/dev/null \
            || mariadb -u root "${LOCAL_DB_NAME}" 2>/dev/null \
            || sudo mariadb "${LOCAL_DB_NAME}"
    }

    echo "5.3 正在将数据库 SQL 文件导入本地 MariaDB (chinacongress) ..."
    # 判断格式并流式导入 SQL
    if [[ "${DB_FILE}" == *.gz ]]; then
        gunzip -c "${DB_FILE}" | run_db
    else
        run_db < "${DB_FILE}"
    fi

    echo "5.4 正在使用 MariaDB CLI 执行本地配置与域名同步..."
    run_db << 'SQL'
SET FOREIGN_KEY_CHECKS = 0;

-- 1. 设置本地站点 URL
UPDATE wp_options SET option_value='http://localhost' WHERE option_name IN ('siteurl', 'home');

-- 2. 确保激活 Avril-child 子主题
UPDATE wp_options SET option_value='avril' WHERE option_name='template';
UPDATE wp_options SET option_value='avril-child' WHERE option_name='stylesheet';

-- 3. 确保子主题 (avril-child) 在配置不存在时继承父主题 Customizer 配置
INSERT IGNORE INTO wp_options (option_name, option_value, autoload)
SELECT 'theme_mods_avril-child', option_value, autoload FROM wp_options WHERE option_name='theme_mods_avril';

SET FOREIGN_KEY_CHECKS = 1;
SQL

    echo "✅ 本地数据库创建、导入及配置同步成功。"
fi

# 6. 从本地 Git 仓库部署二次开发代码 (avril-child)
echo "6. 正在部署二次开发 Git 仓库代码 (avril-child)..."
bash "${SCRIPT_DIR}/sync_custom_code.sh" localhost

# 清理临时目录
rm -rf "${TEMP_DIR}"

echo "------------------------------------------"
echo "🎉 【全新官方框架 + 数据复原】100% 成功完成！"
echo "🌐 请直接在浏览器中访问: http://localhost/"
echo "=========================================="
