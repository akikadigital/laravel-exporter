<?php


namespace Akika\LaravelExporter\Tests\Feature;

use Akika\LaravelExporter\Enums\ExportStatus;
use Akika\LaravelExporter\Facades\Exporter;
use Akika\LaravelExporter\Models\Export;
use Akika\LaravelExporter\Tests\Fixtures\Exports\UsersExport;
use Akika\LaravelExporter\Tests\Fixtures\Models\User;
use Akika\LaravelExporter\Tests\TestCase;
use Illuminate\Support\Facades\Queue;

class ExportScopesTest extends TestCase
{
    public function test_it_filters_exports_by_owner(): void
    {
        Queue::fake();

        $john = User::query()->create([
            'name' => 'John',
        ]);

        $jane = User::query()->create([
            'name' => 'Jane',
        ]);

        $johnExport = Exporter::make(
            UsersExport::class
        )
            ->for($john)
            ->queue();

        Exporter::make(
            UsersExport::class
        )
            ->for($jane)
            ->queue();

        $exports = Export::query()
            ->forOwner($john)
            ->get();

        $this->assertCount(
            1,
            $exports
        );

        $this->assertTrue(
            $exports->first()->is(
                $johnExport
            )
        );
    }

    public function test_active_scope_returns_pending_and_processing_exports(): void
    {
        Queue::fake();

        $pending = Exporter::make(
            UsersExport::class
        )->queue();

        $processing = Exporter::make(
            UsersExport::class
        )->queue();

        $processing->update([
            'status' => ExportStatus::PROCESSING,
        ]);

        $completed = Exporter::make(
            UsersExport::class
        )->queue();

        $completed->update([
            'status' => ExportStatus::COMPLETED,
        ]);

        $failed = Exporter::make(
            UsersExport::class
        )->queue();

        $failed->update([
            'status' => ExportStatus::FAILED,
        ]);

        $cancelled = Exporter::make(
            UsersExport::class
        )->queue();

        $cancelled->update([
            'status' => ExportStatus::CANCELLED,
        ]);

        $exports = Export::query()
            ->active()
            ->get();

        $this->assertCount(2, $exports);

        $this->assertEqualsCanonicalizing(
            [
                $pending->id,
                $processing->id,
            ],
            $exports->pluck('id')->all()
        );
    }
}

