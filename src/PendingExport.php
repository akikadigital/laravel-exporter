<?php

namespace Akika\LaravelExporter;

use Akika\LaravelExporter\Enums\ExportFormat;
use Akika\LaravelExporter\Enums\ExportStatus;
use Akika\LaravelExporter\Jobs\ProcessExport;
use Akika\LaravelExporter\Models\Export;
use Illuminate\Database\Eloquent\Model;
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
        $name = $this->name
            ?? $this->defaultName();

        $uuid = (string) Str::ulid();

        $filename = $this->buildFilename(
            name: $name,
            uuid: $uuid,
        );

        $export = Export::query()->create([
            'uuid' => $uuid,

            'owner_type' => $this->owner?->getMorphClass(),
            'owner_id' => $this->owner?->getKey(),

            'exporter' => $this->exporter,
            'name' => $name,

            'format' => $this->format,
            'status' => ExportStatus::PENDING,

            'disk' => config('exporter.disk'),
            'filename' => $filename,

            'options' => $this->options,

            'expires_at' => now()->addDays(
                config('exporter.expires_after_days', 7)
            ),
        ]);

        $job = new ProcessExport($export->id);

        if ($connection = config(
            'exporter.queue.connection'
        )) {
            $job->onConnection($connection);
        }

        if ($queue = config(
            'exporter.queue.name'
        )) {
            $job->onQueue($queue);
        }

        dispatch($job);

        return $export;
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
}
