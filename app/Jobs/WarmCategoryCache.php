<?php

namespace App\Jobs;

use App\Repositories\BookRepository;
use App\Services\BookCacheService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class WarmCategoryCache implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $categoryId,
        public int $limit = 1000
    ) {}

    public function handle(BookRepository $books, BookCacheService $cache): void
    {
        $cache->rememberCategory(
            $this->categoryId,
            "popular:{$this->limit}",
            now()->addHours(2),
            fn () => $books->activeCategorySample($this->categoryId, $this->limit)
        );
    }
}
