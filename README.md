# Laravel Exporter

Asynchronous, queue-based data exports for Laravel applications.

Laravel Exporter is designed for exporting large datasets without keeping an HTTP request open while the export is generated.

Instead of generating a large CSV directly inside a controller or Livewire request, the package creates an export record, dispatches the work to Laravel's queue, processes the dataset in chunks, stores the resulting file, and tracks the export lifecycle.

## Features

* Asynchronous queue-based exports
* Large dataset processing using chunking
* CSV exports
* Export progress tracking
* Export lifecycle management
* User/owner-associated exports
* Export cancellation
* Expiring exports
* Automatic pruning
* Duplicate active-export prevention
* Export lifecycle events
* Laravel filesystem-backed storage
* Signed download URLs
* Configurable queue connection and queue
* Configurable job attempts and timeout
* Configurable export storage and expiration
* Framework-agnostic core with no Livewire dependency

## Requirements

* PHP 8.2+
* Laravel 11, 12 or 13
* A configured Laravel queue

## Installation

Install the package using Composer:

```bash
composer require akika/laravel-exporter
```

Laravel will automatically discover the package service provider.

### Publish the Configuration

Publish the package configuration:

```bash
php artisan vendor:publish --tag=exporter-config
```

The configuration file will be published to:

```text
config/exporter.php
```

The package supports the following environment variables:

```dotenv
EXPORTER_DISK=local
EXPORTER_PATH=exports

EXPORTER_QUEUE_CONNECTION=
EXPORTER_QUEUE=exports
EXPORTER_QUEUE_TRIES=3
EXPORTER_QUEUE_TIMEOUT=3600

EXPORTER_CHUNK_SIZE=1000
EXPORTER_EXPIRES_AFTER_DAYS=7

EXPORTER_PROGRESS_EVENT_INTERVAL=5

EXPORTER_DOWNLOAD_URL_EXPIRES_AFTER=15
EXPORTER_ROUTE_PREFIX=exports

EXPORTER_PRUNE_DELETE_RECORDS=true
```

All values are optional. The package will use the defaults defined in `config/exporter.php` when an environment variable is not provided.

### Publish the Migrations

```bash
php artisan vendor:publish --tag=exporter-migrations
```

Then run:

```bash
php artisan migrate
```

The package uses its database tables to track export state, progress, metadata, expiration, and active export locks.

## Configuration

The package configuration is located at:

```text
config/exporter.php
```

You can configure:

* Storage disk and export path
* Queue connection and queue name
* Queue attempts and timeout
* Database chunk size
* Export lifetime
* Progress event interval
* Signed download URL lifetime
* Download route prefix
* Route middleware
* Expired export pruning behavior

### Storage

By default, generated exports are stored using Laravel's `local` filesystem disk:

```dotenv
EXPORTER_DISK=local
EXPORTER_PATH=exports
```

Any compatible disk configured in `config/filesystems.php` may be used.

### Queue

Exports are processed asynchronously.

You may configure a dedicated queue:

```dotenv
EXPORTER_QUEUE_CONNECTION=redis
EXPORTER_QUEUE=exports
EXPORTER_QUEUE_TRIES=3
EXPORTER_QUEUE_TIMEOUT=3600
```

If `EXPORTER_QUEUE_CONNECTION` is not configured, Laravel's default queue connection is used.

### Chunk Size

Large datasets are processed in chunks to avoid loading the complete result set into memory:

```dotenv
EXPORTER_CHUNK_SIZE=1000
```

Larger values may improve throughput while consuming more memory. Smaller values reduce memory usage but may result in additional database queries.

### Export Lifetime

Completed, failed, and cancelled exports receive an expiration date:

```dotenv
EXPORTER_EXPIRES_AFTER_DAYS=7
```

Expired exports can subsequently be removed using the `exporter:prune` command.

## Creating an Export

Create an export class that implements the `Exportable` contract.

```php
<?php

namespace App\Exports;

use Akika\LaravelExporter\Concerns\InteractsWithExportOptions;
use Akika\LaravelExporter\Contracts\Exportable;
use App\Models\Purchase;
use Illuminate\Database\Eloquent\Builder;

class PurchasesExport implements Exportable
{
    use InteractsWithExportOptions;

    public function query(): Builder
    {
        return Purchase::query()
            ->when(
                $this->option('store_id'),
                fn (Builder $query, $storeId) =>
                    $query->where('store_id', $storeId)
            )
            ->orderBy('id');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Reference',
            'Amount',
            'Created At',
        ];
    }

    public function map(mixed $purchase): array
    {
        return [
            $purchase->id,
            $purchase->reference,
            $purchase->amount,
            $purchase->created_at?->toDateTimeString(),
        ];
    }

    public function validateOptions(): void
    {
        //
    }
}
```

Every exporter implements four core methods:

```text
query()
headings()
map()
validateOptions()
```

`query()` returns the query supplying the export records.

`headings()` defines the output columns.

`map()` transforms each record into an export row.

`validateOptions()` validates options supplied to the export. When an exporter does not require option validation, the method may remain empty.

## Validating Export Options

Export-specific options can be validated before the export is processed.

