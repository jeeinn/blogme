<?php

declare(strict_types=1);

namespace Blogme\Support;

final class DateFormat
{
    public static function goToPhp(string $goFormat): string
    {
        $replacements = [
            ['2006', 'Y'],
            ['06', 'y'],
            ['01', 'm'],
            ['02', 'd'],
            ['15', 'H'],
            ['03', 'h'],
            ['04', 'i'],
            ['05', 's'],
            ['PM', 'A'],
        ];

        $php = $goFormat;
        foreach ($replacements as [$go, $phpToken]) {
            $php = str_replace($go, $phpToken, $php);
        }
        return $php;
    }
}
