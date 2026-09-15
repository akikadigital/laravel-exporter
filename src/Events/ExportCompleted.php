<?php

namespace Akika\LaravelExporter\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ExportCompleted
{
    use Dispatchable;
    
    public function __construct(
        public readonly int $exportId,
    ) {}
}
