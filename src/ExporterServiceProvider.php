<?php

namespace Akika\LaravelExporter;

use Illuminate\Support\ServiceProvider;

class ExporterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/exporter.php',
            'exporter'
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/exporter.php'
            => config_path('exporter.php'),
        ], 'exporter-config');
    }
}