For example:

```php
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
```

This allows invalid export requests to fail before unnecessary queue work is performed.

## Queueing an Export

Use the `Exporter` facade to create and queue an export:

```php
use Akika\LaravelExporter\Facades\Exporter;
use App\Exports\PurchasesExport;

$export = Exporter::make(PurchasesExport::class)
    ->name('Purchases')
    ->for(auth()->user())
    ->with([
        'store_id' => 12,
    ])
    ->queue();
```

The call returns an `Export` model while the actual file is generated asynchronously by the queue.

The application does not need to keep the HTTP request open while the dataset is exported.

## Export Options

Use `with()` to pass application-specific options to an exporter:

```php
$export = Exporter::make(PurchasesExport::class)
    ->with([
        'store_id' => 12,
        'from' => '2026-09-01',
        'to' => '2026-09-30',
    ])
    ->queue();
```

Inside the exporter, individual options can be accessed using:

```php
$storeId = $this->option('store_id');
```

A default value may also be provided:

```php
$storeId = $this->option(
    'store_id',
    null
);
```

## Associating an Export With an Owner

An export may be associated with an Eloquent model, such as the authenticated user:

```php
$export = Exporter::make(PurchasesExport::class)
    ->for(auth()->user())
    ->queue();
```

The owner is stored using a polymorphic relationship.

This allows applications to retrieve exports belonging to a particular user or other model without the package being coupled to a specific authentication model.

## Unique Exports

Use `unique()` to prevent identical active export requests from being queued multiple times:

```php
$export = Exporter::make(PurchasesExport::class)
    ->for(auth()->user())
    ->with([
        'store_id' => 12,
    ])
    ->unique()
    ->queue();
```

If an identical export is already pending or processing, the existing export is returned instead of creating another export and queue job.

Once that export reaches a terminal state, another identical export may be created.

The fingerprint used for duplicate detection considers the export definition, owner, format, and canonicalized options.

`unique()` provides request idempotency. It is not export-result caching.

## Checking Progress

Refresh the export model to retrieve its latest state:

```php
$export->refresh();

$export->status;
$export->processed_rows;
$export->total_rows;
$export->progress;
$export->remaining_rows;
```

`progress` is calculated from the processed and total row counts rather than being stored as a separate database value.

For example:

```php
if ($export->isProcessing()) {
    echo "{$export->progress}%";
}
```

## Status Helpers

The `Export` model provides convenient lifecycle helpers:

```php
$export->isPending();
$export->isProcessing();
$export->isCompleted();
$export->isFailed();
$export->isCancelled();
$export->isActive();
$export->isFinished();
```

These helpers avoid requiring applications to directly compare enum values throughout their code.

## Querying Exports

Exports can be queried using the provided model scopes.

For example, retrieve exports belonging to the authenticated user:

```php
use Akika\LaravelExporter\Models\Export;

$exports = Export::query()
    ->forOwner(auth()->user())
    ->latest()
    ->paginate();
```

Available status scopes include:

```php
Export::query()->pending();
Export::query()->processing();
Export::query()->active();
Export::query()->completed();
Export::query()->failed();
Export::query()->cancelled();
Export::query()->finished();
Export::query()->expired();
```

Scopes may be combined with normal Eloquent queries:

```php
$exports = Export::query()
    ->forOwner(auth()->user())
    ->completed()
    ->latest()
    ->paginate();
```

## Cancelling an Export

An export that has not reached a terminal state may be cancelled:

```php
$export->cancel();
```

A cancelled export enters the `CANCELLED` state and receives an expiration date.

Queue workers processing exports should respect the cancellation state while processing chunks.

## Downloads

Once an export has completed, a signed download URL can be generated:

```php
$url = $export->downloadUrl();
```

`downloadUrl()` returns `null` when the export is not currently downloadable.

For code that requires a valid download URL, use:

```php
$url = $export->requireDownloadUrl();
```

`requireDownloadUrl()` throws an `ExportNotDownloadableException` when the export cannot be downloaded.

The default signed URL lifetime is configured using:

```dotenv
EXPORTER_DOWNLOAD_URL_EXPIRES_AFTER=15
```

The default download route is protected by the configured route middleware.

## Export Lifecycle

A successful export normally follows this lifecycle:

```text
PENDING
   │
   ▼
PROCESSING
   │
   ▼
COMPLETED
```

An export may instead reach:

```text
FAILED
```

or:

```text
CANCELLED
```

The active states are:

```text
PENDING
PROCESSING
```

The terminal states are:

```text
COMPLETED
FAILED
CANCELLED
```

Terminal exports receive an expiration date and may later be pruned.

## Events

Laravel Exporter dispatches events throughout the export lifecycle.

Available lifecycle events include:

```text
ExportStarted
ExportProgressUpdated
ExportCompleted
ExportFailed
ExportCancelled
```

Applications may listen for these events to implement:

* Browser notifications
* Broadcasting
* Email notifications
* Audit logging
* Application-specific workflows
* UI progress updates

For example:

```php
use Akika\LaravelExporter\Events\ExportCompleted;

Event::listen(
    ExportCompleted::class,
    function (ExportCompleted $event) {
        // Handle completed export.
    }
);
```

