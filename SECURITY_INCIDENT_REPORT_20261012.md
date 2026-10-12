# 网站安全事件调查与应急响应报告 (2026-10-12)

今天发现 https://chinacongress.net/wp-login.php 返回 not found ，经深入排查服务器运行日志、系统文件完整性、数据库记录以及操作系统底层权限配置，现将本次网站遭受攻击者攻击的详细入侵调查报告整理如下：

---

## 一、 事件直接诱因与异常表现

1. **直接原因**：
   - 生产服务器（`/var/www/chinacongress/`）物理磁盘上的 **`wp-login.php`** 在 **2026年10月10日 04:01:54** 被攻击者重命名为了 **`wp-logins.php`**。
   - 当客户端请求 `https://chinacongress.net/wp-login.php` 时，Nginx 将 FastCGI 请求传递给 PHP-FPM，PHP-FPM 在目标路径下找不到该脚本，直接返回纯文本 `File not found.`（HTTP 404）。
   - 反向验证：直接访问 `https://chinacongress.net/wp-logins.php` 可正常打开后台登录表单。

2. **攻击者目的**：
   - 封锁合法管理员的正常管理后台入口，制造网站底层报错假象；
   - 掩护其在后台进行的大规模 Webshell 部署、持久化后门驻留以及博彩关键词 SEO 寄生行为。

---

## 二、 攻击者是如何实现的？通过什么方式？获得了什么权限？

根据 Nginx 历史访问日志（`/var/log/nginx/access.log*`）与数据库 `wp_usermeta` 用户会话数据深度审计，完整的攻击链路及证据链还原如下：

### 1. 受害账号与凭证失窃取证核实

为了精准锁定受害账户并厘清攻击路径，对数据库底层原始会话表（`wp_usermeta` 中的 `session_tokens` 序列化数据）进行了逐字节解包审计：

- **受害账号确认为用户 ID: 1（`******`）—— 【被攻击者攻破并接管】**：
  - 数据库底层铁证记录了多条来自攻击者攻击源 IP 的成功登录会话：
    - `45.251.6.21`（登录时间：2026-10-10 10:16:58 UTC / 03:16:58 服务器本地时间）
    - `45.121.37.189`（登录时间：2026-10-10 11:12:50 UTC / 04:12:50 服务器本地时间）
    - `149.154.161.219`（登录时间：2026-10-10 11:14:43 UTC / 04:14:43 服务器本地时间）
  - **取证结论**：失窃并被攻击者直接用于登录 WordPress 仪表盘的账号确认为超级管理员 **`******`**。

### 2. 攻击方式：`xmlrpc.php` 批量暴力操作机制与密码获取溯源

攻击者是如何拿到 `******` 密码的？结合 Nginx 访问日志深度关联分析，还原如下：

1. **全网分布式 `xmlrpc.php` 批量爆破事实**：
   - 历史日志统计显示，事发前后系统遭遇了**超过 133,000 次**针对 `/xmlrpc.php` 的恶意 POST 请求；
   - 这些请求来自于全球数以百计的肉鸡节点（如 `41.238.56.160`、`2a09:bac5:...` 等），且多伪装为 `Jetpack by WordPress.com` 爬虫；
   - 其利用了 XML-RPC 协议中的 **`system.multicall`** 批处理方法——允许攻击者在单个 HTTP 请求中并发嵌套测试数百个账号密码组合（放大攻击），从而在极短时间内完成数十万次字典试错，且能绕过基于单请求频率的安全拦截。

2. **登入时刻的“手握现成密码”特征**：
   - 在 10月09日 03:47:04（攻击者首次登录后台上传恶意主题）时，攻击者主控 IP（`2a00:1d34:e78a:...`）访问 `wp-login.php` 仅仅 6 秒后，**仅发起了 1 次 POST 请求就直接返回 302 成功登入后台**；
   - 这表明：该 IP 在发起登录前，**就已经明确知晓了 `******` 的正确密码**。

3. **密码获取途径推断**：
   - **途径 A（最符合攻击特征）：自动化 `xmlrpc.php` 爆破集群命中后回传**  
     攻击者长期运行的分布式爆破集群通过 `xmlrpc.php` 的批量并发接口对目标进行字典攻击，一旦某个节点命中弱口令，脚本即刻将成功的凭证回传给主控服务器，随后由攻击者（或高权限任务脚本）定向登入 Web 后台执行后门投放。
   - **途径 B：第三方社工库撞库（Credential Stuffing）**  
     若管理员账号曾使用过通用或历史泄露过的旧密码，攻击者可能直接利用公开或地下贩卖的凭据泄露库定向精准撞库一击即中。

