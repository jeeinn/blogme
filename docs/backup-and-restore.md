# 备份与恢复指南

本文提供 `blogme` 在生产环境下的最小备份集合与恢复步骤。

## 1. 最小可用备份集（推荐）

以下 4 项可覆盖绝大多数迁移与恢复场景：

- `db.sqlite`（文章、用户、标签、导航等核心数据）
- `config.json`（站点配置与当前主题名）
- `public/uploads/`（封面与正文图片上传）
- `data/themes/`（主题模板、主题本地化、自定义主题）

## 2. 可重建目录（通常不需要备份）

以下目录是运行期镜像或缓存，可由程序重新生成：

- `public/assets/`
- `public/admin/assets/`
- `storage/cache/`
- `storage/runtime/`
- `storage/logs/`
- `storage/rate_limit/`

## 3. 仅备份 `public/ + config.json + db.sqlite` 是否可行

可以运行，但有前提：

- `data/themes/` 没有自定义改动，且目标环境能从代码仓恢复同版本主题。

否则会丢失主题模板或主题语言改动，建议始终备份 `data/themes/`。

## 4. 备份示例

Linux/macOS:

```bash
tar -czf blogme-backup-$(date +%F).tar.gz \
  db.sqlite \
  config.json \
  public/uploads \
  data/themes
```

Windows PowerShell:

```powershell
$date = Get-Date -Format 'yyyy-MM-dd'
Compress-Archive -Path db.sqlite,config.json,public\uploads,data\themes -DestinationPath "blogme-backup-$date.zip" -Force
```

## 5. 恢复步骤（最小操作）

1. 部署同版本代码（或先 `git checkout` 到目标版本）。
2. 停止 Web 服务（避免恢复中读写冲突）。
3. 覆盖恢复：`db.sqlite`、`config.json`、`public/uploads`、`data/themes`。
4. 确认目录可写：`public/uploads`、`public/assets`、`public/admin/assets`、`storage/runtime`。
5. 启动服务并验证：
   - 后台可登录
   - 文章与图片可访问
   - 主题样式正常

## 6. 旧版本上传目录说明

- 历史路径 `data/uploads` 已不作为运行期主路径。
- 新版本会在启动时尝试一次性迁移到 `public/uploads`。
- 迁移完成后会写入标记文件：`storage/runtime/legacy_uploads_migrated.flag`。
