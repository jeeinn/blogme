<?php

declare(strict_types=1);

namespace Blogme\Services;

use Blogme\Core\HttpException;

final class MediaService
{
    public function __construct(private readonly string $root)
    {
    }

    public function saveCover(string $postId, ?array $file): string
    {
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return '';
        }
        $dir = $this->root . '/public/uploads/covers';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $dst = $dir . '/' . $postId . '.jpg';
        $this->saveImageFile((string) $file['tmp_name'], $dst, 1024);
        return 'uploads/covers/' . $postId . '.jpg';
    }

    public function savePhoto(array $file): string
    {
        $year = gmdate('Y');
        $month = gmdate('m');
        $unix = (string) time();
        $id = $this->uuid();
        $dir = $this->root . '/public/uploads/images/' . $year . '/' . $month;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $filename = $unix . '_' . $id . '.jpg';
        $dst = $dir . '/' . $filename;
        $this->saveImageFile((string) $file['tmp_name'], $dst, 2000);
        return 'uploads/images/' . $year . '/' . $month . '/' . $filename;
    }

    /** @return array<int, array<string, string>> */
    public function collectPhotoFiles(): array
    {
        $base = realpath($this->root . '/public/uploads/images');
        if ($base === false || !is_dir($base)) {
            return [];
        }

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'avif', 'svg'];
        $normalizedBase = str_replace('\\', '/', $base);
        $rii = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );
        $files = [];

        foreach ($rii as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $filename = $file->getFilename();
            if ($filename === '' || str_starts_with($filename, '.')) {
                continue;
            }

            $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
            if ($ext === '' || !in_array($ext, $allowedExtensions, true)) {
                continue;
            }

            $normalizedPathname = str_replace('\\', '/', $file->getPathname());
            if (!str_starts_with($normalizedPathname, $normalizedBase . '/')) {
                continue;
            }

            $relative = substr($normalizedPathname, strlen($normalizedBase) + 1);
            if ($relative === '' || $relative === false) {
                continue;
            }

            $segments = explode('/', $relative);
            if (count($segments) !== 3) {
                continue;
            }
            [$year, $month, $name] = $segments;
            if (!preg_match('/^\\d{4}$/', $year)) {
                continue;
            }
            if (!preg_match('/^(0[1-9]|1[0-2])$/', $month)) {
                continue;
            }
            if ($name === '' || str_contains($name, '/')) {
                continue;
            }

            $files[] = [
                'Year' => $year,
                'Month' => $month,
                'Filename' => $name,
            ];
        }

        return $files;
    }

    /** @return array<int, array<string, mixed>> */
    public function normalizeFilesArray(mixed $files): array
    {
        if (!is_array($files)) {
            return [];
        }
        if (isset($files['name']) && !is_array($files['name'])) {
            return [$files];
        }
        if (!isset($files['name']) || !is_array($files['name'])) {
            return [];
        }
        $result = [];
        foreach ($files['name'] as $i => $name) {
            $result[] = [
                'name' => $name,
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$i] ?? 0,
            ];
        }
        return $result;
    }

    private function saveImageFile(string $srcTmpPath, string $dstPath, int $maxWidth): void
    {
        $raw = @file_get_contents($srcTmpPath);
        if ($raw === false) {
            throw new HttpException(500, 'Upload read failed');
        }
        if (!function_exists('imagecreatefromstring')) {
            if (!@move_uploaded_file($srcTmpPath, $dstPath)) {
                copy($srcTmpPath, $dstPath);
            }
            return;
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

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