### 3. 获取的权限级别
- **WordPress 最高超级管理员权限（Administrator / Level 10）**，允许上传主题、安装插件、编辑文件、新建用户。

### 4. 攻击时序全过程复盘

```mermaid
sequenceDiagram
    autonumber
    actor Attacker as 攻击者 (IP: 2a00:1d34... / 45.251.6.21)
    participant WPLogin as wp-login.php
    participant Admin as WP 后台 (/wp-admin/)
    participant FS as 服务器文件系统

    Note over Attacker,WPLogin: 阶段一：管理员凭证失窃与初始渗透
    Attacker->>WPLogin: 10月09日 03:47:04 使用 ****** 密码成功 POST 登录 (HTTP 302 重定向到后台)
    Attacker->>Admin: 10月09日 03:47:18 利用管理员上传主题功能，上传恶意主题包 default-twenty
    FS-->>Attacker: 主题内包含初始 Webshell (wp-app.php, prvcf.php, adminer.php, wget.php)
    Attacker->>WPLogin: 10月09日 03:48:06 登出后台

    Note over Attacker,FS: 阶段二：激活后门、全面接管与黑帽 SEO 植入
    Attacker->>FS: 10月10日 03:13:55 通过 prvcf.php 激活 hdaufhsua146hfAnt1w4f.php 脚本
    Attacker->>Admin: 10月10日 03:23:18 登入后台在线安装插件 wp-file-manager (文件管理器)
    Attacker->>FS: 10月10日 03:54 ~ 04:12 植入 6MB 大马 wp-malibox.php 与提权后门 wp-opml.php
    Attacker->>FS: 10月10日 04:01:54 将 wp-login.php 强行重命名为 wp-logins.php 封锁入口
    Attacker->>FS: 10月10日 06:15 篡改 wp-blog-header.php 植入爬虫伪装代理 (Cloaking)
    Attacker->>FS: 10月10日 生成数十个 SURGA11 印尼博彩静态页面并绑定 Google 站长认证
    Attacker->>Admin: 10月10日 11:14:15 注册后备隐藏管理员账号 webit
```

---

## 三、 攻击者修改的文件与恶意操作详细清单

### 1. 篡改的核心系统文件
| 文件路径 | 恶意操作与危害说明 |
| :--- | :--- |
| `/var/www/chinacongress/wp-login.php` | **被重命名为 `wp-logins.php`**，切断管理员正常入口，阻止管理员及时上线发现异样。 |
| `/var/www/chinacongress/wp-blog-header.php` | **注入 Cloaking 爬虫伪装代码**。当检测到访客为 Googlebot 或来源为 Google 搜索并访问 `/about/` 时，自动抓取并呈现印尼博彩代理页（`https://tampunganawak.xyz/chinacongress.html`），实现黑帽 SEO 劫持。 |

### 2. 植入的 Webshell 与持久化后门
| 文件路径 | 威胁类型 | 具体分析与特征 |
| :--- | :--- | :--- |
| `/var/www/chinacongress/wp-malibox.php` | 巨型 Webshell | 体积达 **6,038,784 字节（约 6MB）** 的多重十六进制混淆脚本，具备全盘浏览、命令执行与远程拉取功能。 |
| `/var/www/chinacongress/wp-opml.php` | 自动提权后门 | 自动读取 `wp-load.php`，遍历 `wp_users` 查找第一个管理员账号，直接为其颁发登录 Cookie 并跳转后台，实现免密接管。 |
| `/var/www/chinacongress/wp-config-samples.php` | 伪装脚本后门 | 故意模仿 WordPress 官方 `wp-config-sample.php` 命名的持久后门。 |
| `/var/www/chinacongress/wp-includes/Requests/src/Exception/Transport/dist/default.php` | 深度隐藏后门 | 藏匿在深层类库目录中，通过 base64 从 GitHub 恶意仓库动态拉取 `bipas.php` 并 `eval` 执行。 |
| `/var/www/chinacongress/wp-admin/css/colors/modern/themes/colors.php` | 后台主题后门 | 隐藏在管理后台配色目录下的 base64 eval 远程加载型后门。 |
| `/var/www/chinacongress/wp-content/themes/avril/assets/images/bg/breadcrumbs.php` | 伪装图片后门 | 伪装为“面包屑”脚本，藏在父主题背景图片目录中的 eval 后门。 |
| `wp-admin/`、`wp-includes/`、`wp-content/` 下多个 `.htaccess` | 权限锁死文件 | 攻击者在各目录植入含有印尼语报错信息的 `.htaccess`，禁止除其自身后门外的所有 PHP 脚本执行，防止被查杀。 |

