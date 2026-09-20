<?php

namespace Akika\LaravelExporter\Contracts;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

interface Exportable
{
    /**
     * Build the query that supplies rows for the export.
     */
    public function query(): EloquentBuilder|QueryBuilder;

    /**
     * Return the column headings.
     */
    public function headings(): array;

    /**
     * Transform a database record into an export row.
     */
    public function map(mixed $row): array;

    /**
     * Set the options supplied to the export.
     */
    public function setOptions(array $options): static;

    /**
     * Return all export options.
     */
    public function options(): array;

    /**
     * Retrieve a single export option.
     */
    public function option(
        string $key,
        mixed $default = null
    ): mixed;

    /**
     * Validate the supplied export options.
     */
    public function validateOptions(): void;
}
