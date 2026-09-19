<?php

namespace Akika\LaravelExporter\Tests\Feature;

use Akika\LaravelExporter\Enums\ExportStatus;
use Akika\LaravelExporter\Facades\Exporter;
use Akika\LaravelExporter\Jobs\ProcessExport;
use Akika\LaravelExporter\Tests\Fixtures\Exports\UsersExport;
use Akika\LaravelExporter\Tests\Fixtures\Models\User;
use Akika\LaravelExporter\Tests\TestCase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class ProcessExportTest extends TestCase
{
    public function test_it_generates_a_csv_export(): void
    {
        Storage::fake('local');
        Queue::fake();

        User::query()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        User::query()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        User::query()->create([
            'name' => 'Peter Doe',
            'email' => 'peter@example.com',
        ]);

        $export = Exporter::make(
            UsersExport::class
        )
            ->name('Users')
            ->queue();

        $job = new ProcessExport(
            $export->id
        );

        $job->handle();

        $export->refresh();

        $this->assertSame(
            ExportStatus::COMPLETED,
            $export->status
        );

        $this->assertSame(
            3,
            $export->total_rows
        );

        $this->assertSame(
            3,
            $export->processed_rows
        );

        $this->assertSame(
            100,
            $export->progress
        );

        $this->assertNotNull(
            $export->completed_at
        );

        $this->assertNotNull(
            $export->expires_at
        );

        Storage::disk('local')
            ->assertExists(
                $export->path
            );

        $content = Storage::disk('local')
            ->get($export->path);

        $this->assertStringContainsString(
            'ID,Name,Email',
            $content
        );

        $this->assertStringContainsString(
            '"John Doe",john@example.com',
            $content
        );

        $this->assertStringContainsString(
            '"Jane Doe",jane@example.com',
            $content
        );
    }


    public function test_export_options_are_applied(): void
    {
        Storage::fake('local');
        Queue::fake();

        User::query()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        User::query()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $export = Exporter::make(
            UsersExport::class
        )
            ->with([
                'email' => 'john@example.com',
            ])
            ->queue();

        (new ProcessExport(
            $export->id
        ))->handle();

        $export->refresh();

        $this->assertSame(
            1,
            $export->total_rows
        );

        $content = Storage::disk('local')
            ->get($export->path);

        $this->assertStringContainsString(
            'John Doe',
            $content
        );

        $this->assertStringNotContainsString(
            'Jane Doe',
            $content
        );
    }
}