The core package does not require Livewire or any particular frontend framework.

## Progress Events

Progress events are emitted according to the configured percentage interval:

```dotenv
EXPORTER_PROGRESS_EVENT_INTERVAL=5
```

With the default value, progress events are emitted approximately when the export advances through:

```text
5%
10%
15%
20%
...
100%
```

This prevents an event from being emitted for every exported row when processing large datasets.

## Pruning Expired Exports

Expired exports can be cleaned up using:

```bash
php artisan exporter:prune
```

The command removes expired export files.

When configured to do so, it also removes the corresponding database records:

```dotenv
EXPORTER_PRUNE_DELETE_RECORDS=true
```

The command additionally performs defensive cleanup of stale export locks.

### Scheduling Pruning

You should run the prune command periodically using Laravel's scheduler.

For example:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('exporter:prune')
    ->daily();
```

Ensure Laravel's scheduler itself is configured to run in your production environment.

## Queue Worker

Exports require a running Laravel queue worker.

For example:

```bash
php artisan queue:work
```

If exports use the dedicated `exports` queue:

```bash
php artisan queue:work --queue=exports
```

Production applications should manage queue workers using an appropriate process supervisor or Laravel Horizon.

The package provides configuration for queue attempts and job timeout:

```dotenv
EXPORTER_QUEUE_TRIES=3
EXPORTER_QUEUE_TIMEOUT=3600
```

Large exports may require an appropriate timeout based on dataset size, database performance, and storage performance.

## Duplicate Request Protection

Calling `unique()` prevents duplicate active export requests.

For example, repeated requests such as:

```text
PurchasesExport
Owner: User #15
Format: CSV
store_id: 12
from: 2026-09-01
to: 2026-09-30
```

produce the same canonical fingerprint.

While an identical export is pending or processing, Laravel Exporter returns the existing export instead of creating another one.

When the export reaches a terminal state, its active lock is released.

This is particularly useful for preventing accidental duplicate exports caused by repeated button clicks or duplicate HTTP requests.

## How It Works

```text
User requests export
        │
        ▼
Exporter::make(...)
        │
        ├── validate exporter
        ├── validate configuration
        ├── validate options
        └── optionally calculate fingerprint
        │
        ▼
Export record
    PENDING
        │
        ▼
ProcessExport job
        │
        ▼
   PROCESSING
        │
        ├── rebuild exporter
        ├── validate options
        ├── execute query
        ├── process records in chunks
        ├── map rows
        ├── write CSV
        └── update progress
        │
        ▼
    COMPLETED
        │
        ▼
Signed download URL
        │
        ▼
    Expiration
        │
        ▼
 exporter:prune
```

If processing fails:

```text
PROCESSING
    │
    ▼
  FAILED
```

If the export is cancelled:

```text
PENDING / PROCESSING
        │
        ▼
    CANCELLED
```

## Security

Generated exports may contain sensitive application data.

Applications are responsible for ensuring that users are authorized to request, view, and download exports.

Laravel Exporter uses signed download URLs, but signed URLs should not be treated as a replacement for application authorization where sensitive data is involved.

The default download route uses:

```php
[
    'web',
    'auth',
]
```

Applications using a different authentication mechanism may publish the configuration and change the route middleware.

Avoid using a publicly accessible filesystem disk for sensitive exports unless public access is intentional.

For sensitive exports:

* Use an appropriate private filesystem disk.
* Keep authentication and authorization checks in place.
* Use short-lived signed download URLs.
* Configure an appropriate export expiration period.
* Schedule `exporter:prune` regularly.
* Avoid exposing another user's export records or download URLs.

## Error Handling

Laravel Exporter provides package-specific exceptions for errors that applications may need to handle.

These include exceptions such as:

```text
ExporterException
InvalidExporterException
ExportConfigurationException
UnsupportedExportFormatException
ExportNotDownloadableException
```

For example:

```php
use Akika\LaravelExporter\Exceptions\ExporterException;

try {
    $export = Exporter::make(PurchasesExport::class)
        ->queue();
} catch (ExporterException $exception) {
    // Handle exporter-specific errors.
}
```

Validation exceptions raised by `validateOptions()` retain Laravel's normal validation behavior.

## Testing

Run the package test suite using:

```bash
composer test
```

Or run PHPUnit directly:

```bash
vendor/bin/phpunit
```

The test suite covers core functionality including:

* Export creation
* CSV generation
* Export options
* Export lifecycle
* Progress calculation
* Lifecycle events
* Cancellation
* Fingerprinting
* Duplicate export prevention
* Lock release
* Owner scopes
* Status scopes
* Expiration
* Pruning
* Configuration validation
* Invalid exporter handling

## Development

Clone the repository and install its dependencies:

```bash
composer install
```

Validate the Composer configuration:

```bash
composer validate --strict
```

Regenerate Composer's autoloader when necessary:

```bash
composer dump-autoload
```

Run the complete test suite:

```bash
composer test
```

Changes should not be committed unless the existing test suite remains green.

## License

Laravel Exporter is open-source software licensed under the MIT license.
