<?php

declare(strict_types=1);

namespace Blogme\Core;

final class RateLimiter
{
    public function __construct(private readonly string $dir)
    {
    }

    public function allow(string $key, float $ratePerSecond, int $burst): bool
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9._-]/', '_', $key);
        if (!is_string($safeKey) || $safeKey === '') {
            $safeKey = 'default';
        }

        $file = $this->dir . '/' . $safeKey . '.json';
        $now = microtime(true);

        $data = ['tokens' => (float) $burst, 'time' => $now];
        if (is_file($file)) {
            $raw = file_get_contents($file);
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && isset($decoded['tokens'], $decoded['time'])) {
                    $data = [
                        'tokens' => (float) $decoded['tokens'],
                        'time' => (float) $decoded['time'],
                    ];
                }
            }
        }

        $elapsed = max(0.0, $now - $data['time']);
        $tokens = min((float) $burst, $data['tokens'] + ($elapsed * $ratePerSecond));

        if ($tokens < 1.0) {
            $this->write($file, ['tokens' => $tokens, 'time' => $now]);
            return false;
        }

        $tokens -= 1.0;
        $this->write($file, ['tokens' => $tokens, 'time' => $now]);
        return true;
    }

    private function write(string $file, array $payload): void
    {
        file_put_contents($file, json_encode($payload, JSON_UNESCAPED_SLASHES));
    }
}
