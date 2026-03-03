<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRow extends Model
{
    protected $fillable = [
        'import_batch_id',
        'row_number',
        'raw_data',
        'mapped_data',
        'status',
        'errors',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'mapped_data' => 'array',
        'errors' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }
}
