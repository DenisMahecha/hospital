<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeathRecord extends Model
{
    use HasFactory;

    protected $table = 'death_records';

    protected $fillable = [
        'patient_id',
        'date_of_death',
        'time_of_death',
        'cause',
        'location',
        'doctor_name',
        'notes',
    ];

    protected $casts = [
        'date_of_death' => 'date',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
