<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vital extends Model
{
    use HasFactory;

    protected $fillable = ['visit_id', 'recorded_by', 'temperature', 'blood_pressure', 'weight', 'heart_rate', 'respiratory_rate'];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }
}