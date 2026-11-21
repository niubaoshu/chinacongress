#!/usr/bin/env bash
# ==============================================================================
# Google Drive 极简灾备 → 本地原样重建脚本 (Disaster Recovery Drill)
#
# 说明：仅依赖 Google Drive 的 3 类不可再生备份资产 (db + secrets-and-config + uploads)
#       + WordPress.org 官方纯洁核心/主题/插件 (按 inventory.txt 自动还原)
#       + 本仓库 Git 托管的自研代码 (avril-child 与 cc-* 插件)，
#       在本机 /srv/http/my_site_name 100% 原样重建站点。
#
# 用法：
#   bash wp-content/themes/avril-child/scripts/restore_from_gdrive_localhost.sh [备份根目录]
#   默认备份根目录：~/GoogleDrive/chinacongress-backups
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../../../.." && pwd)"
GDRIVE_BACKUP_ROOT="${1:-${HOME}/GoogleDrive/chinacongress-backups}"
LOCAL_WEB_ROOT="/srv/http/my_site_name"
LOCAL_DB_NAME="${LOCAL_DB_NAME:-chinacongress}"
LOCAL_DB_USER="${LOCAL_DB_USER:-chinacongress}"
LOCAL_DB_PASS="${LOCAL_DB_PASS:-}"
TMP_DIR="$(mktemp -d /tmp/wp_gdrive_restore.XXXXXX)"
trap 'rm -rf "${TMP_DIR}"' EXIT

echo "=========================================="
echo "🧪 开始执行【Google Drive 极简灾备 → 本地原样重建】"
echo "📂 备份源目录: ${GDRIVE_BACKUP_ROOT}"
echo "=========================================="

# 1. 校验 3 类核心备份资产
DB_FILE="$(find "${GDRIVE_BACKUP_ROOT}/db" -maxdepth 1 -name 'db_*.sql.gz' 2>/dev/null | sort -r | head -n 1)"
CFG_FILE="$(find "${GDRIVE_BACKUP_ROOT}/secrets-and-config" -maxdepth 1 -name 'config_*.tar.gz' 2>/dev/null | sort -r | head -n 1)"

for path in "${DB_FILE}" "${CFG_FILE}" "${GDRIVE_BACKUP_ROOT}/uploads/current"; do
    if [ -z "${path}" ] || [ ! -e "${path}" ]; then
        echo "❌ 备份资产不完整，缺失: ${path:-<empty>}"
        exit 1
    fi
done

echo "🗄️ 最新数据库快照: $(basename "${DB_FILE}")"
echo "🔐 最新配置归档包: $(basename "${CFG_FILE}")"

