<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to obtain
 * it through the world-wide-web, please send an email to
 * contact@novalo.fr so we can send you a copy immediately.
 *
 * @author    Novalo <contact@novalo.fr>
 * @copyright 2026 Novalo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License v3.0 (AFL-3.0)
 */
declare(strict_types=1);

/*
 * CI check: every string a merchant can see exists in the four catalogues.
 *
 * PrestaShop falls back to the English source silently when a string is
 * missing, so a forgotten translation never fails anywhere else: the French
 * merchant just reads one English sentence in the middle of a French page.
 *
 * Usage: php .github/scripts/check-translations.php [module root]
 * Exit code 0 when everything is in order, 1 otherwise.
 */

const LOCALES = ['en-US', 'fr-FR', 'es-ES', 'de-DE'];
const DOMAIN_FILE = 'ModulesSomcpAdmin';
const DOMAIN = 'Modules.Somcp.Admin';
// Methods whose first argument is a string of our domain. trans() is only
// read when the call names the domain, because core trans() calls exist too.
const CALLS = ['l10n', 't', 'trans'];

$root = rtrim($argv[1] ?? getcwd(), '/');
$errors = [];

// 1. Catalogues: valid XML, no duplicate id, same sources everywhere.
$catalogues = [];
foreach (LOCALES as $locale) {
    $path = sprintf('%s/translations/%s/%s.%s.xlf', $root, $locale, DOMAIN_FILE, $locale);
    if (!is_file($path)) {
        $errors[] = "missing catalogue: $path";
        continue;
    }
    $previous = libxml_use_internal_errors(true);
    $xml = simplexml_load_file($path);
    if ($xml === false) {
        foreach (libxml_get_errors() as $error) {
            $errors[] = sprintf('%s:%d: invalid XML: %s', $path, $error->line, trim($error->message));
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        continue;
    }
    libxml_use_internal_errors($previous);
    $xml->registerXPathNamespace('x', 'urn:oasis:names:tc:xliff:document:1.2');

    $ids = [];
    $sources = [];
    foreach ($xml->xpath('//x:trans-unit') as $unit) {
        $id = (string) $unit['id'];
        $source = (string) $unit->source;
        $target = (string) $unit->target;
        if (isset($ids[$id])) {
            $errors[] = "$path: duplicate id $id";
        }
        $ids[$id] = true;
        if (isset($sources[$source])) {
            $errors[] = "$path: duplicate source \"$source\"";
        }
        if (trim($target) === '') {
            $errors[] = "$path: empty target for \"$source\"";
        }
        $sources[$source] = $target;
    }
    $catalogues[$locale] = $sources;
}

if (count($catalogues) === count(LOCALES)) {
    $reference = array_keys($catalogues['en-US']);
    foreach (LOCALES as $locale) {
        $keys = array_keys($catalogues[$locale]);
        foreach (array_diff($reference, $keys) as $source) {
            $errors[] = "$locale: missing \"$source\" (present in en-US)";
        }
        foreach (array_diff($keys, $reference) as $source) {
            $errors[] = "$locale: extra \"$source\" (absent from en-US)";
        }
    }
}

// 2. Strings used by the code.
$used = [];
$skip = ['/vendor/', '/docs/', '/.github/', '/dev/'];
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    $path = $file->getPathname();
    $relative = substr($path, strlen($root));
    foreach ($skip as $part) {
        if (str_contains($relative, $part)) {
            continue 2;
        }
    }
    switch ($file->getExtension()) {
        case 'php':
            foreach (phpStrings((string) file_get_contents($path)) as [$string, $line]) {
                $used[$string][] = "$relative:$line";
            }
            break;
        case 'tpl':
            foreach (tplStrings((string) file_get_contents($path), $relative) as [$string, $line]) {
                $used[$string][] = "$relative:$line";
            }
            break;
        case 'json':
            if (basename($path) === 'prompt.json') {
                foreach (promptStrings($path) as $string) {
                    $used[$string][] = $relative;
                }
            }
            break;
    }
}

