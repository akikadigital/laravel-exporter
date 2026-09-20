<?php

namespace Akika\LaravelExporter\Broadcasting;

use Akika\LaravelExporter\Models\Export;

final class ExportBroadcaster
{
    public function enabled(): bool
    {
        return (bool) config(
            'exporter.broadcasting.enabled',
            false
        );
    }

    public function channel(Export $export): ?string
    {
        return ExportChannel::for($export);
    }

    public function data(Export $export): array
    {
        return ExportBroadcastData::from($export);
    }
}
