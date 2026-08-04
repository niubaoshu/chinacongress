# 📖 WordPress 主题与子主题二次开发核心指南 (Developer Guide)

> 本文档旨在为新加入项目的开发者提供一份系统、清晰的 WordPress 主题/子主题开发指南，涵盖核心概念、继承规则、代码规范及本项目的二次开发架构。

---

## 📚 目录

1. [WordPress 主题与子主题继承机制](#1-wordpress-主题与子主题继承机制)
2. [核心文件构成与作用](#2-核心文件构成与作用)
3. [WordPress 核心钩子机制 (Hooks)](#3-wordpress-核心钩子机制-hooks)
4. [数据缓存与 WP-Cron 后台任务](#4-数据缓存与-wp-cron-后台任务)
5. [本项目 (Avril-Child) 架构与定制核心](#5-本项目-avril-child-架构与定制核心)
6. [本地开发与部署工作流](#6-本地开发与部署工作流)

---

## 1. WordPress 主题与子主题继承机制

### 💡 为什么使用子主题 (Child Theme)？
在 WordPress 中，父主题（Parent Theme，本项目中为 `avril`）包含了基础样式和核心框架。直接修改父主题代码会导致**主题更新升级时修改被完全覆盖抹去**。

子主题（`avril-child`）是一种安全的二次开发机制：
- **代码隔离**：所有自定义功能、新组件和 CSS 样式均保存在子主题目录下。
- **安全升级**：父主题更新时，子主题的代码不会受到任何影响。

### 🔄 模板继承优先级 (Template Hierarchy)
当访客请求一个页面时，WordPress 会按照固定优先级在主题目录中查找对应的模板文件：

```text
访问页面/文章
   │
   ├─► 1. 优先在子主题 (avril-child) 中查找对应模板
   │      │
   │      ├─► 存在 ──► 使用子主题模板渲染
   │      │
   │      └─► 不存在 ──► 降级在父主题 (avril) 中查找
   │
   └─► 2. 若父子主题均无特定模板 ──► 使用 index.php 兜底渲染
```

#### 模板覆盖规则：
- **普通 PHP 模板**（如 `archive.php`, `single.php`, `category.php`）：子主题放置同名文件即可**完全覆盖**父主题的对应模板。
- **样式表 `style.css`**：子主题的 `style.css` 会在父主题样式加载后再加载，通过 CSS 优先级（或 `!important`）覆盖父主题样式。
- **`functions.php` 的特殊性**：子主题的 `functions.php` **不会**覆盖父主题的 `functions.php`，而是**在父主题 `functions.php` 执行前优先加载**。

---

## 2. 核心文件构成与作用

在 `wp-content/themes/avril-child/` 目录下：

| 文件 / 目录 | 核心作用说明 |
| :--- | :--- |
| **`style.css`** | 包含子主题元信息声明（如 `Template: avril`），以及所有自定义样式、响应式媒体查询规则。 |
| **`functions.php`** | 子主题的核心逻辑大脑。包含钩子注册、API 同步、WP-Cron 挂载及自定义函数。 |
| **`archive.php`** | 分类/标签/日期/作者归档页模板（实现了统一的智能封面大图卡片与分页导航）。 |
| **`single.php`** | 文章详情页模板（承载正文、社交分享条及一键复制全文章节功能）。 |
| **`category.php`** | 分类列表页专属模板。 |
| **`template-parts/`** | 模块化切片组件目录：<br>• `content/content-search.php`：列表卡片切片<br>• `content/content-page.php`：文章正文切片<br>• `sections/section-blog.php`：首页最新发布板块<br>• `sections/section-features.php`：首页推荐内容板块 |
| **`scripts/`** | DevOps 运维与部署工具脚本集合（备份、本地还原、线上代码同步等）。 |

---

## 3. WordPress 核心钩子机制 (Hooks)

WordPress 采用事件驱动的插件化架构，主要通过两类**钩子 (Hooks)** 扩展功能：

### 1. 动作钩子 (Action Hooks: `add_action`)
允许你在特定事件发生时执行自定义代码（如加载脚本、注册 Cron 任务、在 `<head>` 或 `<footer>` 插入代码）。

```php
// 示例：在前端加载脚本样式
add_action( 'wp_enqueue_scripts', 'avril_child_enqueue_styles', 99 );

// 示例：在 <head> 自动插入 Open Graph 社交元数据
add_action( 'wp_head', 'chinacongress_add_social_og_tags', 5 );
```

### 2. 过滤器钩子 (Filter Hooks: `add_filter`)
允许你在数据输出或写入数据库之前拦截并修改数据。

```php
// 示例：拦截正文输出，统一将绝对域名转换为相对路径
add_filter( 'the_content', 'chinacongress_make_content_relative', 99 );

// 示例：拦截分类标题，移除系统多余的前缀“分类：”
add_filter( 'get_the_archive_title', 'chinacongress_clean_archive_title' );
```

---

## 4. 数据缓存与 WP-Cron 后台任务

为了避免高并发访问时向第三方/远程 API 发起阻塞式 HTTP 请求，项目采用了 **Transient 缓存** 与 **WP-Cron 后台定时任务**。

### 1. Transient 临时缓存 API
WordPress 提供的内存/数据库缓存机制：
```php
// 设置带 300 秒（5分钟）过期的缓存
set_transient( 'chinacongress_latest_mainland_members', $members, 300 );

// 读取缓存
$members = get_transient( 'chinacongress_latest_mainland_members' );
```

### 2. WP-Cron 后台异步同步机制
为了达到**首屏 0 毫秒延时**，数据同步工作不在访客请求页面时触发，而是由 WP-Cron 在后台每 5 分钟静默执行：

```text
[服务器后台 WP-Cron] ──每 5 分钟触发──► 请求 https://reg.congresscenter.org/api/...
                                             │
                                             ▼
                                     写入数据库 / Transient 缓存
                                             │
[前台访客访问] ◄────── 0 毫秒直接读取 ──────┘
```

---

## 5. 本项目 (Avril-Child) 架构与定制核心

新开发者在阅读代码时，应重点理解以下核心业务功能在 `functions.php` 中的实现：

1. **双选民登记卡片与走马灯 (`avril_lite_cta()`)**
   - 首页并排显示“海外院选民登记人数”与“大陆院选民登记人数”。
   - 居中展示“最新登记选民：”，带 3.5 秒平滑渐变（Fade & Slide）向上无缝走马灯。

2. **智能媒体抓取引擎 (`chinacongress_get_first_image_url()`)**
   - 提取顺序：文章特色图片 (Featured Image) ➔ 正文第一张 `<img src>` ➔ YouTube 1280x720 封面 ➔ `<video poster>` ➔ 规则 Logo 兜底。

3. **社交分享与全文章节一键复制 (`content-page.php`)**
   - 整合 Telegram, X (Twitter), Facebook, WhatsApp 分享。
   - 使用 Web Clipboard API 结合纯文本格式化输出，实现“一键复制全文与段落”。

4. **全站路径相对化清洗 (`chinacongress_make_content_relative()`)**
   - 在文章保存入库 (`content_save_pre`) 及前台渲染 (`the_content`) 时，自动将硬编码的绝对域名转为相对路径 `/`，保障迁移与多环境部署安全。

---

## 6. 本地开发与部署工作流

### 🛠️ 常用开发命令

1. **语法检查（修改 PHP 代码后必跑）**：
   ```bash
   php -l wp-content/themes/avril-child/functions.php
   ```

2. **同步代码至本地 `http://localhost/` 并清除 OPcache**：
   ```bash
   bash wp-content/themes/avril-child/scripts/sync_custom_code.sh localhost
   ```

3. **部署主线代码至线上生产服务器**：
   ```bash
   bash wp-content/themes/avril-child/scripts/sync_custom_code.sh production
   ```

---

*文档维护状态：最新更新 | 适用于 Avril-Child 7.0+*
