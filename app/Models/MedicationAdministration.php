<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicationAdministration extends Model
{
    use HasFactory;

    protected $fillable = ['admission_id', 'prescription_id', 'medication_name', 'dose', 'route', 'scheduled_at', 'administered_at', 'status', 'notes', 'administered_by'];
    protected $casts = ['scheduled_at' => 'datetime', 'administered_at' => 'datetime'];
    public function admission(): BelongsTo { return $this->belongsTo(Admission::class); }
    public function prescription(): BelongsTo { return $this->belongsTo(Prescription::class); }
    public function administeredBy(): BelongsTo { return $this->belongsTo(User::class, 'administered_by'); }
}