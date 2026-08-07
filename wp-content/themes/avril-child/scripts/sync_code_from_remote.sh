#!/usr/bin/env bash
# ==============================================================================
# 从线上生产服务器 (Chinacongress) 反向同步/拉取最新子主题代码到本地工作区
# 说明：当线上代码有同事直接更新时，使用此脚本优先拉取到本地
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CHILD_THEME_SRC="$(cd "${SCRIPT_DIR}/.." && pwd)"
REMOTE_SRC="Chinacongress:/var/www/chinacongress/wp-content/themes/avril-child/"

echo "=========================================="
echo "📥 正在从线上生产服务器 (Chinacongress) 拉取最新代码到本地..."
echo "=========================================="

rsync -avz --exclude='.git' --exclude='scripts' \
  "${REMOTE_SRC}" \
  "${CHILD_THEME_SRC}/"

echo "✅ 线上最新代码已成功同步回本地工作区！"
echo "=========================================="
