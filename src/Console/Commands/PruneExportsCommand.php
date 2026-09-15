<?php

namespace Akika\LaravelExporter\Console\Commands;

use Akika\LaravelExporter\Enums\ExportStatus;
use Akika\LaravelExporter\Models\Export;
use Illuminate\Console\Command;
use Throwable;

class PruneExportsCommand extends Command
{
    protected $signature = 'exporter:prune';

    protected $description =
    'Delete expired export files and optionally their database records.';

    public function handle(): int
    {
        $deleteRecords = (bool) config(
            'exporter.prune.delete_records',
            true
        );

        $deletedFiles = 0;
        $deletedRecords = 0;
        $failed = 0;

        Export::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->whereIn('status', [
                ExportStatus::COMPLETED->value,
                ExportStatus::FAILED->value,
                ExportStatus::CANCELLED->value,
            ])
            ->orderBy('id')
            ->chunkById(
                100,
                function ($exports) use (
                    $deleteRecords,
                    &$deletedFiles,
                    &$deletedRecords,
                    &$failed
                ) {
                    // existing pruning logic
                }
            );

        $this->info(
            sprintf(
                'Pruning complete. Files: %d, records: %d, failed: %d.',
                $deletedFiles,
                $deletedRecords,
                $failed
            )
        );

        return $failed > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
