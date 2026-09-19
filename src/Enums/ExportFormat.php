<?php

namespace Akika\LaravelExporter\Enums;

enum ExportFormat: string
{
    case CSV = 'csv';
    // case XLSX = 'xlsx';
    // case JSON = 'json';

    public function extension(): string
    {
        return match ($this) {
            self::CSV => 'csv',
        };
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::CSV => 'text/csv',
        };
    }
}