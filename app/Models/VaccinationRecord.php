<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VaccinationRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'child_name',
        'date_of_birth',
        'vaccine_name',
        'dose_number',
        'status',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];
}
