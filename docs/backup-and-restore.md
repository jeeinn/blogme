# 备份与恢复指南

本文提供 `blogme` 在生产环境下的最小备份集合与恢复步骤。

## 1. 最小可用备份集（推荐）

以下 4 项可覆盖绝大多数迁移与恢复场景：

- `db.sqlite`（文章、用户、标签、导航等核心数据）
- `config.json`（站点配置与当前主题名）
- `public/uploads/`（封面与正文图片上传）
- `public/themes/`（主题模板、主题本地化、自定义主题）

## 2. 可重建目录（通常不需要备份）

以下目录是运行期镜像或缓存，可由程序重新生成：

- `storage/cache/`
- `storage/runtime/`
- `storage/logs/`
- `storage/rate_limit/`

## 3. 仅备份 `public/ + config.json + db.sqlite` 是否可行

可以运行，但有前提：

- `public/themes/` 已包含你要保留的主题改动，并且目标环境代码版本兼容。

否则会丢失主题模板或主题语言改动，建议始终备份 `public/themes/`。

## 4. 备份示例

Linux/macOS:

```bash
tar -czf blogme-backup-$(date +%F).tar.gz \
  db.sqlite \
  config.json \
  public/uploads \
  public/themes
```

Windows PowerShell:

```powershell
$date = Get-Date -Format 'yyyy-MM-dd'
Compress-Archive -Path db.sqlite,config.json,public\uploads,public\themes -DestinationPath "blogme-backup-$date.zip" -Force
```

## 5. 恢复步骤（最小操作）

1. 部署同版本代码（或先 `git checkout` 到目标版本）。
2. 停止 Web 服务（避免恢复中读写冲突）。
3. 覆盖恢复：`db.sqlite`、`config.json`、`public/uploads`、`public/themes`。
4. 确认目录可写：`public/uploads`、`public/themes`、`storage/runtime`。
5. 启动服务并验证：
   - 后台可登录
   - 文章与图片可访问
   - 主题样式正常

## 6. 目录说明

- 当前版本仅使用 `public/uploads` 与 `public/themes` 作为运行期内容目录。
- 备份与恢复时不再涉及 `data/` 历史目录。
