<?php

namespace Akika\LaravelExporter\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ExportProgressUpdated
{
    use Dispatchable;
    
    public function __construct(
        public readonly int $exportId,
        public readonly int $processedRows,
        public readonly int $totalRows,
        public readonly int $progress,
    ) {}
}
