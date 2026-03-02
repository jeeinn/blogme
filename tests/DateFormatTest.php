<?php

declare(strict_types=1);

namespace Blogme\Tests;

use Blogme\Support\DateFormat;
use PHPUnit\Framework\TestCase;

final class DateFormatTest extends TestCase
{
    public function testGoDateFormatConversion(): void
    {
        self::assertSame('Y-m-d', DateFormat::goToPhp('2006-01-02'));
        self::assertSame('m/d/Y', DateFormat::goToPhp('01/02/2006'));
        self::assertSame('H:i', DateFormat::goToPhp('15:04'));
        self::assertSame('A h:i', DateFormat::goToPhp('PM 03:04'));
    }
}
