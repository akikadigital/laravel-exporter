<?php

namespace Akika\LaravelExporter\Support;

use Akika\LaravelExporter\Models\Export;

class ExportCreationResult
{
    public function __construct(
        public readonly Export $export,
        public readonly bool $created,
    ) {}
}
