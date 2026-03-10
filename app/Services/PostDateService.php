<?php

declare(strict_types=1);

namespace Blogme\Services;

final class PostDateService
{
    public function parsePublishedAtFromPost(mixed $value, int $fallback): int
    {
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '' || !preg_match('/^-?\\d+$/', $value)) {
                return $fallback;
            }
            $unix = (int) $value;
            return $unix > 0 ? $unix : $fallback;
        }

        if (is_int($value)) {
            return $value > 0 ? $value : $fallback;
        }

        if (is_float($value)) {
            $unix = (int) $value;
            return $unix > 0 ? $unix : $fallback;
        }

        return $fallback;
    }

    public function parsePublishedDatetimeAsSiteTimezone(mixed $value, int $siteTimezone, int $fallback): int
    {
        if (!is_string($value)) {
            return $fallback;
        }
        $value = trim($value);
        if ($value === '') {
            return $fallback;
        }

        if (!preg_match('/^(\\d{4})-(\\d{2})-(\\d{2})[T\\s](\\d{2}):(\\d{2})(?::(\\d{2}))?$/', $value, $matches)) {
            return $fallback;
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];
        $hour = (int) $matches[4];
        $minute = (int) $matches[5];
        $second = isset($matches[6]) ? (int) $matches[6] : 0;

        if (!checkdate($month, $day, $year)) {
            return $fallback;
        }
        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59 || $second < 0 || $second > 59) {
            return $fallback;
        }

        $utc = gmmktime($hour, $minute, $second, $month, $day, $year);
        if ($utc === false) {
            return $fallback;
        }
        $unix = (int) $utc - $siteTimezone;
        return $unix > 0 ? $unix : $fallback;
    }
}
