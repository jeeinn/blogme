# Mermaid 前后台运行时资源说明

## 1. 目标

Mermaid 在本项目中只做“运行时渲染增强”：

- 后台编辑器：对 Mermaid 代码块提供预览能力
- 前台文章页：对 Markdown 渲染后的 Mermaid 代码块进行增强渲染

内容持久化仍保持为 Markdown fenced code block，不在数据库中保存 SVG/HTML，也不引入服务端 Mermaid 解析。

## 2. 涉及文件

- 后台源码入口：`public/admin/assets/editor-crepe-src.js`
- 后台构建产物：`public/admin/assets/editor-crepe.js`
- 前台源码入口：`public/themes/default/assets/mermaid-page-src.js`
- 前台构建产物：`public/themes/default/assets/mermaid-page.js`

构建命令：

```bash
npm run build:admin-editor
npm run build:theme-mermaid
```

## 3. 共享虚拟空间部署原则

- 生产环境默认直接使用仓库内已构建好的静态资源。
- 服务器不要求安装 Node.js、npm，也不要求在线打包。
- 这些 Mermaid 相关资源必须作为 `public/` 下的真实静态文件存在，并由 Nginx/Apache 直接承载。
- 若修改 Mermaid 相关源码，应在本地或 CI 先完成构建，再上传静态资源目录。

推荐整体同步：

- `public/admin/assets/`
- `public/themes/default/assets/`

不要只替换单个入口文件，因为构建结果可能同时包含：

- JS 入口文件
- CSS 文件
- 字体/图片等静态资源
- 后续版本可能新增的代码分包 chunk

## 4. 当前接入约束

- 后台与前台 Mermaid 初始化应保持一致的安全配置，避免编辑器预览和文章页行为不一致。
- 当前已明确使用 `htmlLabels: false`，以规避运行时 HTML label/`foreignObject` 带来的兼容与安全复杂度。
- Mermaid 检测应对代码块语言大小写不敏感，例如 `language-mermaid` 与 `language-Mermaid`。

## 5. 维护建议

- 升级 `mermaid` 版本后，至少回归以下场景：
  - 后台编辑器预览
  - 前台文章页渲染
  - 中文文本显示
  - `title` front matter 显示
- 若后续启用代码分包，部署文档仍按“整目录上传”执行，无需改变共享虚拟空间部署模型。
- 若需要进一步瘦身，应优先选择：
  - 构建压缩（`minify`）
  - 按需加载 Mermaid 运行时
  - 避免引入额外服务端依赖
