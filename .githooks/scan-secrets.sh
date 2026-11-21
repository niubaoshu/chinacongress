#!/usr/bin/env bash
# ==============================================================================
# Git 敏感信息防泄露扫描器 (供 pre-commit / pre-push 共用)
# 用法：
#   .githooks/scan-secrets.sh --staged
#   .githooks/scan-secrets.sh --range <from>..<to>
# ==============================================================================

set -euo pipefail

MODE="${1:-}"
RANGE="${2:-}"

if [ "${MODE}" = "--staged" ]; then
    FILES="$(git diff --cached --name-only --diff-filter=ACMR)"
    DIFF_CMD=(git diff --cached --unified=0 --no-color --diff-filter=ACMR)
elif [ "${MODE}" = "--range" ] && [ -n "${RANGE}" ]; then
    FILES="$(git diff --name-only --diff-filter=ACMR "${RANGE}")"
    DIFF_CMD=(git diff --unified=0 --no-color --diff-filter=ACMR "${RANGE}")
else
    echo "用法: $0 --staged | --range <from>..<to>" >&2
    exit 2
fi

[ -z "${FILES}" ] && exit 0

FOUND=0

# 1. 高危敏感文件名拦截
while IFS= read -r file; do
    [ -z "${file}" ] && continue
    base="$(basename "${file}")"
    case "${base}" in
        wp-config.php|rclone.conf|.env|id_rsa|id_ed25519|*.sql|*.sql.gz|*.pem|*.key|*.p12|*.kdbx)
            echo "❌ [敏感文件拦截] 禁止提交高危文件: ${file}" >&2
            FOUND=1
            ;;
        .env.*)
            if [ "${base}" != ".env.example" ] && [ "${base}" != ".env.sample" ]; then
                echo "❌ [敏感文件拦截] 禁止提交环境配置文件: ${file}" >&2
                FOUND=1
            fi
            ;;
    esac
done <<< "${FILES}"

# 2. 高危密钥/Token/明文密码内容扫描（仅扫描新增行，排除本扫描器自身）
PY_SCANNER='
import re, sys

found = int(sys.argv[1])
current_file = ""

RULES = [
    ("Google OAuth Client Secret", re.compile(r"GOCSPX-[A-Za-z0-9_-]{20,}")),
    ("Google OAuth Access Token",  re.compile(r"ya29\.[A-Za-z0-9._-]{30,}")),
    ("Google OAuth Refresh Token", re.compile(r"1//[0-9A-Za-z_-]{30,}")),
    ("Google API Key",             re.compile(r"AIza[0-9A-Za-z_-]{35}")),
    ("Private Key Header",         re.compile(r"-----BEGIN [A-Z0-9 ]*PRIVATE KEY-----")),
    ("GitHub Token",               re.compile(r"(?:ghp_[A-Za-z0-9]{36}|github_pat_[A-Za-z0-9_]{22,})")),
    ("AWS Access Key ID",          re.compile(r"AKIA[0-9A-Z]{16}")),
    ("Telegram Bot Token",         re.compile(r"\b[0-9]{8,10}:AA[A-Za-z0-9_-]{33}\b")),
    ("WP/SMTP 明文密码常量",        re.compile(r"define\(\s*[\x27\"](?:SMTP_PASS|DB_PASSWORD)[\x27\"]\s*,\s*[\x27\"]([^\x27\"]{4,})[\x27\"]\s*\)")),
    ("Shell 环境变量明文密码",      re.compile(r"\b(?:SMTP_PASS|REMOTE_DB_PASS|LOCAL_DB_PASS)=[\x27\"]([^\x27\"$]{4,})[\x27\"]")),
]

PLACEHOLDER_RE = re.compile(r"(__DB_PASS__|你的|your_|example|placeholder|changeme|xxx)", re.IGNORECASE)

for raw_line in sys.stdin:
    line = raw_line.rstrip("\r\n")
    if line.startswith("+++ b/"):
        current_file = line[6:]
        continue
    if not line.startswith("+") or line.startswith("+++"):
        continue
    added = line[1:]
    for name, pattern in RULES:
        m = pattern.search(added)
        if not m:
            continue
        if m.lastindex and PLACEHOLDER_RE.search(m.group(1)):
            continue
        snippet = added.strip()
        if len(snippet) > 90:
            snippet = snippet[:87] + "..."
        sys.stderr.write(f"❌ [敏感内容拦截] {name} | 文件: {current_file}\n   命中片段: {snippet}\n")
        found = 1

if found:
    sys.stderr.write("\n🚫 已阻断操作：检测到疑似敏感文件或凭据，请清理后再提交/推送。\n")
    sys.exit(1)
'

"${DIFF_CMD[@]}" -- . ':!.githooks/scan-secrets.sh' | python3 -c "${PY_SCANNER}" "${FOUND}"
