<?php

namespace App\Exports;

use App\Models\Book;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class BooksExport implements FromQuery, ShouldAutoSize, ShouldQueue, WithChunkReading, WithHeadings, WithMapping
{
    use Exportable;

    public function __construct(
        protected array $filters = [],
        protected array $columns = []
    ) {}

    public static function availableColumns(): array
    {
        return [
            'isbn' => 'ISBN',
            'title' => 'Title',
            'author' => 'Author',
            'category' => 'Category',
            'price' => 'Price',
            'stock' => 'Stock',
            'status' => 'Status',
            'published_at' => 'Published At',
            'created_at' => 'Created At',
        ];
    }

    public function headings(): array
    {
        return array_values($this->resolvedColumns());
    }

    public function map($book): array
    {
        $row = [
            'isbn' => $book->isbn,
            'title' => $book->title,
            'author' => $book->author,
            'category' => $book->category?->name,
            'price' => number_format((float) $book->price, 2, '.', ''),
            'stock' => $book->stock,
            'status' => $book->status,
            'published_at' => optional($book->published_at)->format('Y-m-d'),
            'created_at' => optional($book->created_at)->format('Y-m-d H:i:s'),
        ];

        return collect(array_keys($this->resolvedColumns()))
            ->map(fn (string $column) => $row[$column] ?? null)
            ->all();
    }

    public function query(): Builder
    {
        return Book::query()
            ->select(['id', 'category_id', 'isbn', 'title', 'author', 'price', 'stock', 'status', 'published_at', 'created_at'])
            ->with('category:id,name')
            ->when($this->filters['category'] ?? null, fn (Builder $query, string $category) => $query->whereHas('category', fn (Builder $inner) => $inner->where('slug', $category)))
            ->when($this->filters['price_min'] ?? null, fn (Builder $query, $value) => $query->where('price', '>=', $value))
            ->when($this->filters['price_max'] ?? null, fn (Builder $query, $value) => $query->where('price', '<=', $value))
            ->when($this->filters['stock_status'] ?? null, function (Builder $query, string $stockStatus) {
                return match ($stockStatus) {
                    'in_stock' => $query->where('stock', '>', 0),
                    'out_of_stock' => $query->where('stock', 0),
                    'low_stock' => $query->whereBetween('stock', [1, 10]),
                    default => $query,
                };
            })
            ->when($this->filters['date_from'] ?? null, fn (Builder $query, $value) => $query->whereDate('created_at', '>=', $value))
            ->when($this->filters['date_to'] ?? null, fn (Builder $query, $value) => $query->whereDate('created_at', '<=', $value))
            ->orderBy('id');
    }

    public function chunkSize(): int
    {
        return 2000;
    }

    protected function resolvedColumns(): array
    {
        $available = static::availableColumns();
        $requested = array_values(array_intersect($this->columns ?: array_keys($available), array_keys($available)));

        return collect($requested)->mapWithKeys(fn (string $column) => [$column => $available[$column]])->all();
    }
}
