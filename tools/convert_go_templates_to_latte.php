<?php

declare(strict_types=1);

final class GoTemplateToLatteConverter
{
    private int $loopIndex = 0;

    /** @var array<int, string> */
    private array $dotStack = ['$ctx'];

    /** @var array<int, string> */
    private array $blockStack = [];

    public function convert(string $template): string
    {
        $this->loopIndex = 0;
        $this->dotStack = ['$ctx'];
        $this->blockStack = [];

        $tokens = preg_split('/(\{\{[-]?.*?[-]?\}\})/s', $template, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if (!is_array($tokens)) {
            return $template;
        }

        $out = '';
        foreach ($tokens as $token) {
            if (!str_starts_with($token, '{{')) {
                $out .= $token;
                continue;
            }

            $raw = trim(substr($token, 2, -2));
            $tag = trim($raw, "- \t\n\r\0\x0B");
            if ($tag === '') {
                continue;
            }

            // Keep these for compatibility with existing strip/replace logic in renderer.
            if (str_starts_with($tag, 'define ') || str_starts_with($tag, 'template ')) {
                $out .= $token;
                continue;
            }

            if (str_starts_with($tag, 'if ')) {
                $cond = trim(substr($tag, 3));
                $out .= '{if ' . $this->convertExpr($cond) . '}';
                $this->blockStack[] = 'if';
                continue;
            }

            if ($tag === 'else') {
                $out .= '{else}';
                continue;
            }

            if (str_starts_with($tag, 'range ')) {
                $meta = $this->parseRangeMeta(trim(substr($tag, 6)));
                $expr = $this->convertExpr($meta['expr']);

                if ($meta['key_var'] !== null && $meta['value_var'] !== null) {
                    $key = '$' . $meta['key_var'];
                    $value = '$' . $meta['value_var'];
                    $out .= '{foreach ' . $expr . ' as ' . $key . ' => ' . $value . '}';
                    $this->dotStack[] = $value;
                } elseif ($meta['key_var'] !== null) {
                    $value = '$' . $meta['key_var'];
                    $out .= '{foreach ' . $expr . ' as ' . $value . '}';
                    $this->dotStack[] = $value;
                } else {
                    $dot = '$dot' . (++$this->loopIndex);
                    $out .= '{foreach ' . $expr . ' as ' . $dot . '}';
                    $this->dotStack[] = $dot;
                }

                $this->blockStack[] = 'foreach';
                continue;
            }

            if ($tag === 'break') {
                $out .= '{breakIf true}';
                continue;
            }

            if ($tag === 'end') {
                $type = array_pop($this->blockStack);
                if ($type === 'if') {
                    $out .= '{/if}';
                } elseif ($type === 'foreach') {
                    $out .= '{/foreach}';
                    array_pop($this->dotStack);
                } else {
                    // Likely for {{ define }} blocks; renderer handles these.
                    $out .= $token;
                }
                continue;
            }

            $out .= '{=' . $this->convertExpr($tag) . '}';
        }

        return $out;
    }

    private function currentDotVar(): string
    {
        return $this->dotStack[count($this->dotStack) - 1];
    }

    private function convertExpr(string $expr): string
    {
        $tokens = $this->tokenize($expr);
        return $this->convertTokenList($tokens);
    }

    /** @param array<int, string> $tokens */
    private function convertTokenList(array $tokens): string
    {
        $tokens = $this->trimOuterParens($tokens);
        $segments = $this->splitByPipe($tokens);
        if ($segments === []) {
            return 'null';
        }

        $value = $this->convertCommand($segments[0]);
        for ($i = 1; $i < count($segments); $i++) {
            $cmd = $segments[$i];
            if ($cmd === []) {
                continue;
            }
            $filter = $cmd[0];
            $args = [];
            for ($j = 1; $j < count($cmd); $j++) {
                [$argTokens, $next] = $this->collectArgument($cmd, $j);
                $j = $next - 1;
                $args[] = $this->convertTokenList($argTokens);
            }
            if ($args === []) {
                $value .= '|' . $filter;
            } else {
                $value .= '|' . $filter . ':' . implode(', ', $args);
            }
        }

        return $value;
    }

    /** @param array<int, string> $tokens */
    private function convertCommand(array $tokens): string
    {
        $tokens = $this->trimOuterParens($tokens);
        if ($tokens === []) {
            return 'null';
        }
        if (count($tokens) === 1) {
            return $this->convertAtom($tokens[0]);
        }

        $head = $tokens[0];
        if ($this->isFunction($head)) {
            if ($head === 'and') {
                $head = 'bool_and';
            } elseif ($head === 'or') {
                $head = 'bool_or';
            } elseif ($head === 'not') {
                $head = 'bool_not';
            } elseif ($head === '__') {
                $head = 'tr';
            } elseif ($head === '_f') {
                $head = 'trf';
            }
            $args = [];
            for ($i = 1; $i < count($tokens); $i++) {
                [$argTokens, $next] = $this->collectArgument($tokens, $i);
                $i = $next - 1;
                $args[] = $this->convertTokenList($argTokens);
            }
            return $head . '(' . implode(', ', $args) . ')';
        }

        return $this->convertAtom($head);
    }

    private function convertAtom(string $token): string
    {
        if ($token === '.') {
            return $this->currentDotVar();
        }
        if ($token === '$') {
            return '$ctx';
        }
        if ($token === 'true' || $token === 'false' || $token === 'null') {
            return $token;
        }
        if (is_numeric($token)) {
            return $token;
        }
        if (str_starts_with($token, '"') && str_ends_with($token, '"')) {
            return var_export(stripcslashes(substr($token, 1, -1)), true);
        }
        if (str_starts_with($token, '$.')) {
            return $this->convertPath('$ctx', substr($token, 2));
        }
        if (str_starts_with($token, '$')) {
            $parts = explode('.', substr($token, 1), 2);
            $base = '$' . $parts[0];
            if (count($parts) === 1) {
                return $base;
            }
            return $this->convertPath($base, $parts[1]);
        }
        if (str_starts_with($token, '.')) {
            return $this->convertPath($this->currentDotVar(), substr($token, 1));
        }

        // Go templates treat bare identifiers as strings if not functions/variables.
        return var_export($token, true);
    }

    private function convertPath(string $base, string $path): string
    {
        if ($path === '') {
            return $base;
        }
        $parts = explode('.', $path);
        $expr = $base;
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $expr .= '->' . $part;
        }
        return $expr;
    }

    private function isFunction(string $name): bool
    {
        return in_array($name, [
            'add', 'sub', 'seq', 'min', 'max', 'html', 'unix2date', 'timezone', 'markdown',
            '__', '_f', 'eq', 'ne', 'gt', 'lt', 'ge', 'le', 'and', 'or', 'not', 'len',
        ], true);
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
}

$root = dirname(__DIR__);
$paths = [];
$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/resources/templates'));
foreach ($iter as $file) {
    if ($file->isFile() && strtolower($file->getExtension()) === 'html') {
        $paths[] = $file->getPathname();
    }
}
$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/data/themes'));
foreach ($iter as $file) {
    if ($file->isFile() && strtolower($file->getExtension()) === 'html') {
        $paths[] = $file->getPathname();
    }
}

$converter = new GoTemplateToLatteConverter();
$changed = 0;
foreach ($paths as $path) {
    $src = file_get_contents($path);
    if (!is_string($src)) {
        fwrite(STDERR, "Failed to read: $path\n");
        exit(1);
    }
    $dst = $converter->convert($src);
    if ($dst !== $src) {
        file_put_contents($path, $dst);
        $changed++;
        echo "Converted: $path\n";
    }
}

echo "Done. Changed files: $changed\n";
