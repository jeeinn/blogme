<?php

declare(strict_types=1);

namespace Blogme\Core;

use Blogme\Services\LocaleService;
use Blogme\Support\DateFormat;
use Blogme\Support\Markdown;
use Blogme\Support\SafeString;

final class GoTemplateEngine
{
    public function __construct(private readonly string $root, private readonly LocaleService $locale)
    {
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
        $tokens = preg_split('/(\{\{[-]?.*?[-]?\}\})/s', $template, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if (!is_array($tokens)) {
            return $template;
        }
        $index = 0;
        [$nodes, ] = $this->parseNodes($tokens, $index, []);
        return $this->renderNodes($nodes, $rootData, $rootData, [], [
            'is_theme' => $isTheme,
            'locale' => (string) ($rootData['Config']['Locale'] ?? 'en-us'),
            'date_format' => (string) ($rootData['Config']['DateFormat'] ?? 'Y-m-d'),
            'timezone' => (int) ($rootData['Config']['Timezone'] ?? 0),
        ]);
    }

    /** @return array{0: array<int, array<string, mixed>>, 1: string|null} */
    private function parseNodes(array $tokens, int &$index, array $stopTags): array
    {
        $nodes = [];
        $count = count($tokens);

        while ($index < $count) {
            $token = $tokens[$index];
            $index++;
            if (!str_starts_with($token, '{{')) {
                $nodes[] = ['type' => 'text', 'value' => $token];
                continue;
            }
            $raw = trim(substr($token, 2, -2));
            $tag = trim($raw, "- \t\n\r\0\x0B");
            if ($tag === '') {
                continue;
            }
            if (in_array($tag, $stopTags, true)) {
                return [$nodes, $tag];
            }
            if (str_starts_with($tag, 'if ')) {
                $cond = trim(substr($tag, 3));
                [$thenNodes, $stop] = $this->parseNodes($tokens, $index, ['else', 'end']);
                $elseNodes = [];
                if ($stop === 'else') {
                    [$elseNodes, ] = $this->parseNodes($tokens, $index, ['end']);
                }
                $nodes[] = [
                    'type' => 'if',
                    'cond' => $cond,
                    'then' => $thenNodes,
                    'else' => $elseNodes,
                ];
                continue;
            }
            if (str_starts_with($tag, 'range ')) {
                $meta = $this->parseRangeMeta(trim(substr($tag, 6)));
                [$body, ] = $this->parseNodes($tokens, $index, ['end']);
                $nodes[] = [
                    'type' => 'range',
                    'expr' => $meta['expr'],
                    'key_var' => $meta['key_var'],
                    'value_var' => $meta['value_var'],
                    'body' => $body,
                ];
                continue;
            }
            if ($tag === 'break') {
                $nodes[] = ['type' => 'break'];
                continue;
            }
            if ($tag === 'else' || $tag === 'end') {
                return [$nodes, $tag];
            }
            $nodes[] = ['type' => 'output', 'expr' => $tag];
        }

        return [$nodes, null];
    }

    /** @return array{expr:string, key_var:?string, value_var:?string} */
    private function parseRangeMeta(string $rangeExpr): array
    {
        if (preg_match('/^\$([A-Za-z_][A-Za-z0-9_]*)\s*,\s*\$([A-Za-z_][A-Za-z0-9_]*)\s*:=\s*(.+)$/', $rangeExpr, $m) === 1) {
            return ['expr' => $m[3], 'key_var' => $m[1], 'value_var' => $m[2]];
        }
        if (preg_match('/^\$([A-Za-z_][A-Za-z0-9_]*)\s*:=\s*(.+)$/', $rangeExpr, $m) === 1) {
            return ['expr' => $m[2], 'key_var' => $m[1], 'value_var' => null];
        }
        return ['expr' => $rangeExpr, 'key_var' => null, 'value_var' => null];
    }

    /** @param array<int, array<string, mixed>> $nodes */
    private function renderNodes(array $nodes, mixed $dot, array $root, array $vars, array $ctx): string
    {
        $output = '';
        foreach ($nodes as $node) {
            $type = $node['type'];
            if ($type === 'text') {
                $output .= (string) $node['value'];
                continue;
            }
            if ($type === 'output') {
                $value = $this->evalExpr((string) $node['expr'], $dot, $root, $vars, $ctx);
                $output .= $this->escapeOutput($value);
                continue;
            }
            if ($type === 'if') {
                $cond = $this->evalExpr((string) $node['cond'], $dot, $root, $vars, $ctx);
                if ($this->isTruthy($cond)) {
                    $output .= $this->renderNodes($node['then'], $dot, $root, $vars, $ctx);
                } else {
                    $output .= $this->renderNodes($node['else'], $dot, $root, $vars, $ctx);
                }
                continue;
            }
            if ($type === 'range') {
                $iterable = $this->evalExpr((string) $node['expr'], $dot, $root, $vars, $ctx);
                if (!is_array($iterable)) {
                    continue;
                }
                foreach ($iterable as $k => $v) {
                    $childVars = $vars;
                    if (is_string($node['key_var']) && $node['key_var'] !== '' && is_string($node['value_var']) && $node['value_var'] !== '') {
                        $childVars[$node['key_var']] = $k;
                        $childVars[$node['value_var']] = $v;
                    } elseif (is_string($node['key_var']) && $node['key_var'] !== '') {
                        $childVars[$node['key_var']] = $v;
                    }
                    try {
                        $output .= $this->renderNodes($node['body'], $v, $root, $childVars, $ctx);
                    } catch (BreakLoopException) {
                        break;
                    }
                }
                continue;
            }
            if ($type === 'break') {
                throw new BreakLoopException();
            }
        }
        return $output;
    }

    private function evalExpr(string $expr, mixed $dot, array $root, array $vars, array $ctx): mixed
    {
        $tokens = $this->tokenize($expr);
        return $this->evalTokenList($tokens, $dot, $root, $vars, $ctx);
    }

    /** @return array<int, string> */
    private function tokenize(string $expr): array
    {
        $tokens = [];
        $len = strlen($expr);
        $buf = '';
        $inQuote = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $expr[$i];
            if ($inQuote) {
                $buf .= $ch;
                if ($ch === '"' && ($i === 0 || $expr[$i - 1] !== '\\')) {
                    $tokens[] = $buf;
                    $buf = '';
                    $inQuote = false;
                }
                continue;
            }

            if ($ch === '"') {
                if (trim($buf) !== '') {
                    $tokens[] = trim($buf);
                }
                $buf = '"';
                $inQuote = true;
                continue;
            }

            if ($ch === '(' || $ch === ')' || $ch === '|') {
                if (trim($buf) !== '') {
                    $tokens[] = trim($buf);
                }
                $tokens[] = $ch;
                $buf = '';
                continue;
            }

            if (ctype_space($ch)) {
                if (trim($buf) !== '') {
                    $tokens[] = trim($buf);
                    $buf = '';
                }
                continue;
            }

            $buf .= $ch;
        }

        if (trim($buf) !== '') {
            $tokens[] = trim($buf);
        }

        return array_values(array_filter($tokens, static fn (string $v): bool => $v !== '-'));
    }

