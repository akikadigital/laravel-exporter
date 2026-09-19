<?php

namespace Akika\LaravelExporter\Contracts;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

interface Exportable
{
    /**
     * Build the query that supplies rows for the export.
     *
     * The query should not be executed here. Laravel Exporter will
     * process the query in chunks during export generation.
     */
    public function query(): EloquentBuilder|QueryBuilder;

    /**
     * Return the column headings for the exported file.
     *
     * @return array<int, string>
     */
    public function headings(): array;

    /**
     * Transform a database record into an export row.
     *
     * The returned values should correspond to the columns
     * defined by headings().
     *
     * @return array<int, mixed>
     */
    public function map(mixed $row): array;

    /**
     * Validate the options supplied to this export.
     *
     * Implementations may throw a validation exception when
     * the supplied options are invalid.
     */
    public function validateOptions(): void;
}
