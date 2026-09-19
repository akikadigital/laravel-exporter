<?php

namespace Akika\LaravelExporter;

use Akika\LaravelExporter\Contracts\Exportable;
use InvalidArgumentException;

abstract class Exporter implements Exportable
{
    public function __construct(
        protected array $options = [],
    ) {}

    public function options(): array
    {
        return $this->options;
    }

    public function option(
        string $key,
        mixed $default = null,
    ): mixed {
        return data_get(
            $this->options,
            $key,
            $default
        );
    }

    /**
     * Validate an exported row against its headings.
     */
    public function validateRow(array $row): void
    {
        $expected = count($this->headings());
        $actual = count($row);

        if ($expected !== $actual) {
            throw new InvalidArgumentException(
                sprintf(
                    'Export row contains %d columns, but %d headings were defined.',
                    $actual,
                    $expected
                )
            );
        }
    }

    public function validateOptions(): void
    {
        validator(
            $this->options(),
            [
                'store_id' => [
                    'nullable',
                    'integer',
                ],

                'from' => [
                    'nullable',
                    'date',
                ],

                'to' => [
                    'nullable',
                    'date',
                    'after_or_equal:from',
                ],
            ]
        )->validate();
    }
}
