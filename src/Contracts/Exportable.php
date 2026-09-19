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

    public function validateOptions(): void;
}
