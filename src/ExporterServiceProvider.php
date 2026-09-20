<?php

namespace Akika\LaravelExporter;

use Akika\LaravelExporter\Console\Commands\PruneExportsCommand;
use Akika\LaravelExporter\Services\ExportManager;
use Illuminate\Support\ServiceProvider;

class ExporterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/exporter.php',
            'exporter'
        );

        $this->app->singleton(
            ExportManager::class,
            fn() => new ExportManager()
        );
    }

    public function boot(): void
    {
        // Load the package routes
        $this->loadRoutesFrom(
            __DIR__ . '/../routes/web.php'
        );

        // php artisan vendor:publish --tag=exporter-config
        $this->publishes([
            __DIR__ . '/../config/exporter.php'
            => config_path('exporter.php'),
        ], 'exporter-config');

        // php artisan vendor:publish --tag=exporter-migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/create_exports_table.php.stub'
            => database_path(
                'migrations/'
                    . date('Y_m_d_His')
                    . '_create_exports_table.php'
            ),

            __DIR__ . '/../database/migrations/create_export_locks_table.php.stub'
            => database_path(
                'migrations/'
                    . date('Y_m_d_His', time() + 1)
                    . '_create_export_locks_table.php'
            ),
        ], 'exporter-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                PruneExportsCommand::class,
            ]);
        }
    }
}
