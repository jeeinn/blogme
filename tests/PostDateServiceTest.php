<?php

declare(strict_types=1);

namespace Blogme\Tests;

use Blogme\Services\PostDateService;
use PHPUnit\Framework\TestCase;

final class PostDateServiceTest extends TestCase
{
    public function testParsePublishedAtFromPost(): void
    {
        $service = new PostDateService();

        self::assertSame(123, $service->parsePublishedAtFromPost('123', 0));
        self::assertSame(0, $service->parsePublishedAtFromPost('abc', 0));
        self::assertSame(10, $service->parsePublishedAtFromPost(10, 0));
        self::assertSame(0, $service->parsePublishedAtFromPost(-5, 0));
    }

    public function testParsePublishedDatetimeAsSiteTimezone(): void
    {
        $service = new PostDateService();
        $timezone = 8 * 3600;

        $unix = $service->parsePublishedDatetimeAsSiteTimezone('2026-03-10 12:00', $timezone, 0);
        self::assertGreaterThan(0, $unix);

        $invalid = $service->parsePublishedDatetimeAsSiteTimezone('bad', $timezone, 42);
        self::assertSame(42, $invalid);
    }
}
