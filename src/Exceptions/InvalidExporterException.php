<?php

namespace Akika\LaravelExporter\Exceptions;

class InvalidExporterException extends ExporterException
{
    public static function for(string $exporter): self
    {
        return new self(
            "Exporter [{$exporter}] is invalid or does not extend the required exporter class."
        );
    }
}
