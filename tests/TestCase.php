<?php

namespace Akika\LaravelExporter\Tests;

use Akika\LaravelExporter\ExporterServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders(
        $app
    ): array {
        return [
            ExporterServiceProvider::class,
        ];
    }

    protected function defineEnvironment(
        $app
    ): void {
        $app['config']->set(
            'database.default',
            'testing'
        );

        $app['config']->set(
            'database.connections.testing',
            [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]
        );

        $app['config']->set(
            'filesystems.default',
            'local'
        );

        $app['config']->set(
            'filesystems.disks.local',
            [
                'driver' => 'local',
                'root' => storage_path(
                    'framework/testing/disks/local'
                ),
                'throw' => false,
            ]
        );

        $app['config']->set(
            'exporter.disk',
            'local'
        );

        $app['config']->set(
            'exporter.path',
            'exports'
        );

        $app['config']->set(
            'exporter.chunk_size',
            2
        );

        $app['config']->set(
            'exporter.expires_after_days',
            7
        );

        $app['config']->set(
            'exporter.progress.event_interval',
            5
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    protected function setUpDatabase(): void
    {
        $this->createExportsTable();
        $this->createExportLocksTable();
        $this->createUsersTable();
    }

    protected function createExportsTable(): void
    {
        Schema::create(
            'exports',
            function (Blueprint $table) {
                $table->id();
                $table->ulid('uuid')->unique();

                $table->nullableMorphs(
                    'owner'
                );

                $table->string('exporter');
                $table->string('name');

                $table
                    ->string('format', 20)
                    ->default('csv');

                $table
                    ->string('status', 30)
                    ->default('pending');

                $table->string('disk')
                    ->nullable();

                $table->string('path')
                    ->nullable();

                $table->string('filename')
                    ->nullable();

                $table->unsignedBigInteger(
                    'total_rows'
                )->nullable();

                $table->unsignedBigInteger(
                    'processed_rows'
                )->default(0);

                $table->json('options')
                    ->nullable();

                $table->json('meta')
                    ->nullable();

                $table->string(
                    'fingerprint',
                    64
                )->nullable();

                $table->text(
                    'error_message'
                )->nullable();

                $table->timestamp(
                    'started_at'
                )->nullable();

                $table->timestamp(
                    'completed_at'
                )->nullable();

                $table->timestamp(
                    'failed_at'
                )->nullable();

                $table->timestamp(
                    'expires_at'
                )->nullable();

                $table->timestamps();

                $table->index('status');
                $table->index('expires_at');
                $table->index('fingerprint');
            }
        );
    }

    protected function createExportLocksTable(): void
    {
        Schema::create(
            'export_locks',
            function (Blueprint $table) {
                $table->id();

                $table
                    ->string('fingerprint', 64)
                    ->unique();

                $table
                    ->foreignId('export_id')
                    ->unique()
                    ->constrained('exports')
                    ->cascadeOnDelete();

                $table->timestamps();
            }
        );
    }

    protected function createUsersTable(): void
    {
        Schema::create(
            'users',
            function (Blueprint $table) {
                $table->id();
                $table->string('name');

                $table->string('email')
                    ->nullable();

                $table->timestamps();
            }
        );
    }
}
