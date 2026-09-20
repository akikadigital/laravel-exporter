<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | The Laravel filesystem disk used to store generated export files.
    | Any disk configured in config/filesystems.php may be used, including
    | local, S3, or a custom filesystem disk.
    |
    */

    'disk' => env(
        'EXPORTER_DISK',
        'local'
    ),

    /*
    |--------------------------------------------------------------------------
    | Export Path
    |--------------------------------------------------------------------------
    |
    | The base directory within the configured filesystem disk where export
    | files will be stored.
    |
    */

    'path' => env(
        'EXPORTER_PATH',
        'exports'
    ),

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Exports are generated asynchronously using Laravel's queue system.
    | You may specify a dedicated queue connection and queue name for export
    | jobs, allowing them to run independently from other application jobs.
    |
    */

    'queue' => [

        /*
        | Queue Connection
        |
        | When null, Laravel's default queue connection will be used.
        */

        'connection' => env(
            'EXPORTER_QUEUE_CONNECTION'
        ),

        /*
        | Queue Name
        |
        | The queue onto which export jobs will be dispatched.
        */

        'name' => env(
            'EXPORTER_QUEUE',
            'exports'
        ),

        /*
        | Maximum Attempts
        |
        | The maximum number of times an export job may be attempted before
        | Laravel considers it failed.
        */

        'tries' => (int) env(
            'EXPORTER_QUEUE_TRIES',
            3
        ),

        /*
        | Job Timeout
        |
        | Maximum number of seconds an export job may run before the queue
        | worker considers it timed out.
        |
        | Large exports may require a higher timeout depending on the size
        | of the dataset and the performance of the storage destination.
        */

        'timeout' => (int) env(
            'EXPORTER_QUEUE_TIMEOUT',
            3600
        ),

    ],

    /*
    |--------------------------------------------------------------------------
    | Chunk Size
    |--------------------------------------------------------------------------
    |
    | The number of database records processed at a time while generating an
    | export. Chunking prevents large datasets from being loaded entirely
    | into memory.
    |
    | Larger values may improve throughput but increase memory usage. Smaller
    | values reduce memory usage at the cost of additional database queries.
    |
    */

    'chunk_size' => (int) env(
        'EXPORTER_CHUNK_SIZE',
        1000
    ),

    /*
    |--------------------------------------------------------------------------
    | Export Lifetime
    |--------------------------------------------------------------------------
    |
    | Number of days an export remains available after reaching a terminal
    | state. Completed, failed, and cancelled exports receive an expiration
    | date based on this value.
    |
    | Expired exports may subsequently be removed by the exporter:prune
    | command.
    |
    */

    'expires_after_days' => (int) env(
        'EXPORTER_EXPIRES_AFTER_DAYS',
        7
    ),

    /*
    |--------------------------------------------------------------------------
    | Progress Tracking
    |--------------------------------------------------------------------------
    |
    | Configure how progress updates are emitted while an export is being
    | processed.
    |
    */

    'progress' => [

        /*
        | Event Interval
        |
        | Progress events are emitted when the export percentage advances by
        | at least this amount. For example, a value of 5 results in updates
        | around 5%, 10%, 15%, and so on.
        |
        | Increasing this value reduces the number of progress events emitted
        | for large exports.
        */

        'event_interval' => (int) env(
            'EXPORTER_PROGRESS_EVENT_INTERVAL',
            5
        ),

    ],

    /*
    |--------------------------------------------------------------------------
    | Downloads
    |--------------------------------------------------------------------------
    |
    | Configure how generated export files are exposed for download.
    |
    */

    'downloads' => [

        /*
        | Download Route
        |
        | The named route used when generating signed download URLs.
        */

        'route' => 'exports.download',

        /*
        | Signed URL Lifetime
        |
        | Number of minutes a generated signed download URL remains valid.
        | Applications may override this value when generating a URL when
        | supported by the download API.
        */

        'url_expires_after' => (int) env(
            'EXPORTER_DOWNLOAD_URL_EXPIRES_AFTER',
            15
        ),

    ],

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the HTTP route used to serve export downloads.
    |
    | The default middleware requires both a web session and an authenticated
    | user. Applications exposing exports through a different authentication
    | mechanism may publish this configuration and change the middleware.
    |
    */

    'route' => [

        /*
        | Route Prefix
        */

        'prefix' => env(
            'EXPORTER_ROUTE_PREFIX',
            'exports'
        ),

        /*
        | Route Middleware
        |
        | Keep appropriate authentication/authorization protection in place
        | when export files may contain sensitive application data.
        */

        'middleware' => [
            'web',
            'auth',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Pruning
    |--------------------------------------------------------------------------
    |
    | Configure cleanup behavior for exports whose expiration date has passed.
    | Run the exporter:prune command periodically using Laravel's scheduler.
    |
    */

    /*
|--------------------------------------------------------------------------
| Broadcasting
|--------------------------------------------------------------------------
|
| Laravel Exporter can optionally broadcast export lifecycle events to
| private owner-specific channels. This allows frontend applications to
| receive real-time progress, completion, failure, and cancellation updates.
|
| Broadcasting is disabled by default. The consuming application is
| responsible for configuring Laravel broadcasting and authorizing the
| generated private channels.
|
*/

    'broadcasting' => [

        /*
    | Enable Broadcasting
    */

        'enabled' => (bool) env(
            'EXPORTER_BROADCASTING_ENABLED',
            false
        ),

        /*
    | Channel Prefix
    |
    | Owner-specific channels are generated using this prefix.
    |
    | Example:
    |
    | exports.App.Models.User.15
    |
    */

        'channel_prefix' => env(
            'EXPORTER_BROADCAST_CHANNEL_PREFIX',
            'exports'
        ),

    ],

    'prune' => [

        /*
        | Delete Database Records
        |
        | When enabled, an expired export's database record is deleted after
        | its generated file has been successfully removed.
        |
        | When disabled, the file is removed but the export record is retained
        | for applications that require historical export metadata.
        */

        'delete_records' => (bool) env(
            'EXPORTER_PRUNE_DELETE_RECORDS',
            true
        ),

    ],

];
