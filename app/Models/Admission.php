<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Admission extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'ward_id',
        'bed_id',
        'admission_number',
        'admission_date',
        'status',
        'reason',
        'notes',
        'discharge_summary',
        'discharge_condition',
        'discharge_medications',
        'follow_up_date',
        'discharged_by',
        'discharged_at',
    ];

    protected $casts = [
        'admission_date' => 'datetime',
        'follow_up_date' => 'date',
        'discharged_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function bed(): BelongsTo { return $this->belongsTo(Bed::class); }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function nursingNotes(): HasMany
    {
        return $this->hasMany(NursingNote::class);
    }

    public function vitals(): HasMany { return $this->hasMany(AdmissionVital::class); }
    public function medicationAdministrations(): HasMany { return $this->hasMany(MedicationAdministration::class); }
    public function transfers(): HasMany { return $this->hasMany(AdmissionTransfer::class); }
    public function wardRounds(): HasMany { return $this->hasMany(WardRound::class); }
}
