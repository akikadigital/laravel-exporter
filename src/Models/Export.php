<?php

namespace Akika\LaravelExporter\Models;

use Akika\LaravelExporter\Enums\ExportFormat;
use Akika\LaravelExporter\Enums\ExportStatus;
use Akika\LaravelExporter\Events\ExportCancelled;
use Akika\LaravelExporter\Events\ExportCompleted;
use Akika\LaravelExporter\Events\ExportFailed;
use Akika\LaravelExporter\Events\ExportStarted;
use Akika\LaravelExporter\Exceptions\ExportNotDownloadableException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
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

    public function lock(): HasOne
    {
        return $this->hasOne(
            ExportLock::class
        );
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

    public function isExpired(): bool
    {
        return $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    public function isDownloadable(): bool
    {
        if (! $this->isCompleted()) {
            return false;
        }

        if ($this->isExpired()) {
            return false;
        }

        if (! $this->disk || ! $this->path) {
            return false;
        }

        return Storage::disk($this->disk)
            ->exists($this->path);
    }

    public function isActive(): bool
    {
        return in_array(
            $this->status,
            [
                ExportStatus::PENDING,
                ExportStatus::PROCESSING,
            ],
            true
        );
    }

    public function requireDownloadUrl(
        ?int $expiresAfterMinutes = null
    ): string {
        $url = $this->downloadUrl(
            $expiresAfterMinutes
        );

        if ($url === null) {
            throw ExportNotDownloadableException::for(
                $this->getKey()
            );
        }

        return $url;
    }

    public function downloadUrl(
        ?int $expiresAfterMinutes = null
    ): ?string {
        if (! $this->isDownloadable()) {
            return null;
        }

        $expiresAfterMinutes ??= max(
            1,
            (int) config(
                'exporter.downloads.url_expires_after',
                15
            )
        );

        return URL::temporarySignedRoute(
            config(
                'exporter.downloads.route',
                'exports.download'
            ),
            now()->addMinutes(
                $expiresAfterMinutes
            ),
            [
                'export' => $this,
            ]
        );
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
            event(
                new ExportStarted(
                    $this->getKey()
                )
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

        $completedAt = now();

        $this->forceFill([
            'status' => ExportStatus::COMPLETED,
            'path' => $path,
            'processed_rows' =>
            $this->total_rows
                ?? $this->processed_rows,
            'meta' => $meta,
            'completed_at' => $completedAt,
            'expires_at' => $this->expirationDate(),
            'failed_at' => null,
            'error_message' => null,
        ])->save();

        $this->releaseUniqueLock();

        event(
            new ExportCompleted(
                $this->getKey()
            )
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

        $failedAt = now();

        $this->forceFill([
            'status' => ExportStatus::FAILED,
            'failed_at' => $failedAt,
            'expires_at' => $this->expirationDate(),
            'error_message' => $message,
        ])->save();

        $this->releaseUniqueLock();

        event(
            new ExportFailed(
                $this->getKey(),
                $message
            )
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
            'expires_at' => $this->expirationDate(),
        ])->save();

        $this->releaseUniqueLock();

        event(
            new ExportCancelled(
                $this->getKey(),
            )
        );

        return true;
    }

    protected function expirationDate(): Carbon
    {
        return now()->addDays(
            max(
                1,
                (int) config(
                    'exporter.expires_after_days',
                    7
                )
            )
        );
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

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function deleteFile(): bool
    {
        if (! $this->disk || ! $this->path) {
            return true;
        }

        $disk = Storage::disk(
            $this->disk
        );

        if (! $disk->exists($this->path)) {
            return true;
        }

        return $disk->delete(
            $this->path
        );
    }

    public function scopeActive(
        Builder $query
    ): Builder {
        return $query->whereIn(
            'status',
            ExportStatus::active()
        );
    }

    public function scopePending(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            ExportStatus::PENDING->value
        );
    }

    public function scopeProcessing(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            ExportStatus::PROCESSING->value
        );
    }

    public function scopeCompleted(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            ExportStatus::COMPLETED->value
        );
    }

    public function scopeFailed(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            ExportStatus::FAILED->value
        );
    }

    public function scopeCancelled(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            ExportStatus::CANCELLED->value
        );
    }

    public function scopeFinished(
        Builder $query
    ): Builder {
        return $query->whereIn(
            'status',
            ExportStatus::finished()
        );
    }

    public function scopeExpired(
        Builder $query
    ): Builder {
        return $query
            ->whereNotNull('expires_at')
            ->where(
                'expires_at',
                '<=',
                now()
            );
    }

    public function scopeForOwner(
        Builder $query,
        Model $owner
    ): Builder {
        return $query
            ->where(
                'owner_type',
                $owner->getMorphClass()
            )
            ->where(
                'owner_id',
                $owner->getKey()
            );
    }

    public static function findActiveByFingerprint(
        string $fingerprint
    ): ?static {
        return static::query()
            ->active()
            ->where(
                'fingerprint',
                $fingerprint
            )
            ->latest('id')
            ->first();
    }

    public function releaseUniqueLock(): void
    {
        $this->lock()->delete();
    }

    protected function progress(): Attribute
    {
        return Attribute::get(
            function (): int {
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
                        (
                            $this->processed_rows
                            / $this->total_rows
                        ) * 100
                    )
                );
            }
        );
    }

    protected function remainingRows(): Attribute
    {
        return Attribute::get(
            function (): ?int {
                if ($this->total_rows === null) {
                    return null;
                }

                return max(
                    0,
                    $this->total_rows
                        - $this->processed_rows
                );
            }
        );
    }
}
