# 开发者与运维指南

本项目为前端二次开发子主题代码仓库与全套 DevOps 自动化运维脚本集合。

---


## 📂 项目文件架构

```text
public_html/ (Git 仓库根目录)
├── .gitignore                          # Git 忽略配置（仅追踪子主题与自主研发插件）
├── README.md                           # 本项目开发者与运维指南文档
├── API_ENDPOINTS.md                    # 外部第三方平台 API 接口集成文档
├── wp-content/
│   ├── plugins/                        # 自主研发插件集（Git 核心追踪项目）
│   │   ├── cc-assets/                  # CC Assets 前端资产按需加载插件 (<!-- cc:assets --> 自动载入 main.js)
│   │   │   └── cc-assets.php
│   │   └── cc-footer-credits/          # 中國議會 — 文章署名（含圖標）古腾堡动态区块插件
│   │       ├── cc-footer-credits.php   # 插件核心注册与设置页
│   │       ├── render.php              # 前台 HTML 动态渲染与样式生成
│   │       ├── parse.php               # 名单库数据结构解析与字段清洗
│   │       ├── editor.js               # 原生 JS 古腾堡可视化编辑区块逻辑
│   │       ├── editor.css              # 编辑器专属控件样式
│   │       └── style.css               # 前台署名与图标响应式样式表
│   └── themes/
│       └── avril-child/                # 二次开发子主题（Git 核心追踪项目）
│           ├── VIDEO_GUIDE.md          # 🎬 文章内嵌活动短视频使用与排版指南
│           ├── DEVELOPER_GUIDE.md      # 📖 WordPress 子主题开发架构与规范指南
│           ├── functions.php           # 子主题核心逻辑、动态过滤器（相对路径清洗、顶栏重写等）
│           ├── style.css               # 二次开发 CSS 响应式样式表
│           ├── single.php              # 文章详情页切片模版
│           ├── archive.php             # 归档列表页模版
│           ├── assets/                 # 自定义字体及静态资源
│           ├── template-parts/         # 首页及页面组件模版切片
│           │   ├── content/
│           │   │   ├── card-post.php   # 通用自适应文章大图卡片组件（支持 home/side/archive 模式）
│           │   │   └── ...
│           │   └── sections/
│           │       ├── section-features.php # 首页“推荐内容”模块切片（自动抓图与无缝跳转）
│           │       └── section-blog.php     # 首页“最新发布”双列大图卡片模块切片
│           └── scripts/                # 本地/开发环境 DevOps 自动化脚本集
│               ├── restore_full_mirror_localhost.sh  # 一键本地镜像全量复原脚本
│               ├── sync_user_data_from_remote.sh     # 一键从线上同步纯用户数据至本地
│               ├── backup_user_data.sh               # 一键打包备份线上或本地数据 SQL+Uploads
│               ├── sync_custom_code.sh               # 一键同步/部署子主题与自主插件代码至本地或生产服务器
│               ├── sync_code_from_remote.sh          # 一键从线上拉取最新代码（子主题+自主插件）回本地
│               └── clean_localhost.sh                # 一键清空本地开发环境
```

---

## 🛠️ DevOps 自动化脚本功能说明

所有运维脚本均位于 `wp-content/themes/avril-child/scripts/` 目录下：

### 1. `restore_full_mirror_localhost.sh`（一键全量复原本地镜像）
- **功能**：从零开始构建一个与线上环境 100% 一致的本地测试站点（`http://localhost/`）。
- **用法**：
  ```bash
  bash wp-content/themes/avril-child/scripts/restore_full_mirror_localhost.sh
  ```

### 2. `sync_user_data_from_remote.sh`（一键同步纯用户数据）
- **功能**：从线上生产服务器拉取最新数据库备份并导入本地，同时增量同步 `wp-content/uploads/` 媒体资源文件，自动转换域名为本地环境。
- **用法**：
  ```bash
  bash wp-content/themes/avril-child/scripts/sync_user_data_from_remote.sh
  ```

### 3. `backup_user_data.sh`（一键备份数据库与媒体库）
- **功能**：打包导出当前数据库并增量归档媒体上传文件至指定备份目录。
- **用法**：
  ```bash
  bash wp-content/themes/avril-child/scripts/backup_user_data.sh
  ```

### 4. `sync_custom_code.sh`（一键同步/部署子主题与自主插件代码）
- **功能**：将本地 Git 仓库中的 `avril-child` 子主题与自主研发插件（`cc-assets`, `cc-footer-credits`）代码同步部署到本地 Web 目录或线上生产服务器。
- **安全机制**：
  - 代码安全性拦截：部署前自动执行全站 PHP 语法 Lint 检测（`php -l`），一旦发现语法错误即刻中断部署。
  - 线上部署限制：强制校验当前 Git 分支，必须在 `main` 主线分支且工作区干净时才允许向生产环境部署。
  - 运维隔离：向线上生产服务器部署时，自动添加 `--exclude='scripts'`，绝不上推本地运维脚本。
- **用法**：
  ```bash
  # 同步至本地 localhost 运行目录 (/srv/http/my_site_name/...)
  bash wp-content/themes/avril-child/scripts/sync_custom_code.sh localhost

  # 部署至线上生产服务器
  bash wp-content/themes/avril-child/scripts/sync_custom_code.sh production
  ```

### 5. `sync_code_from_remote.sh`（一键从线上拉取最新代码到本地）
- **功能**：遵循「Remote-First」原则，从生产服务器反向同步最新的子主题与自主研发插件源码到本地 Git 工作区。
- **用法**：
  ```bash
  bash wp-content/themes/avril-child/scripts/sync_code_from_remote.sh
  ```

