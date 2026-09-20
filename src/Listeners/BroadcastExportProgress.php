<?php

namespace Akika\LaravelExporter\Listeners;

use Akika\LaravelExporter\Broadcasting\Events\ExportProgressBroadcast;
use Akika\LaravelExporter\Broadcasting\ExportBroadcaster;
use Akika\LaravelExporter\Events\ExportProgressUpdated;
use Akika\LaravelExporter\Models\Export;

class BroadcastExportProgress
{
    public function __construct(
        protected ExportBroadcaster $broadcaster
    ) {}

    public function handle(ExportProgressUpdated $event): void
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

        $channel = $this->broadcaster->channel($export);

        if ($channel === null) {
            return;
        }

        event(
            new ExportProgressBroadcast(
                channel: $channel,
                export: $this->broadcaster->data($export),
            )
        );
    }
}
