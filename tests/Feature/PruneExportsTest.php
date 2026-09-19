<?php

namespace Akika\LaravelExporter\Tests\Feature;

use Akika\LaravelExporter\Facades\Exporter;
use Akika\LaravelExporter\Jobs\ProcessExport;
use Akika\LaravelExporter\Tests\Fixtures\Exports\UsersExport;
use Akika\LaravelExporter\Tests\Fixtures\Models\User;
use Akika\LaravelExporter\Tests\TestCase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class PruneExportsTest extends TestCase
{
    public function test_it_prunes_expired_exports(): void
    {
        Storage::fake('local');
        Queue::fake();

        User::query()->create([
            'name' => 'John Doe',
        ]);

        $export = Exporter::make(
            UsersExport::class
        )->queue();

        (new ProcessExport(
            $export->id
        ))->handle();

        $export->refresh();

        $path = $export->path;

        Storage::disk('local')
            ->assertExists($path);

        $export->update([
            'expires_at' => now()
                ->subMinute(),
        ]);

        $this->artisan(
            'exporter:prune'
        )->assertSuccessful();

        Storage::disk('local')
            ->assertMissing($path);

        $this->assertDatabaseMissing(
            'exports',
            [
                'id' => $export->id,
            ]
        );
    }

    public function test_it_does_not_prune_unexpired_exports(): void
    {
        Storage::fake('local');
        Queue::fake();

        User::query()->create([
            'name' => 'John Doe',
        ]);

        $export = Exporter::make(
            UsersExport::class
        )->queue();

        (new ProcessExport(
            $export->id
        ))->handle();

        $export->refresh();

        $this->artisan(
            'exporter:prune'
        )->assertSuccessful();

        Storage::disk('local')
            ->assertExists(
                $export->path
            );

        $this->assertDatabaseHas(
            'exports',
            [
                'id' => $export->id,
            ]
        );
    }
}
