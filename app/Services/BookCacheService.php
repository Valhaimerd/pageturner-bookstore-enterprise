<?php

namespace App\Services;

use App\Models\Book;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class BookCacheService
{
    public const CATALOG_TAG = 'books:catalog';

    public const SUMMARY_TAG = 'books:summaries';

    public function rememberCatalogPage(string $key, \DateTimeInterface|\DateInterval|int|null $ttl, callable $callback): mixed
    {
        return $this->remember('catalog', 'catalog:'.$key, [self::CATALOG_TAG], $ttl, $callback);
    }

    public function rememberCategoryCatalog(int|string $categoryId, string $key, \DateTimeInterface|\DateInterval|int|null $ttl, callable $callback): mixed
    {
        $group = $this->categoryGroup($categoryId);

        return $this->remember($group, "{$group}:{$key}", [$group], $ttl, $callback);
    }

    public function rememberCatalog(string $key, \DateTimeInterface|\DateInterval|int|null $ttl, callable $callback): mixed
    {
        return $this->rememberCatalogPage($key, $ttl, $callback);
    }

    public function rememberCategory(int|string $categoryId, string $key, \DateTimeInterface|\DateInterval|int|null $ttl, callable $callback): mixed
    {
        return $this->rememberCategoryCatalog($categoryId, $key, $ttl, $callback);
    }

    public function rememberIsbn(string $isbn, \DateTimeInterface|\DateInterval|int|null $ttl, callable $callback): mixed
    {
        return $this->remember($this->isbnGroup($isbn), $this->isbnKey($isbn), [], $ttl, $callback);
    }

    public function rememberBestsellerSummary(string $key, \DateTimeInterface|\DateInterval|int|null $ttl, callable $callback): mixed
    {
        return $this->remember('summaries', 'summaries:'.$key, [self::SUMMARY_TAG], $ttl, $callback);
    }

    public function invalidateCatalog(): void
    {
        $this->invalidateGroup('catalog', [self::CATALOG_TAG]);
    }

    public function invalidateCategory(int|string|null $categoryId): void
    {
        if ($categoryId === null || $categoryId === '') {
            return;
        }

        $group = $this->categoryGroup($categoryId);

        $this->invalidateGroup($group, [$group]);
    }

    public function invalidateIsbn(?string $isbn): void
    {
        if (! $isbn) {
            return;
        }

        $this->invalidateGroup($this->isbnGroup($isbn), []);
        Cache::store($this->storeName())->forget($this->isbnKey($isbn));
    }

    public function invalidateSummaries(): void
    {
        $this->invalidateGroup('summaries', [self::SUMMARY_TAG]);
    }

    public function invalidateBook(Book $book): void
    {
        $this->invalidateCatalog();
        $this->invalidateCategory($book->category_id);
        $this->invalidateIsbn($book->isbn);
        $this->invalidateSummaries();
    }

    protected function remember(string $group, string $key, array $tags, \DateTimeInterface|\DateInterval|int|null $ttl, callable $callback): mixed
    {
        $store = $this->store($tags);
        $this->trackKey($group, $key);

        return $store->remember($key, $ttl, $callback);
    }

    protected function store(array $tags = []): Repository
    {
        $store = Cache::store($this->storeName());

        return $tags !== [] && $this->supportsTags() ? $store->tags($tags) : $store;
    }

    protected function invalidateGroup(string $group, array $tags): void
    {
        $store = Cache::store($this->storeName());

        if ($tags !== [] && $this->supportsTags()) {
            $store->tags($tags)->flush();

            return;
        }

        foreach ($this->trackedKeys($group) as $key) {
            $store->forget($key);
        }

        $store->forget($this->registryKey($group));
    }

    protected function trackKey(string $group, string $key): void
    {
        if ($this->supportsTags()) {
            return;
        }

        $store = Cache::store($this->storeName());
        $registryKey = $this->registryKey($group);
        $keys = $store->get($registryKey, []);

        if (! in_array($key, $keys, true)) {
            $keys[] = $key;
            $store->forever($registryKey, $keys);
        }
    }

    protected function trackedKeys(string $group): array
    {
        $keys = Cache::store($this->storeName())->get($this->registryKey($group), []);

        return is_array($keys) ? $keys : [];
    }

    protected function supportsTags(): bool
    {
        return in_array(config('cache.stores.'.$this->storeName().'.driver'), ['redis', 'memcached'], true);
    }

    protected function storeName(): string
    {
        return config('cache.catalog_store', config('cache.default'));
    }

    protected function isbnKey(string $isbn): string
    {
        return 'book:isbn:'.$isbn;
    }

    protected function isbnGroup(string $isbn): string
    {
        return 'isbn:'.$isbn;
    }

    protected function categoryGroup(int|string $categoryId): string
    {
        return 'category:'.$categoryId;
    }

    protected function registryKey(string $group): string
    {
        return 'books:cache-registry:'.$group;
    }
}
