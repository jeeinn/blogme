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

## 4. 首次上线步骤

1. 上传代码到服务器（建议 Git 部署）
2. 执行：

```bash
composer install --no-dev --optimize-autoloader
```

3. 确保可写权限已配置
4. 访问 `/wizard` 完成初始化
5. 初始化后检查：
  - `db.sqlite` 已创建
  - `config.json` 已生成
  - 后台可登录

## 5. 共享主机注意事项

- 若不能设置 DocumentRoot 到 `public/`：
  - 需将 `public/index.php` 作为入口并调整相对路径，或通过主机面板映射子目录为网站根
- 若禁用 `exec`：不影响本项目核心运行
- 若禁用 `gd`：图片将不进行压缩，仅保存原图

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
- 备份与恢复可参考：
  - [docs/backup-and-restore.md](./docs/backup-and-restore.md)

## 7. 安全建议

- 强制 HTTPS
- 设置强密码并定期轮换
- 如可配置 WAF，限制后台路径暴力请求
- 定期检查上传目录和日志目录权限

## 8. 运维命令

- 重置用户密码：

```bash
php cli.php reset-password user@example.com
```

- 运行测试（仅开发环境）：

```bash
./vendor/bin/phpunit
```
