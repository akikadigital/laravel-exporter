<?php

namespace Akika\LaravelExporter\Tests\Feature;

use Akika\LaravelExporter\Enums\ExportFormat;
use Akika\LaravelExporter\Enums\ExportStatus;
use Akika\LaravelExporter\Facades\Exporter;
use Akika\LaravelExporter\Jobs\ProcessExport;
use Akika\LaravelExporter\Tests\Fixtures\Exports\UsersExport;
use Akika\LaravelExporter\Tests\Fixtures\Models\User;
use Akika\LaravelExporter\Tests\TestCase;
use Illuminate\Support\Facades\Queue;

class ExportCreationTest extends TestCase
{
    public function test_it_creates_and_queues_an_export(): void
    {
        Queue::fake();

        $user = User::query()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $export = Exporter::make(
            UsersExport::class
        )
            ->name('Users')
            ->for($user)
            ->with([
                'email' => 'john@example.com',
            ])
            ->queue();

        $this->assertDatabaseHas(
            'exports',
            [
                'id' => $export->id,
                'owner_type' =>
                $user->getMorphClass(),
                'owner_id' =>
                $user->getKey(),
                'exporter' =>
                UsersExport::class,
                'name' => 'Users',
                'format' =>
                ExportFormat::CSV->value,
                'status' =>
                ExportStatus::PENDING->value,
            ]
        );

        Queue::assertPushed(
            ProcessExport::class,
            fn(ProcessExport $job) =>
            $job->exportId === $export->id
        );
    }

    public function test_it_generates_a_default_name(): void
    {
        Queue::fake();

        $export = Exporter::make(
            UsersExport::class
        )->queue();

        $this->assertSame(
            'Users',
            $export->name
        );
    }
}