# 2. 清空并下载纯洁 WordPress 官方核心
echo "1/6 正在准备本地目录并下载纯洁 WordPress 官方核心..."
mkdir -p "${LOCAL_WEB_ROOT}"
find "${LOCAL_WEB_ROOT}" -mindepth 1 -delete 2>/dev/null \
    || rm -rf "${LOCAL_WEB_ROOT}"/* "${LOCAL_WEB_ROOT}"/.[!.]* "${LOCAL_WEB_ROOT}"/..?* 2>/dev/null \
    || sudo find "${LOCAL_WEB_ROOT}" -mindepth 1 -delete 2>/dev/null || true

curl -fsSL "https://cn.wordpress.org/latest-zh_CN.tar.gz" | tar -xz -C "${LOCAL_WEB_ROOT}" --strip-components=1
echo "✅ WordPress 纯洁核心安装完成"

# 3. 从备份包还原 wp-config.php（保留原 SALT 与 SMTP_*，仅替换本地 DB 凭据）
echo "2/6 正在从 secrets-and-config 还原 wp-config.php（适配本地数据库）..."
tar -xzf "${CFG_FILE}" -C "${TMP_DIR}"
cp "${TMP_DIR}/wordpress/wp-config.php" "${LOCAL_WEB_ROOT}/wp-config.php"
chmod 644 "${LOCAL_WEB_ROOT}/wp-config.php"
if [ -f "${TMP_DIR}/wordpress/.htaccess" ]; then
    cp "${TMP_DIR}/wordpress/.htaccess" "${LOCAL_WEB_ROOT}/.htaccess"
    chmod 644 "${LOCAL_WEB_ROOT}/.htaccess"
fi

python3 - "${LOCAL_WEB_ROOT}/wp-config.php" "${LOCAL_DB_NAME}" "${LOCAL_DB_USER}" "${LOCAL_DB_PASS}" << 'PY'
import re, sys
path, db_name, db_user, db_pass = sys.argv[1:5]
text = open(path, encoding="utf-8").read()
def rep(key, val):
    global text
    esc = val.replace("\\", "\\\\").replace("'", "\\'")
    text = re.sub(
        rf"define\(\s*['\"]{key}['\"]\s*,\s*['\"][^'\"]*['\"]\s*\)",
        f"define( '{key}', '{esc}' )",
        text,
    )
rep("DB_NAME", db_name)
rep("DB_USER", db_user)
rep("DB_PASSWORD", db_pass)
rep("DB_HOST", "localhost")
open(path, "w", encoding="utf-8").write(text)
PY
echo "✅ wp-config.php 已从网盘备份还原并适配本地 DB（原 SALT 与 SMTP 配置已保留）"

# 4. 按 inventory.txt 自动从 WordPress.org 安装纯洁官方父主题与第三方插件
echo "3/6 正在按 inventory.txt 从 WordPress.org 安装官方父主题与第三方插件..."
mkdir -p "${LOCAL_WEB_ROOT}/wp-content/themes" "${LOCAL_WEB_ROOT}/wp-content/plugins" "${LOCAL_WEB_ROOT}/wp-content/uploads" "${LOCAL_WEB_ROOT}/wp-content/upgrade"

# 收集仓库内自研插件名，避免去 WordPress.org 下载自研插件
CUSTOM_PLUGINS=""
for d in "${REPO_ROOT}"/wp-content/plugins/*/; do
    [ -d "${d}" ] && CUSTOM_PLUGINS="${CUSTOM_PLUGINS} $(basename "${d}") "
done

# 安装 Avril 父主题（如 inventory 中有其他非默认官方父主题也自动安装）
curl -fsSL "https://downloads.wordpress.org/theme/avril.zip" -o "${TMP_DIR}/avril.zip"
unzip -q -o "${TMP_DIR}/avril.zip" -d "${LOCAL_WEB_ROOT}/wp-content/themes/"
echo "   ✅ 已安装官方父主题: avril"

# 从 inventory.txt 解析线上已启用的第三方插件并自动从官方库安装
if [ -f "${TMP_DIR}/inventory.txt" ]; then
    ACTIVE_PLUGINS="$(python3 - "${TMP_DIR}/inventory.txt" << 'PY'
import sys
in_plugins = False
slugs = []
for line in open(sys.argv[1], encoding="utf-8", errors="ignore"):
    s = line.strip()
    if s == "=== Plugins ===":
        in_plugins = True
        continue
    if s.startswith("===") and in_plugins:
        break
    if not in_plugins or not s or s.startswith("+") or s.startswith("-"):
        continue
    cols = [c.strip() for c in s.strip("|").split("|")] if "|" in s else s.split()
    if len(cols) >= 2 and cols[0] and cols[0] != "name" and cols[1] == "active":
        slugs.append(cols[0])
print(" ".join(slugs))
PY
)"
    for slug in ${ACTIVE_PLUGINS}; do
        if [[ "${CUSTOM_PLUGINS}" == *" ${slug} "* ]]; then
            continue
        fi
        echo "   -> 正在从 WordPress.org 安装官方插件: ${slug}"
        if curl -fsSL "https://downloads.wordpress.org/plugin/${slug}.zip" -o "${TMP_DIR}/${slug}.zip"; then
            unzip -q -o "${TMP_DIR}/${slug}.zip" -d "${LOCAL_WEB_ROOT}/wp-content/plugins/"
            echo "   ✅ 已安装: ${slug}"
        else
            echo "   ⚠️ 未能在 WordPress.org 找到插件 ${slug}（可能是非公开插件），已跳过"
        fi
    done
fi

