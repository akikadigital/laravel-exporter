<?php

namespace Akika\LaravelExporter\Broadcasting;

use Akika\LaravelExporter\Models\Export;

final class ExportBroadcastData
{
    public static function from(Export $export): array
    {
        return [
            'id' => $export->getKey(),
            'uuid' => $export->uuid,
            'name' => $export->name,
            'status' => $export->status->value,
            'progress' => $export->progress,
            'processed_rows' => $export->processed_rows,
            'total_rows' => $export->total_rows,
            'remaining_rows' => $export->remaining_rows,
        ];
    }
}
