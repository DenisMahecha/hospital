<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_number',
        'patient_id',
        'requested_by',
        'test_name',
        'status',
        'collected_by',
        'collected_at',
        'processing_started_by',
        'processing_started_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'notes',
        'billing_amount',
        'result',
        'reference_range',
        'completed_by',
        'completed_at',
    ];

    protected $casts = ['collected_at' => 'datetime', 'processing_started_at' => 'datetime', 'rejected_at' => 'datetime', 'completed_at' => 'datetime'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function resultHistories(): HasMany
    {
        return $this->hasMany(LabResultHistory::class);
    }

    public function resultReads(): HasMany
    {
        return $this->hasMany(LabResultRead::class);
    }
}
