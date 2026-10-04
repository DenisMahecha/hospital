<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DrugBatch extends Model
{
    use HasFactory;

    protected $fillable = ['drug_id', 'batch_number', 'quantity', 'expiry_date', 'unit_cost', 'status'];

    protected $casts = ['expiry_date' => 'date', 'unit_cost' => 'decimal:2'];

    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}