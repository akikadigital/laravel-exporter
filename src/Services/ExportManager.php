<?php

namespace Akika\LaravelExporter\Services;

use Akika\LaravelExporter\Exceptions\InvalidExporterException;
use Akika\LaravelExporter\Exporter;
use Akika\LaravelExporter\PendingExport;

class ExportManager
{
    public function make(string $exporter): PendingExport
    {
        if (
            ! class_exists($exporter)
            || ! is_subclass_of(
                $exporter,
                Exporter::class
            )
        ) {
            throw InvalidExporterException::for(
                $exporter
            );
        }

        return new PendingExport(
            exporter: $exporter
        );
    }
}
