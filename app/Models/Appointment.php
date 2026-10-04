<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = ['patient_id', 'booked_by', 'doctor_id', 'appointment_number', 'scheduled_at', 'department', 'reason', 'status', 'cancellation_reason'];

    protected $casts = ['scheduled_at' => 'datetime'];

    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function bookedBy(): BelongsTo { return $this->belongsTo(User::class, 'booked_by'); }
    public function doctor(): BelongsTo { return $this->belongsTo(User::class, 'doctor_id'); }
}
