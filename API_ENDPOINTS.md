# 🌐 外部平台 API 接口集成文档 (External Platform API Endpoints)

> [!IMPORTANT]
> **特别声明**：本文档记录的所有 API 接口均为**第三方外部平台（如 `api.fdcusa.org` 与 `reg.congresscenter.org`）提供的公共接口**，**并非本网站（Chinacongress.net）或本项目自行开发或托管的本地接口**。本项目仅在前端页面通过 HTTP/Fetch 异步调用上述外部平台接口，用以实时展示大陆院与海外院的相关注册统计数据。

---

## 1. 海外院选民登记人数与最新选民接口 (Overseas Council API)

- **提供方平台**：`api.fdcusa.org`
- **接口 URL**：`https://api.fdcusa.org/index.php?token=8d9f3b7c2e6a` *(或 `https://api.fdcusa.org/?token=8d9f3b7c2e6a`)*
- **请求方式**：`GET`
- **数据格式**：`JSON`
- **响应示例**：
  ```json
  {
      "success": true,
      "total": 444,
      "data": [
          {
              "name": "Z**",
              "residence": "德国"
          },
          {
              "name": "L**",
              "residence": "德国"
          },
          {
              "name": "蒋**",
              "residence": "其他"
          }
      ]
  }
  ```
- **字段说明**：
  - `success` *(boolean)*: 接口响应状态。
  - `total` *(integer)*: 海外院选民登记总人数。
  - `data` *(array)*: 海外院最新登记选民列表。
    - `residence` *(string)*: 选民居住国家/地区。
    - `name` *(string)*: 选民脱敏显示姓名。

---

## 2. 大陆院注册人数统计接口 (Registration Count API)

- **提供方平台**：`reg.congresscenter.org`
- **接口 URL**：`https://reg.congresscenter.org/api/public/registration_count.json`
- **请求方式**：`GET`
- **数据格式**：`JSON`
- **响应示例**：
  ```json
  {
      "updated_at": "2026-08-02T22:12:22-07:00",
      "total": 730
  }
  ```
- **字段说明**：
  - `updated_at` *(string)*: 数据最后更新时间（ISO 8601 格式）。
  - `total` *(integer)*: 大陆院注册总人数。

---

## 3. 大陆院最新注册成员接口 (Latest Members API)

- **提供方平台**：`reg.congresscenter.org`
- **接口 URL**：`https://reg.congresscenter.org/api/public/latest_members.json`
- **请求方式**：`GET`
- **数据格式**：`JSON`
- **响应示例 A（最新周期统计输出格式 - 2026-09-14 起）**：
  ```json
  {
      "updated_at": "2026-09-14T09:30:01-07:00",
      "members": [
          {
              "province": "本周",
              "display_name": "020 位"
          },
          {
              "province": "本月",
              "display_name": "080 位"
          },
          {
              "province": "本季",
              "display_name": "342 位"
          }
      ]
  }
  ```
- **响应示例 B（传统具体选民脱敏列表格式）**：
  ```json
  {
      "updated_at": "2026-08-02T22:12:22-07:00",
      "members": [
          {
              "province": "江蘇",
              "display_name": "***7E6"
          },
          {
              "province": "廣東",
              "display_name": "***X9K"
          },
          {
              "province": "北京",
              "display_name": "***JT4"
          }
      ]
  }
  ```
- **字段说明**：
  - `updated_at` *(string)*: 数据最后更新时间（ISO 8601 格式）。
  - `members` *(array)*: 大陆院最新注册成员或统计数据列表。
    - `province` *(string)*: 成员所属省份/地区（或统计周期说明，如“本周”、“本月”、“本季”）。
    - `display_name` *(string)*: 成员脱敏显示名称（或统计周期注册人数，如“020 位”）。
