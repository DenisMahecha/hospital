<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashierReconciliation extends Model
{
    protected $fillable = ['cashier_id', 'business_date', 'system_total', 'declared_total', 'variance', 'notes'];
    protected $casts = ['business_date' => 'date', 'system_total' => 'decimal:2', 'declared_total' => 'decimal:2', 'variance' => 'decimal:2'];

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }
}