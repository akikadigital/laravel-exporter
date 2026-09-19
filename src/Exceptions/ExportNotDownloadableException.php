<?php

namespace Akika\LaravelExporter\Exceptions;

class ExportNotDownloadableException extends ExporterException
{
    public static function for(int|string $exportId): self
    {
        return new self(
            "Export [{$exportId}] is not available for download."
        );
    }
}
