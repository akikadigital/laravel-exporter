<?php

namespace Akika\LaravelExporter\Writers;

use Akika\LaravelExporter\Contracts\ExportWriter;
use Akika\LaravelExporter\Models\Export;
use RuntimeException;

class CsvWriter implements ExportWriter
{
    /**
     * @var resource|null
     */
    protected $handle = null;

    protected ?string $temporaryPath = null;

    protected ?string $storagePath = null;

    public function open(Export $export): void
    {
        $this->storagePath = $this->buildStoragePath($export);

        $this->temporaryPath = tempnam(
            sys_get_temp_dir(),
            'laravel-exporter-'
        );

        if ($this->temporaryPath === false) {
            throw new RuntimeException(
                'Unable to create temporary export file.'
            );
        }

        $this->handle = fopen(
            $this->temporaryPath,
            'wb'
        );

        if ($this->handle === false) {
            throw new RuntimeException(
                'Unable to open temporary export file.'
            );
        }
    }

    public function writeHeadings(array $headings): void
    {
        $this->writeRow($headings);
    }

    public function writeRow(array $row): void
    {
        $this->ensureOpen();

        if (fputcsv($this->handle, $row) === false) {
            throw new RuntimeException(
                'Unable to write export row.'
            );
        }
    }

    public function close(): void
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }

        $this->handle = null;
    }

    public function path(): string
    {
        if ($this->storagePath === null) {
            throw new RuntimeException(
                'Export writer has not been opened.'
            );
        }

        return $this->storagePath;
    }

    public function temporaryPath(): string
    {
        if ($this->temporaryPath === null) {
            throw new RuntimeException(
                'Export writer has not been opened.'
            );
        }

        return $this->temporaryPath;
    }

    public function cleanup(): void
    {
        $this->close();

        if (
            $this->temporaryPath !== null
            && file_exists($this->temporaryPath)
        ) {
            @unlink($this->temporaryPath);
        }

        $this->temporaryPath = null;
    }

    protected function buildStoragePath(
        Export $export
    ): string {
        $directory = trim(
            (string) config('exporter.path', 'exports'),
            '/'
        );

        return $directory . '/' . $export->filename;
    }

    protected function ensureOpen(): void
    {
        if (! is_resource($this->handle)) {
            throw new RuntimeException(
                'Export writer is not open.'
            );
        }
    }
}