foreach ($used as $string => $where) {
    foreach (LOCALES as $locale) {
        if (isset($catalogues[$locale]) && !array_key_exists($string, $catalogues[$locale])) {
            $errors[] = sprintf('%s: "%s" missing from %s', $where[0], $string, $locale);
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", array_unique($errors)) . "\n");
    fwrite(STDERR, sprintf("\n%d problem(s). Add the strings to dev/translations.py and run it.\n", count(array_unique($errors))));
    exit(1);
}

printf("Translations OK: %d strings used, %d in each catalogue.\n", count($used), count($catalogues['en-US']));
exit(0);

/**
 * Literal first arguments of l10n(), t() and trans(..., 'Modules.Somcp.Admin').
 * A first argument built from variables cannot be checked statically and is
 * skipped; concatenated literals are joined.
 *
 * @return list<array{string, int}>
 */
function phpStrings(string $code): array
{
    $tokens = token_get_all($code);
    $count = count($tokens);
    $found = [];
    for ($i = 0; $i < $count; ++$i) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_STRING || !in_array($tokens[$i][1], CALLS, true)) {
            continue;
        }
        $previous = previousSignificant($tokens, $i);
        // A declaration ("function t(") is not a call.
        if ($previous !== null && is_array($previous) && $previous[0] === T_FUNCTION) {
            continue;
        }
        $j = nextSignificant($tokens, $i + 1);
        if ($j === null || $tokens[$j] !== '(') {
            continue;
        }
        $line = $tokens[$i][2];
        $string = '';
        $literal = false;
        for ($j = $j + 1; $j < $count; ++$j) {
            $token = $tokens[$j];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
                $string .= literal($token[1]);
                $literal = true;
                continue;
            }
            if ($token === '.') {
                continue;
            }
            break;
        }
        if (!$literal || ($token !== ',' && $token !== ')')) {
            continue;
        }
        if ($tokens[$i][1] === 'trans' && !callNamesDomain($tokens, $j)) {
            continue;
        }
        $found[] = [$string, $line];
    }

    return $found;
}

/**
 * Whether the rest of a trans() call, up to its closing parenthesis, contains
 * the domain literal.
 */
function callNamesDomain(array $tokens, int $from): bool
{
    $depth = 1;
    for ($j = $from, $count = count($tokens); $j < $count; ++$j) {
        $token = $tokens[$j];
        if ($token === '(' || $token === '[') {
            ++$depth;
        } elseif ($token === ')' || $token === ']') {
            if (--$depth === 0) {
                return false;
            }
        } elseif (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING && literal($token[1]) === DOMAIN) {
            return true;
        }
    }

    return false;
}

function literal(string $token): string
{
    $quote = $token[0];
    $body = substr($token, 1, -1);
    if ($quote === "'") {
        return str_replace(['\\\\', "\\'"], ['\\', "'"], $body);
    }

    // Double-quoted literals with variables are T_ENCAPSED_AND_WHITESPACE, not
    // this token, so only escapes are left to resolve.
    return stripcslashes($body);
}

function nextSignificant(array $tokens, int $from): ?int
{
    for ($j = $from, $count = count($tokens); $j < $count; ++$j) {
        if (!is_array($tokens[$j]) || !in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            return $j;
        }
    }

    return null;
}

function previousSignificant(array $tokens, int $from)
{
    for ($j = $from - 1; $j >= 0; --$j) {
        if (!is_array($tokens[$j]) || !in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            return $tokens[$j];
        }
    }

    return null;
}

/**
 * {l s='...' d='Modules.Somcp.Admin'} in Smarty templates. A {l} without our
 * domain is reported too: it would be looked up in the wrong catalogue.
 *
 * @return list<array{string, int}>
 */
function tplStrings(string $template, string $where): array
{
    global $errors;
    $found = [];
    preg_match_all('/\{l\s+s=([\'"])((?:\\\\.|(?!\1).)*)\1([^}]*)\}/s', $template, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    foreach ($matches as $match) {
        $line = substr_count(substr($template, 0, $match[0][1]), "\n") + 1;
        $string = str_replace(['\\' . $match[1][0], '\\\\'], [$match[1][0], '\\'], $match[2][0]);
        if (!preg_match('/\bd=([\'"])' . preg_quote(DOMAIN, '/') . '\1/', $match[3][0])) {
            $errors[] = sprintf('%s:%d: {l s="%s"} does not name the %s domain', $where, $line, $string, DOMAIN);
            continue;
        }
        $found[] = [$string, $line];
    }

    return $found;
}

/**
 * Fields of a prompt.json that the merchant reads: title, description and each
 * argument description.
 *
 * @return list<string>
 */
function promptStrings(string $path): array
{
    global $errors;
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data)) {
        $errors[] = "$path: invalid JSON";

        return [];
    }
    $strings = [];
    foreach (['title', 'description'] as $key) {
        if (isset($data[$key]) && is_string($data[$key]) && $data[$key] !== '') {
            $strings[] = $data[$key];
        }
    }
    foreach ($data['arguments'] ?? [] as $argument) {
        if (is_array($argument) && isset($argument['description']) && is_string($argument['description']) && $argument['description'] !== '') {
            $strings[] = $argument['description'];
        }
    }

    return $strings;
}