# 5. 从 Git 仓库部署自研子主题与自研插件，并从 Google Drive 并发拉取 uploads
echo "4/6 正在部署 Git 自研代码并从 Google Drive 并发还原 uploads..."
chmod -R 777 "${LOCAL_WEB_ROOT}/wp-content" 2>/dev/null || true
bash "${SCRIPT_DIR}/sync_custom_code.sh" localhost

echo "   -> 正在拉取 uploads 媒体库 ..."
if command -v rclone >/dev/null 2>&1; then
    rclone copy "${GDRIVE_BACKUP_ROOT}/uploads/current" "${LOCAL_WEB_ROOT}/wp-content/uploads" \
        --transfers 16 --checkers 16 --stats 5s --stats-one-line --stats-log-level NOTICE
else
    rsync -a "${GDRIVE_BACKUP_ROOT}/uploads/current/" "${LOCAL_WEB_ROOT}/wp-content/uploads/"
fi
chmod -R 777 "${LOCAL_WEB_ROOT}/wp-content/uploads" 2>/dev/null || true
echo "✅ 自研代码与媒体库全部还原完成"

# 6. 重建本地数据库并导入快照
echo "5/6 正在重建本地 MariaDB 数据库并导入网盘 SQL 快照..."
CREATE_DB_SQL="DROP DATABASE IF EXISTS \`${LOCAL_DB_NAME}\`; CREATE DATABASE \`${LOCAL_DB_NAME}\` DEFAULT CHARACTER SET utf8mb4; CREATE USER IF NOT EXISTS '${LOCAL_DB_USER}'@'localhost' IDENTIFIED BY '${LOCAL_DB_PASS}'; GRANT ALL PRIVILEGES ON \`${LOCAL_DB_NAME}\`.* TO '${LOCAL_DB_USER}'@'localhost'; FLUSH PRIVILEGES;"

mariadb -u root -e "${CREATE_DB_SQL}" 2>/dev/null \
    || sudo mariadb -e "${CREATE_DB_SQL}" 2>/dev/null \
    || mariadb -u "${LOCAL_DB_USER}" -e "${CREATE_DB_SQL}" 2>/dev/null || true

LOCAL_PASS_ARG=""
[ -n "${LOCAL_DB_PASS}" ] && LOCAL_PASS_ARG="-p${LOCAL_DB_PASS}"

if mariadb -u "${LOCAL_DB_USER}" ${LOCAL_PASS_ARG} -e "SELECT 1;" "${LOCAL_DB_NAME}" >/dev/null 2>&1; then
    run_db() { mariadb -u "${LOCAL_DB_USER}" ${LOCAL_PASS_ARG} "${LOCAL_DB_NAME}"; }
elif mariadb -u root -e "SELECT 1;" "${LOCAL_DB_NAME}" >/dev/null 2>&1; then
    run_db() { mariadb -u root "${LOCAL_DB_NAME}"; }
elif sudo mariadb -e "SELECT 1;" "${LOCAL_DB_NAME}" >/dev/null 2>&1; then
    run_db() { sudo mariadb "${LOCAL_DB_NAME}"; }
else
    echo "❌ 无法连接到本地 MariaDB！"
    exit 1
fi

gunzip -c "${DB_FILE}" | run_db

run_db << 'SQL'
SET FOREIGN_KEY_CHECKS = 0;
UPDATE wp_options SET option_value='http://localhost' WHERE option_name IN ('siteurl', 'home');
UPDATE wp_options SET option_value='0' WHERE option_name='limit_login_mfa_enabled';
SET FOREIGN_KEY_CHECKS = 1;
SQL
echo "✅ 数据库导入及本地配置适配完成"

# 7. 健康检查
echo "6/6 正在执行本地站点连通性验证..."
HTTP_CODE="$(curl -s -o /dev/null -w '%{http_code}' http://localhost/ || echo '000')"
echo "🌐 http://localhost/ -> HTTP ${HTTP_CODE}"
echo "=========================================="
echo "🎉 【Google Drive 极简灾备本地原样重建】完成！请打开 http://localhost/ 验收。"
echo "=========================================="
