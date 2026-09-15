<?php

namespace Akika\LaravelExporter\Contracts;

use Akika\LaravelExporter\Models\Export;

interface ExportWriter
{
    public function open(Export $export): void;

    public function writeHeadings(array $headings): void;

    public function writeRow(array $row): void;

    public function close(): void;

    public function path(): string;
}
