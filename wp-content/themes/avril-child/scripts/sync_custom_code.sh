#!/usr/bin/env bash
# ==============================================================================
# 二次开发代码同步与部署脚本 (Custom Code Git Deployment)
# 说明：仅同步我们二次开发维护的子主题 (avril-child) 代码
# 纯粹由 Git 仓库分支控制，零需要 root/sudo 权限！
# ==============================================================================

set -euo pipefail

TARGET="${1:-localhost}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CHILD_THEME_SRC="$(cd "${SCRIPT_DIR}/.." && pwd)"
LOCAL_DEST="/srv/http/my_site_name/wp-content/themes/avril-child"
REMOTE_DEST="Chinacongress:/var/www/chinacongress/wp-content/themes/avril-child"

CURRENT_BRANCH="$(git -C "${CHILD_THEME_SRC}" rev-parse --abbrev-ref HEAD)"

echo "=========================================="
echo "🚀 开始同步/部署二次开发代码 (avril-child)..."
echo "📌 当前 Git 分支：[ ${CURRENT_BRANCH} ]"
echo "=========================================="

if [ "${TARGET}" = "localhost" ]; then
    echo "1. 正在将当前分支 [ ${CURRENT_BRANCH} ] 代码同步至本地 localhost ..."
    mkdir -p "${LOCAL_DEST}"
    rsync -avz --no-o --no-g --delete --exclude='.git' \
      "${CHILD_THEME_SRC}/" \
      "${LOCAL_DEST}/"
    echo "✅ 本地 localhost 同步完成 (无需 root/sudo)！"

elif [ "${TARGET}" = "production" ] || [ "${TARGET}" = "remote" ]; then
    if [ "${CURRENT_BRANCH}" != "main" ]; then
        echo "⚠️ 【发布拦截】：线上生产部署必须在 main 分支上执行！当前为 ${CURRENT_BRANCH}。"
        echo "如需线上发布，请先切换到 main 分支：git checkout main"
        exit 1
    fi

    echo "2. 正在将主线 main 分支代码部署到线上生产服务器 (Chinacongress) [排除 scripts 目录] ..."
    rsync -avz --delete --exclude='.git' --exclude='scripts' \
      "${CHILD_THEME_SRC}/" \
      "${REMOTE_DEST}/"
    echo "✅ 线上生产环境部署完成！"

else
    echo "❌ 目标参数错误！用法: $0 [localhost | production]"
    exit 1
fi

echo "=========================================="
