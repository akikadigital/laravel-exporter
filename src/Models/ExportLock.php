<?php

namespace Akika\LaravelExporter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportLock extends Model
{
    protected $fillable = [
        'fingerprint',
        'export_id',
    ];

    public function export(): BelongsTo
    {
        return $this->belongsTo(
            Export::class
        );
    }
}
