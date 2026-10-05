#!/usr/bin/env bash
# ==============================================================================
# 从线上生产服务器 (Chinacongress) 反向同步/拉取最新子主题及自主插件代码到本地工作区
# 说明：当线上代码有更新时，使用此脚本优先拉取到本地
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CHILD_THEME_SRC="$(cd "${SCRIPT_DIR}/.." && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../../.." && pwd)"

REMOTE_THEME_SRC="Chinacongress:/var/www/chinacongress/wp-content/themes/avril-child/"
REMOTE_PLUGINS_SRC="Chinacongress:/var/www/chinacongress/wp-content/plugins/"

echo "=========================================="
echo "📥 正在从线上生产服务器 (Chinacongress) 拉取最新代码到本地..."
echo "=========================================="

echo "1. 同步子主题 (avril-child)..."
rsync -avz --exclude='.git' --exclude='scripts' \
  "${REMOTE_THEME_SRC}" \
  "${CHILD_THEME_SRC}/"

echo "2. 同步自主研发插件 (cc-assets, cc-footer-credits)..."
mkdir -p "${REPO_ROOT}/wp-content/plugins/cc-assets" "${REPO_ROOT}/wp-content/plugins/cc-footer-credits"
rsync -avz "${REMOTE_PLUGINS_SRC}cc-assets/" "${REPO_ROOT}/wp-content/plugins/cc-assets/"
rsync -avz "${REMOTE_PLUGINS_SRC}cc-footer-credits/" "${REPO_ROOT}/wp-content/plugins/cc-footer-credits/"

echo "✅ 线上最新代码（子主题与自主研发插件）已成功同步回本地工作区！"
echo "=========================================="
