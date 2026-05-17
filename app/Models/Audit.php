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
            $audit->checksum = static::checksumFor($audit);
        });

        static::updating(function (self $audit): bool {
            $allowed = ['archived_at', 'updated_at'];

            return collect(array_keys($audit->getDirty()))
                ->every(fn (string $attribute) => in_array($attribute, $allowed, true));
        });

        static::deleting(function (): bool {
            return false;
        });
    }

    public static function checksumFor(self $audit): string
    {
        return hash('sha256', json_encode([
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
        ], JSON_THROW_ON_ERROR));
    }

    public function hasValidChecksum(): bool
    {
        return hash_equals((string) $this->checksum, static::checksumFor($this));
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
