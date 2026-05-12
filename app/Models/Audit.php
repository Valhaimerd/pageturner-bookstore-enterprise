<?php

namespace App\Models;

use OwenIt\Auditing\Models\Audit as BaseAudit;

class Audit extends BaseAudit
{
    protected $casts = [
        'old_values' => 'json',
        'new_values' => 'json',
        'archived_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $audit): void {
            $audit->checksum = hash('sha256', json_encode([
                'user_type' => $audit->user_type,
                'user_id' => $audit->user_id,
                'event' => $audit->event,
                'auditable_type' => $audit->auditable_type,
                'auditable_id' => $audit->auditable_id,
                'old_values' => $audit->old_values,
                'new_values' => $audit->new_values,
                'url' => $audit->url,
                'method' => $audit->method,
                'ip_address' => $audit->ip_address,
                'user_agent' => $audit->user_agent,
                'tags' => $audit->tags,
                'created_at' => optional($audit->created_at)->toIso8601String(),
            ], JSON_THROW_ON_ERROR));
        });
    }

    public function getMetadataAttribute(): array
    {
        return [
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'url' => $this->url,
            'method' => $this->method,
            'checksum' => $this->checksum,
        ];
    }
}
