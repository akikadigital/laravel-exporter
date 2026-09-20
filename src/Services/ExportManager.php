<?php

namespace Akika\LaravelExporter\Services;

use Akika\LaravelExporter\Contracts\Exportable;
use Akika\LaravelExporter\Exceptions\InvalidExporterException;
use Akika\LaravelExporter\PendingExport;

class ExportManager
{
    public function make(string $exporter): PendingExport
    {
        if (
            ! class_exists($exporter)
            || ! is_subclass_of($exporter, Exportable::class)
        ) {
            throw InvalidExporterException::for(
                $exporter
            );
        }

        return new PendingExport(
            $exporter
        );
    }
}
