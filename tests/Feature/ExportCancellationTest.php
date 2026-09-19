<?php

namespace Akika\LaravelExporter\Tests\Feature;

use Akika\LaravelExporter\Enums\ExportStatus;
use Akika\LaravelExporter\Events\ExportCancelled;
use Akika\LaravelExporter\Facades\Exporter;
use Akika\LaravelExporter\Tests\Fixtures\Exports\UsersExport;
use Akika\LaravelExporter\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

class ExportCancellationTest extends TestCase
{
    public function test_an_export_can_be_cancelled(): void
    {
        Queue::fake();
        Event::fake();

        $export = Exporter::make(
            UsersExport::class
        )->queue();

        $result = $export->cancel();

        $export->refresh();

        $this->assertTrue($result);

        $this->assertSame(
            ExportStatus::CANCELLED,
            $export->status
        );

        $this->assertNotNull(
            $export->expires_at
        );

        Event::assertDispatched(
            ExportCancelled::class,
            fn(ExportCancelled $event) =>
            $event->exportId === $export->id
        );
    }
}
