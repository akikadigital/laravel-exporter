<?php

namespace Akika\LaravelExporter\Events;
class ExportCompleted
{
    
    public function __construct(
        public readonly int $exportId,
    ) {}
}
