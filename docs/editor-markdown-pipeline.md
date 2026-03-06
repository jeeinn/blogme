# 编辑器改造说明（Milkdown Crepe）

## 1. 目标

本项目后台编辑器从 EasyMDE 切换为 Milkdown Crepe，并坚持以下数据链路：

`Editor UI -> Markdown(唯一持久化) -> DB -> Markdown parser -> HTML`

核心原则：

- `posts.content` 仅保存 Markdown 字符串。
- 不新增 JSON 内容字段，不做双写。
- 前台渲染继续走现有 `Blogme\Support\Markdown::render()`。

## 2. 为什么选择 Crepe

- 提供块级交互（含块编辑能力），但底层仍是 Markdown 往返。
- 适合后续“全站导出 Markdown”能力：导出时可直接读取 DB 内容，无需格式转换。
- 相比存储块 JSON 的方案，长期维护成本更低，内容语义更稳定。
- 当前实现已禁用 `Latex` feature，以保持后台产物更轻量。

## 3. 实现范围（Phase 1）

仅替换后台新建/编辑页编辑器 UI，不改后端存储与渲染逻辑：

- 模板：
  - `resources/templates/admin_post_create.html`
  - `resources/templates/admin_post_edit.html`
- 静态资源：
  - `public/admin/assets/editor-crepe-src.js`（源码入口）
  - `public/admin/assets/editor-crepe-theme.css`（不含 latex.css 的主题组合）
  - `public/admin/assets/editor-crepe.js`（打包后的运行脚本）
  - `public/admin/assets/editor-crepe.css`（打包后的样式）
  - `public/admin/assets/editor-assets/`（若启用更多功能时的静态依赖）
  - `public/admin/assets/style.css`

## 3.1 本地打包与编译步骤

为避免浏览器直接加载第三方 ESM/CDN 样式链导致的兼容问题，采用本地打包产物：

1. 安装前端构建依赖

```bash
npm install
```

2. 编译编辑器资源

```bash
npm run build:admin-editor
```

3. 产物说明

- `public/admin/assets/editor-crepe.js`
- `public/admin/assets/editor-crepe.css`
- `public/admin/assets/editor-assets/*`

4. 何时需要重新编译

- 修改 `public/admin/assets/editor-crepe-src.js`
- 修改 `public/admin/assets/editor-crepe-theme.css`
- 升级 `@milkdown/crepe` 版本
- 调整打包参数（`package.json` 中 `build:admin-editor`）

## 4. 前端接入方式

### 4.1 编辑器容器

页面保留 `textarea[name="content"]` 作为提交源，并隐藏：

- `#content`：真实提交字段（Markdown）
- `#block-editor`：Crepe 挂载节点

### 4.2 Markdown 同步

`editor-crepe.js`（由 `editor-crepe-src.js` 打包）在 `markdownUpdated` 回调中将最新 Markdown 实时回写到 `#content`。

表单提交时再次写入一次，确保最终提交值与编辑器一致。

### 4.3 图片上传

Crepe 的 `image-block` 上传钩子绑定现有接口 `/admin/photos/api`：

- 请求字段：
  - `_csrf`
  - `photo_file`
- 返回字段：
  - `path`

上传成功后在 Markdown 中使用该路径。

### 4.4 未保存离开提醒

编辑内容与初始内容不一致时，注册 `beforeunload` 提醒；提交时移除提醒。

### 4.5 Latex 说明

- 后台编辑器已禁用 `Latex` feature，不提供公式块编辑 UI。
- 若需要启用公式能力，需要：
  - 在 `editor-crepe-src.js` 中将 `Crepe.Feature.Latex` 设为 `true`（或移除禁用配置）。
  - 在 `editor-crepe-theme.css` 中恢复 `latex.css` 相关样式引入。
  - 重新执行 `npm run build:admin-editor`。

## 5. 不变项（必须保持）

- `AdminController` 仍通过 `post('content')` 保存正文。
- 数据库 `posts.content` 仍为纯文本 Markdown。
- 前台主题仍使用 `{{ markdown .Post.Content }}` 渲染。

这三点保证未来导出能力简单、稳定。

## 6. 导出功能设计建议

后续新增“导出全站 Markdown”时建议：

1. 新增 CLI 命令（例如 `php cli.php export-markdown`）。
2. 直接读取 `posts.content` 输出为 `.md` 文件。
3. 文件命名建议：`{published_date}-{slug}.md`。
4. 可选在文件头添加 front matter（标题、日期、标签、作者）。

由于内容已是 Markdown，导出过程无需转换器，也不会有格式损失。

## 7. 回归验证建议

- 创建文章后检查 DB 中 `posts.content` 为 Markdown。
- 编辑旧文章后保存，前台渲染与预期一致。
- 插入图片并保存，前台可正常展示。
- `composer test` 全量通过。
