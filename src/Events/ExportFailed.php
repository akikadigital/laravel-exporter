<?php

namespace Akika\LaravelExporter\Events;

class ExportFailed
{
    
    public function __construct(
        public readonly int $exportId,
        public readonly ?string $message = null,
    ) {}
}
