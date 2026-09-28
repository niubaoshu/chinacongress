# 🎬 文章内嵌活动短视频使用指南 (Video Guide)

> 本文档面向网站内容编辑人员与前端开发者，详细说明在 WordPress 文章编辑器中插入、排版与发布站内活动短视频（<15MB、<3分钟）的标准操作流程。

---

## 📌 适用场景与视频规范

* **适用类型**：现场抗议活动实况、新闻快讯剪辑、人物发声短片、知识问答短视频。
* **视频规格推荐**：
  * **格式**：推荐 `.mp4`（H.264 视频编码 + AAC 音频编码，全平台与所有移动设备兼容性最佳）。
  * **时长**：建议在 **3 分钟以内**。
  * **体积**：建议在 **15MB 以下**（轻量加载快，节省 CDN 流量与服务器带宽）。
  * **存储方式**：直接上传至 WordPress 后台媒体库（站内保存）。

---

## 🚀 两种推荐使用方式

系统已实现全自动的**流式居中美化**（最大宽度 650px 自适应）与**多视频互斥播放**（播放当前视频时，其他视频自动暂停），再也**不需要在文章末尾复制粘贴任何 `<script>` 脚本**！

### 方式一：极简短代码（⭐ 最推荐，纯文字直接输入）

在后台古腾堡或经典编辑器中，像平时打字一样新建一行，直接填入短代码：

#### 1. 带标题/说明文字（最常用）：
```text
[cc_video src="/wp-content/uploads/2026/09/video_sample.mp4" caption="一人一票改变中国，中国议会创始选民在洛杉矶领事馆前呐喊"]
```

#### 2. 纯视频（无需任何标题）：
```text
[cc_video src="/wp-content/uploads/2026/09/video_sample.mp4"]
```

> **💡 小贴士**：
> * `src` 既可以填写相对路径（如 `/wp-content/uploads/...`），也可以填写完整的网址（如 `https://chinacongress.net/wp-content/...`），系统均能自动识别。
> * `caption` 为选填项。若填写，会在视频下方自动显示一段首行缩进 2 个字符、行距舒适的文字说明。

---

### 方式二：自定义 HTML 区块（兼容手写标签）

若习惯使用古腾堡的「**自定义 HTML**」区块，可直接书写纯 `<video>` 标签：

```html
<video src="/wp-content/uploads/2026/09/video_sample.mp4" caption="华盛顿抗议现场视频剪辑"></video>
```

若无需标题：
```html
<video src="/wp-content/uploads/2026/09/video_sample.mp4"></video>
```

> **⚠️ 历史避坑指南（切勿再贴脚本）**：
> * **切勿**再往文章正文中复制粘贴 `<script>...</script>` 脚本！
> * 全局守护脚本已在子主题底层自动接管，只要页面出现 `<video>` 就会自动完成居中包装、互斥播放和相对路径补全。

---

## 🎯 多视频连续插入效果（互斥播放）

在一篇活动回顾或多现场报道中，您可以在不同段落间插入多个短视频：

```text
现场第一视角：
[cc_video src="/wp-content/uploads/2026/09/clip1.mp4" caption="上午10点：代表团抵达现场"]

下午集会发言：
[cc_video src="/wp-content/uploads/2026/09/clip2.mp4" caption="下午2点：选民代表发表演讲"]
```

* **互斥效果**：访客在手机或电脑上点击播放其中某一个视频时，页面中**其他正在播放的视频会自动暂停**，杜绝声音相互干扰重叠。

---

## 🎨 视觉与排版特性

1. **自动居中自适应**：
   * 电脑端自动限制为 `650px` 最大宽度居中排版，与文章正文字段完美贴合；
   * 手机竖屏自适应屏幕宽度，绝不超出屏幕左右边界。
2. **轻量加载底色**：
   * 视频自带 `6px` 优雅微圆角与极淡的悬浮阴影，未缓冲完成前采用深色暗底占位，避免页面闪烁。
3. **原生播放控件**：
   * 支持全屏、音量调节、快进快退、画中画（不同浏览器原生支持）。


---

### 🚀 现在：只需简单替换即可升级

主题底层现已**彻底移除了旧全屏霸屏逻辑**，换成了正文流式居中播放器。**您再也不需要写任何 `setTimeout` 或空容器了！**

#### 🛑 以前的写法（不再需要）：
```html
<!-- 以前做法 1：开头的空 div，写死了 260px 宽度 -->
<div id="videoplay" style="margin:0 auto;width:260px !important;height:auto;display:flex;justify-content:center;background:url('...') center center / cover no-repeat;"></div>

...正文内容...

<!-- 以前做法 2：底部的 setTimeout 延迟注入脚本 -->
<script>
setTimeout(function() {
    document.getElementById("videoplay").innerHTML = '<video controls style="width:260px !important; height:auto" src="/wp-content/uploads/2026/09/demo.mp4"></video>';
}, 500);
</script>
```

#### ✅ 升级替换说明：
将之前写法中 `id="videoplay"` 的 `div` 替换为以下任意一种新写法：

* **方式 1：极简短代码（⭐ 最推荐）**
  ```text
  [cc_video src="/wp-content/uploads/2026/09/demo.mp4" caption="王丹博士华府抗议现场致辞片段"]
  ```

* **方式 2：纯 HTML 标签（如果您习惯手写 HTML）**
  ```html
  <video src="/wp-content/uploads/2026/09/demo.mp4" caption="王丹博士华府抗议现场致辞片段"></video>
  ```

底部的以下脚本**不再需要，直接删除即可**：
```html
<script>
setTimeout(function() {
    document.getElementById("videoplay").innerHTML = '<video controls style="width:260px !important; height:auto" src="/wp-content/uploads/2026/09/demo.mp4"></video>';
}, 500);
</script>
```


