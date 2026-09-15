<?php

namespace Akika\LaravelExporter\Facades;

use Akika\LaravelExporter\Services\ExportManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Akika\LaravelExporter\PendingExport make(string $exporter)
 *
 * @see ExportManager
 */
class Exporter extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ExportManager::class;
    }
}
