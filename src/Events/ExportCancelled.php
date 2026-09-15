<?php

namespace Akika\LaravelExporter\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ExportCancelled
{
    use Dispatchable;
    
    public function __construct(
        public readonly int $exportId,
    ) {}
}
