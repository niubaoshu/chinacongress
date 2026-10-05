#!/usr/bin/env bash
# ==============================================================================
# 自定义代码同步与部署脚本 (Custom Code Git Deployment)
# 说明：同步我们二次开发的子主题 (avril-child) 与自主研发插件 (cc-assets, cc-footer-credits)
# 纯粹由 Git 仓库分支控制，零需要 root/sudo 权限！
# ==============================================================================

set -euo pipefail

TARGET="${1:-localhost}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CHILD_THEME_SRC="$(cd "${SCRIPT_DIR}/.." && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../../.." && pwd)"

LOCAL_THEME_DEST="/srv/http/my_site_name/wp-content/themes/avril-child"
LOCAL_PLUGINS_DEST="/srv/http/my_site_name/wp-content/plugins"

REMOTE_THEME_DEST="Chinacongress:/var/www/chinacongress/wp-content/themes/avril-child"
REMOTE_PLUGINS_DEST="Chinacongress:/var/www/chinacongress/wp-content/plugins"

CURRENT_BRANCH="$(git -C "${REPO_ROOT}" rev-parse --abbrev-ref HEAD)"

echo "=========================================="
echo "🚀 开始同步/部署自定义代码 (avril-child 与自主研发插件)..."
echo "📌 当前 Git 分支：[ ${CURRENT_BRANCH} ]"
echo "=========================================="

# 0. 自动 PHP 语法安全性自动拦截检查 (Lint Check)
echo "🔍 正在进行全站自定义 PHP 语法安全性检查 (Lint Check)..."
LINT_FAILED=0
CHECK_PATHS=("${CHILD_THEME_SRC}")
for plugin in cc-assets cc-footer-credits; do
    if [ -d "${REPO_ROOT}/wp-content/plugins/${plugin}" ]; then
        CHECK_PATHS+=("${REPO_ROOT}/wp-content/plugins/${plugin}")
    fi
done

while IFS= read -r -d '' php_file; do
    if ! php -l "${php_file}" >/dev/null 2>&1; then
        echo "❌ 【部署拦截】：发现 PHP 语法错误：${php_file}"
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
    echo "1. 正在将当前分支 [ ${CURRENT_BRANCH} ] 代码同步至本地 localhost ..."
    mkdir -p "${LOCAL_THEME_DEST}" "${LOCAL_PLUGINS_DEST}"
    rsync -avz --no-o --no-g --delete --exclude='.git' --exclude='*.md' \
      "${CHILD_THEME_SRC}/" \
      "${LOCAL_THEME_DEST}/"

    for plugin in cc-assets cc-footer-credits; do
        if [ -d "${REPO_ROOT}/wp-content/plugins/${plugin}" ]; then
            mkdir -p "${LOCAL_PLUGINS_DEST}/${plugin}"
            rsync -avz --no-o --no-g --delete --exclude='.git' --exclude='*.md' \
              "${REPO_ROOT}/wp-content/plugins/${plugin}/" \
              "${LOCAL_PLUGINS_DEST}/${plugin}/"
        fi
    done
    echo "✅ 本地 localhost 同步完成 (无需 root/sudo)！"

elif [ "${TARGET}" = "production" ] || [ "${TARGET}" = "remote" ]; then
    if [ "${CURRENT_BRANCH}" != "main" ]; then
        echo "⚠️ 【发布拦截】：线上生产部署必须在 main 分支上执行！当前为 ${CURRENT_BRANCH}。"
        echo "如需线上发布，请先切换到 main 分支：git checkout main"
        exit 1
    fi

    if [ -n "$(git -C "${REPO_ROOT}" status --porcelain)" ]; then
        echo "⚠️ 【发布拦截】：本地 Git 工作区有未提交的代码改动！请先 git commit 或 stash 后再部署线上！"
        exit 1
    fi

    echo "2. 正在将主线 main 分支代码部署到线上生产服务器 (Chinacongress) [排除 scripts 与 *.md] ..."
    rsync -avz --delete --exclude='.git' --exclude='scripts' --exclude='*.md' \
      "${CHILD_THEME_SRC}/" \
      "${REMOTE_THEME_DEST}/"

    for plugin in cc-assets cc-footer-credits; do
        if [ -d "${REPO_ROOT}/wp-content/plugins/${plugin}" ]; then
            rsync -avz --delete --exclude='.git' --exclude='*.md' \
              "${REPO_ROOT}/wp-content/plugins/${plugin}/" \
              "${REMOTE_PLUGINS_DEST}/${plugin}/"
        fi
    done
    echo "✅ 线上生产环境部署完成！"

else
    echo "❌ 目标参数错误！用法: $0 [localhost | production]"
    exit 1
fi

echo "=========================================="
