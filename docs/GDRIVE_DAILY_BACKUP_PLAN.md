# 生产网站每日自动备份至 Google Drive 备忘方案

> **目标**：在不影响线上网站性能、不暴露 Google 网盘其他私人文件的前提下，每日自动将网站全部生产资料（数据库、媒体库、主题插件、敏感与系统配置）备份至 Google Drive，确保单靠网盘备份即可 **100% 原样恢复整站**。

---

## 一、 方案选型：服务端 `rclone` + 定时任务（推荐）

| 方案 | 原理 | 优缺点评估 |
| :--- | :--- | :--- |
| **服务端 `rclone` + `systemd timer` / `cron`（选定）** | 线上服务器每日 `mysqldump` + `rclone` 同步至 Google Drive | 脱离 PHP 不卡前台；媒体库增量同步极省空间；不依赖个人电脑开机；权限可控且易于告警 |
| WordPress 插件（UpdraftPlus 等） | WP 后台定时打包上传 | 跑在 PHP 进程内易超时/占内存；免费版增量和轮转受限；增加插件攻击面 |
| 个人电脑拉取再上传 | 复用本地 `backup_user_data.sh` | 依赖个人电脑每天开机，流量绕行且不可靠 |

---

## 二、 完整备份清单（仅备份 3 类不可再生资产）

假设线上服务器完全损毁，需在全新 Linux 服务器上原样重建 `https://chinacongress.net`，网盘只需备份以下 **3 类不可再生资产**：

| 网盘目录 | 备份内容 | 同步方式 | 保留策略 |
| :--- | :--- | :--- | :--- |
| **`db/`** | MySQL `chinacongress` 全库（`mysqldump --single-transaction --quick --routines --triggers` 压缩为 `.sql.gz`） | 每日全量快照（无锁导出，不影响访客读写） | 保留最近 **30 天**，到期自动清理 |
| **`uploads/`** | `/var/www/chinacongress/wp-content/uploads/` 全部图片、视频、附件 | 每日 `rclone sync` 增量同步 + `--backup-dir` | 最新镜像在 `current/`；被删或覆盖的旧版本移至 `archive/YYYYMMDD/`（保留 30 天防误删） |
| **`secrets-and-config/`** | 每日打包 `config_YYYYMMDD.tar.gz`，内含：<br>• `wp-config.php`（数据库凭据、8 组密钥盐、SMTP 配置）<br>• Nginx 站点配置、PHP-FPM 池配置（`www.conf`）与 Let's Encrypt 续期配置<br>• 备份基础设施配置：`rclone.conf`、`systemd` 定时单元（`.service`/`.timer`）、`cc-backup-gdrive.sh` 及 `root` crontab<br>• 运行环境与组件清单 `inventory.txt`（WP / PHP / MySQL / Nginx 版本及已启用插件/主题列表） | 每日快照 | 保留最近 **30 天** |

> **为什么不备份 WordPress 核心、第三方插件和主题？**
> * WordPress 核心与免费第三方插件/主题全部来自 WordPress.org 官方源，灾备时按 `inventory.txt` 自动安装纯洁官方版本即可，既避免数千小文件同步瓶颈，又能防止潜在后门随备份回流；
> * 自研子主题（`avril-child`）与自研插件（`cc-assets`、`cc-contact-link`、`cc-footer-credits`）已由 GitHub 托管；
> * 若未来引入官方库无法下载的付费商业插件，仅需单独保存其安装包 `.zip` 即可。

---

## 三、 Google Drive 目标目录结构

```text
Google Drive/
└── chinacongress-backups/                  # rclone (drive.file) 专属目录，与网盘其他私人内容隔离
    ├── db/
    │   ├── db_YYYYMMDD.sql.gz              # 每日数据库全量快照（保留30天）
    │   └── ...
    ├── uploads/
    │   ├── current/                        # 与线上 uploads/ 一致的最新增量镜像
    │   └── archive/                        # 被删/被覆盖文件的历史归档（保留30天）
    │       └── YYYYMMDD/
    └── secrets-and-config/                 # 敏感数据与系统配置专门目录
        ├── config_YYYYMMDD.tar.gz          # wp-config.php + Nginx 配置 + 环境清单（保留30天）
        └── ...
```

---

## 四、 安全边界与 Google 权限说明

### 1. 权限隔离：为什么不会暴露网盘其他内容？
* **Google 官方强制权限范围（Scope）**：授权时必须选择 **`drive.file`**（`rclone config` 中的 **选项 `3`**：`Access to files created by rclone only`）。
  * ✅ **允许**：仅能查看、创建、更新、删除由 `rclone` 自己创建的文件夹和文件（即 `chinacongress-backups/`）。
  * ❌ **禁止**：完全看不到网盘中原有的任何私人文件或目录，也无权访问 Gmail、Google Photos、日历等其他任何 Google 服务。
  * 随时可在 `https://myaccount.google.com/permissions` 一键撤销授权，撤销后服务器立即失效。
