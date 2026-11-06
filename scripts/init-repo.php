<?php

/**
 * One-shot bootstrap for projects created from this template.
 *
 * Runs from GitHub's init workflow (instantiation push), transforms the project
 * tree in place, verifies the result, then removes itself.
 */

declare(strict_types=1);

use Symfony\Component\Process\ExecutableFinder;

// Register the Composer autoloader...
require \dirname(__DIR__).'/vendor/autoload.php';

/** The package this repository ships as. */
const TEMPLATE_PACKAGE = 'feryardiant/laravel-template';
/** Template-only files removed before verification. */
const REMOVAL_TARGET = [
    '.github/workflows/init-repo.yml',
    'tests/InitRepoTest.php',
];

/**
 * Bootstraps a generated project tree.
 *
 * The repository name comes from GITHUB_REPOSITORY, which the init workflow
 * always provides. Missing configuration fails before the tree is touched.
 *
 * @param  resource  $stdout
 * @param  array<string, mixed>  $env
 */
function bootstrapProject(string $root, $stdout, array $env): int
{
    $repository = $env['GITHUB_REPOSITORY'] ?? null;

    if (! \is_string($repository) || $repository === '') {
        fwrite($stdout, 'FAIL: GITHUB_REPOSITORY is not set'.PHP_EOL);

        return 1;
    }

    // Composer package names are always lowercase.
    $target = mb_strtolower($repository, 'UTF-8');

    rewriteTemplateReferences($root, $target);
    resetPackageVersions($root);

    $removed = removeTemplateOnlyFiles($root, [
        'composer' => isCommandAvailable('composer'),
        'npm' => isCommandAvailable('npm'),
    ]);

    foreach ($removed as $file) {
        fwrite($stdout, 'removed '.$file.PHP_EOL);
    }

    $failures = verifyTransformation($root, $target);

    if ($failures !== []) {
        foreach ($failures as $failure) {
            fwrite($stdout, 'FAIL: '.$failure.PHP_EOL);
        }

        return 1;
    }

    if (! removePath($root.'/scripts/init-repo.php')) {
        fwrite($stdout, 'FAIL: unable to remove scripts/init-repo.php'.PHP_EOL);

        return 1;
    }

    fwrite($stdout, 'verified'.PHP_EOL);

    return 0;
}

/**
 * Entry point when the script is executed directly.
 */
function main(): int
{
    return bootstrapProject(
        \dirname(__DIR__),
        STDOUT,
        (array) getenv()
    );
}

/**
 * Visits every template-relevant text file below the root.
 *
 * Skips dependency directories, binary files, and this script itself.
 * The visitor receives the relative path and the file content.
 *
 * @param  callable(string, string): void  $visitor
 */
function walkTemplateFiles(string $root, callable $visitor): void
{
    $skipped = ['/vendor/', '/node_modules/', '/.git/'];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }

        $path = $file->getPathname();
        $relative = str_replace('\\', '/', ltrim(substr($path, \strlen($root)), '/'));
        $skip = $path === __FILE__;

        foreach ($skipped as $directory) {
            if (str_contains('/'.$relative.'/', $directory)) {
                $skip = true;
                break;
            }
        }

        if ($skip) {
            continue;
        }

        $content = file_get_contents($path);

        if ($content === false || str_contains(substr($content, 0, 8192), "\0")) {
            continue;
        }

        $visitor($relative, $content);
    }
}

/**
 * Replaces template references with the target package name.
 *
 * Returns the sorted list of changed files relative to the root.
 *
 * @return string[]
 */
function rewriteTemplateReferences(string $root, string $target): array
{
    $changed = [];

    walkTemplateFiles($root, function (
        string $relative,
        string $content
    ) use (
        $root,
        $target,
        &$changed
    ): void {
        if (! str_contains($content, TEMPLATE_PACKAGE)) {
            return;
        }

        $replaced = str_replace(TEMPLATE_PACKAGE, $target, $content);

        if ($replaced !== $content) {
            file_put_contents($root.'/'.$relative, $replaced);
            $changed[] = $relative;
        }
    });

    sort($changed);

    return $changed;
}

