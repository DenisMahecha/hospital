<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionTransfer extends Model
{
    use HasFactory;

    protected $fillable = ['admission_id', 'from_ward_id', 'to_ward_id', 'reason', 'transferred_by', 'transferred_at'];
    protected $casts = ['transferred_at' => 'datetime'];
    public function admission(): BelongsTo { return $this->belongsTo(Admission::class); }
    public function fromWard(): BelongsTo { return $this->belongsTo(Ward::class, 'from_ward_id'); }
    public function toWard(): BelongsTo { return $this->belongsTo(Ward::class, 'to_ward_id'); }
    public function transferredBy(): BelongsTo { return $this->belongsTo(User::class, 'transferred_by'); }
}