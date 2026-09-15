<?php

namespace Akika\LaravelExporter\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ExportFailed
{
    use Dispatchable;
    
    public function __construct(
        public readonly int $exportId,
        public readonly ?string $message = null,
    ) {}
}
