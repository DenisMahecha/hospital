<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TheatreSchedule extends Model
{
    use HasFactory;

    protected $table = 'theatre_schedules';

    protected $fillable = [
        'surgery_name',
        'scheduled_date',
        'theatre_room',
        'status',
    ];

    protected $casts = [
        'scheduled_date' => 'datetime',
    ];
}