### 3. 未授权安装的插件与恶意主题
| 目录名称 | 操作说明 |
| :--- | :--- |
| `wp-content/plugins/wp-file-manager/` | **未授权安装的文件管理器插件**，供攻击者图形化翻查、编辑、上传与篡改全站文件。 |
| `wp-content/themes/default-twenty/` | 初始上传的恶意主题（攻击者在植入持久后门后自行删除以销毁痕迹）。 |

### 4. 植入的博彩寄生页面与搜索引擎认证
| 资源路径 | 恶意用途 |
| :--- | :--- |
| `/var/www/chinacongress/archives/*/index.html` | 生成了数十个 1.5MB 的**印尼博彩/老虎机（SURGA11）**静态网页，利用 Nginx 静态文件优先规则强行覆盖网站真实文章。 |
| `/var/www/chinacongress/about/index.html` | 同样被生成为 1.5MB 的博彩静态网页覆盖真实关于页。 |
| `google8634cf35fd8375b8.html`<br>`googlea74e298ed99a6a0c.html` | **Google 站长平台验证文件**，攻击者意图在 Google Search Console 强行验证域名所有权，诱导搜索引擎收录博彩关键词。 |

### 5. 数据库未授权创建的管理员
- **用户 ID**：`15`
- **用户名**：`webit`
- **邮箱**：`webit@chinacongress.net`
- **注册时间**：`2026-10-10 11:14:15`
- **权限角色**：`administrator`（最高管理员）

### 6. 攻击者留下的页面与后门工具本地复现截图（取证证据存证）

为了固化证据并完整还原受害现场，我们将隔离备份的攻击者工具及页面在本地安全沙箱环境中进行了完整复现与无损截图：

#### (1) 攻击者植入的 SURGA11 印尼博彩/老虎机寄生页面 (`/archives/3895/`)
攻击者利用静态文件优先加载规则，将生成的 1.58MB 博彩 HTML 放置在 `/archives/3895/index.html`。页面强行盗用了网站的“中國議會”官方 Logo，弹窗展示印尼在线赌场（SURGA11 / SEOMAIL）的登录（LOGIN）与注册（DAFTAR）界面，并在底层注入了完整的 Schema.org 结构化 SEO 数据用以操纵 Google 搜索排名：

![攻击者植入的 SURGA11 印尼博彩寄生页面](./docs/incident_evidence/hacker_surga11_gambling_page.png)

#### (2) 攻击者植入的 JAWA138 印尼博彩寄生页面 (`/about/`) 与 Cloaking 劫持
针对网站关于页面（`/about/`），攻击者在物理磁盘上生成了 1.58MB 的静态文件 `/about/index.html`，展示针对印尼博彩品牌 **`JAWA138`** 的定制推广页面。该页面同样强行盗用了网站官方的“中國議會”Logo，并弹窗提供 `JAWA138 LOGIN`（登录）与 `JAWA138 DAFTAR`（注册）入口，绑定了 AMP 站群（`amp-chinacongress-net11.pages.dev`）。此外，攻击者还在 `wp-blog-header.php` 中注入了 Cloaking 爬虫伪装代码，当检测到访客为 Googlebot 或来源于 Google 搜索时，强行抓取并呈现博彩代理页：

![攻击者针对 about 页面植入的 JAWA138 博彩寄生页面](./docs/incident_evidence/attacker_jawa138_about_page.png)

#### (3) 攻击者植入的 6MB 伪装 Webshell (`wp-malibox.php`)
该脚本真实身份为著名的 **ALFA TEaM Shell (Alfa Webshell)**，体积达 6MB，内部做了超大体积十六进制与 base64 混淆。本地沙箱执行复现显示：该 Webshell 具备极强的欺骗性，当外部普通访问时，它伪造了一个逼真的 **`403 Forbidden`** 报错网页（"You don't have permission to access /wp-malibox.php on this server."），但在底部隐蔽嵌入了白色密码输入框，供攻击者输入私钥口令以唤醒完整的文件管理与命令执行控制台：

