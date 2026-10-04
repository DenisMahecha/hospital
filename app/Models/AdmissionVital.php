<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionVital extends Model
{
    use HasFactory;

    protected $fillable = ['admission_id', 'recorded_by', 'temperature', 'blood_pressure', 'pulse', 'respiratory_rate', 'oxygen_saturation', 'weight', 'consciousness', 'recorded_at'];
    protected $casts = ['recorded_at' => 'datetime'];
    public function admission(): BelongsTo { return $this->belongsTo(Admission::class); }
    public function recordedBy(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
}