# 找回密码加固逻辑测试

桩函数隔离测试：不启动 WordPress，用极简桩函数模拟 WP 核心函数，
直接运行 functions.php 里被测函数的**真实代码**。

## 运行方法

```bash
# 任一测试
~/workspace/.php/php test-lostpassword-validation.php

# 全部
for t in test-*.php; do ~/workspace/.php/php "$t" || break; done
```

需要 PHP（本机没有系统 PHP，用 `~/workspace/.php/php`，PHP 8.3 静态构建）。
全部通过返回 0，有失败返回 1。

## 测试文件

| 文件 | 被测代码 | 场景数 |
|---|---|---|
| `test-lostpassword-validation.php` | 6.2 双重校验 | 7：匹配放行 / 不匹配拦截 / 省略字段绕过拦截 / 空字段 / 用户名栏填邮箱 / 编程调用跳过 / 未知用户 |
| `test-lostpassword-text.php` | 6.3 文案修正 | 7：标签→用户名 / 提示语或→和 / retrievepassword / 登录页不动 / 非默认域不动 / 其他文案不动 / 无 action 不动 |
| `test-login-errors.php` | 第 3 节 login_errors | 4：找回密码页提示邮箱 / retrievepassword / 登录页保持原样 / 无 action 保持原样 |
| `bootstrap.php` | 提取与断言小助手 | — |

## 关键设计

- **不快照被测代码**：每个测试运行时从 `../functions.php` 实时提取被测函数，
  保证测的永远是最新代码。提取规则见 `bootstrap.php`。
- **结束括号必须顶格**：提取依赖"函数/闭包的结束 `}` 顶格写"这一约定；
  若将来改动破坏该格式，对应测试会报错退出（exit 2），不会静默测错。
- **桩的诚实边界**：桩按"理解中的 WP 行为"手写，只验证逻辑正确性，
  不替代线上真实验证。改完逻辑请重跑全部测试，并到线上实际验证。

## 安全说明

本目录随 `scripts/` 一起被部署脚本排除（`--exclude='scripts'`），
**永远不会同步到生产服务器**，也不会被公网访问到。
