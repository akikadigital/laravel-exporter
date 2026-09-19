<?php

namespace Akika\LaravelExporter\Tests\Fixtures\Exports;

use Akika\LaravelExporter\Exporter;
use Akika\LaravelExporter\Tests\Fixtures\Models\User;
use Illuminate\Database\Eloquent\Builder;

class UsersExport extends Exporter
{
    public function query(): Builder
    {
        return User::query()
            ->when(
                $this->option('email'),
                fn(
                    Builder $query,
                    string $email
                ) => $query->where(
                    'email',
                    $email
                )
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

    public function map(
        mixed $user
    ): array {
        return [
            $user->id,
            $user->name,
            $user->email,
        ];
    }
}
