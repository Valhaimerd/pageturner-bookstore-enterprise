<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Events\AuditCustom;

class AuditLogger
{
    public function userEvent(?User $user, string $event, array $oldValues = [], array $newValues = [], ?Request $request = null): void
    {
        if (! $user) {
            return;
        }

        $this->log($user, $event, $oldValues, $newValues, $request);
    }

    public function log(AuditableContract $model, string $event, array $oldValues = [], array $newValues = [], ?Request $request = null): void
    {
        $model->auditEvent = $event;
        $model->isCustomEvent = true;
        $model->auditCustomOld = $oldValues;
        $model->auditCustomNew = $newValues;
        $model->preloadedResolverData = array_filter([
            'url' => $request?->fullUrl(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'method' => $request?->method(),
        ]);

        Event::dispatch(new AuditCustom($model));

        $model->auditCustomOld = [];
        $model->auditCustomNew = [];
        $model->preloadedResolverData = [];
        $model->isCustomEvent = false;
    }
}
