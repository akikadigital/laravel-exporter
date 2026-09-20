<?php

namespace Akika\LaravelExporter\Tests\Feature;

use Akika\LaravelExporter\Broadcasting\ExportBroadcastData;
use Akika\LaravelExporter\Broadcasting\ExportBroadcaster;
use Akika\LaravelExporter\Broadcasting\ExportChannel;
use Akika\LaravelExporter\Enums\ExportStatus;
use Akika\LaravelExporter\Events\ExportCompleted;
use Akika\LaravelExporter\Events\ExportStarted;
use Akika\LaravelExporter\Facades\Exporter;
use Akika\LaravelExporter\Jobs\ProcessExport;
use Akika\LaravelExporter\Models\Export;
use Akika\LaravelExporter\Tests\Fixtures\Exports\UsersExport;
use Akika\LaravelExporter\Tests\Fixtures\Models\User;
use Akika\LaravelExporter\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class ExportEventsTest extends TestCase
{
    public function test_it_dispatches_started_and_completed_events(): void
    {
        Storage::fake('local');
        Queue::fake();
        Event::fake();

        User::query()->create([
            'name' => 'John Doe',
        ]);

        $export = Exporter::make(
            UsersExport::class
        )->queue();

        (new ProcessExport(
            $export->id
        ))->handle();

        Event::assertDispatched(
            ExportStarted::class,
            fn(ExportStarted $event) =>
            $event->exportId === $export->id
        );

        Event::assertDispatched(
            ExportCompleted::class,
            fn(ExportCompleted $event) =>
            $event->exportId === $export->id
        );
    }

    public function test_it_generates_an_owner_specific_broadcast_channel(): void
    {
        config([
            'exporter.broadcasting.channel_prefix' => 'exports',
        ]);

        $user = User::query()->create([
            'name' => 'John Doe',
        ]);

        $export = Export::query()->create([
            'uuid' => (string) str()->uuid(),
            'owner_type' => $user::class,
            'owner_id' => $user->getKey(),
            'exporter' => 'TestExporter',
            'name' => 'Users',
            'format' => 'csv',
            'status' => ExportStatus::PENDING,
        ]);

        $channel = ExportChannel::for($export);

        $this->assertSame(
            'exports.' .
                str_replace('\\', '.', $user::class) .
                '.' .
                $user->getKey(),
            $channel
        );
    }

    public function test_ownerless_exports_do_not_have_a_broadcast_channel(): void
    {
        $export = Export::query()->create([
            'uuid' => (string) str()->uuid(),
            'owner_type' => null,
            'owner_id' => null,
            'exporter' => 'TestExporter',
            'name' => 'Users',
            'format' => 'csv',
            'status' => ExportStatus::PENDING,
        ]);

        $this->assertNull(
            ExportChannel::for($export)
        );
    }

    public function test_broadcast_payload_does_not_expose_internal_data(): void
    {
        $export = Export::query()->create([
            'uuid' => (string) str()->uuid(),
            'exporter' => 'TestExporter',
            'name' => 'Users',
            'format' => 'csv',
            'status' => ExportStatus::PROCESSING,
            'processed_rows' => 25,
            'total_rows' => 100,
        ]);

        $data = ExportBroadcastData::from($export);

        $this->assertSame(
            $export->getKey(),
            $data['id']
        );

        $this->assertSame(
            'Users',
            $data['name']
        );

        $this->assertSame(
            ExportStatus::PROCESSING->value,
            $data['status']
        );

        $this->assertSame(
            25,
            $data['processed_rows']
        );

        $this->assertSame(
            100,
            $data['total_rows']
        );

        $this->assertArrayNotHasKey('disk', $data);
        $this->assertArrayNotHasKey('path', $data);
        $this->assertArrayNotHasKey('fingerprint', $data);
        $this->assertArrayNotHasKey('error_message', $data);
        $this->assertArrayNotHasKey('exporter', $data);
    }

    public function test_broadcasting_is_disabled_by_default(): void
    {
        config([
            'exporter.broadcasting.enabled' => false,
        ]);

        $broadcaster = app(
            ExportBroadcaster::class
        );

        $this->assertFalse(
            $broadcaster->enabled()
        );
    }
}
