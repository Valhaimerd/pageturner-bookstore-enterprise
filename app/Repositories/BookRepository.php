<?php

namespace App\Repositories;

use App\Models\Book;
use App\Models\Category;
use App\Services\BookCacheService;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class BookRepository
{
    public function __construct(protected BookCacheService $cache) {}

    public function catalogCursor(array $filters = [], int $perPage = 12): CursorPaginator
    {
        $perPage = max(1, min($perPage, 100));

        return $this->rememberCatalogResult('web', $filters, $perPage, function () use ($filters, $perPage) {
            return $this->catalogCardQuery()
                ->with('category:id,name,slug')
                ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $this->applyCategorySlugFilter($query, $category))
                ->orderBy('published_at', 'desc')
                ->orderBy('id', 'desc')
                ->cursorPaginate($perPage)
                ->withQueryString();
        });
    }

    public function apiCursorCatalog(array $filters = [], int $perPage = 20): CursorPaginator
    {
        $perPage = max(1, min($perPage, 100));

        return $this->rememberCatalogResult('api', $filters, $perPage, function () use ($filters, $perPage) {
            return $this->apiListQuery()
                ->with('category:id,name,slug')
                ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $this->applySearch($query, $search))
                ->when($filters['category_id'] ?? null, fn (Builder $query, int|string $categoryId) => $query->where('category_id', $categoryId))
                ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $this->applyCategorySlugFilter($query, $category))
                ->orderBy('published_at', 'desc')
                ->orderBy('id', 'desc')
                ->cursorPaginate($perPage)
                ->withQueryString();
        });
    }

    public function cursorCatalog(array $filters = [], int $perPage = 20): CursorPaginator
    {
        return $this->apiCursorCatalog($filters, $perPage);
    }

    public function categoryCursorCatalog(string $slug, int $perPage = 20): CursorPaginator
    {
        return $this->apiCursorCatalog(['category' => $slug], $perPage);
    }

    public function findActiveBySlug(string $slug): ?Book
    {
        return Book::query()
            ->select($this->detailColumns())
            ->with([
                'category:id,name,slug',
                'reviews' => fn ($query) => $query->where('is_visible', true)->latest(),
                'reviews.user:id,name',
            ])
            ->where('status', 'active')
            ->where('slug', $slug)
            ->first();
    }

    public function findActiveByIsbn(string $isbn): ?Book
    {
        return $this->cache->rememberIsbn($isbn, now()->addMinutes(30), fn () => Book::query()
            ->select($this->listColumns())
            ->with('category:id,name,slug')
            ->where('status', 'active')
            ->where('isbn', $isbn)
            ->first());
    }

    public function activeCategorySample(int|string $categoryId, int $limit = 1000): Collection
    {
        return Book::query()
            ->select(['id', 'title', 'author', 'price', 'stock', 'published_at', 'category_id'])
            ->where('status', 'active')
            ->where('category_id', $categoryId)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function bestsellerInventorySummaries(): \Illuminate\Support\Collection
    {
        return $this->cache->rememberBestsellerSummary('all', now()->addMinutes(10), function () {
            if (! Schema::hasTable('mv_bestseller_stats')) {
                return collect();
            }

            return DB::table('mv_bestseller_stats')
                ->join('categories', 'categories.id', '=', 'mv_bestseller_stats.category_id')
                ->select([
                    'mv_bestseller_stats.category_id',
                    'categories.name as category_name',
                    'categories.slug as category_slug',
                    'mv_bestseller_stats.total_books',
                    'mv_bestseller_stats.avg_price',
                    'mv_bestseller_stats.total_inventory',
                    'mv_bestseller_stats.bestseller_count',
                    'mv_bestseller_stats.latest_publication',
                    'mv_bestseller_stats.refreshed_at',
                ])
                ->orderBy('categories.name')
                ->get();
        });
    }

    public function benchmarkCatalog(int $perPage = 100): CursorPaginator
    {
        return $this->apiCursorCatalog([], $perPage);
    }

    public function benchmarkCategory(int $categoryId, int $perPage = 100): CursorPaginator
    {
        return $this->apiCursorCatalog(['category_id' => $categoryId], $perPage);
    }

    public function search(string $term, int $perPage = 20, int|string|null $category = null): CursorPaginator
    {
        $filters = ['search' => $term];

        if ($category !== null) {
            $filters[is_numeric($category) ? 'category_id' : 'category'] = $category;
        }

        return $this->apiCursorCatalog($filters, $perPage);
    }

    public function recommendationCandidates(array $intent, string $prompt, int $limit = 12): Collection
    {
        $limit = max(1, min($limit, 50));
        $search = trim(implode(' ', array_filter([
            $intent['topic'] ?? null,
            $intent['learning_goal'] ?? null,
            $prompt,
        ])));

        $query = Book::query()
            ->select($this->detailColumns())
            ->with('category:id,name,slug')
            ->withAvg(['reviews' => fn ($query) => $query->where('is_visible', true)], 'rating')
            ->where('status', 'active')
            ->where('stock', '>', 0)
            ->when($intent['category'] ?? null, function (Builder $query, string $category) {
                $query->whereHas('category', function (Builder $categoryQuery) use ($category) {
                    $categoryQuery
                        ->where('is_active', true)
                        ->where(function (Builder $inner) use ($category) {
                            $inner->where('slug', $category)
                                ->orWhere('name', 'like', "%{$category}%");
                        });
                });
            })
            ->when($intent['format'] ?? null, fn (Builder $query, string $format) => $query->where('format', $format));

        if ($search !== '') {
            $this->applySearch($query, $search);
        }

        $candidates = $query
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        if ($candidates->isNotEmpty()) {
            return $candidates;
        }

        return Book::query()
            ->select($this->detailColumns())
            ->with('category:id,name,slug')
            ->withAvg(['reviews' => fn ($query) => $query->where('is_visible', true)], 'rating')
            ->where('status', 'active')
            ->where('stock', '>', 0)
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function resolveActiveCategoryIdBySlug(string $slug): ?int
    {
        return Category::query()
            ->where('is_active', true)
            ->where('slug', $slug)
            ->value('id');
    }

    protected function catalogCardQuery(): Builder
    {
        return Book::query()
            ->select([
                'id',
                'category_id',
                'slug',
                'title',
                'author',
                'price',
                'stock',
                'cover_image',
                'published_at',
                'status',
            ])
            ->where('status', 'active');
    }

    protected function apiListQuery(): Builder
    {
        return Book::query()
            ->select($this->listColumns())
            ->where('status', 'active');
    }

    protected function rememberCatalogResult(string $scope, array $filters, int $perPage, callable $callback): CursorPaginator
    {
        $key = $this->pageCacheKey($scope, $filters, $perPage);

        if (($filters['category_id'] ?? null) !== null) {
            return $this->cache->rememberCategoryCatalog($filters['category_id'], $key, now()->addMinutes(5), $callback);
        }

        if (($filters['category'] ?? null) !== null) {
            $categoryId = $this->resolveActiveCategoryIdBySlug((string) $filters['category']);

            return $this->cache->rememberCategoryCatalog(
                $categoryId ?: 'missing:'.$filters['category'],
                $key,
                now()->addMinutes(5),
                $callback
            );
        }

        return $this->cache->rememberCatalogPage($key, now()->addMinutes(5), $callback);
    }

    protected function applyCategorySlugFilter(Builder $query, string $slug): Builder
    {
        $categoryId = $this->resolveActiveCategoryIdBySlug($slug);

        return $categoryId ? $query->where('category_id', $categoryId) : $query->whereRaw('1 = 0');
    }

    protected function applySearch(Builder $query, string $search): Builder
    {
        $search = trim($search);

        if ($search === '') {
            return $query;
        }

        $normalizedIsbn = $this->normalizeIsbnSearch($search);

        if ($normalizedIsbn !== null) {
            return $query->where('isbn', $normalizedIsbn);
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return $query->where(function (Builder $inner) use ($search) {
                $inner->whereFullText(['title', 'description'], $search)
                    ->orWhere('author', 'like', "%{$search}%")
                    ->orWhere('isbn', 'like', "%{$search}%");
            });
        }

        if (! in_array(config('scout.driver'), ['null', 'collection'], true)) {
            try {
                $ids = Book::search($search)->take(5000)->keys()->all();

                if ($ids !== []) {
                    return $query->whereIn('id', $ids);
                }
            } catch (Throwable) {
                // Fall back to portable SQL below when Scout is unavailable for the active driver.
            }
        }

        return $query->where(function (Builder $inner) use ($search) {
            $inner->where('title', 'like', "%{$search}%")
                ->orWhere('author', 'like', "%{$search}%")
                ->orWhere('isbn', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    protected function normalizeIsbnSearch(string $search): ?string
    {
        $normalized = str_replace(['-', ' '], '', $search);

        return preg_match('/^(97[89])?\d{9}(\d|X)$/i', $normalized) === 1 ? $normalized : null;
    }

    protected function pageCacheKey(string $scope, array $filters, int $perPage): string
    {
        ksort($filters);

        return $scope.':'.sha1(json_encode([
            'filters' => $filters,
            'per_page' => $perPage,
            'cursor' => request()->query('cursor'),
        ]));
    }

    protected function listColumns(): array
    {
        return [
            'id',
            'category_id',
            'slug',
            'title',
            'author',
            'publisher',
            'format',
            'isbn',
            'price',
            'stock',
            'status',
            'published_at',
            'created_at',
        ];
    }

    protected function detailColumns(): array
    {
        return array_merge($this->listColumns(), ['description', 'cover_image', 'is_featured']);
    }
}
