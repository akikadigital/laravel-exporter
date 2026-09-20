<?php

namespace Akika\LaravelExporter\Broadcasting\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class ExportCompletedBroadcast implements ShouldBroadcastNow
{
    public function __construct(
        public readonly string $channel,
        public readonly array $export,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel($this->channel),
        ];
    }

    public function broadcastAs(): string
    {
        return 'export.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'export' => $this->export,
        ];
    }
}
