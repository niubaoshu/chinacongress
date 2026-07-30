#!/usr/bin/env bash
# ==============================================================================
# 线上纯用户数据一键备份脚本 (Production User Data Backup)
# 说明：一键备份线上生产服务器的纯用户数据（数据库 SQL + wp-content/uploads/ 媒体库）
# 备份输出目录：/home/niubaoshu/work/chinacongress/chinacongress_data_backups/
# 用法：
#   ./scripts/backup_user_data.sh
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_BASE="/home/niubaoshu/work/chinacongress/chinacongress_data_backups"
TIMESTAMP="$(date +'%Y%m%d_%H%M%S')"
TARGET_DIR="${BACKUP_BASE}/remote_production_backup_${TIMESTAMP}"

mkdir -p "${TARGET_DIR}"

echo "=========================================="
echo "🌐 开始执行【线上生产服务器 Chinacongress】纯用户数据一键备份..."
echo "=========================================="

# 1. 导出线上 MySQL 数据库
REMOTE_DB_USER="${REMOTE_DB_USER:-chinacongress}"
REMOTE_DB_PASS="${REMOTE_DB_PASS:-}"
REMOTE_DB_NAME="${REMOTE_DB_NAME:-chinacongress}"

DB_PASS_ARG=""
if [ -n "${REMOTE_DB_PASS}" ]; then
    DB_PASS_ARG="-p${REMOTE_DB_PASS}"
fi

DB_FILE="${TARGET_DIR}/db_production_${TIMESTAMP}.sql"
echo "1. 正在通过 SSH 导出线上数据库 ..."
ssh Chinacongress "mysqldump -u${REMOTE_DB_USER} ${DB_PASS_ARG} ${REMOTE_DB_NAME}" > "${DB_FILE}"

if [ -s "${DB_FILE}" ]; then
    gzip -f "${DB_FILE}"
    echo "✅ 线上数据库成功导出并压缩：${DB_FILE}.gz"
else
    echo "❌ 线上数据库导出失败！"
    exit 1
fi

# 2. 打包线上 wp-content/uploads/ 媒体库
echo "2. 正在通过 rsync 打包线上 wp-content/uploads/ 媒体库 ..."
mkdir -p "${TARGET_DIR}/uploads_temp"
rsync -avz --no-o --no-g Chinacongress:/var/www/chinacongress/wp-content/uploads/ "${TARGET_DIR}/uploads_temp/"
tar -czf "${TARGET_DIR}/uploads_production_${TIMESTAMP}.tar.gz" -C "${TARGET_DIR}/uploads_temp" .
rm -rf "${TARGET_DIR}/uploads_temp"
echo "✅ 线上媒体库成功打包：${TARGET_DIR}/uploads_production_${TIMESTAMP}.tar.gz"

echo "------------------------------------------"
echo "🎉 【线上生产环境纯用户数据】备份成功！"
echo "📂 存放位置: ${TARGET_DIR}"
echo "=========================================="
