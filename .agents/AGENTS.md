# Project Rules & Workflow Constraints

## 核心原则：线上代码变更优先同步到本地 (Remote-First Syncing Rule)
当对比线上生产服务器（Chinacongress）与本地 Git 仓库中的代码时：
- **核心规矩**：一旦发现线上服务器的代码与本地代码存在不一致，**必须优先将线上服务器的新代码同步/拉取回本地**，防止覆盖线上可能存在的最新改动！
- 在确认本地代码已完整吸收线上改动后，才能开展新的开发与部署操作。

## Git 提交规范 (Git Commit Message Rule)
- **核心规矩**：在进行 Git 提交时，Git 的 commit 信息（包括提交类型与具体说明）**必须统一使用中文编写**（例如：`修复(面包屑): 文章页面隐藏末尾文章标题`）。

## 敏感数据与凭据零明文原则 (Zero-Plaintext Secrets Rule)
- **禁止明文入库**：严禁在任何代码、运维脚本、配置文件、Markdown 文档或注释中硬编码明文密码、API Key、OAuth Client Secret、Access/Refresh Token、私钥、数据库密码或私密凭据（文档示例一律使用明显占位符如 `your_secret`、`你的密码`）。
- **外部注入规范**：Shell 脚本一律通过环境变量读取凭据（如 `${SMTP_PASS:-}`、`${REMOTE_DB_PASS:-}`）；PHP 代码一律读取已被 `.gitignore` 排除的 `wp-config.php` 常量；`rclone.conf` 等系统凭据仅存放在服务器系统目录（如 `/root/.config/`，`chmod 600`），严禁放入网站目录或 Git 仓库。
- **强制启用与遵守钩子**：本仓库已配置 `.githooks/`（`pre-commit` 与 `pre-push` 敏感信息防泄露扫描）。**严禁使用 `--no-verify` 绕过钩子**；若钩子拦截，必须彻底清除敏感内容后再提交或推送。


