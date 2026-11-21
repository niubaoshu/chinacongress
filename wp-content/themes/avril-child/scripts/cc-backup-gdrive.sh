#!/usr/bin/env bash
# ==============================================================================
# China Congress 生产环境每日全量/增量灾备脚本 (至 Google Drive)
# 位置：/usr/local/sbin/cc-backup-gdrive.sh
# 说明：
#   1. db/                 -> MySQL 无锁每日全量快照 (保留 30 天)
#   2. secrets-and-config/ -> wp-config.php + Nginx 配置 + 环境清单 (保留 30 天)
#   3. uploads/            -> wp-content/uploads/ 增量同步 + archive 防误删 (保留 30 天)
#   4. wp-code/            -> themes/ + plugins/ 增量同步
# ==============================================================================

set -euo pipefail

export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"

REMOTE_NAME="gdrive"
REMOTE_ROOT="${REMOTE_NAME}:chinacongress-backups"
WP_ROOT="/var/www/chinacongress"
DB_NAME="chinacongress"
RETENTION_DAYS=30
ALERT_EMAIL="niubaoshu@gmail.com"
DATE_TAG="$(date +'%Y%m%d')"
TMP_DIR="$(mktemp -d /tmp/cc_gdrive_backup.XXXXXX)"
chmod 700 "${TMP_DIR}"

LOG_FILE="/var/log/cc-backup-gdrive.log"
exec > >(tee -a "${LOG_FILE}") 2>&1

log() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] $*"
}

send_alert() {
    local exit_code="$1"
    local tail_log
    tail_log="$(tail -n 30 "${LOG_FILE}" 2>/dev/null || true)"
    wp --allow-root --path="${WP_ROOT}" eval '
        $subject = "[告警] ChinaCongress 每日 Google Drive 备份失败 (exit " . $args[0] . ")";
        $body    = "备份时间: " . date("Y-m-d H:i:s") . "\n主机: " . gethostname() . "\n\n最近日志:\n" . $args[1];
        wp_mail($args[2], $subject, $body);
    ' -- "${exit_code}" "${tail_log}" "${ALERT_EMAIL}" >/dev/null 2>&1 || true
}

cleanup() {
    local exit_code=$?
    rm -rf "${TMP_DIR}"
    if [ "${exit_code}" -ne 0 ]; then
        log "❌ 备份异常中断 (exit=${exit_code})，正在发送邮件告警..."
        send_alert "${exit_code}"
    fi
}
trap cleanup EXIT

log "=========================================="
log "🚀 开始执行 ChinaCongress 每日 Google Drive 备份 (${DATE_TAG})"

# 0. 前置检查：确认 rclone remote 已配置
if ! rclone listremotes | grep -qx "${REMOTE_NAME}:"; then
    log "❌ 未检测到 rclone remote '${REMOTE_NAME}:'，请先完成 Google Drive 授权。"
    exit 1
fi

# 1. 无锁导出数据库并压缩
DB_ARCHIVE="${TMP_DIR}/db_${DATE_TAG}.sql.gz"
log "1/5 正在无锁导出数据库 (${DB_NAME})..."
mysqldump --single-transaction --quick --routines --triggers "${DB_NAME}" | gzip -9 > "${DB_ARCHIVE}"
if [ ! -s "${DB_ARCHIVE}" ] || ! gzip -t "${DB_ARCHIVE}"; then
    log "❌ 数据库导出文件为空或压缩包损坏！"
    exit 1
fi
log "✅ 数据库导出完成: $(du -h "${DB_ARCHIVE}" | awk '{print $1}')"

# 2. 打包敏感配置、Nginx 配置与环境清单
CFG_STAGE="${TMP_DIR}/config_stage"
mkdir -p "${CFG_STAGE}/wordpress" "${CFG_STAGE}/nginx" "${CFG_STAGE}/letsencrypt"
log "2/5 正在收集 wp-config.php、Nginx 配置与运行环境清单..."

