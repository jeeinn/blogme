# blogme

`blogme` 是将 `tunalog` 从 Go 技术栈重构到 PHP 技术栈后的版本，保持原有功能模块、前端风格（Alpine + TocasUI）和 SQLite 存储模型。

## 技术栈

- PHP 8.1+
- SQLite（PDO）
- 前端：Alpine.js + TocasUI（与原项目一致）
- 架构：MVC（Controller + Repository + Core Service）
- 测试：PHPUnit

## 已迁移功能

- 初始化向导（`/wizard`）
- 登录/登出（`/login`、`/admin/logout`）
- 后台管理：
  - 用户管理
  - 文章管理（创建/编辑/回收站/删除/置顶/密码文章）
  - 标签管理
  - 导航管理
  - 站点设置
  - 外观设置
  - 图片库上传/删除
- 前台：
  - 首页列表
  - 文章详情（含密码解锁）
  - 标签/作者/归档过滤
  - RSS 输出（`/rss.xml`）
  - 404 主题页
- CLI：
  - 重置密码：`php cli.php reset-password <email>`

## 项目结构

```text
blogme/
├─ app/
│  ├─ Controllers/
│  ├─ Core/
│  ├─ Repositories/
│  ├─ Services/
│  └─ Support/
├─ bootstrap/
├─ resources/
│  ├─ templates/
│  └─ locales/
├─ docs/
├─ public/
│  ├─ uploads/
│  ├─ themes/
│  └─ admin/assets/
├─ storage/
├─ tests/
├─ cli.php
└─ composer.json
```

## 快速开始

1. 安装依赖

```bash
composer install
```

2. 启动（开发模式）

```bash
php -S 127.0.0.1:8080 -t public dev-router.php
```

说明：`-t public` 必须带上。该命令会让内建服务器优先直出 `public/` 下的静态资源（如 `/themes/*`、`/admin/assets/*`、`/uploads/*`），其余请求再交给 `public/index.php`。

3. 首次访问

- 前台：`http://127.0.0.1:8080`
- 向导：`http://127.0.0.1:8080/wizard`
- 后台：`http://127.0.0.1:8080/admin`

## 数据与配置

- 数据库文件：`db.sqlite`
- 站点配置：`config.json`
- 上传目录：`public/uploads/`（运行期唯一上传目录）
- 主题目录：`public/themes/`（主题模板、主题本地化、主题静态资源）

## 目录职责（简化后）

- `resources/`：项目内置资源（后台模板、基础语言包）
- `public/`：Web 可访问目录（上传文件、后台静态、主题文件）
  - `public/uploads/`：用户上传内容（持久化数据）
  - `public/admin/assets/`：后台静态资源（受 Git 管理）
  - `public/themes/`：主题目录（模板 + 主题语言 + 主题静态）
  - `/assets/*`：兼容路径，运行时映射到当前主题的 `public/themes/<theme>/assets/*`

## 编辑器与数据链路

- 编辑器改造与 Markdown 持久化链路说明：[`docs/editor-markdown-pipeline.md`](./docs/editor-markdown-pipeline.md)
- 若更新后台编辑器资源，请执行：
  - `npm install`
  - `npm run build:admin-editor`

## 测试

```bash
./vendor/bin/phpunit
```

## 部署

共享主机、Apache 重写、权限、备份与上线建议见：

- [DEPLOYMENT.md](./DEPLOYMENT.md)
- [docs/backup-and-restore.md](./docs/backup-and-restore.md)
