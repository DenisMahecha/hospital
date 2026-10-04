<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmergencyCase extends Model
{
    use HasFactory;

    protected $fillable = ['patient_id', 'case_number', 'temporary_name', 'priority', 'complaint', 'status', 'notes', 'registered_by'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}