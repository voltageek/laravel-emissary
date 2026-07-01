#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$warnOnly = in_array('--warn-only', $argv, true);
$violations = 0;

function fail(string $msg): void
{
    global $violations, $warnOnly;
    $violations++;
    $prefix = $warnOnly ? "\033[33m⚠ WARN\033[0m" : "\033[31m✖ ERROR\033[0m";
    echo "$prefix: $msg\n";
}

function ok(string $msg): void
{
    echo "\033[32m✓\033[0m $msg\n";
}

function info(string $msg): void
{
    echo "  $msg\n";
}

// ═══════════════════════════════════════════════════════════════════════
// Check 1: Config keys in config/emissary.php → docs/reference/config.blade.php
// ═══════════════════════════════════════════════════════════════════════

function extractConfigDotKeys(string $configFile): array
{
    $lines = file($configFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }

    $keys = [];
    $stack = [];
    $inBlockComment = false;

    foreach ($lines as $line) {
        $trimmed = ltrim($line);

        if ($inBlockComment) {
            if (str_contains($trimmed, '*/')) {
                $inBlockComment = false;
            }
            continue;
        }

        if (str_starts_with($trimmed, '/*')) {
            if (! str_contains($trimmed, '*/')) {
                $inBlockComment = true;
            }
            continue;
        }

        if (str_starts_with($trimmed, '//')) {
            continue;
        }

        if ($trimmed === 'return [' || $trimmed === '];') {
            continue;
        }

        $indent = strlen($line) - strlen($trimmed);
        $depth = (int) ($indent / 4);

        while (count($stack) > max(0, $depth - 1)) {
            array_pop($stack);
        }

        if (preg_match("/^'([^']+)'\s*=>/", $trimmed, $m)) {
            $keyName = $m[1];

            $opensArray = (bool) preg_match('/=>\s*\[/', $trimmed);

            if ($opensArray) {
                $stack[] = $keyName;
            } else {
                $fullKey = array_merge($stack, [$keyName]);
                $keys[] = implode('.', $fullKey);
            }
        }
    }

    return $keys;
}

echo "═══════════════════════════════════════════\n";
echo "  Docs Lint — verifying docs ↔ code sync\n";
echo "═══════════════════════════════════════════\n\n";

echo "\033[1m[1/4] Config keys check\033[0m\n";

$configFile = "$root/config/emissary.php";
$configDoc = "$root/docs/source/reference/config.blade.php";

if (! file_exists($configFile)) {
    fail("config/emissary.php not found at $configFile");
} elseif (! file_exists($configDoc)) {
    fail("Config reference docs not found at $configDoc");
} else {
    $configKeys = extractConfigDotKeys($configFile);
    $docContent = file_get_contents($configDoc);
    $checked = 0;
    $missing = 0;

    foreach ($configKeys as $key) {
        // Check for either the full dotted path or the leaf key name
        if (! str_contains($docContent, $key)) {
            // Try the leaf key name alone (docs sometimes use just the leaf name)
            $parts = explode('.', $key);
            $leaf = end($parts);
            if (! str_contains($docContent, $leaf)) {
                fail("Config key '{$key}' not found in docs/source/reference/config.blade.php");
                $missing++;
            }
        }
        $checked++;
    }

    if ($missing === 0) {
        ok("All $checked config keys referenced in docs");
    } else {
        info("$missing of $checked config keys missing from docs");
    }
}

// ═══════════════════════════════════════════════════════════════════════
// Check 2: AgentError constants → docs/reference/api/dtos.blade.php
// ═══════════════════════════════════════════════════════════════════════

echo "\n\033[1m[2/4] AgentError constants check\033[0m\n";

$errorFile = "$root/src/AgentError.php";
$apiDocsDir = "$root/docs/source/reference/api";

