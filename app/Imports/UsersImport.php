<?php

namespace App\Imports;

use App\Imports\Concerns\TracksImportProgress;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Events\ImportFailed;
use Maatwebsite\Excel\Validators\Failure;

class UsersImport implements ShouldQueue, SkipsEmptyRows, SkipsOnFailure, ToModel, WithBatchInserts, WithChunkReading, WithEvents, WithHeadingRow, WithValidation
{
    use Importable;
    use SkipsFailures;
    use TracksImportProgress;

    public function __construct(
        int $importLogId,
        protected string $duplicateStrategy = 'skip'
    ) {
        $this->importLogId = $importLogId;
    }

    public function model(array $row): ?User
    {
        try {
            $email = strtolower($this->normalizeText($row['email'] ?? ''));
            $password = $this->normalizeText($row['password'] ?? '');

            $attributes = [
                'name' => $this->normalizeText($row['name'] ?? ''),
                'email' => $email,
                'role' => $this->normalizeText($row['role'] ?? 'customer') ?: 'customer',
                'subscription_tier' => $this->normalizeText($row['subscription_tier'] ?? 'standard') ?: 'standard',
                'phone' => $this->nullableText($row['phone'] ?? null),
                'default_city' => $this->nullableText($row['city'] ?? null),
                'default_province' => $this->nullableText($row['province'] ?? null),
                'default_country' => $this->nullableText($row['country'] ?? null),
                'is_active' => true,
                'password' => Hash::make($password !== '' ? $password : 'password123'),
            ];

            $existing = User::where('email', $email)->first();

            if ($existing) {
                if ($this->duplicateStrategy === 'update_existing') {
                    $existing->fill(collect($attributes)->except('password')->all())->save();
                    $this->markRowSucceeded();

                    return null;
                }

                $this->markRowFailed([
                    'row' => $row,
                    'errors' => ['Duplicate email encountered and duplicate strategy is set to skip.'],
                ]);

                return null;
            }

            $this->markRowSucceeded();

            return new User($attributes);
        } catch (\Throwable $exception) {
            $this->markRowFailed([
                'row' => $row,
                'errors' => [$exception->getMessage()],
            ]);

            return null;
        }
    }

    public function rules(): array
    {
        return [
            '*.name' => ['required', 'string', 'max:255'],
            '*.email' => ['required', 'email', 'max:255'],
            '*.role' => ['required', Rule::in(['admin', 'customer'])],
            '*.subscription_tier' => ['nullable', Rule::in(['standard', 'premium'])],
            '*.phone' => ['nullable', 'max:255'],
            '*.city' => ['nullable', 'string', 'max:255'],
            '*.province' => ['nullable', 'string', 'max:255'],
            '*.country' => ['nullable', 'string', 'max:255'],
            '*.password' => ['nullable', 'string', 'min:8'],
        ];
    }

    public function onFailure(Failure ...$failures): void
    {
        foreach ($failures as $failure) {
            $this->markRowFailed([
                'row' => $failure->row(),
                'attribute' => $failure->attribute(),
                'errors' => $failure->errors(),
                'values' => $failure->values(),
            ]);
        }
    }

    public function batchSize(): int
    {
        return 1000;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => fn () => $this->markProcessing(),
            AfterImport::class => fn () => $this->markCompleted(),
            ImportFailed::class => fn (ImportFailed $event) => $this->markFailed($event->getException()->getMessage()),
        ];
    }

    protected function normalizeText(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    protected function nullableText(mixed $value): ?string
    {
        $value = $this->normalizeText($value);

        return $value === '' ? null : $value;
    }
}
