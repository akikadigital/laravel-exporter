<?php

namespace Akika\LaravelExporter\Services;

use Akika\LaravelExporter\Contracts\Exportable;
use Akika\LaravelExporter\PendingExport;
use InvalidArgumentException;

class ExportManager
{
    public function make(string $exporter): PendingExport
    {
        if (! class_exists($exporter)) {
            throw new InvalidArgumentException(
                "Exporter class [{$exporter}] does not exist."
            );
        }

        if (! is_subclass_of($exporter, Exportable::class)) {
            throw new InvalidArgumentException(
                "Exporter class [{$exporter}] must implement "
                    . Exportable::class . '.'
            );
        }

        return new PendingExport($exporter);
    }
}
