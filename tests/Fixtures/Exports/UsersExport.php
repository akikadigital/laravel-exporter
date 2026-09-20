<?php

namespace Akika\LaravelExporter\Tests\Fixtures\Exports;

use Akika\LaravelExporter\Concerns\InteractsWithExportOptions;
use Akika\LaravelExporter\Contracts\Exportable;
use Akika\LaravelExporter\Tests\Fixtures\Models\User;
use Illuminate\Database\Eloquent\Builder;

class UsersExport implements Exportable
{
    use InteractsWithExportOptions;

    public function query(): Builder
    {
        return User::query()
            ->when(
                $this->option('email'),
                fn(Builder $query, string $email) =>
                $query->where('email', $email)
            )
            ->orderBy('id');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Email',
        ];
    }

    public function map(mixed $row): array
    {
        return [
            $row->id,
            $row->name,
            $row->email,
        ];
    }

    public function validateOptions(): void
    {
        //
    }
}
