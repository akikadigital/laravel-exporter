<?php

namespace Akika\LaravelExporter\Models;

use Akika\LaravelExporter\Enums\ExportFormat;
use Akika\LaravelExporter\Enums\ExportStatus;
use Akika\LaravelExporter\Events\ExportCancelled;
use Akika\LaravelExporter\Events\ExportCompleted;
use Akika\LaravelExporter\Events\ExportFailed;
use Akika\LaravelExporter\Events\ExportStarted;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use Throwable;


class Export extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'format' => ExportFormat::class,
            'status' => ExportStatus::class,
            'options' => 'array',
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'progress' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function isPending(): bool
    {
        return $this->status === ExportStatus::PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === ExportStatus::PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === ExportStatus::COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === ExportStatus::FAILED;
    }

    public function isCancelled(): bool
    {
        return $this->status === ExportStatus::CANCELLED;
    }

    public function isFinished(): bool
    {
        return $this->status->isFinished();
    }

    public function existsOnDisk(): bool
    {
        if (! $this->disk || ! $this->path) {
            return false;
        }

        return Storage::disk($this->disk)->exists($this->path);
    }

    /// Actions to update the status of the export.
    public function markAsProcessing(
        ?int $totalRows = null
    ): static {
        $wasPending = $this->isPending();

        $this->forceFill([
            'status' => ExportStatus::PROCESSING,
            'total_rows' => $totalRows,
            'processed_rows' => 0,
            'started_at' => $this->started_at ?? now(),
            'completed_at' => null,
            'failed_at' => null,
            'error_message' => null,
        ])->save();

        if ($wasPending) {
            ExportStarted::dispatch(
                $this->getKey()
            );
        }

        return $this;
    }

    public function markAsCompleted(
        string $path,
        array $meta = []
    ): static {
        if ($this->isCompleted()) {
            return $this;
        }

        $this->forceFill([
            'status' => ExportStatus::COMPLETED,
            'path' => $path,
            'processed_rows' =>
            $this->total_rows
                ?? $this->processed_rows,
            'meta' => $meta,
            'completed_at' => now(),
            'failed_at' => null,
            'error_message' => null,
        ])->save();

        ExportCompleted::dispatch(
            $this->getKey()
        );

        return $this;
    }

    public function markAsFailed(
        ?Throwable $exception = null
    ): static {
        if ($this->isFailed()) {
            return $this;
        }

        if ($this->isCancelled()) {
            return $this;
        }

        $message = $exception?->getMessage();

        $this->forceFill([
            'status' => ExportStatus::FAILED,
            'failed_at' => now(),
            'error_message' => $message,
        ])->save();

        ExportFailed::dispatch(
            $this->getKey(),
            $message
        );

        return $this;
    }

    public function cancel(): bool
    {
        if ($this->status->isFinished()) {
            return false;
        }

        $this->forceFill([
            'status' => ExportStatus::CANCELLED,
        ])->save();

        ExportCancelled::dispatch(
            $this->getKey()
        );

        return true;
    }

    public function updateProcessedRows(int $rows): static
    {
        $this->forceFill([
            'processed_rows' => max(0, $rows),
        ])->save();

        return $this;
    }

    public function getProgressAttribute(): int
    {
        if ($this->isCompleted()) {
            return 100;
        }

        if (
            ! $this->total_rows
            || $this->total_rows <= 0
        ) {
            return 0;
        }

        return min(
            100,
            (int) floor(
                ($this->processed_rows / $this->total_rows) * 100
            )
        );
    }
}
