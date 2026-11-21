#!/usr/bin/env bash
# ==============================================================================
# 本地 localhost 与线上生产站点一致性验收脚本 (Local vs Production Parity Check)
#
# 用法：
#   bash wp-content/themes/avril-child/scripts/verify_localhost_parity.sh
# ==============================================================================

set -euo pipefail

LOCAL_URL="${LOCAL_URL:-http://localhost}"
PROD_URL="${PROD_URL:-https://chinacongress.net}"
LOCAL_WEB_ROOT="${LOCAL_WEB_ROOT:-/srv/http/my_site_name}"

TMP_DIR="$(mktemp -d /tmp/cc_parity_check.XXXXXX)"
trap 'rm -rf "${TMP_DIR}"' EXIT

FAIL=0
pass() { echo "  ✅ $*"; }
warn() { echo "  ⚠️ $*"; }
fail() { echo "  ❌ $*"; FAIL=1; }

echo "=========================================="
echo "🔍 开始执行【本地 ${LOCAL_URL} vs 线上 ${PROD_URL}】一致性验收..."
echo "=========================================="

# 1. 首页 HTTP 状态码与页面拉取
LOCAL_CODE="$(curl -sS -o "${TMP_DIR}/local.html" -w '%{http_code}' "${LOCAL_URL}/" || echo '000')"
PROD_CODE="$(curl -sS -o "${TMP_DIR}/prod.html" -w '%{http_code}' "${PROD_URL}/" || echo '000')"

if [ "${LOCAL_CODE}" = "200" ]; then
    pass "本地首页响应 HTTP ${LOCAL_CODE}"
else
    fail "本地首页异常: HTTP ${LOCAL_CODE}"
fi

if [ "${PROD_CODE}" = "200" ]; then
    pass "线上首页响应 HTTP ${PROD_CODE}"
else
    warn "线上首页暂不可达 (HTTP ${PROD_CODE})，跳过与线上 HTML 对比"
fi

# 2. 首页核心 <section> 结构与体积对比
if [ "${LOCAL_CODE}" = "200" ] && [ "${PROD_CODE}" = "200" ]; then
    LOCAL_SECTIONS="$(grep -oE '<section[^>]+id="[^"]+"' "${TMP_DIR}/local.html" | sed -E 's/.*id="([^"]+)".*/\1/' | tr '\n' ' ' | sed 's/ $//')"
    PROD_SECTIONS="$(grep -oE '<section[^>]+id="[^"]+"' "${TMP_DIR}/prod.html" | sed -E 's/.*id="([^"]+)".*/\1/' | tr '\n' ' ' | sed 's/ $//')"

    if [ -n "${LOCAL_SECTIONS}" ] && [ "${LOCAL_SECTIONS}" = "${PROD_SECTIONS}" ]; then
        pass "首页核心区块完全一致: [${LOCAL_SECTIONS}]"
    else
        fail "首页核心区块不一致！\n     本地: [${LOCAL_SECTIONS}]\n     线上: [${PROD_SECTIONS}]"
    fi

    LOCAL_BYTES="$(wc -c < "${TMP_DIR}/local.html")"
    PROD_BYTES="$(wc -c < "${TMP_DIR}/prod.html")"
    DIFF_PCT="$(python3 -c "l=${LOCAL_BYTES}; p=${PROD_BYTES}; print(round(abs(l-p)*100.0/max(p,1), 1))")"
    if python3 -c "import sys; sys.exit(0 if float(${DIFF_PCT}) <= 15.0 else 1)"; then
        pass "首页 HTML 体积匹配 (本地 ${LOCAL_BYTES} B vs 线上 ${PROD_BYTES} B，差异 ${DIFF_PCT}%)"
    else
        warn "首页 HTML 体积差异较大 (本地 ${LOCAL_BYTES} B vs 线上 ${PROD_BYTES} B，差异 ${DIFF_PCT}%)"
    fi
fi

