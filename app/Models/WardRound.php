<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WardRound extends Model
{
    use HasFactory;

    protected $fillable = ['admission_id', 'recorded_by', 'round_date', 'note', 'plan', 'recorded_at'];
    protected $casts = ['round_date' => 'date', 'recorded_at' => 'datetime'];
    public function admission(): BelongsTo { return $this->belongsTo(Admission::class); }
    public function recordedBy(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
}