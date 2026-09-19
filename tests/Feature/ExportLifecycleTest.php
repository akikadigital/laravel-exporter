<?php


namespace Akika\LaravelExporter\Tests\Feature;

use Akika\LaravelExporter\Enums\ExportStatus;
use Akika\LaravelExporter\Exceptions\ExportConfigurationException;
use Akika\LaravelExporter\Exceptions\InvalidExporterException;
use Akika\LaravelExporter\Facades\Exporter;
use Akika\LaravelExporter\Models\Export;
use Akika\LaravelExporter\Tests\Fixtures\Exports\UsersExport;
use Akika\LaravelExporter\Tests\Fixtures\Models\User;
use Akika\LaravelExporter\Tests\TestCase;
use Illuminate\Support\Facades\Queue;

class ExportLifecycleTest extends TestCase
{
    public function test_it_calculates_progress(): void
    {
        $export = new Export([
            'status' =>
            ExportStatus::PROCESSING,

            'total_rows' => 100,
            'processed_rows' => 37,
        ]);

        $this->assertSame(
            37,
            $export->progress
        );
    }

    public function test_progress_is_zero_when_total_is_unknown(): void
    {
        $export = new Export([
            'status' =>
            ExportStatus::PROCESSING,

            'total_rows' => null,
            'processed_rows' => 0,
        ]);

        $this->assertSame(
            0,
            $export->progress
        );
    }

    public function test_completed_export_has_one_hundred_percent_progress(): void
    {
        $export = new Export([
            'status' =>
            ExportStatus::COMPLETED,

            'total_rows' => 100,
            'processed_rows' => 100,
        ]);

        $this->assertSame(
            100,
            $export->progress
        );
    }

    public function test_it_rejects_invalid_exporter_classes(): void
    {
        $this->expectException(
            InvalidExporterException::class
        );

        Exporter::make(
            User::class
        );
    }

    public function test_it_rejects_missing_storage_disk_configuration(): void
    {
        config([
            'exporter.disk' => null,
        ]);

        $this->expectException(
            ExportConfigurationException::class
        );

        Exporter::make(
            UsersExport::class
        )->queue();
    }
}
