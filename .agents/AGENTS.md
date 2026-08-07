# Project Rules & Workflow Constraints

## 核心原则：线上代码变更优先同步到本地 (Remote-First Syncing Rule)
当对比线上生产服务器（Chinacongress）与本地 Git 仓库中的代码时：
- **核心规矩**：一旦发现线上服务器的代码与本地代码存在不一致，**必须优先将线上服务器的新代码同步/拉取回本地**，防止覆盖线上可能存在的最新改动！
- 在确认本地代码已完整吸收线上改动后，才能开展新的开发与部署操作。
