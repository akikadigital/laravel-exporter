<?php

namespace Akika\LaravelExporter\Console\Commands;

use Akika\LaravelExporter\Enums\ExportStatus;
use Akika\LaravelExporter\Models\Export;
use Akika\LaravelExporter\Models\ExportLock;
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
        $deletedLocks = 0;
        $failed = 0;

        Export::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->whereIn('status', ExportStatus::finished())
            ->orderBy('id')
            ->chunkById(
                100,
                function ($exports) use (
                    $deleteRecords,
                    &$deletedFiles,
                    &$deletedRecords,
                    &$failed
                ) {
                    foreach ($exports as $export) {
                        try {
                            if ($export->path) {
                                if (! $export->deleteFile()) {
                                    $failed++;

                                    $this->warn(
                                        "Unable to delete file for export [{$export->uuid}]."
                                    );

                                    continue;
                                }

                                $deletedFiles++;
                            }

                            if ($deleteRecords) {
                                $export->delete();

                                $deletedRecords++;
                            }
                        } catch (Throwable $exception) {
                            $failed++;

                            report($exception);

                            $this->warn(
                                "Unable to prune export [{$export->uuid}]: "
                                    . $exception->getMessage()
                            );
                        }
                    }
                }
            );

        /*
         * Remove locks belonging to exports that have already reached
         * a terminal state. Normally these are removed by the export
         * lifecycle methods, but this repairs stale locks.
         */
        $deletedLocks += ExportLock::query()
            ->whereHas(
                'export',
                function ($query) {
                    $query->whereIn('status', ExportStatus::finished());
                }
            )
            ->delete();

        /*
         * Defensive cleanup for orphaned locks.
         *
         * Normally cascadeOnDelete() removes these automatically.
         */
        $deletedLocks += ExportLock::query()
            ->whereDoesntHave('export')
            ->delete();

        $this->info(
            sprintf(
                'Pruning complete. Files: %d, records: %d, locks: %d, failed: %d.',
                $deletedFiles,
                $deletedRecords,
                $deletedLocks,
                $failed
            )
        );

        return $failed > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
