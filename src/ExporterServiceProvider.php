<?php

namespace Akika\LaravelExporter;

use Akika\LaravelExporter\Console\Commands\PruneExportsCommand;
use Akika\LaravelExporter\Events\ExportCancelled;
use Akika\LaravelExporter\Events\ExportCompleted;
use Akika\LaravelExporter\Events\ExportFailed;
use Akika\LaravelExporter\Events\ExportProgressUpdated;
use Akika\LaravelExporter\Listeners\BroadcastExportCancelled;
use Akika\LaravelExporter\Listeners\BroadcastExportCompleted;
use Akika\LaravelExporter\Listeners\BroadcastExportFailed;
use Akika\LaravelExporter\Listeners\BroadcastExportProgress;
use Akika\LaravelExporter\Services\ExportManager;
use Illuminate\Support\Facades\Event;
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

        Event::listen(
            ExportProgressUpdated::class,
            BroadcastExportProgress::class
        );

        Event::listen(
            ExportCompleted::class,
            BroadcastExportCompleted::class
        );

        Event::listen(
            ExportFailed::class,
            BroadcastExportFailed::class
        );

        Event::listen(
            ExportCancelled::class,
            BroadcastExportCancelled::class
        );
    }
}
