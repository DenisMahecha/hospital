<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Bed extends Model
{
    use HasFactory;
    protected $fillable = ['ward_id', 'bed_number', 'room', 'status'];
    public function ward(): BelongsTo { return $this->belongsTo(Ward::class); }
    public function activeAdmission(): HasOne { return $this->hasOne(Admission::class)->where('status', 'admitted'); }
}