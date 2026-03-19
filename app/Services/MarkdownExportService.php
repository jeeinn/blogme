<?php

declare(strict_types=1);

namespace Blogme\Services;

use Blogme\Repositories\PostRepository;

final class MarkdownExportService
{
    public function __construct(
        private readonly string $root,
        private readonly PostRepository $posts
    ) {
    }

    /** @return array{count:int,directory:string} */
    public function exportAll(array $config = []): array
    {
        $directory = $this->root . '/exports/markdown';
        $timezone = (int) ($config['Timezone'] ?? 0);
        $posts = $this->posts->list([], $config);

        $this->resetDirectory($directory);

        $usedNames = [];
        foreach ($posts as $post) {
            $filename = $this->resolveFilename($post, $usedNames);
            file_put_contents($directory . '/' . $filename, $this->renderDocument($post, $timezone));
        }

        return [
            'count' => count($posts),
            'directory' => $directory,
        ];
    }

    private function resetDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            $this->deleteDirectory($directory);
        }

        mkdir($directory, 0755, true);
    }

    private function deleteDirectory(string $directory): void
    {
        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
                continue;
            }

            if (is_file($path)) {
                unlink($path);
            }
        }

        rmdir($directory);
    }

    /** @param array<string, mixed> $post */
    private function resolveFilename(array $post, array &$usedNames): string
    {
        $title = $this->sanitizeFilenameSegment((string) ($post['Title'] ?? ''));
        if ($title === '') {
            $title = $this->sanitizeFilenameSegment((string) ($post['Slug'] ?? ''));
        }
        if ($title === '') {
            $title = (string) ($post['ID'] ?? 'post');
        }

        $type = $this->postType($post);
        $baseName = $title . ' - ' . $type;
        $filename = $baseName . '.md';
        $index = 2;

        while (isset($usedNames[$filename])) {
            $filename = $baseName . ' (' . $index . ').md';
            $index++;
        }

        $usedNames[$filename] = true;
        return $filename;
    }

    /** @param array<string, mixed> $post */
    private function postType(array $post): string
    {
        if ((int) ($post['TrashedAt'] ?? 0) !== 0) {
            return 'trash';
        }

        return (string) ($post['Visibility'] ?? 'public');
    }

    private function sanitizeFilenameSegment(string $value): string
    {
        $value = preg_replace('/[<>:"\/\\\\|?*\x00-\x1F]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
        $value = trim($value, ". \t\n\r\0\x0B");
        return $value;
    }

    /** @param array<string, mixed> $post */
    private function renderDocument(array $post, int $timezone): string
    {
        $lines = [
            '---',
            'id: ' . $this->yamlValue((string) ($post['ID'] ?? '')),
            'title: ' . $this->yamlValue((string) ($post['Title'] ?? '')),
            'slug: ' . $this->yamlValue((string) ($post['Slug'] ?? '')),
            'type: ' . $this->yamlValue($this->postType($post)),
            'visibility: ' . $this->yamlValue((string) ($post['Visibility'] ?? '')),
            'excerpt: ' . $this->yamlValue($this->singleLine((string) ($post['OriginalExcerpt'] ?? ''))),
            'author:',
            '  id: ' . $this->yamlValue((string) (($post['Author']['ID'] ?? ''))),
            '  nickname: ' . $this->yamlValue((string) (($post['Author']['Nickname'] ?? ''))),
            '  email: ' . $this->yamlValue((string) (($post['Author']['Email'] ?? ''))),
        ];

        $tags = $post['Tags'] ?? [];
        if (is_array($tags) && $tags !== []) {
            $lines[] = 'tags:';
            foreach ($tags as $tag) {
                $lines[] = '  - ' . $this->yamlValue((string) ($tag['Name'] ?? ''));
            }
        } else {
            $lines[] = 'tags: []';
        }

        $lines[] = 'published_at: ' . $this->yamlValue($this->formatDatetime((int) ($post['PublishedAt'] ?? 0), $timezone));
        $lines[] = 'created_at: ' . $this->yamlValue($this->formatDatetime((int) ($post['CreatedAt'] ?? 0), $timezone));
        $lines[] = 'updated_at: ' . $this->yamlValue($this->formatDatetime((int) ($post['UpdatedAt'] ?? 0), $timezone));
        $lines[] = 'trashed_at: ' . $this->yamlNullableDatetime((int) ($post['TrashedAt'] ?? 0), $timezone);
        $lines[] = '---';
        $lines[] = '';

        $content = (string) ($post['Content'] ?? '');
        return implode("\n", $lines) . $content . (str_ends_with($content, "\n") ? '' : "\n");
    }

    private function singleLine(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function yamlValue(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    private function yamlNullableDatetime(int $unix, int $timezone): string
    {
        if ($unix <= 0) {
            return 'null';
        }

        return $this->yamlValue($this->formatDatetime($unix, $timezone));
    }

    private function formatDatetime(int $unix, int $timezone): string
    {
        return gmdate('Y-m-d\TH:i:s', $unix + $timezone) . $this->formatOffset($timezone);
    }

    private function formatOffset(int $offsetSeconds): string
    {
        $sign = $offsetSeconds >= 0 ? '+' : '-';
        $offsetSeconds = abs($offsetSeconds);
        $hours = intdiv($offsetSeconds, 3600);
        $minutes = intdiv($offsetSeconds % 3600, 60);
        return sprintf('%s%02d:%02d', $sign, $hours, $minutes);
    }
}
