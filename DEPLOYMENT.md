# 部署文档（生产/共享主机）

## 1. 环境要求

- PHP 8.1 及以上
- 扩展：`pdo`、`pdo_sqlite`、`json`（建议 `gd` 用于图片压缩）
- Web 服务器：Apache/Nginx（共享主机优先 Apache）
- 可写目录：
  - `public/uploads`（用户上传）
  - `public/admin/assets`（后台静态资源）
  - `public/themes`（若在线安装/更新主题）
  - `storage/rate_limit`
  - `storage/runtime`
  - 根目录（用于创建 `db.sqlite`、`config.json`）

## 2. 目录与站点根

推荐将站点 DocumentRoot 指向：

```text
<project>/public
```

这样可避免直接暴露 `app/`、`bootstrap/`、`storage/` 等目录。

## 3. Apache 配置

项目已提供 `public/.htaccess`，会将不存在的文件统一转发到 `index.php`。

如果主机禁用了 `.htaccess`，需在虚拟主机中配置等价重写规则。

### 3.1 静态压缩

- 项目中的 `public/.htaccess` 已加入 `mod_deflate` 与 `mod_brotli` 的压缩规则。
- 若主机未启用对应模块，Apache 会自动跳过，不影响站点运行。
- 建议至少启用一种：
  - `mod_deflate`（通用，通常默认可用）
  - `mod_brotli`（压缩率更高，现代环境优先）
- 压缩对象建议限定为文本类资源：
  - `text/html`
  - `text/css`
  - `application/javascript`
  - `application/json`
  - `image/svg+xml`
- 不建议再压缩已高度压缩的二进制资源，如：
  - `jpg`
  - `png`
  - `webp`
  - `woff2`

Apache 虚拟主机中可使用与 `public/.htaccess` 等价的配置：

```apache
<IfModule mod_headers.c>
    Header append Vary Accept-Encoding
</IfModule>

<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE text/javascript
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/json
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE image/svg+xml
</IfModule>

<IfModule mod_brotli.c>
    AddOutputFilterByType BROTLI_COMPRESS text/plain
    AddOutputFilterByType BROTLI_COMPRESS text/html
    AddOutputFilterByType BROTLI_COMPRESS text/css
    AddOutputFilterByType BROTLI_COMPRESS text/javascript
    AddOutputFilterByType BROTLI_COMPRESS application/javascript
    AddOutputFilterByType BROTLI_COMPRESS application/json
    AddOutputFilterByType BROTLI_COMPRESS application/xml
    AddOutputFilterByType BROTLI_COMPRESS application/rss+xml
    AddOutputFilterByType BROTLI_COMPRESS image/svg+xml
</IfModule>
```

## 3.2 Nginx 压缩

项目提供了 `public/nginx.rewrite.conf` 示例，可直接把压缩规则放在 `server` 块中；若你的部署统一在全局管理压缩，也可以挪到 `http` 块。

建议配置：

```nginx
gzip on;
gzip_vary on;
gzip_min_length 1024;
gzip_comp_level 6;
gzip_proxied any;
gzip_types
    text/plain
    text/css
    text/javascript
    application/javascript
    application/json
    application/xml
    application/rss+xml
    image/svg+xml;

# 若已安装 brotli 模块，可额外启用
brotli on;
brotli_comp_level 5;
brotli_min_length 1024;
brotli_types
    text/plain
    text/css
    text/javascript
    application/javascript
    application/json
    application/xml
    application/rss+xml
    image/svg+xml;
```

说明：

- `brotli` 指令仅在 Nginx 已安装对应模块时可用；若未安装，请只保留 `gzip`。
- 对你当前体积较大的静态资源，传输压缩收益很明显：
  - `public/admin/assets/editor-crepe.js`
  - `public/themes/default/assets/mermaid-page.js`
- 这类文件建议与现有缓存头一起使用，避免首次下载后重复传输。

## 4. 首次上线步骤

1. 上传代码到服务器（建议 Git 部署）
2. 执行：

```bash
composer install --no-dev --optimize-autoloader
```

3. 确保可写权限已配置
4. 初始化数据库（可选，推荐）：

```bash
php cli.php migrate
```

说明：若不便执行 CLI 命令，首次访问时会自动初始化数据库（共享主机友好）。

5. 访问 `/wizard` 完成初始化
6. 初始化后检查：
  - `db.sqlite` 已创建
  - `config.json` 已生成
  - 后台可登录

## 4.1 前端构建资源发布原则

- 生产环境默认使用仓库内已构建好的静态资源，不要求服务器安装 Node.js 或 npm。
- 若修改了后台编辑器或前台 Mermaid 渲染逻辑，应在本地或 CI 执行构建后，再上传产物到服务器。
- 这些资源必须以物理文件形式部署在 `public/` 下，由 Nginx/Apache 直接返回，不通过 PHP 路由转发输出。
- 当前主题内的 Mermaid、Highlight.js 等前端运行时脚本也按此原则本地部署，不依赖外部 CDN。
- 当前相关命令：
  - `npm run build:admin-editor`
  - `npm run build:theme-mermaid`
- 上传时应整体同步以下目录，而不是仅替换单个入口文件：
  - `public/admin/assets/`
  - `public/themes/default/assets/`
- 这样可以兼容共享虚拟空间场景，也能为后续可能引入的静态分包保留部署余量。

## 5. 共享主机注意事项

- 若不能设置 DocumentRoot 到 `public/`：
  - 需将 `public/index.php` 作为入口并调整相对路径，或通过主机面板映射子目录为网站根
- 若禁用 `exec`：不影响本项目核心运行
- 若禁用 `gd`：图片将不进行压缩，仅保存原图
- 若无法在服务器执行 Node.js 构建：
  - 直接上传本地已编译好的 `public/admin/assets/` 与 `public/themes/default/assets/` 即可，不影响 Mermaid 功能运行

## 6. 性能与稳定性建议

- 开启 PHP OPcache
- 定期备份：
  - `db.sqlite`
  - `config.json`
  - `public/uploads/`
  - `public/themes/`（如有自定义主题或主题改动）
- 如使用 CDN，优先缓存：
  - `/assets/*`
  - `/themes/*`
  - `/admin/assets/*`
  - `/uploads/*`（按业务策略）
- 若静态资源有版本更新：
  - 建议整目录覆盖上传，避免旧文件与新构建产物混用
- 备份与恢复可参考：
  - [docs/backup-and-restore.md](./docs/backup-and-restore.md)

## 7. 安全建议

- 强制 HTTPS
- 设置强密码并定期轮换
- 如可配置 WAF，限制后台路径暴力请求
- 定期检查上传目录和日志目录权限

## 8. 运维命令

- 初始化数据库：

```bash
php cli.php migrate
```

- 重置用户密码：

```bash
php cli.php reset-password user@example.com
```

- 运行测试（仅开发环境）：

```bash
./vendor/bin/phpunit
```
