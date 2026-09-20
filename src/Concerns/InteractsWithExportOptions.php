<?php

namespace Akika\LaravelExporter\Concerns;

trait InteractsWithExportOptions
{
    protected array $exportOptions = [];

    public function setOptions(
        array $options
    ): static {
        $this->exportOptions = $options;

        return $this;
    }

    public function options(): array
    {
        return $this->exportOptions;
    }

    public function option(
        string $key,
        mixed $default = null
    ): mixed {
        return data_get(
            $this->exportOptions,
            $key,
            $default
        );
    }
}
