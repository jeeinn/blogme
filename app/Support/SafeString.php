<?php

declare(strict_types=1);

namespace Blogme\Support;

final class SafeString
{
    public function __construct(public readonly string $value)
    {
    }
}
