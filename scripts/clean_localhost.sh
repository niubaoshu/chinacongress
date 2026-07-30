#!/usr/bin/env bash
# ==============================================================================
# 本地 localhost 完全清空脚本 (Shell 版本)
# 作用: 一键清空本地 /srv/http/my_site_name 目录及 MariaDB 数据库 (chinacongress)
# ==============================================================================

set -euo pipefail

LOCAL_WEB_ROOT="/srv/http/my_site_name"
DB_NAME="chinacongress"
DB_USER="chinacongress"

echo "=========================================="
echo "🧹 开始彻底清空本地 localhost 环境..."
echo "=========================================="

# 1. 清空本地区域数据库所有表
echo "1. 正在清空本地区域数据库 (${DB_NAME}) 所有数据表..."

TABLES=$(mariadb -u"${DB_USER}" -D"${DB_NAME}" -e "SHOW TABLES;" 2>/dev/null | tail -n +2 || true)
if [ -z "${TABLES}" ]; then
    TABLES=$(mariadb -uroot -D"${DB_NAME}" -e "SHOW TABLES;" 2>/dev/null | tail -n +2 || true)
fi

if [ -n "${TABLES}" ]; then
    SQL="SET FOREIGN_KEY_CHECKS = 0;"
    for table in ${TABLES}; do
        SQL="${SQL} DROP TABLE IF EXISTS \`${table}\`;"
    done
    SQL="${SQL} SET FOREIGN_KEY_CHECKS = 1;"
    mariadb -u"${DB_USER}" -D"${DB_NAME}" -e "${SQL}" 2>/dev/null || mariadb -uroot -D"${DB_NAME}" -e "${SQL}" 2>/dev/null || true
    echo "✅ 数据库 (${DB_NAME}) 所有表已彻底清空。"
else
    echo "ℹ️ 数据库 (${DB_NAME}) 为空或表已存在清理状态。"
fi

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