    /** @param array<int, string> $tokens */
    private function evalTokenList(array $tokens, mixed $dot, array $root, array $vars, array $ctx): mixed
    {
        $tokens = $this->trimOuterParens($tokens);
        $segments = $this->splitByPipe($tokens);
        if (count($segments) === 0) {
            return null;
        }

        $value = $this->evalCommand($segments[0], $dot, $root, $vars, $ctx);
        for ($i = 1; $i < count($segments); $i++) {
            $cmd = $segments[$i];
            if ($cmd === []) {
                continue;
            }
            $fn = $cmd[0];
            $argValues = [];
            for ($j = 1; $j < count($cmd); $j++) {
                [$argTokens, $next] = $this->collectArgument($cmd, $j);
                $j = $next - 1;
                $argValues[] = $this->evalTokenList($argTokens, $dot, $root, $vars, $ctx);
            }
            array_unshift($argValues, $value);
            $value = $this->callFunction($fn, $argValues, $ctx);
        }
        return $value;
    }

    /** @param array<int, string> $tokens */
    private function evalCommand(array $tokens, mixed $dot, array $root, array $vars, array $ctx): mixed
    {
        $tokens = $this->trimOuterParens($tokens);
        if ($tokens === []) {
            return null;
        }
        if (count($tokens) === 1) {
            return $this->evalAtom($tokens[0], $dot, $root, $vars);
        }

        $head = $tokens[0];
        if ($this->isFunction($head)) {
            $args = [];
            for ($i = 1; $i < count($tokens); $i++) {
                [$argTokens, $next] = $this->collectArgument($tokens, $i);
                $i = $next - 1;
                $args[] = $this->evalTokenList($argTokens, $dot, $root, $vars, $ctx);
            }
            return $this->callFunction($head, $args, $ctx);
        }

        return $this->evalAtom($head, $dot, $root, $vars);
    }

