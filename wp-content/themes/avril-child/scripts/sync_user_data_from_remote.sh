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
LOCAL_WEB_PATH="${LOCAL_WEB_PATH:-/srv/http/my_site_name}"
if [ ! -d "${LOCAL_WEB_PATH}" ] && [ -d "$(cd "${SCRIPT_DIR}/../.." && pwd)" ]; then
    LOCAL_WEB_PATH="$(cd "${SCRIPT_DIR}/../.." && pwd)"
fi

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

# 2. 导出线上 MySQL 数据库并一键导入本地 DB
REMOTE_DB_USER="${REMOTE_DB_USER:-chinacongress}"
REMOTE_DB_PASS="${REMOTE_DB_PASS:-}"
REMOTE_DB_NAME="${REMOTE_DB_NAME:-chinacongress}"

DB_PASS_ARG=""
if [ -n "${REMOTE_DB_PASS}" ]; then
    DB_PASS_ARG="-p${REMOTE_DB_PASS}"
fi

echo "2. 正在通过 SSH 导出线上数据库并一键导入本地 localhost 数据库..."
TEMP_DUMP="/tmp/remote_dump_$$.sql"
trap 'rm -f "${TEMP_DUMP}"' EXIT

ssh "${REMOTE_HOST}" "mysqldump -u${REMOTE_DB_USER} ${DB_PASS_ARG} ${REMOTE_DB_NAME}" > "${TEMP_DUMP}"

if [ -s "${TEMP_DUMP}" ]; then
    LOCAL_DB_NAME="${LOCAL_DB_NAME:-chinacongress}"
    LOCAL_DB_USER="${LOCAL_DB_USER:-chinacongress}"
    LOCAL_DB_PASS="${LOCAL_DB_PASS:-}"

    # 自动创建本地数据库与用户授权
    run_db_setup() {
        mariadb -u root -e "CREATE DATABASE IF NOT EXISTS \`${LOCAL_DB_NAME}\` DEFAULT CHARACTER SET utf8mb4; CREATE USER IF NOT EXISTS '${LOCAL_DB_USER}'@'localhost' IDENTIFIED BY '${LOCAL_DB_PASS}'; GRANT ALL PRIVILEGES ON \`${LOCAL_DB_NAME}\`.* TO '${LOCAL_DB_USER}'@'localhost'; FLUSH PRIVILEGES;" 2>/dev/null \
            || sudo mariadb -e "CREATE DATABASE IF NOT EXISTS \`${LOCAL_DB_NAME}\` DEFAULT CHARACTER SET utf8mb4; CREATE USER IF NOT EXISTS '${LOCAL_DB_USER}'@'localhost' IDENTIFIED BY '${LOCAL_DB_PASS}'; GRANT ALL PRIVILEGES ON \`${LOCAL_DB_NAME}\`.* TO '${LOCAL_DB_USER}'@'localhost'; FLUSH PRIVILEGES;" 2>/dev/null \
            || true
    }
    run_db_setup

    LOCAL_PASS_ARG=""
    if [ -n "${LOCAL_DB_PASS}" ]; then
        LOCAL_PASS_ARG="-p${LOCAL_DB_PASS}"
    fi

    run_db() {
        mariadb -u "${LOCAL_DB_USER}" ${LOCAL_PASS_ARG} "${LOCAL_DB_NAME}" 2>/dev/null \
            || mariadb -u root ${LOCAL_PASS_ARG} "${LOCAL_DB_NAME}" 2>/dev/null \
            || sudo mariadb "${LOCAL_DB_NAME}"
    }

    echo "正在导入本地 MariaDB 数据库..."
    run_db < "${TEMP_DUMP}"
    rm -f "${TEMP_DUMP}"

    echo "正在配置本地站点域名、导航菜单与主题配置..."
    run_db << 'SQL'
SET FOREIGN_KEY_CHECKS = 0;
UPDATE wp_options SET option_value='http://localhost' WHERE option_name IN ('siteurl', 'home');
UPDATE wp_options SET option_value='avril' WHERE option_name='template';
UPDATE wp_options SET option_value='avril-child' WHERE option_name='stylesheet';
INSERT INTO wp_options (option_name, option_value, autoload)
SELECT 'theme_mods_avril-child', option_value, autoload FROM wp_options WHERE option_name='theme_mods_avril'
ON DUPLICATE KEY UPDATE option_value=VALUES(option_value);
UPDATE wp_postmeta SET meta_value = REPLACE(meta_value, 'https://chinacongress.net', '') WHERE meta_key = '_menu_item_url';
UPDATE wp_postmeta SET meta_value = REPLACE(meta_value, 'http://chinacongress.net', '') WHERE meta_key = '_menu_item_url';
SET FOREIGN_KEY_CHECKS = 1;
SQL

    echo "✅ 线上数据库导入及本地域名/配置同步完成。"
else
    rm -f "${TEMP_DUMP}"
    echo "❌ 线上数据库导出失败，请检查连接或环境变量配置。"
    exit 1
fi

echo "------------------------------------------"
echo "🎉 线上【纯用户数据】成功同步至 localhost！"
echo "=========================================="
