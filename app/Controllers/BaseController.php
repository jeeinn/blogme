<?php

declare(strict_types=1);

namespace Blogme\Controllers;

use Blogme\Core\App;
use Blogme\Core\HttpException;
use Blogme\Support\AppData;
use Blogme\Support\DateFormat;

abstract class BaseController
{
    public function __construct(protected readonly App $app)
    {
    }

    protected function request(): \Blogme\Core\Request
    {
        return $this->app->request();
    }

    protected function requireConfig(): void
    {
        if (!$this->app->configExists()) {
            \Blogme\Core\redirect('/wizard');
            throw new HttpException(302, 'redirect');
        }
    }

    protected function requireLogin(): void
    {
        if ($this->app->currentUserId() === '') {
            \Blogme\Core\redirect('/login');
            throw new HttpException(302, 'redirect');
        }
    }

    protected function currentUser(): ?array
    {
        $uid = $this->app->currentUserId();
        if ($uid === '') {
            return null;
        }
        return $this->app->users()->byId($uid);
    }

    protected function checkCsrf(): void
    {
        $token = (string) $this->request()->post('_csrf', '');
        if (!$this->app->csrf()->verify($token)) {
            throw new HttpException(400, 'CSRF token mismatch');
        }
    }

    protected function throttle(string $scope): void
    {
        $key = $scope . '_' . $this->request()->clientIp();
        if (!$this->app->rateLimiter()->allow($key, 2.0, 2)) {
            throw new HttpException(429, 'Too Many Requests');
        }
    }

    protected function setMessage(string $key): void
    {
        $this->app->flash()->set($key);
    }

    protected function getMessage(): string
    {
        $key = $this->app->flash()->getAndClear();
        if ($key === '') {
            return '';
        }
        return $this->app->translate($key);
    }

    protected function baseData(string $routePattern): array
    {
        $self = $this->currentUser();
        $config = $this->app->config();
        $relativeRoot = $this->app->routeRelativeRoot($routePattern);
        $absolute = $this->request()->scheme() . '://' . $this->request()->host() . $this->request()->path();
        return [
            'Self' => $self,
            'Config' => $config,
            'Message' => $this->getMessage(),
            'CSRF' => $this->app->csrf()->token(),
            'URL' => [
                'Root' => $this->request()->scheme() . '://' . $this->request()->host() . '/',
                'Absolute' => $absolute,
                'RelativeRoot' => $relativeRoot,
                'AbsoluteHost' => $this->request()->scheme() . '://' . $this->request()->host() . '/',
                'PageType' => $this->app->routePageType($routePattern),
            ],
        ];
    }

    protected function queryPage(): int
    {
        $v = (int) $this->request()->query('page', 1);
        return $v > 0 ? $v : 1;
    }

    protected function totalPages(int $total, int $perPage): int
    {
        if ($perPage <= 0) {
            return 1;
        }
        return (int) ceil($total / $perPage);
    }

    protected function paginationQuery(): string
    {
        $query = $_GET;
        unset($query['page']);
        $str = http_build_query($query);
        return $str === '' ? '' : ($str . '&');
    }

    protected function pagination(int $page, int $total, int $perPage): array
    {
        return [
            'CurrentPage' => $page,
            'TotalCount' => $total,
            'TotalPages' => $this->totalPages($total, $perPage),
            'Query' => $this->paginationQuery(),
        ];
    }

    protected function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(' ', '-', $value);
        $value = preg_replace("/[^A-Za-z0-9\\-._~!$&'()*+,;=\\p{L}\\p{N}]/u", '', $value) ?? $value;
        return $value;
    }

    /** @return array<int, string> */
    protected function createTags(string $tagNames): array
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $tagNames)), static fn (string $v): bool => $v !== ''));
        if ($parts === []) {
            return [];
        }
        $existing = $this->app->tags()->byNames($parts);
        $ids = [];
        foreach ($parts as $name) {
            $hit = null;
            foreach ($existing as $tag) {
                if ($tag['Name'] === $name) {
                    $hit = $tag;
                    break;
                }
            }
            if ($hit !== null) {
                $ids[] = $hit['ID'];
                continue;
            }
            $id = $this->uuid();
            $this->app->tags()->create([
                'id' => $id,
                'slug' => $this->slugify($name),
                'name' => $name,
                'description' => '',
                'created_at' => time(),
            ]);
            $ids[] = $id;
        }
        return $ids;
    }

    protected function saveCover(string $postId): string
    {
        $file = $this->request()->file('cover_file');
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return '';
        }
        $dst = $this->app->root() . '/data/uploads/covers/' . $postId . '.jpg';
        $this->saveImageFile((string) $file['tmp_name'], $dst, 1024);
        return 'uploads/covers/' . $postId . '.jpg';
    }

    protected function savePhoto(array $file): string
    {
        $year = gmdate('Y');
        $month = gmdate('m');
        $unix = (string) time();
        $id = $this->uuid();
        $dir = $this->app->root() . '/data/uploads/images/' . $year . '/' . $month;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $filename = $unix . '_' . $id . '.jpg';
        $dst = $dir . '/' . $filename;
        $this->saveImageFile((string) $file['tmp_name'], $dst, 2000);
        return 'uploads/images/' . $year . '/' . $month . '/' . $filename;
    }

    protected function saveImageFile(string $srcTmpPath, string $dstPath, int $maxWidth): void
    {
        $raw = @file_get_contents($srcTmpPath);
        if ($raw === false) {
            throw new HttpException(500, 'Upload read failed');
        }
        $image = @imagecreatefromstring($raw);
        if ($image === false) {
            if (!@move_uploaded_file($srcTmpPath, $dstPath)) {
                copy($srcTmpPath, $dstPath);
            }
            return;
        }
        $w = imagesx($image);
        $h = imagesy($image);
        $targetW = min($w, $maxWidth);
        $targetH = (int) floor(($h / max(1, $w)) * $targetW);
        $resized = imagecreatetruecolor($targetW, $targetH);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetW, $targetH, $w, $h);
        imagejpeg($resized, $dstPath, 90);
        imagedestroy($image);
        imagedestroy($resized);
    }

    protected function goDateToPhp(string $goFormat): string
    {
        return DateFormat::goToPhp($goFormat);
    }

    protected function defaultConfig(array $wizard): array
    {
        return [
            'Name' => $wizard['Name'],
            'Description' => $wizard['Description'],
            'IsPublic' => true,
            'DateFormat' => '2006-01-02',
            'TimeFormat' => '15:04',
            'Timezone' => $wizard['Timezone'],
            'InjectedHead' => '',
            'InjectedFoot' => '',
            'InjectedPostStart' => '',
            'InjectedPostEnd' => '',
            'FooterText' => 'Powered by <a href="https://tunalog.org" target="_blank">Tunalog</a> 🐟',
            'ColorScheme' => '',
            'ContainerWidth' => 'medium',
            'FontFamily' => 'sans',
            'FontSize' => 'medium',
            'HighlightJS' => true,
            'AuthorBlock' => 'start',
            'PostsPerPage' => 10,
            'Theme' => 'default',
            'Locale' => $wizard['Locale'],
        ];
    }

    protected function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    protected function parseTimezone(string|int $timezone): int
    {
        return (int) $timezone;
    }

    protected function availableLocales(): array
    {
        return AppData::LOCALES;
    }

    protected function availableTimezones(): array
    {
        return AppData::TIMEZONES;
    }
}
