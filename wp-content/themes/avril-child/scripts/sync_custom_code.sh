#!/usr/bin/env bash
# ==============================================================================
# 自定义代码同步与部署脚本 (Custom Code Git Deployment)
# 说明：同步我们二次开发的子主题 (avril-child) 与自主研发插件 (wp-content/plugins/*)
# 纯粹由 Git 仓库分支控制，零需要 root/sudo 权限！
#
# 更新记录：
# - 2026-10-17: 生产部署加固 —— rsync 增加 --no-o --no-g 与 --chmod（修复属主污染
# 导致的线上 403 事故）；部署前自动快照备份；部署后统一文件属主/权限、健康检查、
# 插件激活状态恢复；插件列表改为动态扫描，新增插件自动纳入。
# ==============================================================================

set -euo pipefail

TARGET="${1:-localhost}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CHILD_THEME_SRC="$(cd "${SCRIPT_DIR}/.." && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../../../.." && pwd)"

LOCAL_THEME_DEST="/srv/http/my_site_name/wp-content/themes/avril-child"
LOCAL_PLUGINS_DEST="/srv/http/my_site_name/wp-content/plugins"

REMOTE_WEB_ROOT="/var/www/chinacongress"
REMOTE_THEME_DEST="Chinacongress:${REMOTE_WEB_ROOT}/wp-content/themes/avril-child"
REMOTE_PLUGINS_DEST="Chinacongress:${REMOTE_WEB_ROOT}/wp-content/plugins"

CURRENT_BRANCH="$(git -C "${REPO_ROOT}" rev-parse --abbrev-ref HEAD)"

# 动态收集自研插件目录（新增插件自动纳入部署范围）
PLUGINS_TO_DEPLOY=()
for plugin_dir in "${REPO_ROOT}"/wp-content/plugins/*/; do
[ -d "${plugin_dir}" ] || continue
PLUGINS_TO_DEPLOY+=( "$(basename "${plugin_dir}")")
done
PLUGIN_LIST="${PLUGINS_TO_DEPLOY[*]}"

echo "=========================================="
echo "🚀 开始同步/部署自定义代码 (avril-child 与自主研发插件)..."
echo "📌 当前 Git 分支：[ ${CURRENT_BRANCH}]"
echo "📌 待部署插件：[ ${PLUGIN_LIST:-无}]"
echo "=========================================="

# 0. 自动 PHP 语法安全性自动拦截检查 (Lint Check)
echo "🔍 正在进行全站自定义 PHP 语法安全性检查 (Lint Check)..."
LINT_FAILED=0
CHECK_PATHS=("${CHILD_THEME_SRC}")
for plugin in "${PLUGINS_TO_DEPLOY[@]}"; do
CHECK_PATHS+=("${REPO_ROOT}/wp-content/plugins/${plugin}")
done

while IFS= read -r -d '' php_file; do
    if ! php -l "${php_file}" >/dev/null 2>&1; then
echo "❌ ：发现 PHP 语法错误：${php_file}"
php -l "${php_file}"
LINT_FAILED=1
fi
done < <(find "${CHECK_PATHS[@]}" -type f -name "*.php" -print0)

if [ "${LINT_FAILED}" -eq 1 ]; then
echo "🚨 部署中断：请修复上述 PHP 语法错误后再重试同步！"
exit 1
fi
echo "✅ 所有 PHP 文件语法校验通过 (0 Syntax Errors)！"
echo "------------------------------------------"

if [ "${TARGET}" = "localhost" ]; then
echo "1. 正在将当前分支 [ ${CURRENT_BRANCH}] 代码同步至本地 localhost..."
mkdir -p "${LOCAL_THEME_DEST}" "${LOCAL_PLUGINS_DEST}"
rsync -avz --no-o --no-g --delete --exclude='.git' --exclude='*.md' \
"${CHILD_THEME_SRC}/" \
"${LOCAL_THEME_DEST}/"

for plugin in "${PLUGINS_TO_DEPLOY[@]}"; do
mkdir -p "${LOCAL_PLUGINS_DEST}/${plugin}"
rsync -avz --no-o --no-g --delete --exclude='.git' --exclude='*.md' \
"${REPO_ROOT}/wp-content/plugins/${plugin}/" \
"${LOCAL_PLUGINS_DEST}/${plugin}/"
done
echo "✅ 本地 localhost 同步完成 (无需 root/sudo)！"

elif [ "${TARGET}" = "production" ] || [ "${TARGET}" = "remote" ]; then
if [ "${CURRENT_BRANCH}" !=  "main" ]; then
echo "⚠️ ：线上生产部署必须在 main 分支上执行！当前为 ${CURRENT_BRANCH}。"
echo "如需线上发布，请先切换到 main 分支：git checkout main"
exit 1
fi

if [ -n "$(git -C "${REPO_ROOT}" status --porcelain)" ]; then
echo "⚠️ ：本地 Git 工作区有未提交的代码改动！请先 git commit 或 stash 后再部署线上！"
exit 1
fi

# 部署目标目录清单（主题 + 插件），用于备份与权限统一
REMOTE_DEPLOY_DIRS="${REMOTE_WEB_ROOT}/wp-content/themes/avril-child"
for plugin in "${PLUGINS_TO_DEPLOY[@]}"; do
REMOTE_DEPLOY_DIRS="${REMOTE_DEPLOY_DIRS} ${REMOTE_WEB_ROOT}/wp-content/plugins/${plugin}"
done

# 1. 部署前快照备份（支持一键回滚）
BACKUP_TS="$(date +%Y%m%d_%H%M%S)"
BACKUP_DIR="/root/deploy_backup_${BACKUP_TS}"
echo "💾 正在备份线上当前版本..."
BACKUP_CMDS="mkdir -p '${BACKUP_DIR}' && cp -a '${REMOTE_WEB_ROOT}/wp-content/themes/avril-child' '${BACKUP_DIR}/'"
for plugin in "${PLUGINS_TO_DEPLOY[@]}"; do
BACKUP_CMDS="${BACKUP_CMDS} && { [ -d '${REMOTE_WEB_ROOT}/wp-content/plugins/${plugin}' ] && cp -a '${REMOTE_WEB_ROOT}/wp-content/plugins/${plugin}' '${BACKUP_DIR}/' || echo \" (跳过 ${plugin}：线上尚无此目录)\"; }"
done
ssh Chinacongress "${BACKUP_CMDS}"
echo "✅ 线上备份完成：${BACKUP_DIR}"
echo " 如需回滚请执行：ssh Chinacongress \"cp -a ${BACKUP_DIR}/avril-child/. ${REMOTE_WEB_ROOT}/wp-content/themes/avril-child/\""

# 2. 记录当前已激活插件（部署后若有被 WP 自动停用则恢复）
ACTIVE_PLUGINS_BEFORE="$(ssh Chinacongress "wp plugin list --path='${REMOTE_WEB_ROOT}' --allow-root --status=active --field=name 2>/dev/null" || true)"

echo "2. 正在将主线 main 分支代码部署到线上生产服务器 (Chinacongress) [排除 scripts 与 *.md]..."
# 关键：--no-o --no-g 避免把本地 root 属主污染到线上；
# --chmod 强制目录 755 / 文件 644，保证 nginx 可读（2026-10-17 线上 403 事故教训）。
RSYNC_PROD_FLAGS=( -avz --no-o --no-g --chmod=Du=rwx,Dg=rx,Do=rx,Fu=rw,Fg=r,Fo=r --delete --exclude='.git' --exclude='*.md')
rsync "${RSYNC_PROD_FLAGS[@]}" --exclude='scripts' \
"${CHILD_THEME_SRC}/" \
"${REMOTE_THEME_DEST}/"

for plugin in "${PLUGINS_TO_DEPLOY[@]}"; do
rsync "${RSYNC_PROD_FLAGS[@]}" \
"${REPO_ROOT}/wp-content/plugins/${plugin}/" \
"${REMOTE_PLUGINS_DEST}/${plugin}/"
done

# 3. 统一线上文件属主与权限（与站点其余目录保持一致）
echo "🔧 正在统一线上文件属主与权限 (nginx:nginx, 目录 755, 文件 644)..."
# shellcheck disable=SC2086
ssh Chinacongress "chown -R nginx:nginx ${REMOTE_DEPLOY_DIRS} && find ${REMOTE_DEPLOY_DIRS} -type d -exec chmod 755 {} + && find ${REMOTE_DEPLOY_DIRS} -type f -exec chmod 644 {} +"
echo "✅ 权限统一完成"

# 4. 恢复部署过程中被 WP 自动停用的插件
echo "🔌 正在检查插件激活状态..."
ACTIVE_PLUGINS_AFTER="$(ssh Chinacongress "wp plugin list --path='${REMOTE_WEB_ROOT}' --allow-root --status=active --field=name 2>/dev/null" || true)"
while IFS= read -r plugin; do
[ -n "${plugin}" ] || continue
if ! grep -qx "${plugin}" <<< "${ACTIVE_PLUGINS_AFTER}"; then
echo "⚠️ 插件 ${plugin} 在部署后被停用，正在重新激活..."
ssh Chinacongress "wp plugin activate '${plugin}' --path='${REMOTE_WEB_ROOT}' --allow-root"
fi
done <<< "${ACTIVE_PLUGINS_BEFORE}"
echo "✅ 插件状态检查完成"

# 5. 部署后健康检查
echo "🏥 正在进行部署后健康检查..."
HEALTH_OK=1
check_url() {
local url="$1" code
code="$(curl -s -o /dev/null -w "%{http_code}" --max-time 20 "${url}")"
if [ "${code}" = "200" ]; then
echo " ✅ ${url} -> ${code}"
else
echo " ❌ ${url} -> ${code}"
HEALTH_OK=0
fi
}
check_url "https://chinacongress.net/"
check_url "https://chinacongress.net/wp-content/themes/avril-child/style.css"
if [ "${HEALTH_OK}" = "1" ]; then
echo "✅ 线上生产环境部署完成！🎉"
else
echo "🚨 健康检查未通过！请检查线上状态，必要时用备份回滚："
echo " ssh Chinacongress \"cp -a ${BACKUP_DIR}/avril-child/. ${REMOTE_WEB_ROOT}/wp-content/themes/avril-child/\""
exit 1
fi

else
echo "❌ 目标参数错误！用法: $0 [localhost | production]"
exit 1
fi

echo "=========================================="
