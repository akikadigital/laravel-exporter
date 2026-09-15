<?php

use Akika\LaravelExporter\Http\Controllers\DownloadExportController;
use Illuminate\Support\Facades\Route;

Route::middleware(
    config(
        'exporter.route.middleware',
        ['web', 'auth']
    )
)
    ->prefix(
        config(
            'exporter.route.prefix',
            'exports'
        )
    )
    ->group(function () {
        Route::get(
            '/{export}/download',
            DownloadExportController::class
        )->name(
            config(
                'exporter.downloads.route',
                'exports.download'
            )
        );
    });
