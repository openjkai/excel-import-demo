<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $fillable = [
        'filename',
        'original_filename',
        'status',
        'detected_headers',
        'column_mapping',
        'period_start',
        'period_end',
        'total_rows',
        'valid_rows',
        'error_rows',
        'duplicate_rows',
        'validation_errors',
        'committed_at',
    ];

    protected $casts = [
        'detected_headers' => 'array',
        'column_mapping' => 'array',
        'validation_errors' => 'array',
        'period_start' => 'date',
        'period_end' => 'date',
        'committed_at' => 'datetime',
    ];

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }

    public function financialRecords(): HasMany
    {
        return $this->hasMany(FinancialRecord::class);
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
