<?php

namespace Akika\LaravelExporter\Exceptions;

use Akika\LaravelExporter\Contracts\Exportable;

class InvalidExporterException extends ExporterException
{
    public static function for(
        string $exporter
    ): self {
        return new self(
            sprintf(
                'Exporter [%s] must exist and implement [%s].',
                $exporter,
                Exportable::class
            )
        );
    }
}
