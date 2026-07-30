#!/usr/bin/env bash
# ==============================================================================
# 本地 localhost 完全清空脚本 (Shell 版本)
# 作用: 一键 DROP 本地 MariaDB 数据库 (chinacongress) 及清空 /srv/http/my_site_name
# ==============================================================================

set -euo pipefail

LOCAL_WEB_ROOT="/srv/http/my_site_name"
DB_NAME="chinacongress"

echo "=========================================="
echo "🧹 开始彻底清空本地 localhost 环境..."
echo "=========================================="

# 1. 直接 DROP DATABASE 删除数据库并删除专用用户
echo "1. 正在彻底删除本地数据库 (${DB_NAME}) 及专用数据库用户 (${DB_NAME})..."
DROP_SQL="DROP DATABASE IF EXISTS \`${DB_NAME}\`; DROP USER IF EXISTS '${DB_NAME}'@'localhost'; FLUSH PRIVILEGES;"

mariadb -e "${DROP_SQL}" 2>/dev/null \
    || sudo mariadb -e "${DROP_SQL}" 2>/dev/null \
    || mysql -u root -e "${DROP_SQL}" 2>/dev/null || true
echo "✅ 数据库 (${DB_NAME}) 及专用用户 (${DB_NAME}) 已彻底删除。"

# 2. 清空本地 Web 目录
echo "2. 正在清空本地 Web 目录: ${LOCAL_WEB_ROOT} ..."
if [ -d "${LOCAL_WEB_ROOT}" ]; then
    find "${LOCAL_WEB_ROOT}" -mindepth 1 -delete 2>/dev/null || rm -rf "${LOCAL_WEB_ROOT}"/* "${LOCAL_WEB_ROOT}"/.* 2>/dev/null || true
    echo "✅ 本地 Web 目录文件已全部彻底清除！"
else
    echo "⚠️ 本地 Web 目录 (${LOCAL_WEB_ROOT}) 不存在，跳过该步骤。"
fi

echo "=========================================="
echo "🎉 本地环境彻底清空完毕！"
echo "=========================================="