    /** @param array<int, string> $tokens @return array{0: array<int, string>, 1: int} */
    private function collectArgument(array $tokens, int $start): array
    {
        $token = $tokens[$start];
        if ($token !== '(') {
            return [[$token], $start + 1];
        }
        $depth = 1;
        $result = [];
        for ($i = $start + 1; $i < count($tokens); $i++) {
            if ($tokens[$i] === '(') {
                $depth++;
                $result[] = $tokens[$i];
                continue;
            }
            if ($tokens[$i] === ')') {
                $depth--;
                if ($depth === 0) {
                    return [$result, $i + 1];
                }
                $result[] = $tokens[$i];
                continue;
            }
            $result[] = $tokens[$i];
        }
        return [$result, count($tokens)];
    }

    /** @param array<int, string> $tokens @return array<int, array<int, string>> */
    private function splitByPipe(array $tokens): array
    {
        $segments = [];
        $current = [];
        $depth = 0;
        foreach ($tokens as $token) {
            if ($token === '(') {
                $depth++;
                $current[] = $token;
                continue;
            }
            if ($token === ')') {
                $depth--;
                $current[] = $token;
                continue;
            }
            if ($token === '|' && $depth === 0) {
                $segments[] = $current;
                $current = [];
                continue;
            }
            $current[] = $token;
        }
        $segments[] = $current;
        return $segments;
    }

    /** @param array<int, string> $tokens @return array<int, string> */
    private function trimOuterParens(array $tokens): array
    {
        while (count($tokens) >= 2 && $tokens[0] === '(' && $tokens[count($tokens) - 1] === ')' && $this->isWrapped($tokens)) {
            array_shift($tokens);
            array_pop($tokens);
        }
        return $tokens;
    }

    /** @param array<int, string> $tokens */
    private function isWrapped(array $tokens): bool
    {
        $depth = 0;
        $count = count($tokens);
        foreach ($tokens as $i => $token) {
            if ($token === '(') {
                $depth++;
            } elseif ($token === ')') {
                $depth--;
                if ($depth === 0 && $i < $count - 1) {
                    return false;
                }
            }
        }
        return true;
    }

    private function evalAtom(string $token, mixed $dot, array $root, array $vars): mixed
    {
        if ($token === '.') {
            return $dot;
        }
        if ($token === 'true') {
            return true;
        }
        if ($token === 'false') {
            return false;
        }
        if (is_numeric($token)) {
            return str_contains($token, '.') ? (float) $token : (int) $token;
        }
        if (str_starts_with($token, '"') && str_ends_with($token, '"')) {
            return stripcslashes(substr($token, 1, -1));
        }
        if (str_starts_with($token, '$')) {
            return $this->resolveVar($token, $dot, $root, $vars);
        }
        if (str_starts_with($token, '.')) {
            return $this->resolvePath($dot, substr($token, 1));
        }
        return $token;
    }

    private function resolveVar(string $token, mixed $dot, array $root, array $vars): mixed
    {
        if ($token === '$') {
            return $root;
        }
        if (str_starts_with($token, '$.')) {
            return $this->resolvePath($root, substr($token, 2));
        }
        $parts = explode('.', substr($token, 1));
        $name = $parts[0];
        $value = $vars[$name] ?? null;
        if (count($parts) === 1) {
            return $value;
        }
        return $this->resolvePath($value, implode('.', array_slice($parts, 1)));
    }

