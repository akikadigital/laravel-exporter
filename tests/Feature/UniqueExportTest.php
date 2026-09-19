<?php

namespace Akika\LaravelExporter\Tests\Feature;

use Akika\LaravelExporter\Facades\Exporter;
use Akika\LaravelExporter\Jobs\ProcessExport;
use Akika\LaravelExporter\Tests\Fixtures\Exports\UsersExport;
use Akika\LaravelExporter\Tests\Fixtures\Models\User;
use Akika\LaravelExporter\Tests\TestCase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class UniqueExportTest extends TestCase
{
    public function test_identical_active_exports_are_deduplicated(): void
    {
        Queue::fake();

        $user = User::query()->create([
            'name' => 'John Doe',
        ]);

        $first = Exporter::make(
            UsersExport::class
        )
            ->for($user)
            ->with([
                'email' => 'john@example.com',
            ])
            ->unique()
            ->queue();

        $second = Exporter::make(
            UsersExport::class
        )
            ->for($user)
            ->with([
                'email' => 'john@example.com',
            ])
            ->unique()
            ->queue();

        $this->assertSame(
            $first->id,
            $second->id
        );

        $this->assertDatabaseCount(
            'exports',
            1
        );

        $this->assertDatabaseCount(
            'export_locks',
            1
        );

        Queue::assertPushed(
            ProcessExport::class,
            1
        );
    }

    public function test_option_order_does_not_create_duplicate_exports(): void
    {
        Queue::fake();

        $first = Exporter::make(
            UsersExport::class
        )
            ->with([
                'status' => 'active',
                'store_id' => 12,
            ])
            ->unique()
            ->queue();

        $second = Exporter::make(
            UsersExport::class
        )
            ->with([
                'store_id' => 12,
                'status' => 'active',
            ])
            ->unique()
            ->queue();

        $this->assertSame(
            $first->id,
            $second->id
        );
    }

    public function test_completed_export_releases_unique_lock(): void
    {
        Storage::fake('local');
        Queue::fake();

        User::query()->create([
            'name' => 'John Doe',
        ]);

        $first = Exporter::make(
            UsersExport::class
        )
            ->unique()
            ->queue();

        $this->assertDatabaseCount(
            'export_locks',
            1
        );

        (new ProcessExport(
            $first->id
        ))->handle();

        $this->assertDatabaseCount(
            'export_locks',
            0
        );

        $second = Exporter::make(
            UsersExport::class
        )
            ->unique()
            ->queue();

        $this->assertNotSame(
            $first->id,
            $second->id
        );
    }
}