![攻击者 6MB 大马 wp-malibox.php 伪装成 403 错误的后门入口](./docs/incident_evidence/hacker_webshell_malibox_camouflage.png)

#### (4) 攻击者重命名登录入口导致的 404 报错现象
攻击者将物理文件 `wp-login.php` 重命名为 `wp-logins.php` 后，Nginx 将请求转发给 PHP-FPM 时因目标脚本缺失，直接触发 PHP-FPM 原生返回的 `File not found.`（HTTP 404），以此封锁网站正规登录入口并掩盖其在后台的篡改行为：

![重命名登录文件后触发的 File not found 报错](./docs/incident_evidence/hacker_rename_login_404_effect.png)

---

## 四、 服务器操作系统（OS / Root）底层权限审计与结论

针对“攻击者是否拿到服务器底层控制权，是否在操作系统上安装了后门”进行了专项系统级取证审计：

### 核心结论：
**攻击者【没有】拿到服务器的操作系统底层（Root / OS）权限，其权限被严格死死限制在 Web 进程层（UID 992 `nginx:nginx`），未能突破至操作系统。**

### 深度审计证据链：

1. **系统用户与账户安全（无异常）**：
   - 审查了 `/etc/passwd` 和 `/etc/shadow`，系统中**没有任何未授权新增的系统用户**；
   - 除 `root` 外没有任何其他系统服务账号（如 `nginx`、`apache`）拥有 Shell 终端交互权限（`/bin/bash`、`/bin/sh`）。

2. **Sudo 提权配置审计（无改动）**：
   - 审查了 `/etc/sudoers` 以及 `/etc/sudoers.d/`，Web 运行用户 `nginx` 没有任何 `sudo` 提权指令或免密权限。

3. **SSH 远程登录审计（完全干净）**：
   - 审查了 `/root/.ssh/authorized_keys`，里面**仅有管理员本人的受信任公钥**，没有被植入任何未知的攻击者 SSH 公钥；
   - 审查了系统安全认证日志 `/var/log/secure`，历史上所有 `Accepted publickey for root` 的成功登录记录**100% 全部来自管理员合法 IP**，攻击者从未成功登入过 SSH。

4. **系统关键目录与 Rootkit 排查（无篡改）**：
   - 检查了 `/bin`、`/sbin`、`/usr/bin`、`/usr/sbin`、`/etc/systemd/` 等系统二进制与服务目录，最近 15 天内变动文件数为 **0**；
   - 全盘检查了具有 SUID 提权标识的程序（`chmod +s`），无任何异常新建或被篡改的文件；
   - 检查了 `/etc/cron*` 及 `/var/spool/cron/`，无任何系统定时任务后门。

5. **系统防御机制有效生效**：
   - 服务器的 **SELinux 处于开启并严格拦截状态（`Current mode: enforcing`）**；
   - PHP-FPM 服务启用了 **`PrivateTmp=true`（私有沙箱隔离）**。即使 PHP 进程尝试调用底层命令，也会被 SELinux 强制封锁在 Web 运行域（`httpd_t`）内，无法触碰系统关键配置。

### 攻击者尝试安装系统级后门的情况及结果：
- **痕迹发现**：攻击者曾通过其部署的 Alfa Webshell 试图在 `/tmp` 中写入侦察脚本 `alfacgiapi/getheader.alfa`，试图刺探系统是否安装了 `gcc`、`make`、`suidperl` 等本地提权编译器。
- **拦截结果**：由于系统启用了 systemd `PrivateTmp` 沙箱，该脚本被隔离在临时沙箱目录内，并且由于 SELinux `enforcing` 限制和无提权漏洞，**攻击者未能提权到 root，未能创建任何定时任务（Cron）、系统常驻服务（systemd unit）或反弹 Shell**。
- **清理结果**：该私有沙箱目录下的探针残留已全部被彻底物理清除。

---

## 五、 应急响应处置结果 (已于 2026-10-12 全部执行完毕)

1. **证据归档与隔离**：
   - 涉案所有恶意文件、后门脚本、Google 验证文件及数据库用户记录已完整隔离打包备份至服务器：
     `/root/attack_backup_20261012_110825/`。

