<?php

declare(strict_types=1);

namespace Blogme\Tests;

use Blogme\Support\Markdown;
use PHPUnit\Framework\TestCase;

final class MarkdownTest extends TestCase
{
    public function testRenderEscapesHtml(): void
    {
        $html = Markdown::render('**bold** <script>alert(1)</script>');
        self::assertStringContainsString('<strong>bold</strong>', $html);
        self::assertStringNotContainsString('<script>', $html);
    }
}