### 6. `clean_localhost.sh`（一键清空本地环境）
- **功能**：一键清理本地开发测试目录（`/srv/http/my_site_name`）并删除本地 MariaDB 中的数据库与用户，还原干净系统。
- **用法**：
  ```bash
  bash wp-content/themes/avril-child/scripts/clean_localhost.sh
  ```

---

## 🧩 自主研发 WordPress 插件说明

本项目除前端子主题外，还通过 Git 集中版本控制托管了本站自研的核心 WordPress 插件：

### 1. `cc-assets`（前端资产按需加载器）
- **路径**：`wp-content/plugins/cc-assets/cc-assets.php`
- **功能特性**：
  - 采用 HTML 注释标记按需加载：当文章内容包含 `<!-- cc:assets -->` 时，自动排队载入子主题的 `js/main.js`；
  - 避免传统 Shortcode 泄露：因 SEO / OpenGraph 等元数据生成会剥离或直读原文，HTML 注释在任何情况下均不会污染页面摘要或社交分享卡片；
  - 自动缓存版本控制：自动探测子主题 `js/*.js` 与 `css/*.css` 的最大 `mtime` 作为快取版本参数，文件修改即刻全站生效，无需手动 bump 版本号。

### 2. `cc-footer-credits`（中國議會 — 文章署名含圖標）
- **路径**：`wp-content/plugins/cc-footer-credits/`
- **功能特性**：
  - **古腾堡原生动态区块 (`cc/footer-credits`)**：每篇文章独立署名，无需 Node/Webpack 构建，原生 JS 零依赖直接运行；
  - **后台名单集中管控**：提供「设定 ➔ 文章署名名单」管理页面，名单库分为「人员」、「角色」、「图标」三大类，每行一笔、以 `|` 管道符分隔；
  - **单点维护、全站联动**：文章仅存储人员 ID 与角色 Key，姓名、个人主页网址、职务与社交图标等数据均来自名单库。只需修改后台名单库一处，全站所有历史文章署名实时同步更新；
  - **安全与健壮性**：严格的输入清洗与转义（针对中文编码 URL 采用 `esc_url_raw` 避免截断）、CSS 背景安全过滤、人员缺失提示（显示删除线而不静默丢失）。

---

## 🔑 环境变量与凭据配置规范

为保证安全性，所有自动化脚本均不包含硬编码明文密码。脚本支持读取以下环境变量：

### 线上生产环境凭据变量
| 环境变量名 | 含义 | 默认缺省值 |
| :--- | :--- | :--- |
| `REMOTE_HOST` | 线上 SSH 主机别名 | `production` |
| `REMOTE_DB_USER` | 线上数据库用户名 | `db_user` |
| `REMOTE_DB_PASS` | **线上数据库密码** | 空（未配置时不传递 `-p` 选项） |
| `REMOTE_DB_NAME` | 线上数据库名称 | `db_name` |

### 本地开发环境凭据变量
| 环境变量名 | 含义 | 默认缺省值 |
| :--- | :--- | :--- |
| `LOCAL_DB_USER` | 本地数据库用户名 | `db_user` |
| `LOCAL_DB_PASS` | **本地数据库密码** | 空（适合 Linux Socket/免密环境） |
| `LOCAL_DB_NAME` | 本地数据库名称 | `db_name` |

### 配置示例
在您本地电脑的 `~/.bashrc` 或 `~/.zshrc` 中增加以下配置即可：
```bash
# 运维脚本环境变量配置
export REMOTE_DB_USER="your_db_user"
export REMOTE_DB_PASS="您的线上数据库真实密码"
export REMOTE_DB_NAME="your_db_name"

# 如果您的本地 MariaDB 强制设置了密码，可以配置以下变量：
# export LOCAL_DB_PASS="您的本地数据库密码"
```

---

## 💻 本地搭建测试环境指南与注意事项

### 1. 上面脚本使用的本地测试环境
- **操作系统**：Linux (Arch Linux / Ubuntu / Debian 推荐)
- **Web 服务器**：Apache 2.4+ 或 Nginx（推荐本地映射路径 `/srv/http/my_site_name` 或工作区路径）
- **PHP**：PHP 7.4 或 PHP 8.x（需包含 `mysqli`, `gd`, `curl`, `json`, `mbstring` 扩展）
- **数据库**：MariaDB 10.5+ 或 MySQL 8.0+

### 2. 一键搭建测试环境步骤,需要提前准备好测试数据。
```bash
bash wp-content/themes/avril-child/scripts/restore_full_mirror_localhost.sh
```

搭建完成后，在本地浏览器访问 `http://localhost/` 即可开始测试。

---

## 🌐 外部平台 API 接口集成说明

本项目前端（如选民注册人数、最新登记列表）依赖调用的公共 JSON API 接口均为**第三方外部平台**（如 `api.fdcusa.org` 与 `reg.congresscenter.org`）提供，**并非本项目本地开发或托管的接口**。

详细的第三方 API 接口定义、请求参数与响应数据结构说明，请查阅独立的接口文档：
📖 **[外部平台 API 接口集成文档](API_ENDPOINTS.md)**

---

## 🛠️ 开发者指南 (Developer Guide)

关于 WordPress 父子主题继承规则、钩子机制、缓存架构及二次开发组件说明，请查阅子主题目录下的完整指南：
📖 **[WordPress 主题与子主题二次开发核心指南](wp-content/themes/avril-child/DEVELOPER_GUIDE.md)**


