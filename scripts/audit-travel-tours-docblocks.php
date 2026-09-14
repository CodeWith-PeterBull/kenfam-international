<?php

/**
 * Audit file, type, and named-function PHPDoc coverage in the TravelTours module.
 *
 * This static probe deliberately avoids booting Laravel or changing application
 * state. It exits non-zero and prints every uncovered declaration so that the
 * documentation convention remains independently verifiable.
 */
declare(strict_types=1);

$moduleRoot = dirname(__DIR__).'/app/Modules/TravelTours';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($moduleRoot));
$issues = [];

/** @return bool Whether a token is insignificant while walking declarations. */
function isIgnorableToken(array|string $token): bool
{
    return is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT], true);
}

/** @return int|null Index of the previous non-whitespace, non-ordinary-comment token. */
function previousMeaningfulToken(array $tokens, int $from): ?int
{
    for ($index = $from; $index >= 0; $index--) {
        if (! isIgnorableToken($tokens[$index])) {
            return $index;
        }
    }

    return null;
}

/** @return int|null Index of the next non-whitespace, non-comment token. */
function nextMeaningfulToken(array $tokens, int $from): ?int
{
    $count = count($tokens);
    for ($index = $from; $index < $count; $index++) {
        if (! isIgnorableToken($tokens[$index])) {
            return $index;
        }
    }

    return null;
}

/** Determine whether a declaration has a directly associated PHPDoc block. */
function hasDeclarationDocblock(array $tokens, int $declarationIndex): bool
{
    $allowedModifiers = array_filter([
        T_PUBLIC,
        T_PROTECTED,
        T_PRIVATE,
        T_STATIC,
        T_FINAL,
        T_ABSTRACT,
        defined('T_READONLY') ? T_READONLY : null,
    ]);
    $index = previousMeaningfulToken($tokens, $declarationIndex - 1);

    while ($index !== null && is_array($tokens[$index]) && in_array($tokens[$index][0], $allowedModifiers, true)) {
        $index = previousMeaningfulToken($tokens, $index - 1);
    }

    return $index !== null && is_array($tokens[$index]) && $tokens[$index][0] === T_DOC_COMMENT;
}

foreach ($files as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $relative = str_replace('\\', '/', substr($path, strlen(dirname(__DIR__)) + 1));
    if (str_contains($relative, '/Resources/views/')) {
        continue;
    }
    $tokens = token_get_all((string) file_get_contents($path));
    $hasFileDocblock = false;

    foreach ($tokens as $token) {
        if (is_array($token) && $token[0] === T_DOC_COMMENT) {
            $hasFileDocblock = true;
            break;
        }
        if (is_array($token) && in_array($token[0], [T_DECLARE, T_NAMESPACE], true)) {
            break;
        }
    }

    if (! $hasFileDocblock) {
        $issues[] = "$relative: missing file-level PHPDoc before declare/namespace";
    }

    foreach ($tokens as $index => $token) {
        if (! is_array($token)) {
            continue;
        }

        $typeTokens = [T_CLASS, T_INTERFACE, T_TRAIT];
        if (defined('T_ENUM')) {
            $typeTokens[] = T_ENUM;
        }

        if (in_array($token[0], $typeTokens, true)) {
            $nameIndex = nextMeaningfulToken($tokens, $index + 1);
            if ($nameIndex === null || ! is_array($tokens[$nameIndex]) || $tokens[$nameIndex][0] !== T_STRING) {
                continue;
            }
            if (! hasDeclarationDocblock($tokens, $index)) {
                $issues[] = "$relative:{$token[2]} missing PHPDoc for {$tokens[$nameIndex][1]}";
            }
        }

        if ($token[0] !== T_FUNCTION) {
            continue;
        }

        $nameIndex = nextMeaningfulToken($tokens, $index + 1);
        if ($nameIndex !== null && $tokens[$nameIndex] === '&') {
            $nameIndex = nextMeaningfulToken($tokens, $nameIndex + 1);
        }
        if ($nameIndex === null || ! is_array($tokens[$nameIndex]) || $tokens[$nameIndex][0] !== T_STRING) {
            continue;
        }
        if (! hasDeclarationDocblock($tokens, $index)) {
            $issues[] = "$relative:{$token[2]} missing PHPDoc for {$tokens[$nameIndex][1]}()";
        }
    }
}

if ($issues !== []) {
    fwrite(STDERR, implode(PHP_EOL, $issues).PHP_EOL);
    fwrite(STDERR, sprintf('TravelTours PHPDoc audit failed with %d issue(s).', count($issues)).PHP_EOL);
    exit(1);
}

echo 'TravelTours PHPDoc audit passed for all module PHP files.'.PHP_EOL;
