<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class BackupMonitoring extends Model implements AuditableContract
{
    use Auditable;
    use HasFactory;

    protected $table = 'backup_monitoring';

    protected $fillable = [
        'initiated_by_user_id',
        'event',
        'status',
        'disk',
        'path',
        'size_bytes',
        'message',
        'metadata',
        'happened_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'happened_at' => 'datetime',
    ];

    public function initiator()
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }
}
