<?php

namespace Akika\LaravelExporter\Tests\Unit;

use Akika\LaravelExporter\Enums\ExportFormat;
use Akika\LaravelExporter\Support\ExportFingerprint;
use Akika\LaravelExporter\Tests\Fixtures\Exports\UsersExport;
use Akika\LaravelExporter\Tests\Fixtures\Models\User;
use Akika\LaravelExporter\Tests\TestCase;

class ExportFingerprintTest extends TestCase
{
    public function test_option_order_does_not_change_fingerprint(): void
    {
        $user = User::query()->create([
            'name' => 'John Doe',
        ]);

        $first = ExportFingerprint::generate(
            UsersExport::class,
            $user,
            ExportFormat::CSV,
            [
                'store_id' => 12,
                'status' => 'active',
            ]
        );

        $second = ExportFingerprint::generate(
            UsersExport::class,
            $user,
            ExportFormat::CSV,
            [
                'status' => 'active',
                'store_id' => 12,
            ]
        );

        $this->assertSame(
            $first,
            $second
        );
    }

    public function test_different_options_create_different_fingerprints(): void
    {
        $user = User::query()->create([
            'name' => 'John Doe',
        ]);

        $first = ExportFingerprint::generate(
            UsersExport::class,
            $user,
            ExportFormat::CSV,
            ['store_id' => 12]
        );

        $second = ExportFingerprint::generate(
            UsersExport::class,
            $user,
            ExportFormat::CSV,
            ['store_id' => 13]
        );

        $this->assertNotSame(
            $first,
            $second
        );
    }

    public function test_different_owners_create_different_fingerprints(): void
    {
        $firstUser = User::query()->create([
            'name' => 'John Doe',
        ]);

        $secondUser = User::query()->create([
            'name' => 'Jane Doe',
        ]);

        $first = ExportFingerprint::generate(
            UsersExport::class,
            $firstUser,
            ExportFormat::CSV,
            []
        );

        $second = ExportFingerprint::generate(
            UsersExport::class,
            $secondUser,
            ExportFormat::CSV,
            []
        );

        $this->assertNotSame(
            $first,
            $second
        );
    }
}
