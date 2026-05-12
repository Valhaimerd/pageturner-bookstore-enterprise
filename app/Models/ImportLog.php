<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class ImportLog extends Model implements AuditableContract
{
    use Auditable;
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'filename',
        'disk',
        'path',
        'status',
        'duplicate_strategy',
        'total_rows',
        'processed_rows',
        'success_rows',
        'failed_rows',
        'failure_report_path',
        'metadata',
        'completed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'completed_at' => 'datetime',
    ];

    protected array $auditExclude = ['metadata'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
