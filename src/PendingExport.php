<?php

namespace Akika\LaravelExporter;

use Akika\LaravelExporter\Enums\ExportFormat;
use Akika\LaravelExporter\Enums\ExportStatus;
use Akika\LaravelExporter\Jobs\ProcessExport;
use Akika\LaravelExporter\Models\Export;
use Akika\LaravelExporter\Models\ExportLock;
use Akika\LaravelExporter\Support\ExportCreationResult;
use Akika\LaravelExporter\Support\ExportFingerprint;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PendingExport
{
    
    protected ?Model $owner = null;

    protected ?string $name = null;

    protected array $options = [];

    protected ExportFormat $format = ExportFormat::CSV;

    protected bool $unique = false;

    public function __construct(
        protected readonly string $exporter,
    ) {}

    public function for(?Model $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function name(string $name): static
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException(
                'Export name cannot be empty.'
            );
        }

        $this->name = $name;

        return $this;
    }

    public function with(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function format(ExportFormat|string $format): static
    {
        $this->format = is_string($format)
            ? ExportFormat::from($format)
            : $format;

        return $this;
    }

    public function unique(bool $unique = true): static
    {
        $this->unique = $unique;

        return $this;
    }

    public function queue(): Export
    {
        $this->validateOptions();

        $fingerprint =
            $this->fingerprint();

        $result = $fingerprint
            ? $this->createUniqueExport(
                $fingerprint
            )
            : new ExportCreationResult(
                export: $this->createExport(),
                created: true,
            );

        if ($result->created) {
            $this->dispatch(
                $result->export
            );
        }

        return $result->export;
    }

    protected function dispatch(
        Export $export
    ): void {
        $job = new ProcessExport(
            $export->id
        );

        if (
            $connection = config(
                'exporter.queue.connection'
            )
        ) {
            $job->onConnection(
                $connection
            );
        }

        if (
            $queue = config(
                'exporter.queue.name'
            )
        ) {
            $job->onQueue(
                $queue
            );
        }

        dispatch($job);
    }

    protected function createExport(
        ?string $fingerprint = null
    ): Export {
        $name = $this->name
            ?? $this->defaultName();

        $uuid = (string) Str::ulid();

        $filename = $this->buildFilename(
            name: $name,
            uuid: $uuid,
        );

        return Export::query()->create([
            'uuid' => $uuid,

            'owner_type' =>
            $this->owner?->getMorphClass(),

            'owner_id' =>
            $this->owner?->getKey(),

            'exporter' =>
            $this->exporter,

            'name' => $name,

            'format' =>
            $this->format,

            'status' =>
            ExportStatus::PENDING,

            'disk' =>
            config('exporter.disk'),

            'filename' =>
            $filename,

            'options' =>
            $this->options,

            'fingerprint' =>
            $fingerprint,

            'expires_at' => null,
        ]);
    }

    protected function createUniqueExport(
        string $fingerprint
    ): ExportCreationResult {
        try {
            return DB::transaction(
                function () use (
                    $fingerprint
                ) {
                    $lock = ExportLock::query()
                        ->where(
                            'fingerprint',
                            $fingerprint
                        )
                        ->with('export')
                        ->first();

                    if (
                        $lock
                        && $lock->export
                    ) {
                        return new ExportCreationResult(
                            export: $lock->export,
                            created: false,
                        );
                    }

                    $export = $this->createExport(
                        $fingerprint
                    );

                    ExportLock::query()->create([
                        'fingerprint' =>
                        $fingerprint,

                        'export_id' =>
                        $export->id,
                    ]);

                    return new ExportCreationResult(
                        export: $export,
                        created: true,
                    );
                }
            );
        } catch (QueryException $exception) {
            $lock = ExportLock::query()
                ->where(
                    'fingerprint',
                    $fingerprint
                )
                ->with('export')
                ->first();

            if (
                $lock
                && $lock->export
            ) {
                return new ExportCreationResult(
                    export: $lock->export,
                    created: false,
                );
            }

            throw $exception;
        }
    }
    
    protected function createExportResult(): ExportCreationResult
    {
        return new ExportCreationResult(
            export: $this->createExport(),
            created: true,
        );
    }

    protected function defaultName(): string
    {
        return Str::headline(
            Str::beforeLast(
                class_basename($this->exporter),
                'Export'
            )
        );
    }

    protected function buildFilename(
        string $name,
        string $uuid,
    ): string {
        return sprintf(
            '%s-%s.%s',
            Str::slug($name),
            $uuid,
            $this->format->extension(),
        );
    }

    protected function validateOptions(): void
    {
        try {
            json_encode(
                $this->options,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $exception) {
            throw new InvalidArgumentException(
                'Export options must be JSON serializable.',
                previous: $exception,
            );
        }
    }

    protected function fingerprint(): ?string
    {
        if (! $this->unique) {
            return null;
        }

        return ExportFingerprint::generate(
            exporter: $this->exporter,
            owner: $this->owner,
            format: $this->format,
            options: $this->options,
        );
    }
    
}
