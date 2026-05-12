<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UsersExport implements FromQuery, ShouldAutoSize, ShouldQueue, WithHeadings, WithMapping
{
    use Exportable;

    public function __construct(
        protected array $filters = [],
        protected array $columns = [],
        protected bool $redactPii = false
    ) {}

    public static function availableColumns(): array
    {
        return [
            'name' => 'Name',
            'email' => 'Email',
            'role' => 'Role',
            'subscription_tier' => 'Subscription Tier',
            'phone' => 'Phone',
            'default_city' => 'City',
            'default_province' => 'Province',
            'default_country' => 'Country',
            'email_verified_at' => 'Verified At',
            'created_at' => 'Created At',
        ];
    }

    public function headings(): array
    {
        return array_values($this->resolvedColumns());
    }

    public function map($user): array
    {
        $row = [
            'name' => $user->name,
            'email' => $this->redactPii ? $this->maskEmail($user->email) : $user->email,
            'role' => $user->role,
            'subscription_tier' => $user->subscription_tier,
            'phone' => $this->redactPii ? $this->maskPhone($user->phone) : $user->phone,
            'default_city' => $user->default_city,
            'default_province' => $user->default_province,
            'default_country' => $user->default_country,
            'email_verified_at' => optional($user->email_verified_at)->format('Y-m-d H:i:s'),
            'created_at' => optional($user->created_at)->format('Y-m-d H:i:s'),
        ];

        return collect(array_keys($this->resolvedColumns()))
            ->map(fn (string $column) => $row[$column] ?? null)
            ->all();
    }

    public function query(): Builder
    {
        return User::query()
            ->when($this->filters['role'] ?? null, fn (Builder $query, string $role) => $query->where('role', $role))
            ->when($this->filters['verified_only'] ?? false, fn (Builder $query) => $query->whereNotNull('email_verified_at'))
            ->latest();
    }

    protected function resolvedColumns(): array
    {
        $available = static::availableColumns();
        $requested = array_values(array_intersect($this->columns ?: array_keys($available), array_keys($available)));

        return collect($requested)->mapWithKeys(fn (string $column) => [$column => $available[$column]])->all();
    }

    protected function maskEmail(?string $value): ?string
    {
        if (! $value || ! str_contains($value, '@')) {
            return $value;
        }

        [$local, $domain] = explode('@', $value, 2);

        return substr($local, 0, 1).str_repeat('*', max(strlen($local) - 1, 2)).'@'.$domain;
    }

    protected function maskPhone(?string $value): ?string
    {
        if (! $value) {
            return $value;
        }

        return substr($value, 0, 3).str_repeat('*', max(strlen($value) - 5, 2)).substr($value, -2);
    }
}
