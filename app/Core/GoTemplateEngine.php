<?php

declare(strict_types=1);

namespace Blogme\Core;

use Blogme\Services\LocaleService;
use Blogme\Support\DateFormat;
use Blogme\Support\Markdown;
use Countable;
use Latte\Engine;
use Latte\Loaders\StringLoader;
use Latte\Runtime\Html;
use Traversable;

final class GoTemplateEngine
{
    private Engine $engine;
    private bool $isThemeRender = false;
    private string $renderLocale = 'en-us';
    private string $renderDateFormat = '2006-01-02';

    public function __construct(private readonly string $root, private readonly LocaleService $locale)
    {
        $this->engine = new Engine();
        $cacheDir = $this->root . '/storage/cache/latte';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        $this->engine->setTempDirectory($cacheDir);
        $this->registerHelpers();
    }

    public function renderAdmin(string $template, array $data): string
    {
        $base = $this->read($this->root . '/resources/templates/admin_base.html');
        $content = $this->read($this->root . '/resources/templates/' . $template . '.html');
        $content = $this->stripDefine($content, 'content');

        if (str_contains($content, '{{ template "pagination" . }}')) {
            $pagination = $this->stripDefine($this->read($this->root . '/resources/templates/admin_pagination.html'), 'pagination');
            $content = str_replace('{{ template "pagination" . }}', $pagination, $content);
        }

        $merged = str_replace('{{ template "content" . }}', $content, $base);
        return $this->renderString($merged, $data, false);
    }

    public function renderPage(string $template, array $data): string
    {
        $content = $this->read($this->root . '/resources/templates/' . $template . '.html');
        return $this->renderString($content, $data, false);
    }

    public function renderTheme(string $templateFile, string $theme, array $data): string
    {
        $this->locale->loadThemeLocale($theme);
        $content = $this->read($this->root . '/data/themes/' . $theme . '/' . $templateFile);
        if (str_contains($content, '{{ template "author.html" . }}')) {
            $author = $this->read($this->root . '/data/themes/' . $theme . '/author.html');
            $content = str_replace('{{ template "author.html" . }}', $author, $content);
        }
        return $this->renderString($content, $data, true);
    }

    private function stripDefine(string $template, string $define): string
    {
        $pattern = '/^\s*\{\{\s*define\s+"' . preg_quote($define, '/') . '"\s*\}\}(.*)\{\{\s*end\s*\}\}\s*$/s';
        if (preg_match($pattern, $template, $m) === 1) {
            return $m[1];
        }
        return $template;
    }

    private function read(string $file): string
    {
        $content = file_get_contents($file);
        if (!is_string($content)) {
            throw new HttpException(500, 'Template not found: ' . $file);
        }
        return $content;
    }

    private function renderString(string $template, array $rootData, bool $isTheme): string
    {
        $config = is_array($rootData['Config'] ?? null) ? $rootData['Config'] : [];
        $this->isThemeRender = $isTheme;
        $this->renderLocale = (string) ($config['Locale'] ?? 'en-us');
        $this->renderDateFormat = (string) ($config['DateFormat'] ?? '2006-01-02');

        $latte = clone $this->engine;
        $latte->setLoader(new StringLoader());

        return $latte->renderToString($template, [
            'ctx' => $this->normalizeData($rootData),
        ]);
    }

