<?php

namespace Akika\LaravelExporter\Tests\Feature;

use Akika\LaravelExporter\Events\ExportCompleted;
use Akika\LaravelExporter\Events\ExportStarted;
use Akika\LaravelExporter\Facades\Exporter;
use Akika\LaravelExporter\Jobs\ProcessExport;
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
}
