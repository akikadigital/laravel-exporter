<?php

namespace Akika\LaravelExporter\Listeners;

use Akika\LaravelExporter\Broadcasting\Events\ExportCancelledBroadcast;
use Akika\LaravelExporter\Broadcasting\ExportBroadcaster;
use Akika\LaravelExporter\Events\ExportCancelled;
use Akika\LaravelExporter\Models\Export;

class BroadcastExportCancelled
{
    public function __construct(
        protected ExportBroadcaster $broadcaster
    ) {}

    public function handle(ExportCancelled $event): void
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
            new ExportCancelledBroadcast(
                channel: $channel,
                export: $this->broadcaster->data($export),
            )
        );
    }
}