/**
 * Resets root package versions to 0.0.0.
 *
 * Touches package.json and the two root entries of package-lock.json;
 * dependency versions and composer.lock stay untouched.
 */
function resetPackageVersions(string $root): void
{
    $targets = ['package.json', 'package-lock.json'];

    foreach ($targets as $file) {
        $path = $root.'/'.$file;

        if (! is_file($path)) {
            continue;
        }

        $content = file_get_contents($path);

        if ($content === false) {
            continue;
        }

        $content = preg_replace('/("version":\s*")[^"]*(")/', '${1}0.0.0${2}', $content, 1);

        if ($file === 'package-lock.json' && $content) {
            $content = preg_replace(
                '/("packages":\s*\{\s*"":\s*\{[^}]*?"version":\s*")[^"]*(")/',
                '${1}0.0.0${2}',
                $content,
                1
            );
        }

        file_put_contents($path, $content);
    }
}

/**
 * Removes a file or directory tree, tolerating missing paths.
 */
function removePath(string $path): bool
{
    if (is_file($path)) {
        return unlink($path);
    }

    if (! is_dir($path)) {
        return false;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    /** @var SplFileInfo $item */
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());

            continue;
        }

        unlink($item->getPathname());
    }

    return rmdir($path);
}

/**
 * Removes template-only files.
 *
 * Lock files are only deleted when the tool that regenerates them is
 * available. Returns the sorted removed paths.
 *
 * @param  array{composer?: bool, npm?: bool}  $capabilities
 * @return string[]
 */
function removeTemplateOnlyFiles(string $root, array $capabilities): array
{
    $targets = REMOVAL_TARGET;

    if (! empty($capabilities['composer'])) {
        $targets[] = 'composer.lock';
    }

    if (! empty($capabilities['npm'])) {
        $targets[] = 'package-lock.json';
    }

    $removed = [];

    foreach ($targets as $target) {
        if (removePath($root.'/'.$target)) {
            $removed[] = $target;
        }
    }

    sort($removed);

    return $removed;
}

/**
 * Checks whether a command can be executed.
 */
function isCommandAvailable(string $command): bool
{
    return (new ExecutableFinder)->find($command) !== null;
}

/**
 * Verifies the transformation, returning human-readable failures.
 *
 * An empty array means the tree is clean.
 *
 * @return string[]
 */
function verifyTransformation(string $root, string $target): array
{
    $failures = [];

    foreach (REMOVAL_TARGET as $file) {
        if (file_exists($root.'/'.$file)) {
            $failures[] = $file.' still present';
        }
    }

    $packageJson = is_file($root.'/package.json') ? file_get_contents($root.'/package.json') : false;

    if ($packageJson === false || ! str_contains($packageJson, '"version": "0.0.0"')) {
        $failures[] = 'package.json version is not 0.0.0';
    }

    $attributes = is_file($root.'/.gitattributes') ? file_get_contents($root.'/.gitattributes') : false;
    $totalEntries = $attributes === false ? 0 : preg_match_all('/^.*export-ignore$/m', $attributes);
    $activeEntries = $attributes === false ? 0 : preg_match_all('/^[^#\s].*export-ignore$/m', $attributes);

    if ($totalEntries === 0 || $activeEntries !== $totalEntries) {
        $failures[] = 'export-ignore entries still commented out';
    }

    $composerJson = is_file($root.'/composer.json') ? file_get_contents($root.'/composer.json') : false;

    if (
        $composerJson === false
        || preg_match('/"name":\s*"'.preg_quote($target, '/').'"/', $composerJson) !== 1
    ) {
        $failures[] = 'composer.json name is not '.$target;
    }

    if (
        $packageJson === false
        || preg_match('/"name":\s*"@'.preg_quote($target, '/').'"/', $packageJson) !== 1
    ) {
        $failures[] = 'package.json name is not @'.$target;
    }

    walkTemplateFiles($root, function (
        string $relative,
        string $content
    ) use (&$failures, $target): void {
        // Mask the replacement so a target named after the template still passes.
        if (str_contains(str_replace($target, '', $content), TEMPLATE_PACKAGE)) {
            $failures[] = 'residual template reference in '.$relative;
        }
    });

    return $failures;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(main());
}
