<?php

namespace Akika\LaravelExporter\Listeners;

use Akika\LaravelExporter\Broadcasting\Events\ExportFailedBroadcast;
use Akika\LaravelExporter\Broadcasting\ExportBroadcaster;
use Akika\LaravelExporter\Events\ExportFailed;
use Akika\LaravelExporter\Models\Export;

class BroadcastExportFailed
{
    public function __construct(
        protected ExportBroadcaster $broadcaster
    ) {}

    public function handle(ExportFailed $event): void
    {
        if (! $this->broadcaster->enabled()) {
            return;
        }

        $export = Export::query()->find(
            $event->exportId
        );

        if (! $export) {
            return;
        }

        $channel = $this->broadcaster->channel(
            $export
        );

        if ($channel === null) {
            return;
        }

        event(
            new ExportFailedBroadcast(
                channel: $channel,
                export: $this->broadcaster->data($export),
            )
        );
    }
}
