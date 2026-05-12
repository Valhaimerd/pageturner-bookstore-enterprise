<?php

namespace App\Observers;

use App\Models\Book;
use App\Services\BookCacheService;

class BookObserver
{
    public function __construct(protected BookCacheService $cacheService) {}

    public function created(Book $book): void
    {
        $this->cacheService->invalidateCatalog();
        $this->cacheService->invalidateCategory($book->category_id);
        $this->cacheService->invalidateIsbn($book->isbn);
        $this->cacheService->invalidateSummaries();
    }

    public function updated(Book $book): void
    {
        $this->cacheService->invalidateCatalog();
        $this->cacheService->invalidateCategory($book->category_id);
        $this->cacheService->invalidateCategory($book->getOriginal('category_id'));
        $this->cacheService->invalidateIsbn($book->isbn);
        $this->cacheService->invalidateIsbn($book->getOriginal('isbn'));
        $this->cacheService->invalidateSummaries();
    }

    public function deleted(Book $book): void
    {
        $this->cacheService->invalidateBook($book);
    }
}