    private function registerHelpers(): void
    {
        $this->engine->addFunction('add', fn (mixed $a, mixed $b): int => (int) $a + (int) $b);
        $this->engine->addFunction('sub', fn (mixed $a, mixed $b): int => (int) $a - (int) $b);
        $this->engine->addFunction('seq', function (mixed $start, mixed $end): array {
            $s = (int) $start;
            $e = (int) $end;
            if ($s > $e) {
                return [];
            }
            return range($s, $e);
        });
        $this->engine->addFunction('min', fn (mixed $a, mixed $b): int => min((int) $a, (int) $b));
        $this->engine->addFunction('max', fn (mixed $a, mixed $b): int => max((int) $a, (int) $b));
        $this->engine->addFunction('len', function (mixed $value): int {
            if (is_string($value)) {
                return mb_strlen($value);
            }
            if (is_countable($value)) {
                return count($value);
            }
            return 0;
        });
        $this->engine->addFunction('timezone', fn (mixed $offset): string => gmdate('Y-m-d h:i A', time() + (int) $offset));

        $this->engine->addFunction('eq', fn (mixed $a, mixed $b): bool => $a == $b);
        $this->engine->addFunction('ne', fn (mixed $a, mixed $b): bool => $a != $b);
        $this->engine->addFunction('gt', fn (mixed $a, mixed $b): bool => $a > $b);
        $this->engine->addFunction('lt', fn (mixed $a, mixed $b): bool => $a < $b);
        $this->engine->addFunction('ge', fn (mixed $a, mixed $b): bool => $a >= $b);
        $this->engine->addFunction('le', fn (mixed $a, mixed $b): bool => $a <= $b);
        $this->engine->addFunction('bool_and', function (mixed ...$args): bool {
            foreach ($args as $arg) {
                if (!$this->isTruthy($arg)) {
                    return false;
                }
            }
            return true;
        });
        $this->engine->addFunction('bool_or', function (mixed ...$args): bool {
            foreach ($args as $arg) {
                if ($this->isTruthy($arg)) {
                    return true;
                }
            }
            return false;
        });
        $this->engine->addFunction('bool_not', fn (mixed $arg): bool => !$this->isTruthy($arg));

        $this->engine->addFunction('markdown', fn (mixed $text): Html => new Html(Markdown::render((string) $text)));
        $this->engine->addFunction('html', fn (mixed $value): Html => new Html((string) ($value ?? '')));
        $this->engine->addFilter('html', fn (mixed $value): Html => new Html((string) ($value ?? '')));
        $this->engine->addFunction('unix2date', fn (mixed $value): string => $this->formatUnixDate((int) $value));
        $this->engine->addFilter('unix2date', fn (mixed $value): string => $this->formatUnixDate((int) $value));

        $this->engine->addFunction('tr', fn (mixed $key): string => $this->translate((string) $key));
        $this->engine->addFunction('trf', function (mixed $key, mixed ...$args): string {
            $text = $this->translate((string) $key);
            return sprintf($text, ...$args);
        });
    }

    private function formatUnixDate(int $unix): string
    {
        $phpFormat = DateFormat::goToPhp($this->renderDateFormat);
        return gmdate($phpFormat, $unix);
    }

    private function translate(string $key): string
    {
        if ($this->isThemeRender) {
            return $this->locale->tt($key, $this->renderLocale);
        }
        return $this->locale->t($key, $this->renderLocale);
    }

    private function isTruthy(mixed $value): bool
    {
        if ($value === null || $value === false) {
            return false;
        }
        if (is_string($value)) {
            return $value !== '';
        }
        if (is_int($value) || is_float($value)) {
            return $value !== 0;
        }
        if (is_array($value)) {
            return count($value) > 0;
        }
        if (is_countable($value)) {
            return count($value) > 0;
        }
        return true;
    }

    private function normalizeData(mixed $value): mixed
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_map(fn (mixed $v): mixed => $this->normalizeData($v), $value);
            }
            $obj = new TemplateData();
            foreach ($value as $k => $v) {
                $obj->set((string) $k, $this->normalizeData($v));
            }
            return $obj;
        }
        if (is_object($value)) {
            return $value;
        }
        return $value;
    }
}

final class TemplateData implements \IteratorAggregate, Countable
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }

    public function set(string $name, mixed $value): void
    {
        $this->data[$name] = $value;
    }

    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->data);
    }

    public function count(): int
    {
        return count($this->data);
    }
}
