<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class AuditLog extends Model
{
    use HasFactory;

    public $timestamps = true;

    protected $table = 'audit_logs';

    protected $fillable = [
        'user_id',
        'user_type',
        'action',
        'module_name',
        'model_type',
        'model_id',
        'description',
        'ip_address',
        'user_agent',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $auditLog): bool {
            return Schema::hasTable($auditLog->getTable());
        });

        static::deleting(function (): bool {
            return false;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
