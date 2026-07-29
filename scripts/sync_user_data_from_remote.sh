#!/usr/bin/env bash
# ==============================================================================
# 从线上拉取【纯用户数据】至 localhost 脚本
# 说明：仅拉取数据库内容（文章、配置、数据表）和 wp-content/uploads/ 媒体库文件
# 绝对不拉取或覆盖任何第三方的 Theme/Plugin 代码，也绝对不改动二次开发 Git 代码。
# ==============================================================================

set -euo pipefail

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

# 2. 导出线上 MySQL 数据库并导入本地 DB
TEMP_DUMP="/tmp/remote_db_dump.sql"
echo "2. 正在通过 SSH 导出线上数据库并导入本地 localhost 数据库..."
ssh "${REMOTE_HOST}" "mysqldump -uchinacongress -p*** chinacongress" > "${TEMP_DUMP}"

if [ -s "${TEMP_DUMP}" ]; then
    echo "正在将数据库导入本地 MySQL (chinacongress) ..."
    mariadb -u chinacongress chinacongress < "${TEMP_DUMP}" 2>/dev/null || mysql -u chinacongress chinacongress < "${TEMP_DUMP}"
    
    # 替换本地域名 URL (将 https://chinacongress.net 替换为 http://localhost)
    mariadb -u chinacongress chinacongress -e "UPDATE wp_options SET option_value='http://localhost' WHERE option_name IN ('siteurl', 'home');" 2>/dev/null || true
    
    rm -f "${TEMP_DUMP}"
    echo "✅ 线上数据库导入及本地 localhost 域名修正完成。"
else
    echo "❌ 线上数据库导出失败，请检查连接。"
    exit 1
fi

echo "------------------------------------------"
echo "🎉 线上【纯用户数据】成功同步至 localhost！"
echo "=========================================="
