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

# 1. 直接 DROP DATABASE 删除数据库
echo "1. 正在删除/清空本地区域数据库 (${DB_NAME}) ..."
mariadb -e "DROP DATABASE IF EXISTS \`${DB_NAME}\`; CREATE DATABASE \`${DB_NAME}\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || \
mariadb -u"${DB_NAME}" -e "DROP DATABASE IF EXISTS \`${DB_NAME}\`;" 2>/dev/null || true
echo "✅ 数据库 (${DB_NAME}) 已直接 DROP 彻底删除。"

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
