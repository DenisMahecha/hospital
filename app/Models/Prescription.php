<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prescription extends Model
{
    use HasFactory;

    protected $fillable = ['patient_id', 'prescribed_by', 'visit_id', 'dispensed_drug_id', 'prescription_number', 'medication_name', 'dosage', 'frequency', 'duration', 'quantity_prescribed', 'quantity_dispensed', 'dispensed_at', 'dispensed_by', 'status'];

    protected $casts = ['dispensed_at' => 'datetime'];

    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function prescribedBy(): BelongsTo { return $this->belongsTo(User::class, 'prescribed_by'); }
    public function visit(): BelongsTo { return $this->belongsTo(Visit::class); }
    public function dispensedDrug(): BelongsTo { return $this->belongsTo(Drug::class, 'dispensed_drug_id'); }
    public function dispensedBy(): BelongsTo { return $this->belongsTo(User::class, 'dispensed_by'); }
    public function stockMovements(): HasMany { return $this->hasMany(StockMovement::class); }
}
