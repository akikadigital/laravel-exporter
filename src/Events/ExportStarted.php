<?php

namespace Akika\LaravelExporter\Events;

class ExportStarted
{
    
    public function __construct(
        public readonly int $exportId,
    ) {}
}
