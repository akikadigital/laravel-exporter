<?php

namespace Akika\LaravelExporter\Exceptions;

class UnsupportedExportFormatException extends ExporterException
{
    public static function for(string $format): self
    {
        return new self(
            "Export format [{$format}] is not supported."
        );
    }
}
