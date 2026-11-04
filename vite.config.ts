import tailwindcss from '@tailwindcss/vite'
import laravel from 'laravel-vite-plugin'
import { fontsource } from 'laravel-vite-plugin/fonts'
import { defineConfig, lazyPlugins, loadEnv, type UserConfig } from 'vite-plus'

export default defineConfig(({ mode }): UserConfig => {
    const env = loadEnv(mode, '.', '')
    const appUrl = new URL(env.APP_URL || 'http://localhost')

    return {
        staged: {
            '*.{css,md,json,js,ts,yaml,yml}': 'vp check --fix',
            '{app,config,database,routes,tests}/**/*.php': 'php vendor/bin/pint --parallel',
        },
        fmt: {
            printWidth: 120,
            singleQuote: true,
            semi: false,
            sortImports: true,
            singleAttributePerLine: false,
            htmlWhitespaceSensitivity: 'css',
            ignorePatterns: [
                'composer.json',
                'AGENTS.md',
                '.agents/**/*.md',
                'resources/views/**',
                'tests/coverage/**',
            ],
            sortTailwindcss: { stylesheet: 'resources/css/app.css' },
        },
        lint: {
            ignorePatterns: ['vendor/**', 'node_modules/**', 'public/**', 'tests/coverage/**'],
            jsPlugins: [{ name: 'vite-plus', specifier: 'vite-plus/oxlint-plugin' }],
            options: { typeAware: true, typeCheck: true },
            overrides: [
                {
                    files: ['**/*.config.{ts,js}'],
                    rules: { 'no-console': ['off'] },
                },
            ],
            rules: {
                'vite-plus/prefer-vite-plus-imports': 'error',
                'no-console': ['error', { allow: ['warn', 'error'] }],
            },
        },
        plugins: lazyPlugins(() => [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.ts'],
                refresh: true,
                fonts: [
                    fontsource('Instrument Sans', {
                        weights: [400, 500, 600],
                    }),
                ],
            }),
            tailwindcss(),
        ]),
        server: configureServer(appUrl),
    }
})

function configureServer(appUrl: URL): UserConfig['server'] {
    const server: UserConfig['server'] = {
        watch: {
            ignored: [
                '**/.agents/**',
                '**/database/**',
                '**/docker/**',
                '**/storage/**',
                '**/tests/**',
                '**/vendor/**',
            ],
        },
    }

    if (appUrl.hostname !== 'localhost') {
        // Allow vite to receive request from non `localhost`, e.g using orbstack subdomains,
        server.cors = { origin: ['http://localhost', appUrl.origin] }
    }

    return server
}
