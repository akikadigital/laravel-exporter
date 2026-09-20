<?php

namespace Akika\LaravelExporter\Broadcasting;

use Akika\LaravelExporter\Models\Export;

final class ExportChannel
{
    public static function for(Export $export): ?string
    {
        if (
            $export->owner_type === null
            || $export->owner_id === null
        ) {
            return null;
        }

        $prefix = config(
            'exporter.broadcasting.channel_prefix',
            'exports'
        );

        return sprintf(
            '%s.%s.%s',
            $prefix,
            str_replace('\\', '.', $export->owner_type),
            $export->owner_id
        );
    }
}