cp -a "${WP_ROOT}/wp-config.php" "${CFG_STAGE}/wordpress/"
[ -f "${WP_ROOT}/.htaccess" ] && cp -a "${WP_ROOT}/.htaccess" "${CFG_STAGE}/wordpress/"

[ -f /etc/nginx/nginx.conf ] && cp -a /etc/nginx/nginx.conf "${CFG_STAGE}/nginx/"
for f in /etc/nginx/conf.d/*.conf; do
    [ -f "$f" ] && cp -a "$f" "${CFG_STAGE}/nginx/"
done
[ -d /etc/letsencrypt/renewal ] && cp -a /etc/letsencrypt/renewal "${CFG_STAGE}/letsencrypt/"

{
    echo "Backup Date: $(date -Iseconds)"
    echo "Hostname: $(hostname -f 2>/dev/null || hostname)"
    echo "OS: $(grep '^PRETTY_NAME=' /etc/os-release | cut -d= -f2- | tr -d '"')"
    echo "PHP: $(php -v | head -n 1)"
    echo "Nginx: $(nginx -v 2>&1)"
    echo "MySQL: $(mysqldump --version)"
    echo "WordPress: $(wp --allow-root --path="${WP_ROOT}" core version 2>/dev/null || echo unknown)"
    echo ""
    echo "=== Plugins ==="
    wp --allow-root --path="${WP_ROOT}" plugin list --format=table 2>/dev/null || true
    echo ""
    echo "=== Themes ==="
    wp --allow-root --path="${WP_ROOT}" theme list --format=table 2>/dev/null || true
} > "${CFG_STAGE}/inventory.txt"

CFG_ARCHIVE="${TMP_DIR}/config_${DATE_TAG}.tar.gz"
tar -czf "${CFG_ARCHIVE}" -C "${CFG_STAGE}" .
log "✅ 敏感与系统配置打包完成: $(du -h "${CFG_ARCHIVE}" | awk '{print $1}')"

# 3. 上传数据库与敏感配置快照
log "3/5 正在上传数据库与配置快照至 Google Drive..."
rclone copyto "${DB_ARCHIVE}" "${REMOTE_ROOT}/db/db_${DATE_TAG}.sql.gz" --retries 3 --low-level-retries 10
rclone copyto "${CFG_ARCHIVE}" "${REMOTE_ROOT}/secrets-and-config/config_${DATE_TAG}.tar.gz" --retries 3 --low-level-retries 10
log "✅ 数据库与配置快照上传完成"

# 4. 增量同步 uploads 
log "4/5 正在增量同步 wp-content/uploads/ (含防误删归档) ..."
rclone sync "${WP_ROOT}/wp-content/uploads/" "${REMOTE_ROOT}/uploads/current/"     --backup-dir "${REMOTE_ROOT}/uploads/archive/${DATE_TAG}/"     --fast-list --transfers 4 --checkers 8 --stats 30s --stats-one-line --retries 3 --low-level-retries 10


log "✅ 媒体库增量同步完成"

# 5. 滚动清理超过 30 天的历史快照与归档
log "5/5 正在清理超过 ${RETENTION_DAYS} 天的旧备份..."
rclone delete --min-age "${RETENTION_DAYS}d" "${REMOTE_ROOT}/db" || true
rclone delete --min-age "${RETENTION_DAYS}d" "${REMOTE_ROOT}/secrets-and-config" || true
if rclone lsf "${REMOTE_ROOT}/uploads/archive" >/dev/null 2>&1; then
    rclone delete --min-age "${RETENTION_DAYS}d" "${REMOTE_ROOT}/uploads/archive" || true
    rclone rmdirs "${REMOTE_ROOT}/uploads/archive" --leave-root || true
fi

log "🎉 ChinaCongress 每日 Google Drive 备份全部完成！"
log "=========================================="