    private function resolvePath(mixed $base, string $path): mixed
    {
        if ($path === '') {
            return $base;
        }
        $parts = explode('.', $path);
        $value = $base;
        foreach ($parts as $part) {
            if (is_array($value) && array_key_exists($part, $value)) {
                $value = $value[$part];
                continue;
            }
            if (is_object($value) && isset($value->{$part})) {
                $value = $value->{$part};
                continue;
            }
            if (is_object($value) && method_exists($value, $part)) {
                $value = $value->{$part}();
                continue;
            }
            return null;
        }
        return $value;
    }

    private function isFunction(string $name): bool
    {
        return in_array($name, [
            'add', 'sub', 'seq', 'min', 'max', 'html', 'unix2date', 'timezone', 'markdown',
            '__', '_f', 'eq', 'ne', 'gt', 'lt', 'ge', 'le', 'and', 'or', 'not', 'len',
        ], true);
    }

    private function callFunction(string $name, array $args, array $ctx): mixed
    {
        switch ($name) {
            case 'add':
                return (int) $args[0] + (int) $args[1];
            case 'sub':
                return (int) $args[0] - (int) $args[1];
            case 'seq':
                $start = (int) $args[0];
                $end = (int) $args[1];
                if ($start > $end) {
                    return [];
                }
                return range($start, $end);
            case 'min':
                return min((int) $args[0], (int) $args[1]);
            case 'max':
                return max((int) $args[0], (int) $args[1]);
            case 'html':
                return new SafeString((string) ($args[0] ?? ''));
            case 'unix2date':
                $phpFormat = DateFormat::goToPhp((string) ($ctx['date_format'] ?? 'Y-m-d'));
                return gmdate($phpFormat, (int) $args[0]);
            case 'timezone':
                return gmdate('Y-m-d h:i A', time() + (int) $args[0]);
            case 'markdown':
                return new SafeString(Markdown::render((string) ($args[0] ?? '')));
            case '__':
                $key = (string) ($args[0] ?? '');
                $locale = (string) ($ctx['locale'] ?? 'en-us');
                return ($ctx['is_theme'] ?? false) ? $this->locale->tt($key, $locale) : $this->locale->t($key, $locale);
            case '_f':
                $key = (string) ($args[0] ?? '');
                $locale = (string) ($ctx['locale'] ?? 'en-us');
                $text = ($ctx['is_theme'] ?? false) ? $this->locale->tt($key, $locale) : $this->locale->t($key, $locale);
                return sprintf($text, ...array_slice($args, 1));
            case 'eq':
                return ($args[0] ?? null) == ($args[1] ?? null);
            case 'ne':
                return ($args[0] ?? null) != ($args[1] ?? null);
            case 'gt':
                return ($args[0] ?? null) > ($args[1] ?? null);
            case 'lt':
                return ($args[0] ?? null) < ($args[1] ?? null);
            case 'ge':
                return ($args[0] ?? null) >= ($args[1] ?? null);
            case 'le':
                return ($args[0] ?? null) <= ($args[1] ?? null);
            case 'and':
                foreach ($args as $arg) {
                    if (!$this->isTruthy($arg)) {
                        return false;
                    }
                }
                return true;
            case 'or':
                foreach ($args as $arg) {
                    if ($this->isTruthy($arg)) {
                        return true;
                    }
                }
                return false;
            case 'not':
                return !$this->isTruthy($args[0] ?? null);
            case 'len':
                if (is_array($args[0] ?? null)) {
                    return count($args[0]);
                }
                if (is_string($args[0] ?? null)) {
                    return mb_strlen($args[0]);
                }
                return 0;
            default:
                return null;
        }
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
        return true;
    }

    private function escapeOutput(mixed $value): string
    {
        if ($value instanceof SafeString) {
            return $value->value;
        }
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : '';
        }
        if (is_array($value)) {
            return '';
        }
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

final class BreakLoopException extends \RuntimeException
{
}
