<?php

declare(strict_types=1);

namespace Blogme\Support;

final class Markdown
{
    public static function render(string $text): string
    {
        // Keep runtime dependency optional; fallback keeps content readable.
        if (class_exists(\Parsedown::class)) {
            /** @var \Parsedown $parser */
            $parser = new \Parsedown();
            $parser->setSafeMode(false);
            return $parser->text($text);
        }

        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $escaped) ?? $escaped;
        $escaped = preg_replace('/`(.+?)`/s', '<code>$1</code>', $escaped) ?? $escaped;
        return nl2br($escaped);
    }
}