# 3. 关键页面与子主题静态资源检查
CSS_CODE="$(curl -s -o /dev/null -w '%{http_code}' "${LOCAL_URL}/wp-content/themes/avril-child/style.css" || echo '000')"
LOGIN_CODE="$(curl -s -o /dev/null -w '%{http_code}' "${LOCAL_URL}/wp-login.php" || echo '000')"
[ "${CSS_CODE}" = "200" ] && pass "子主题样式表正常 (HTTP 200)" || fail "子主题样式表加载失败 (HTTP ${CSS_CODE})"
[ "${LOGIN_CODE}" = "200" ] && pass "本地登录页正常 (HTTP 200)" || fail "本地登录页异常 (HTTP ${LOGIN_CODE})"

SAMPLE_POST="$(grep -oE '/archives/[0-9]+' "${TMP_DIR}/local.html" | head -n 1 || true)"
if [ -n "${SAMPLE_POST}" ]; then
    POST_CODE="$(curl -s -o /dev/null -w '%{http_code}' "${LOCAL_URL}${SAMPLE_POST}" || echo '000')"
    [ "${POST_CODE}" = "200" ] && pass "抽样文章页 ${SAMPLE_POST} 正常 (HTTP 200)" || fail "抽样文章页 ${SAMPLE_POST} 异常 (HTTP ${POST_CODE})"
fi

# 4. 本地 WordPress 主题、已激活插件文件完整性与 MFA 状态检查
if [ -f "${LOCAL_WEB_ROOT}/wp-load.php" ]; then
    WP_CHECK="$(php -r '
        $root = $argv[1];
        require $root . "/wp-load.php";
        $tpl = get_option("template");
        $sty = get_option("stylesheet");
        $mfa = get_option("limit_login_mfa_enabled", "0");
        $posts = (int) wp_count_posts()->publish;
        $missing = array();
        $active = (array) get_option("active_plugins", array());
        foreach ($active as $p) {
            if (!file_exists($root . "/wp-content/plugins/" . $p)) {
                $missing[] = $p;
            }
        }
        echo json_encode(array(
            "template" => $tpl,
            "stylesheet" => $sty,
            "mfa" => (string) $mfa,
            "posts" => $posts,
            "active_count" => count($active),
            "missing" => $missing,
        ));
    ' "${LOCAL_WEB_ROOT}" 2>/dev/null || true)"

    if [ -n "${WP_CHECK}" ]; then
        eval "$(python3 - "${WP_CHECK}" << 'PY'
import json, shlex, sys
d = json.loads(sys.argv[1])
print(f"WP_TPL={shlex.quote(str(d['template']))}")
print(f"WP_STY={shlex.quote(str(d['stylesheet']))}")
print(f"WP_MFA={shlex.quote(str(d['mfa']))}")
print(f"WP_POSTS={int(d['posts'])}")
print(f"WP_ACTIVE_CNT={int(d['active_count'])}")
print(f"WP_MISSING={shlex.quote(' '.join(d['missing']))}")
PY
)"
        if [ "${WP_TPL}" = "avril" ] && [ "${WP_STY}" = "avril-child" ]; then
            pass "主题激活正确: ${WP_TPL} -> ${WP_STY}"
        else
            fail "主题异常: ${WP_TPL} -> ${WP_STY} (期望 avril -> avril-child)"
        fi

        if [ -z "${WP_MISSING}" ]; then
            pass "全部 ${WP_ACTIVE_CNT} 个激活插件文件完整存在"
        else
            fail "存在缺失的已激活插件文件: ${WP_MISSING}"
        fi

        if [ "${WP_MFA}" = "0" ] || [ -z "${WP_MFA}" ]; then
            pass "本地 LLAR 云端 MFA 已关闭 (可直接登录)"
        else
            warn "本地 LLAR 云端 MFA 仍为开启状态 (${WP_MFA})，localhost 登录可能无法发验证码"
        fi

        UPLOADS_CNT="$(find "${LOCAL_WEB_ROOT}/wp-content/uploads" -type f 2>/dev/null | wc -l)"
        pass "数据概览: 已发布文章 ${WP_POSTS} 篇，本地 uploads 媒体文件 ${UPLOADS_CNT} 个"
    else
        fail "无法加载本地 WordPress 环境 (${LOCAL_WEB_ROOT}/wp-load.php)"
    fi
fi

echo "------------------------------------------"
if [ "${FAIL}" -eq 0 ]; then
    echo "🎉 验收通过！本地重建站点与线上生产环境保持一致。"
    exit 0
else
    echo "❌ 验收未通过！请根据上述报错项检查修复。"
    exit 1
fi
