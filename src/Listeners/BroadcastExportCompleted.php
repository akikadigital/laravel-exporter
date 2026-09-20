<?php

namespace Akika\LaravelExporter\Listeners;

use Akika\LaravelExporter\Broadcasting\Events\ExportCompletedBroadcast;
use Akika\LaravelExporter\Broadcasting\ExportBroadcaster;
use Akika\LaravelExporter\Events\ExportCompleted;
use Akika\LaravelExporter\Models\Export;

class BroadcastExportCompleted
{
    public function __construct(
        protected ExportBroadcaster $broadcaster
    ) {}

    public function handle(ExportCompleted $event): void
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
            new ExportCompletedBroadcast(
                channel: $channel,
                export: $this->broadcaster->data($export),
            )
        );
    }
}
