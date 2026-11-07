<?php

/**
 * Standalone spec for scripts/init-repo.php.
 *
 * Deliberately outside the application suites: run it directly with
 *
 *     vendor/bin/pest tests/InitRepoTest.php
 *
 * It needs no database, no Laravel test case, and is not part of composer test.
 */

declare(strict_types=1);

use Symfony\Component\Process\Process;

require_once \dirname(__DIR__).'/scripts/init-repo.php';

/**
 * Builds a throwaway template tree mirroring the real repository shape.
 */
$makeFixture = function (): string {
    $root = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'init-repo-spec-'.bin2hex(random_bytes(6));

    foreach (['', '.github/workflows', 'app/Models', 'public', 'scripts/docker', 'tests', 'vendor', 'node_modules', '.git'] as $dir) {
        mkdir($root.\DIRECTORY_SEPARATOR.$dir, 0777, true);
    }

    // The copied script in end-to-end fixtures needs a working autoloader.
    $autoload = \dirname(__DIR__).'/vendor/autoload.php';

    $files = [
        'composer.json' => <<<'JSON'
{
    "name": "feryardiant/laravel-template",
    "description": "Laravel Application Template.",
    "support": {
        "source": "https://github.com/feryardiant/laravel-template"
    },
    "scripts": {
        "format": [
            "pint --parallel"
        ],
        "lint": [
            "pint --parallel --test"
        ]
    }
}
JSON,
        'package.json' => <<<'JSON'
{
    "name": "@feryardiant/laravel-template",
    "version": "1.2.3",
    "private": true
}
JSON,
        'package-lock.json' => <<<'JSON'
{
    "name": "@feryardiant/laravel-template",
    "version": "1.2.3",
    "lockfileVersion": 3,
    "packages": {
        "": {
            "name": "@feryardiant/laravel-template",
            "version": "1.2.3",
            "dependencies": {
                "dep": "^1.0.0"
            }
        },
        "node_modules/dep": {
            "version": "9.9.9"
        }
    }
}
JSON,
        'composer.lock' => <<<'JSON'
{
    "packages": [
        {
            "name": "laravel/framework",
            "version": "v13.0.0"
        }
    ]
}
JSON,
        'README.md' => <<<'MD'
[![License](https://img.shields.io/github/license/feryardiant/laravel-template)](https://github.com/feryardiant/laravel-template)

# Laravel Template
MD,
        '.github/workflows/init-repo.yml' => "name: Init Repo\n",
        '.github/workflows/tests.yml' => "name: Tests\n",
        '.gitattributes' => "* text=auto eol=lf\n\n.agents export-ignore\nAGENTS.md export-ignore\n.github export-ignore\ntests export-ignore\n",
        '.gitignore' => "/vendor\n/node_modules\n/.env\n",
        'app/Models/User.php' => "<?php\n\nclass User\n{\n}\n",
        'docker-compose.yaml' => "services:\n  app:\n    image: ghcr.io/feryardiant/laravel-template:local\n",
        'docker-compose.staging.yaml' => "services:\n  app:\n    image: ghcr.io/feryardiant/laravel-template:staging\n",
        'scripts/docker/compose.yaml' => "services:\n  app:\n    image: ghcr.io/feryardiant/laravel-template:staging\n",
        'tests/InitRepoTest.php' => "<?php // template spec\n",
        'vendor/autoload.php' => "<?php\n\nrequire ".var_export($autoload, true).";\n",
        'vendor/stub.php' => "<?php // vendor stub for feryardiant/laravel-template\n",
        'node_modules/.stub.js' => "// node stub for feryardiant/laravel-template\n",
        '.git/config' => "[core]\n[remote \"origin\"]\n\turl = https://github.com/feryardiant/laravel-template.git\n",
    ];

    foreach ($files as $path => $content) {
        file_put_contents($root.\DIRECTORY_SEPARATOR.$path, $content);
    }

    file_put_contents($root.'/public/favicon.ico', "\x00feryardiant/laravel-template\x00");

    return $root;
};

$root = '';

beforeEach(function () use (&$root, $makeFixture): void {
    $root = $makeFixture();
});

afterEach(function () use (&$root): void {
    if ($root !== '') {
        removePath($root);
        $root = '';
    }
});

describe('rewriteTemplateReferences', function () use (&$root) {
    it('replaces template references across text files', function () use (&$root) {
        rewriteTemplateReferences($root, 'fery/my-app');

        expect(file_get_contents($root.'/composer.json'))
            ->toContain('"name": "fery/my-app"')
            ->toContain('https://github.com/fery/my-app');

        expect(file_get_contents($root.'/package.json'))
            ->toContain('"name": "@fery/my-app"');

        expect(file_get_contents($root.'/README.md'))
            ->toContain('https://github.com/fery/my-app')
            ->not->toContain('feryardiant/laravel-template');

        expect(file_get_contents($root.'/docker-compose.yaml'))
            ->toContain('ghcr.io/fery/my-app:local');

        expect(file_get_contents($root.'/scripts/docker/compose.yaml'))
            ->toContain('ghcr.io/fery/my-app:staging');
    });

    it('reports every changed file relative to the root', function () use (&$root) {
        expect(rewriteTemplateReferences($root, 'fery/my-app'))->toBe([
            'README.md',
            'composer.json',
            'docker-compose.staging.yaml',
            'docker-compose.yaml',
            'package-lock.json',
            'package.json',
            'scripts/docker/compose.yaml',
        ]);
    });

    it('leaves ignored directories alone', function () use (&$root) {
        rewriteTemplateReferences($root, 'fery/my-app');

        expect(file_get_contents($root.'/vendor/stub.php'))->toContain('feryardiant/laravel-template');
        expect(file_get_contents($root.'/node_modules/.stub.js'))->toContain('feryardiant/laravel-template');
        expect(file_get_contents($root.'/.git/config'))->toContain('feryardiant/laravel-template');
    });

    it('leaves binary files alone', function () use (&$root) {
        rewriteTemplateReferences($root, 'fery/my-app');

        expect(file_get_contents($root.'/public/favicon.ico'))
            ->toBe("\x00feryardiant/laravel-template\x00");
    });

    it('leaves files without references untouched', function () use (&$root) {
        $changed = rewriteTemplateReferences($root, 'fery/my-app');

        expect($changed)->not->toContain('app/Models/User.php');
        expect(file_get_contents($root.'/app/Models/User.php'))
            ->toBe("<?php\n\nclass User\n{\n}\n");
    });
});

describe('resetPackageVersions', function () use (&$root) {
    it('resets the root version in package.json', function () use (&$root) {
        resetPackageVersions($root);

        $package = (string) file_get_contents($root.'/package.json');

        expect($package)->toContain('"version": "0.0.0"');
        expect($package)->not->toContain('1.2.3');
    });

    it('resets both root versions in package-lock.json but keeps dependency versions', function () use (&$root) {
        resetPackageVersions($root);

        $lock = (string) file_get_contents($root.'/package-lock.json');

        expect(substr_count($lock, '"version": "0.0.0"'))->toBe(2);
        expect($lock)->not->toContain('1.2.3');
        expect($lock)->toContain('"version": "9.9.9"');
    });

    it('leaves composer.lock alone', function () use (&$root) {
        resetPackageVersions($root);

        $composerLock = (string) file_get_contents($root.'/composer.lock');

        expect($composerLock)->toContain('"version": "v13.0.0"');
        expect($composerLock)->not->toContain('0.0.0');
    });
});

describe('removeTemplateOnlyFiles', function () use (&$root) {
    $bothCaps = ['composer' => true, 'npm' => true];

    it('removes template-only files including both locks', function () use (&$root, $bothCaps) {
        removeTemplateOnlyFiles($root, $bothCaps);

        expect(file_exists($root.'/.github/workflows/init-repo.yml'))->toBeFalse();
        expect(file_exists($root.'/composer.lock'))->toBeFalse();
        expect(file_exists($root.'/package-lock.json'))->toBeFalse();
        expect(file_exists($root.'/tests/InitRepoTest.php'))->toBeFalse();

        // The instantiation gate and the remaining scripts must survive.
        expect(file_exists($root.'/.github/workflows/tests.yml'))->toBeTrue();
        expect(file_exists($root.'/scripts/docker/compose.yaml'))->toBeTrue();
    });

    it('keeps locks whose regenerating tool is unavailable', function () use (&$root) {
        removeTemplateOnlyFiles($root, ['composer' => false, 'npm' => false]);

        expect(file_exists($root.'/composer.lock'))->toBeTrue();
        expect(file_exists($root.'/package-lock.json'))->toBeTrue();
        expect(file_exists($root.'/.github/workflows/init-repo.yml'))->toBeFalse();
        expect(file_exists($root.'/tests/InitRepoTest.php'))->toBeFalse();
    });

    it('reports the removed files', function () use (&$root, $bothCaps) {
        expect(removeTemplateOnlyFiles($root, $bothCaps))->toBe([
            '.github/workflows/init-repo.yml',
            'composer.lock',
            'package-lock.json',
            'tests/InitRepoTest.php',
        ]);
    });
});

describe('verifyTransformation', function () use (&$root) {
    $transform = function (string $root, string $target): void {
        rewriteTemplateReferences($root, $target);
        resetPackageVersions($root);
        removeTemplateOnlyFiles($root, ['composer' => true, 'npm' => true]);
    };

    it('passes on a fully transformed tree', function () use (&$root, $transform) {
        $transform($root, 'fery/my-app');

        expect(verifyTransformation($root, 'fery/my-app'))->toBe([]);
    });

    it('reports residual template references', function () use (&$root, $transform) {
        $transform($root, 'fery/my-app');
        file_put_contents($root.'/README.md', "badge feryardiant/laravel-template badge\n");

        expect(verifyTransformation($root, 'fery/my-app'))
            ->toContain('residual template reference in README.md');
    });

    it('reports residual template references when the target is a prefix', function () use (&$root, $transform) {
        $transform($root, 'feryardiant/laravel');
        file_put_contents($root.'/README.md', "badge feryardiant/laravel-template badge\n");

        expect(verifyTransformation($root, 'feryardiant/laravel'))
            ->toContain('residual template reference in README.md');
    });

    it('reports unreset versions', function () use (&$root, $transform) {
        $transform($root, 'fery/my-app');
        file_put_contents($root.'/package.json', "{\n    \"version\": \"1.2.3\"\n}\n");

        expect(verifyTransformation($root, 'fery/my-app'))
            ->toContain('package.json version is not 0.0.0');
    });

    it('reports template files still present', function () use (&$root, $transform) {
        $transform($root, 'fery/my-app');
        file_put_contents($root.'/.github/workflows/init-repo.yml', "name: Init Repo\n");

        expect(verifyTransformation($root, 'fery/my-app'))
            ->toContain('.github/workflows/init-repo.yml still present');
    });

    it('reports the spec file still present', function () use (&$root, $transform) {
        $transform($root, 'fery/my-app');
        file_put_contents($root.'/tests/InitRepoTest.php', "<?php // template spec\n");

        expect(verifyTransformation($root, 'fery/my-app'))
            ->toContain('tests/InitRepoTest.php still present');
    });

    it('reports a still-commented export-ignore list', function () use (&$root, $transform) {
        $transform($root, 'fery/my-app');
        file_put_contents($root.'/.gitattributes', "# docs export-ignore\n", FILE_APPEND);

        expect(verifyTransformation($root, 'fery/my-app'))
            ->toContain('export-ignore entries still commented out');
    });

    it('checks package names against the target', function () use (&$root, $transform) {
        $transform($root, 'other/lib');

        expect(verifyTransformation($root, 'fery/my-app'))
            ->toContain('composer.json name is not fery/my-app')
            ->toContain('package.json name is not @fery/my-app');
    });
});

describe('bootstrapProject', function () use (&$root, $makeFixture) {
    $installScript = function () use (&$root): void {
        copy(\dirname(__DIR__).'/scripts/init-repo.php', $root.'/scripts/init-repo.php');
    };

    it('bootstraps a github repository without prompting', function () use (&$root, $installScript) {
        $installScript();

        $out = fopen('php://memory', 'r+');
        $code = bootstrapProject($root, $out, ['GITHUB_REPOSITORY' => 'fery/my-app']);
        rewind($out);
        $output = (string) stream_get_contents($out);
        fclose($out);

        expect($code)->toBe(0);
        expect($output)->toContain('removed .github/workflows/init-repo.yml');
        expect($output)->toContain('removed tests/InitRepoTest.php');
        expect($output)->toContain('verified');
        expect($output)->not->toContain('Package name');

        expect(file_get_contents($root.'/composer.json'))->toContain('"name": "fery/my-app"');
        expect(file_get_contents($root.'/package.json'))
            ->toContain('"name": "@fery/my-app"')
            ->toContain('"version": "0.0.0"');
        expect(file_get_contents($root.'/docker-compose.staging.yaml'))->toContain('ghcr.io/fery/my-app:staging');

        if (file_exists($root.'/package-lock.json')) {
            expect(file_get_contents($root.'/package-lock.json'))->toContain('"version": "0.0.0"');
        }

        expect(file_exists($root.'/.github/workflows/init-repo.yml'))->toBeFalse();
        expect(file_exists($root.'/tests/InitRepoTest.php'))->toBeFalse();
        expect(file_exists($root.'/scripts/init-repo.php'))->toBeFalse();
        expect(file_exists($root.'/scripts/docker/compose.yaml'))->toBeTrue();
        expect(file_exists($root.'/.git/HEAD'))->toBeFalse();

        expect(file_exists($root.'/composer.lock'))->toBe(! isCommandAvailable('composer'));
        expect(file_exists($root.'/package-lock.json'))->toBe(! isCommandAvailable('npm'));
    });

    it('accepts mixed-case and template-derived repository names', function () use (&$root, $makeFixture) {
        foreach (['fery/my-app', 'OctoCat/Hello-World', 'feryardiant/laravel', 'feryardiant/laravel-template-new'] as $target) {
            $root = $makeFixture();
            copy(\dirname(__DIR__).'/scripts/init-repo.php', $root.'/scripts/init-repo.php');

            $out = fopen('php://memory', 'r+');
            $code = bootstrapProject($root, $out, ['GITHUB_REPOSITORY' => $target]);
            rewind($out);
            $output = (string) stream_get_contents($out);
            fclose($out);

            expect($code)->toBe(0, 'bootstrap failed for '.$target.":\n".$output);
        }
    });

    it('fails before touching the tree when GITHUB_REPOSITORY is missing', function () use (&$root, $installScript) {
        $installScript();

        $out = fopen('php://memory', 'r+');
        $code = bootstrapProject($root, $out, []);
        rewind($out);
        $output = (string) stream_get_contents($out);
        fclose($out);

        expect($code)->toBe(1);
        expect($output)->toContain('FAIL: GITHUB_REPOSITORY is not set');

        expect(file_get_contents($root.'/composer.json'))->toContain('feryardiant/laravel-template');
        expect(file_exists($root.'/.github/workflows/init-repo.yml'))->toBeTrue();
        expect(file_exists($root.'/tests/InitRepoTest.php'))->toBeTrue();
        expect(file_exists($root.'/scripts/init-repo.php'))->toBeTrue();
    });

    it('fails loudly when verification fails', function () use (&$root, $installScript) {
        $installScript();
        file_put_contents($root.'/.gitattributes', "# docs export-ignore\n", FILE_APPEND);

        $out = fopen('php://memory', 'r+');
        $code = bootstrapProject($root, $out, ['GITHUB_REPOSITORY' => 'fery/my-app']);
        rewind($out);
        $output = (string) stream_get_contents($out);
        fclose($out);

        expect($code)->toBe(1);
        expect($output)->toContain('FAIL: export-ignore entries still commented out');
        expect(file_exists($root.'/scripts/init-repo.php'))->toBeTrue();
    });
});

describe('main', function () use (&$root) {
    it('bootstraps from the command line', function () use (&$root) {
        copy(\dirname(__DIR__).'/scripts/init-repo.php', $root.'/scripts/init-repo.php');

        $process = new Process(
            [PHP_BINARY, $root.'/scripts/init-repo.php'],
            null,
            ['GITHUB_REPOSITORY' => 'fery/my-app']
        );
        $process->mustRun();

        expect($process->getExitCode())->toBe(0);
        expect($process->getOutput().$process->getErrorOutput())->toContain('verified');

        expect(file_get_contents($root.'/composer.json'))->toContain('"name": "fery/my-app"');
        expect(file_exists($root.'/scripts/init-repo.php'))->toBeFalse();
        expect(file_exists($root.'/tests/InitRepoTest.php'))->toBeFalse();
    });
});

it('rejects unknown commands', function () {
    expect(isCommandAvailable('definitely-not-a-command-xyz'))->toBeFalse();
});
