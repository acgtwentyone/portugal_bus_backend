<?php

namespace App\Models;

use App\AdminActionEnum;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only: written through AdminAuditLog, never updated or deleted.
 */
class AdminAction extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'admin_email',
        'action',
        'subject_id',
        'payload',
        'ip',
        'created_at',
    ];

    protected $casts = [
        'action' => AdminActionEnum::class,
        'subject_id' => 'integer',
        'payload' => 'array',
    ];
}