if (! file_exists($errorFile)) {
    fail("src/AgentError.php not found at $errorFile");
} elseif (! is_dir($apiDocsDir)) {
    fail("API reference docs directory not found at $apiDocsDir");
} else {
    $content = file_get_contents($errorFile);
    preg_match_all("/public const \w+ = '([^']+)'/", $content, $matches);
    $errorCodes = $matches[1];

    // Build combined search corpus from all docs source files
    $corpus = '';
    $docsIterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator("$root/docs/source", RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($docsIterator as $file) {
        if ($file->getExtension() === 'php') {
            $corpus .= file_get_contents($file->getPathname());
        }
    }

    $checked = 0;
    $missing = 0;

    foreach ($errorCodes as $code) {
        if (! str_contains($corpus, $code)) {
            fail("AgentError code '{$code}' not found in any docs source file");
            $missing++;
        }
        $checked++;
    }

    if ($missing === 0) {
        ok("All $checked AgentError codes referenced in docs");
    } else {
        info("$missing of $checked error codes missing from docs");
    }
}

// ═══════════════════════════════════════════════════════════════════════
// Check 3: Command signatures → docs/reference/commands.blade.php
// ═══════════════════════════════════════════════════════════════════════

echo "\n\033[1m[3/4] Command signatures check\033[0m\n";

$commandsDir = "$root/src/Commands";
$commandsDoc = "$root/docs/source/reference/commands.blade.php";

if (! is_dir($commandsDir)) {
    fail("Commands directory not found at $commandsDir");
} elseif (! file_exists($commandsDoc)) {
    fail("Commands reference not found at $commandsDoc");
} else {
    $docContent = file_get_contents($commandsDoc);
    $commandFiles = glob("$commandsDir/*.php");
    $checked = 0;
    $missing = 0;

    foreach ($commandFiles as $file) {
        $content = file_get_contents($file);
        if (preg_match("/signature\s*=\s*'([^']+)'/", $content, $m)) {
            $signature = $m[1];
            // Extract the command name (everything before first space or {)
            $cmdName = trim(explode('{', $signature)[0]);
            $cmdName = trim(explode(' ', $cmdName)[0]);

            if (! str_contains($docContent, $cmdName)) {
                fail("Command '{$cmdName}' not found in docs/source/reference/commands.blade.php");
                $missing++;
            }
            $checked++;
        }
    }

    if ($missing === 0) {
        ok("All $checked commands referenced in docs");
    } else {
        info("$missing of $checked commands missing from docs");
    }
}

// ═══════════════════════════════════════════════════════════════════════
// Check 4: PHP syntax in docs code blocks
// ═══════════════════════════════════════════════════════════════════════

echo "\n\033[1m[4/4] PHP syntax in docs code blocks\033[0m\n";

$docsSourceDir = "$root/docs/source";
if (! is_dir($docsSourceDir)) {
    fail("Docs source directory not found at $docsSourceDir");
} else {
    $phpBin = PHP_BINARY;
    $checked = 0;
    $failed = 0;
    $tempDir = sys_get_temp_dir();

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($docsSourceDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $content = file_get_contents($file->getPathname());
        // Extract PHP code blocks: ```php ... ```
        if (preg_match_all('/```php\s*\n(.*?)```/s', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $idx => $match) {
                $code = $match[1];
                $code = trim($code);

                // Decode HTML entities commonly used in Blade files to avoid
                // Blade interpreting PHP tags or quotes as PHP.
                $code = html_entity_decode($code, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                // If the code already has a PHP open tag, try it as-is first
                if (str_starts_with($code, '<?php')) {
                    $tempFile = "$tempDir/docslint_" . basename($file->getPathname(), '.blade.php') . "_{$idx}.php";
                    file_put_contents($tempFile, $code);

                    $output = [];
                    $exitCode = 0;
                    exec("$phpBin -l " . escapeshellarg($tempFile) . " 2>&1", $output, $exitCode);

                    if ($exitCode === 0) {
                        unlink($tempFile);
                        $checked++;
                        continue;
                    }
                    unlink($tempFile);
                    // Fall through: strip PHP open tag and try wrappers
                    $code = trim(preg_replace('/^<\?php\s*/', '', $code));
                }

                // Try progressively more generous wrappers. Docs code blocks are
                // often fragments meant to be copy-pasted into a larger context,
                // not standalone PHP files.
                //
                // Order matters: try most-specific first, then increasingly lenient.
                $wrappers = [
                    // 1. Standalone: code that has its own PHP open tag or is complete
                    "<?php\n" . $code,
                    // 2. Class-level: methods, attributes, properties (NOT in a method body)
                    "<?php\n\nfinal class _W {\n" . $code . "\n}\n",
                    // 3. Method-level: statements, expressions, function calls
                    "<?php\n\nfinal class _W {\n    public function _f(): void {\n" . $code . "\n    }\n}\n",
                    // 4. Array return: for config snippets and array definitions
                    "<?php\n\nreturn [\n" . $code . "\n];\n",
                    // 5. Expression: for bare expressions and value literals
                    "<?php\n\n\$x = (" . $code . ");\n",
                ];

                $passed = false;
                foreach ($wrappers as $wi => $wrapper) {
                    $tempFile = "$tempDir/docslint_" . basename($file->getPathname(), '.blade.php') . "_{$idx}_w{$wi}.php";
                    file_put_contents($tempFile, $wrapper);

                    $output = [];
                    $exitCode = 0;
                    exec("$phpBin -l " . escapeshellarg($tempFile) . " 2>&1", $output, $exitCode);

                    if ($exitCode === 0) {
                        $passed = true;
                        unlink($tempFile);
                        break;
                    }
                    unlink($tempFile);
                }

                $relPath = str_replace($root . '/', '', $file->getPathname());

                if (! $passed) {
                    fail("Syntax error in code block #" . ($idx + 1) . " of $relPath (tried all wrappers)");
                    $failed++;
                }

                $checked++;
            }
        }
    }

    if ($failed === 0) {
        ok("All $checked PHP code blocks in docs pass syntax check");
    } else {
        info("$failed of $checked code blocks have syntax errors");
    }
}

// ═══════════════════════════════════════════════════════════════════════
// Summary
// ═══════════════════════════════════════════════════════════════════════

echo "\n═══════════════════════════════════════════\n";

if ($violations === 0) {
    echo "\033[32m✓ All checks passed — docs are in sync with implementation.\033[0m\n";
    exit(0);
}

if ($warnOnly) {
    echo "\033[33m⚠ $violations warning(s) found (warn-only mode).\033[0m\n";
    exit(0);
}

echo "\033[31m✖ $violations violation(s) found. Run with --warn-only for non-blocking check.\033[0m\n";
exit(1);
