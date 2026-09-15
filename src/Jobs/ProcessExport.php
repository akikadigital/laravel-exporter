<?php

namespace Akika\LaravelExporter\Jobs;

use Akika\LaravelExporter\Contracts\Exportable;
use Akika\LaravelExporter\Models\Export;
use Akika\LaravelExporter\Writers\CsvWriter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Storage;
use DateTime;
use RuntimeException;
use Throwable;

class ProcessExport implements ShouldQueue
{
    use Queueable;
    
    public bool $failOnTimeout = true;

    public function tries(): int
    {
        return max(
            1,
            (int) config(
                'exporter.queue.tries',
                3
            )
        );
    }

    public function retryUntil(): DateTime
    {
        return now()
            ->addSeconds(
                max(
                    60,
                    (int) config(
                        'exporter.queue.timeout',
                        3600
                    )
                )
            )
            ->toDateTime();
    }

    public function __construct(
        public readonly int $exportId,
    ) {}

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping(
                'laravel-exporter:' . $this->exportId
            ))
                ->releaseAfter(30)
                ->expireAfter(3700),
        ];
    }

    public function backoff(): array
    {
        return [
            30,
            120,
            300,
        ];
    }

    public function handle(): void
    {
        $export = Export::query()
            ->findOrFail($this->exportId);

        if (
            $export->isCompleted()
            || $export->isCancelled()
        ) {
            return;
        }

        $writer = new CsvWriter();

        try {
            $exporter = $this->resolveExporter(
                $export
            );

            $query = $exporter->query();

            $totalRows = (clone $query)->count();

            $export->markAsProcessing(
                $totalRows
            );

            $writer->open($export);

            $writer->writeHeadings(
                $exporter->headings()
            );

            $this->processRows(
                export: $export,
                exporter: $exporter,
                writer: $writer,
                query: $query,
            );

            $export->refresh();

            if ($export->isCancelled()) {
                return;
            }

            $writer->close();

            $this->storeFile(
                export: $export,
                writer: $writer,
            );

            $export->refresh();

            if ($export->isCancelled()) {
                $this->deleteStoredFile(
                    $export,
                    $writer->path()
                );

                return;
            }

            $meta = [
                'mime_type' =>
                $export->format->mimeType(),

                'size' =>
                Storage::disk($export->disk)
                    ->size($writer->path()),

                'rows' => $totalRows,
            ];

            $export->markAsCompleted(
                path: $writer->path(),
                meta: $meta,
            );
        } finally {
            $writer->cleanup();
        }
    }

    public function failed(
        ?Throwable $exception
    ): void {
        $export = Export::query()
            ->find($this->exportId);

        if (! $export) {
            return;
        }

        if ($export->isCancelled()) {
            return;
        }

        $export->markAsFailed(
            $exception
        );
    }

    protected function resolveExporter(
        Export $export
    ): Exportable {
        $exporter = app()->makeWith(
            $export->exporter,
            [
                'options' =>
                $export->options ?? [],
            ]
        );

        if (! $exporter instanceof Exportable) {
            throw new RuntimeException(
                "Exporter [{$export->exporter}] "
                    . 'must implement '
                    . Exportable::class . '.'
            );
        }

        return $exporter;
    }

    protected function processRows(
        Export $export,
        Exportable $exporter,
        CsvWriter $writer,
        mixed $query,
    ): void {
        $chunkSize = max(
            1,
            (int) config(
                'exporter.chunk_size',
                1000
            )
        );

        $processed = 0;

        $query->chunkById(
            $chunkSize,
            function ($rows) use (
                $export,
                $exporter,
                $writer,
                &$processed
            ) {
                $export->refresh();

                if ($export->isCancelled()) {
                    return false;
                }

                foreach ($rows as $row) {
                    $mapped = $exporter->map(
                        $row
                    );

                    if (
                        method_exists(
                            $exporter,
                            'validateRow'
                        )
                    ) {
                        $exporter->validateRow(
                            $mapped
                        );
                    }

                    $writer->writeRow(
                        $mapped
                    );

                    $processed++;
                }

                $export->updateProcessedRows(
                    $processed
                );

                return true;
            }
        );
    }

    protected function storeFile(
        Export $export,
        CsvWriter $writer,
    ): void {
        $stream = fopen(
            $writer->temporaryPath(),
            'rb'
        );

        if ($stream === false) {
            throw new RuntimeException(
                'Unable to open completed export '
                    . 'for storage.'
            );
        }

        try {
            $stored = Storage::disk(
                $export->disk
            )->put(
                $writer->path(),
                $stream
            );

            if ($stored === false) {
                throw new RuntimeException(
                    'Unable to store completed export.'
                );
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    protected function deleteStoredFile(
        Export $export,
        string $path
    ): void {
        try {
            Storage::disk($export->disk)
                ->delete($path);
        } catch (Throwable) {
            // Best-effort cleanup.
        }
    }
}
