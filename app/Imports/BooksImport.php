<?php

namespace App\Imports;

use App\Imports\Concerns\TracksImportProgress;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;
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

class BooksImport implements ShouldQueue, SkipsEmptyRows, SkipsOnFailure, ToModel, WithBatchInserts, WithChunkReading, WithEvents, WithHeadingRow, WithValidation
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

    public function model(array $row): ?Book
    {
        try {
            $category = $this->resolveCategory($row['category'] ?? '');
            $isbn = $this->normalizeIsbn($row['isbn'] ?? null);

            $attributes = [
                'category_id' => $category->id,
                'title' => trim((string) ($row['title'] ?? '')),
                'slug' => $this->makeSlug((string) ($row['title'] ?? ''), $isbn),
                'author' => trim((string) ($row['author'] ?? '')),
                'isbn' => $isbn,
                'description' => $row['description'] ?: null,
                'price' => (float) $row['price'],
                'stock' => (int) $row['stock'],
                'status' => 'active',
            ];

            $existing = $isbn ? Book::where('isbn', $isbn)->first() : null;

            if ($existing) {
                if ($this->duplicateStrategy === 'update_existing') {
                    $existing->update($attributes);
                    $this->markRowSucceeded();

                    return null;
                }

                $this->markRowFailed([
                    'row' => $row,
                    'errors' => ['Duplicate ISBN encountered and duplicate strategy is set to skip.'],
                ]);

                return null;
            }

            $this->markRowSucceeded();

            return new Book($attributes);
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
            '*.isbn' => [
                'required',
                'regex:/^(97(8|9))?\d{9}(\d|X)$/i',
            ],
            '*.title' => ['required', 'string', 'max:255'],
            '*.author' => ['required', 'string', 'max:255'],
            '*.price' => ['required', 'numeric', 'min:0.01', 'max:9999.99'],
            '*.stock' => ['required', 'integer', 'min:0'],
            '*.category' => [
                'required',
                'string',
                Rule::exists('categories', 'name'),
            ],
            '*.description' => ['nullable', 'string'],
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

    protected function resolveCategory(string $value): Category
    {
        return Category::where('name', trim($value))
            ->orWhere('slug', Str::slug($value))
            ->firstOrFail();
    }

    protected function makeSlug(string $title, ?string $isbn): string
    {
        $base = Str::slug($title) ?: 'book';
        $slug = $isbn ? Str::lower(str_replace(' ', '-', $isbn)) : $base;

        if (! Book::where('slug', $slug)->exists()) {
            return $slug;
        }

        $counter = 1;

        while (Book::where('slug', "{$base}-{$counter}")->exists()) {
            $counter++;
        }

        return "{$base}-{$counter}";
    }

    protected function normalizeIsbn(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return trim((string) $value);
    }
}
