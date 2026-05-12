<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class ExportLog extends Model implements AuditableContract
{
    use Auditable;
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'format',
        'status',
        'disk',
        'path',
        'total_rows',
        'filters',
        'columns',
        'completed_at',
        'expires_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'columns' => 'array',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
