# 官方网站 - 开发者与运维指南

本项目为前端二次开发子主题代码仓库与全套 DevOps 自动化运维脚本集合。

---


## 📂 项目文件架构

```text
public_html/ (Git 仓库根目录)
├── .gitignore                          # Git 忽略配置（仅追踪 README.md 与 avril-child）
├── README.md                           # 本项目开发者与运维指南文档
└── wp-content/
    └── themes/
        └── avril-child/                # 二次开发子主题（Git 核心追踪项目）
            ├── functions.php           # 子主题核心逻辑、动态过滤器（相对路径清洗、顶栏重写等）
            ├── style.css               # 二次开发 CSS 响应式样式表
            ├── category.php            # 分类列表页专属模版（左图右字大图卡片布局）
            ├── single.php              # 文章详情页切片模版
            ├── archive.php             # 归档列表页模版
            ├── assets/                 # 自定义字体及静态资源
            ├── template-parts/         # 首页及页面组件模版切片
            │   └── sections/
            │       ├── section-features.php # 首页“推荐内容”模块切片（自动抓图与无缝跳转）
            │       └── section-blog.php     # 首页“最新发布”双列大图卡片模块切片
            └── scripts/                # 本地/开发环境 DevOps 自动化脚本集
                ├── restore_full_mirror_localhost.sh  # 一键本地镜像全量复原脚本
                ├── sync_user_data_from_remote.sh     # 一键从线上同步纯用户数据至本地
                ├── backup_user_data.sh               # 一键打包备份线上或本地数据 SQL+Uploads
                ├── sync_custom_code.sh               # 一键同步/部署子主题代码至本地或生产服务器
                ├── clean_localhost.sh                # 一键清空本地开发环境
                ├── convert_features_to_relative.php  # 数据库 Serialized 配置相对化工具
```

---

## 🛠️ DevOps 自动化脚本功能说明

所有运维脚本均位于 `wp-content/themes/avril-child/scripts/` 目录下：

### 1. `restore_full_mirror_localhost.sh`（一键全量复原本地镜像）
- **功能**：从零开始构建一个与线上环境 100% 一致的本地测试站点（`http://localhost/`）。
- **流程**：
  1. 自动从 WordPress.org 官方下载最新版 WordPress 核心、Avril 父主题及 Clever Fox 插件。
  2. 自动定位最新的本地数据备份包，解压 `uploads/` 媒体库与 `sql` 数据库。
  3. 自动创建本地数据库与用户，一键导入并修正 `siteurl` / `home` 域名。
  4. 自动部署子主题 `avril-child` 代码并绑定 WordPress 主题激活状态。
- **用法**：
  ```bash
  bash wp-content/themes/avril-child/scripts/restore_full_mirror_localhost.sh
  ```

### 4. `sync_custom_code.sh`（一键同步/部署子主题代码）
- **功能**：将本地 Git 仓库中的 `avril-child` 子主题代码更新部署到本地 Web 目录或线上生产服务器。
- **安全机制**：
  - 线上部署限制：强制校验当前 Git 分支，必须在 `main` 主线分支上才允许向生产环境部署。
  - 运维隔离：向线上生产服务器部署时，自动添加 `--exclude='scripts'`，绝不上推本地运维脚本。
- **用法**：
  ```bash
  # 同步至本地 localhost 运行目录 (/srv/http/my_site_name/...)
  bash wp-content/themes/avril-child/scripts/sync_custom_code.sh localhost

  # 部署至线上生产服务器
  bash wp-content/themes/avril-child/scripts/sync_custom_code.sh production
  ```

### 5. `clean_localhost.sh`（一键清空本地环境）
- **功能**：一键清理本地开发测试目录（`/srv/http/my_site_name`）并删除本地 MariaDB 中的数据库与用户，还原干净系统。
- **用法**：
  ```bash
  bash wp-content/themes/avril-child/scripts/clean_localhost.sh
  ```
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