* **服务器侧权限隔离**：
  * `rclone` 凭据文件仅存于 `/root/.config/rclone/rclone.conf`（`chmod 600`，仅 `root` 可读）；
  * 备份脚本置于 `/usr/local/sbin/cc-backup-gdrive.sh`，**不放入 `/var/www/` 网站目录，也不提交到 Git 仓库**；
  * WordPress / Nginx / PHP 进程（`nginx` 用户）完全无权读取备份凭据。

### 2. 敏感目录加密选项（实施时二选一）
* **选项 A（明文压缩包，最省心）**：`db/` 与 `secrets-and-config/` 直接存 `.sql.gz` / `.tar.gz`，依托 Google 账号两步验证（2FA）保护，灾备恢复时无需额外解密密钥。
* **选项 B（端到端加密，高安全）**：使用 **`age` 公钥加密**（线上只放公钥，私钥离线保管）或 **`rclone crypt`**，仅对 `db/` 和 `secrets-and-config/` 加密上传；`uploads/` 与 `wp-code/` 保持明文增量同步。

---

## 五、 无浏览器服务器 `rclone` 授权步骤

因线上服务器无图形浏览器，采用 **“本地浏览器授权 → 粘贴 Token 回服务器”** 的标准流程：

### 第 1 步：线上服务器启动 `rclone config`
1. `n`（新建 remote）→ 名称填 `gdrive`
2. `Storage` → 选择 `drive`（Google Drive）
3. `client_id` / `client_secret` → 直接回车留空（使用 rclone 官方已验证内置应用）
4. **`scope`** → 输入 **`3`**（`drive.file`，**严禁选 `1`**）
5. `service_account_file` → 回车留空
6. `Edit advanced config?` → `n`
7. `Use web browser to automatically authenticate?` → **`n`**

### 第 2 步：本地电脑完成浏览器确认
复制线上终端给出的命令在本地电脑执行（本地需已安装 `rclone`）：
```bash
rclone authorize "drive" "<线上终端生成的参数>"
```
在弹出的浏览器中登录 Google 账号并确认仅授予 *“使用此应用打开或创建的特定 Google 云端硬盘文件”* 权限，复制本地终端输出的 JSON Token。

### 第 3 步：粘回服务器并验证隔离
1. 将 JSON Token 粘回线上服务器终端；
2. `Configure this as a Shared Drive (Team Drive)?` → 个人网盘选 `n`，团队盘选 `y`；
3. 保存配置后执行 `chmod 600 /root/.config/rclone/rclone.conf`；
4. 执行 `rclone lsd gdrive:` —— **返回空结果**即证明服务器确实无法看到网盘原有文件。

### 避坑提示
* **Token 自动续期**：`rclone` 持有的 `refresh_token` 会自动静默刷新 `access_token`，长期有效。
* **Client ID 建议**：初期直接使用 rclone 官方内置 Client ID 即可（已通过 Google 验证，无 7 天过期问题）。若未来数据量增大需自建 Google Cloud OAuth Client ID，务必在 OAuth 同意屏幕点击 **「Publish App（发布应用）」**，否则 `Testing` 状态下 `refresh_token` 会在 7 天后强制失效。

---

## 六、 每日自动执行流程设计

1. **调度方式**：`systemd timer`（推荐，便于 `journalctl` 查日志）或 `cron`，每日凌晨 `03:30` 执行 `/usr/local/sbin/cc-backup-gdrive.sh`。
2. **脚本执行顺序**：
   * 创建权限 `700` 的临时目录 `/tmp/cc_backup_xxxx`（注册 `trap` 确保脚本无论成功或异常退出均自动清理）；
   * 导出并校验 `db_YYYYMMDD.sql.gz` 与 `config_YYYYMMDD.tar.gz` 非空；
   * `rclone copy` 上传 `db/` 与 `secrets-and-config/`；
   * `rclone sync` 增量同步 `uploads/`（附 `--backup-dir gdrive:chinacongress-backups/uploads/archive/YYYYMMDD/`）与 `wp-code/`；
   * 清理超过 30 天的旧 `db/`、`secrets-and-config/` 和 `uploads/archive/`；
   * 任一步骤失败时通过线上 Gmail SMTP 发送告警邮件（附带错误日志尾部）。

---

## 七、 灾难恢复（原样重建整站）5 步清单

在新服务器（或本地测试环境）恢复步骤：
1. 安装 Nginx + PHP + MariaDB，按 `inventory.txt` 下载对应版本的纯洁 WordPress 核心；
2. 解压 `secrets-and-config/config_YYYYMMDD.tar.gz`，还原 `wp-config.php` 与 Nginx 站点配置，按 `wp-config.php` 中的库名/账号/密码创建数据库；
3. 解压并导入 `db/db_YYYYMMDD.sql.gz`；
4. 将 `wp-code/themes/`、`wp-code/plugins/` 与 `uploads/current/` 复制回 `wp-content/`，修正属主为 `nginx:nginx`；
5. 重载 Nginx 并用 Certbot 重新签发 HTTPS 证书，完成 100% 原样恢复。
