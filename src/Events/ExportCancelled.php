<?php

namespace Akika\LaravelExporter\Events;

class ExportCancelled
{
    
    public function __construct(
        public readonly int $exportId,
    ) {}
}
