#!/usr/bin/env bash
# ==============================================================================
# 纯用户数据一键备份脚本 (Supports localhost & production)
# 说明：备份纯用户数据（数据库内容 SQL + wp-content/uploads/ 媒体库）
# 备份输出目录：/home/niubaoshu/work/chinacongress/chinacongress_data_backups/
# 用法：
#   ./scripts/backup_user_data.sh            # 默认备份本地 localhost 数据
#   ./scripts/backup_user_data.sh production # 一键备份线上生产环境数据
# ==============================================================================

set -euo pipefail

TARGET="${1:-localhost}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_BASE="/home/niubaoshu/work/chinacongress/chinacongress_data_backups"
TIMESTAMP="$(date +'%Y%m%d_%H%M%S')"

if [ "${TARGET}" = "production" ] || [ "${TARGET}" = "remote" ]; then
    TARGET_DIR="${BACKUP_BASE}/remote_production_backup_${TIMESTAMP}"
    mkdir -p "${TARGET_DIR}"

    echo "=========================================="
    echo "🌐 开始执行【线上生产服务器 Chinacongress】纯用户数据一键备份..."
    echo "=========================================="

    # 1. 导出线上 MySQL 数据库
    DB_FILE="${TARGET_DIR}/db_production_${TIMESTAMP}.sql"
    echo "1. 正在通过 SSH 导出线上数据库 ..."
    ssh Chinacongress "mysqldump -uchinacongress -p*** chinacongress" > "${DB_FILE}"

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

else
    TARGET_DIR="${BACKUP_BASE}/local_backup_${TIMESTAMP}"
    mkdir -p "${TARGET_DIR}"

    echo "=========================================="
    echo "📦 开始执行【本地 localhost】纯用户数据一键备份..."
    echo "=========================================="

    # 1. 导出本地 MySQL 数据库
    DB_FILE="${TARGET_DIR}/db_localhost_${TIMESTAMP}.sql"
    echo "1. 正在一键导出本地数据库 ..."
    php "${SCRIPT_DIR}/db_export.php" "${DB_FILE}"
    gzip -f "${DB_FILE}"
    echo "✅ 本地数据库压缩打包完成：${DB_FILE}.gz"

    # 2. 打包本地 wp-content/uploads/ 媒体资源
    UPLOADS_SRC="/srv/http/my_site_name/wp-content/uploads"
    if [ ! -d "${UPLOADS_SRC}" ]; then
        UPLOADS_SRC="/home/niubaoshu/work/chinacongress/chinacongress.net/public_html/wp-content/uploads"
    fi

    if [ -d "${UPLOADS_SRC}" ]; then
        echo "2. 正在打包本地媒体库 (wp-content/uploads/) ..."
        tar -czf "${TARGET_DIR}/uploads_localhost_${TIMESTAMP}.tar.gz" -C "${UPLOADS_SRC}" .
        echo "✅ 本地媒体文件打包完成：${TARGET_DIR}/uploads_localhost_${TIMESTAMP}.tar.gz"
    fi

    echo "------------------------------------------"
    echo "🎉 【本地 localhost 纯用户数据】备份成功！"
    echo "📂 存放位置: ${TARGET_DIR}"
    echo "=========================================="
fi