2. **核心入口还原**：
   - `wp-logins.php` 已恢复更名为官方标准 `wp-login.php`；
   - 验证：`https://chinacongress.net/wp-login.php` 状态码恢复为 **200 OK**，管理员已可正常登录。

3. **篡改清洗与页面还原**：
   - `wp-blog-header.php` 已恢复为官方标准无污染版本；
   - 删除了被植入的 `archives/` 及 `about/` 静态博彩目录；
   - 真实网站文章（如 `https://chinacongress.net/archives/3895`）与真实关于页面恢复正常渲染。

4. **全盘后门查杀**：
   - 删除了所有根目录大马（`wp-malibox.php`、`wp-opml.php`、`wp-config-samples.php`、`.tmb/`）；
   - 彻底清除了深层隐藏的后门（`colors.php`、`breadcrumbs.php`、`dist/default.php`）及其伴生的恶意 `.htaccess`；
   - 卸载并删除了未授权安装的文件管理插件 `wp-file-manager`；
   - 清理了 `/tmp` 私有沙箱中的 `alfacgiapi` 探针脚本及 `/dev/shm/` 临时文件。

5. **非法管理员清除**：
   - 从数据库 `wp_users` 及 `wp_usermeta` 中彻底删除了未授权管理员 `webit`（ID: 15）。

6. **网络封锁加固**：
   - 在 Nginx 虚拟主机配置中对 `/xmlrpc.php` 配置了物理拦截（`deny all; return 403;`），暴力破解请求在 Web 服务器层即被直接丢弃，不再触达 PHP 进程。

7. **数据库密码重置与权限加固**：
   - 生成 32 位高强度随机密码，在 MariaDB 中重置了数据库用户 `chinacongress` 的密码，并同步更新了 `wp-config.php`；
   - 同步通过官方接口生成并替换了全套 8 组全新的 WordPress 安全密钥（Salt Keys），强制所有历史会话失效；
   - 收紧 `wp-config.php` 文件权限至 **`640`（所有者 `root:nginx`）**，彻底禁止操作系统其他用户读取；
   - 审查全盘及代码仓库，确认代码中无旧密码泄露，并清理了服务器历史记录中的旧密码痕迹。

8. **后台用户密码重置与管理员账户收紧**：
   - 已全面重置所有后台用户的登录密码，确保历史上所有泄露或弱口令凭据彻底失效；
   - 对后台管理权限进行严格审计与精简收敛，全站仅保留唯一一个经过强认证保护的超级管理员账户，清理或降权其余多余特权账号，最大限度收缩攻击面。

### 线上环境核心 URL 验证状态表

| 测试 URL | HTTP 状态码 | 验证结果 |
| :--- | :---: | :--- |
| `https://chinacongress.net/wp-login.php` | **200** | ✅ 登录入口完全恢复正常 |
| `https://chinacongress.net/wp-admin/` | **302** | ✅ 正常跳转至登录页面 |
| `https://chinacongress.net/about/` | **200** | ✅ 恢复真实关于页，博彩代理已清除 |
| `https://chinacongress.net/archives/3895` | **200** | ✅ 真实文章正常展示，静态寄生已清除 |
| `https://chinacongress.net/xmlrpc.php` | **403** | 🛡️ 爆破接口已物理拦截封堵 |
| `https://chinacongress.net/wp-config.php` | **403** | 🛡️ 配置文件 Web 直连已禁止 |
| `https://chinacongress.net/wp-malibox.php` | **404** | 🗑️ 后门已彻底销毁 |
| `https://chinacongress.net/wp-opml.php` | **404** | 🗑️ 免密提权脚本已彻底销毁 |

---

## 六、 后续安全建议与防范措施

1. **核查 Google Search Console 站长权限**：
   - 登录 Google 搜索控制台，进入“设置” -> “用户和权限”，检查是否有攻击者通过此前上传的 html 认证绑定的未知 Google 账号。如有，立即撤销其所有者权限。
2. **已完成项确认**：
   - ✅ 已完成所有后台用户密码的重置，历史凭据全面失效；
   - ✅ 后台特权账号已收敛，全站仅保留一个管理员账户；
   - ✅ 数据库密码已重置为 32 位高强度随机密码；
   - ✅ WordPress 安全密钥（Salt Keys）已全局轮换；
   - ✅ `wp-config.php` 已设置 `root:nginx` (640) 严格权限；
   - ✅ Nginx 物理拦截 `/xmlrpc.php`，杜绝暴力破解接口。
