<?php

namespace Akika\LaravelExporter\Events;

class ExportProgressUpdated
{
    
    public function __construct(
        public readonly int $exportId,
        public readonly int $processedRows,
        public readonly int $totalRows,
        public readonly int $progress,
    ) {}
}
