<?php

namespace Ghazali\EPassPackage;

use Ghazali\EPassPackage\Commands\ExportEpassDataCommand;
use Ghazali\EPassPackage\Commands\GenerateEPassKeysCommand;
use Ghazali\EPassPackage\Commands\InstallEPassCommand;
use Ghazali\EPassPackage\Commands\UninstallEPassCommand;
use Ghazali\EPassPackage\Commands\PruneEpassCacheCommand;
use Ghazali\EPassPackage\Http\Middleware\ApiTokenAuth;
use Illuminate\Routing\Router;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class EPassServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('epass-package')
            ->hasCommands([
                InstallEPassCommand::class,
                UninstallEPassCommand::class,
                GenerateEPassKeysCommand::class,
                ExportEpassDataCommand::class,
                PruneEpassCacheCommand::class,
            ])
            ->hasMigrations([
                'create_epass_users_table',
                'create_epass_cache_table',
                '2026_10_07_000003_add_airport_lds_evidence_to_epass_users_table',
            ]);
    }

    public function registeringPackage(): void
    {
        if (class_exists(\Vendor\HybridCrypto\HybridCryptoServiceProvider::class)) {
            $this->app->register(\Vendor\HybridCrypto\HybridCryptoServiceProvider::class);
        }

        $this->app['config']->set(
            'hybrid-crypto.anti_qcp',
            (bool) config('epass.anti_qcp', false),
        );

        $this->mergeConfigFrom(__DIR__ . '/../config/epass.php', 'epass');
        $this->mergeConfigFrom(__DIR__ . '/../config/epass_fields.php', 'epass_fields');
        $this->mergeConfigFrom(__DIR__ . '/../config/epass_attestation.example.php', 'epass_attestation');
        $this->mergeConfigFrom(__DIR__ . '/../config/epass_passports.php', 'epass_passports');
        $this->mergeConfigFrom(__DIR__ . '/../config/epass_sdk.php', 'epass_sdk');
        $this->mergeConfigFrom(__DIR__ . '/../config/epass_airport.php', 'epass_airport');
        $this->mergeConfigFrom(__DIR__ . '/../config/passport_identity.php', 'passport_identity');
        $this->registerEpassAuthGuard();
    }

    public function bootingPackage(): void
    {
        $this->publishes([
            __DIR__ . '/../config/epass.php' => config_path('epass.php'),
            __DIR__ . '/../config/epass_fields.php' => config_path('epass_fields.php'),
            // epass_attestation.example.php ships with the package (the real file is gitignored).
            // It is published as epass_attestation.php in the host app's config/ directory.
            __DIR__ . '/../config/epass_attestation.example.php' => config_path('epass_attestation.php'),
            __DIR__ . '/../config/epass_passports.php' => config_path('epass_passports.php'),
            __DIR__ . '/../config/epass_sdk.php' => config_path('epass_sdk.php'),
            __DIR__ . '/../config/epass_airport.php' => config_path('epass_airport.php'),
            __DIR__ . '/../config/passport_identity.php' => config_path('passport_identity.php'),
        ], 'epass-config');

        // NOTE: migration publishing is handled automatically by hasMigrations() in
        // configurePackage(). A separate publishes() call is intentionally omitted here
        // to prevent the package's development-only skeleton migrations (create_users,
        // create_cache, create_jobs) from being copied into host applications.

        $this->publishes([
            __DIR__ . '/../resources/js' => public_path('vendor/epass-package/js'),
        ], 'epass-assets');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/epass'),
        ], 'epass-views');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'epass');

        // All package routes are completely retired and absorbed into HandlesEpassProtocol Trait.
        // No routes are registered from web.php or api.php.

        /** @var Router $router */
        $router = $this->app->make('router');
        $router->aliasMiddleware('epass.auth-token', ApiTokenAuth::class);

        if ($this->app->bound('request')
            && ($this->app['request']->header('X-Forwarded-Proto') === 'https'
                || $this->app['request']->header('X-Forwarded-Ssl') === 'on')) {
            // Auto-detect HTTPS behind reverse proxies (Cloudflare, ngrok, etc.)
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // ── Composer Uninstall Event Listener ────────────────────────────────
        \Illuminate\Support\Facades\Event::listen(
            'composer_package.ghazali/epass-package:pre_uninstall',
            function () {
                try {
                    \Illuminate\Support\Facades\Artisan::call('epass:uninstall', [
                        '--delete-config' => true,
                    ]);
                } catch (\Throwable $e) {
                    // Silently ignore any errors to allow composer removal to complete
                }
            }
        );

        // ── Auto-register Composer script in root composer.json ──────────────
        if ($this->app->runningInConsole()) {
            try {
                $composerJsonPath = base_path('composer.json');
                if (file_exists($composerJsonPath)) {
                    $content = file_get_contents($composerJsonPath);
                    $data = json_decode($content, true);
                    if (is_array($data)) {
                        $modified = false;
                        if (!isset($data['scripts'])) {
                            $data['scripts'] = [];
                        }
                        if (!isset($data['scripts']['pre-package-uninstall'])) {
                            $data['scripts']['pre-package-uninstall'] = [];
                        }
                        if (is_string($data['scripts']['pre-package-uninstall'])) {
                            $data['scripts']['pre-package-uninstall'] = [$data['scripts']['pre-package-uninstall']];
                        }

                        $callback = "Ghazali\\EPassPackage\\ComposerScripts::prePackageUninstall";
                        if (!in_array($callback, $data['scripts']['pre-package-uninstall'])) {
                            $data['scripts']['pre-package-uninstall'][] = $callback;
                            $modified = true;
                        }

                        if ($modified) {
                            file_put_contents(
                                $composerJsonPath,
                                json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
                            );
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Silently ignore
            }
        }

        // ── Automatic installation actions (runs if missing) ─────────────────
        $this->app->booted(function () {
            // Do not run auto-install during console commands that do setup, migration, or teardown
            if (app()->runningInConsole()) {
                $arguments = $_SERVER['argv'] ?? [];
                foreach ($arguments as $arg) {
                    if (str_contains($arg, 'uninstall') || str_contains($arg, 'install') || str_contains($arg, 'migrate')) {
                        return;
                    }
                }
            }

            // 1. Auto-publish assets if the core JS asset is missing
            if (!file_exists(public_path('vendor/epass-package/js/epass-auth.js'))) {
                try {
                    \Illuminate\Support\Facades\Artisan::call('vendor:publish', [
                        '--tag' => 'epass-assets',
                        '--force' => true
                    ]);
                } catch (\Throwable $e) {
                    // Silently ignore
                }
            }

            // 2. Auto-publish and run package migrations if either the epass_users or epass_cache table is missing
            try {
                if (!\Illuminate\Support\Facades\Schema::hasTable('epass_users') || !\Illuminate\Support\Facades\Schema::hasTable('epass_cache')) {
                    \Illuminate\Support\Facades\Artisan::call('vendor:publish', [
                        '--tag' => 'epass-package-migrations',
                        '--force' => true
                    ]);
                    \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
                }
            } catch (\Throwable $e) {
                // Silently ignore
            }

            // 3. Auto-run storage:link if the public storage symlink is missing
            if (!file_exists(public_path('storage'))) {
                try {
                    \Illuminate\Support\Facades\Artisan::call('storage:link');
                } catch (\Throwable $e) {
                    // Silently ignore
                }
            }
        });

        // ── Automatic cache cleanup on logout ──────────────────────────────
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Logout::class,
            function () {
                try {
                    \Ghazali\EPassPackage\Services\EpassSessionCacheService::flushAllTracked(request());
                } catch (\Throwable $e) {
                    // Silently ignore
                }
            }
        );

    }

    private function registerEpassAuthGuard(): void
    {
        $guard = \Ghazali\EPassPackage\Support\EpassPackageAuth::guardName();

        $this->app['config']->set(
            "auth.providers.epass_users",
            array_merge(
                (array) config('auth.providers.epass_users', []),
                [
                    'driver' => 'eloquent',
                    'model' => \Ghazali\EPassPackage\Models\EpassUser::class,
                ],
            ),
        );

        $this->app['config']->set(
            "auth.guards.{$guard}",
            array_merge(
                (array) config("auth.guards.{$guard}", []),
                [
                    'driver' => 'session',
                    'provider' => 'epass_users',
                ],
            ),
        );
    }
}
