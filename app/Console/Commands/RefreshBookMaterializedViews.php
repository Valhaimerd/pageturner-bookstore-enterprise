<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RefreshBookMaterializedViews extends Command
{
    protected $signature = 'app:refresh-materialized-views';

    protected $aliases = ['books:refresh-materialized-views'];

    protected $description = 'Refresh Lab 7 precomputed bestseller and inventory summaries.';

    public function handle(): int
    {
        if (! Schema::hasTable('mv_bestseller_stats')) {
            $this->error('The mv_bestseller_stats table does not exist. Run migrations first.');

            return self::FAILURE;
        }

        DB::transaction(function (): void {
            DB::table('mv_bestseller_stats')->delete();

            $salesByCategory = DB::table('order_items')
                ->join('books as sold_books', 'sold_books.id', '=', 'order_items.book_id')
                ->select('sold_books.category_id')
                ->selectRaw('COUNT(DISTINCT order_items.book_id) as bestseller_count')
                ->where('sold_books.status', 'active')
                ->whereNotNull('order_items.book_id')
                ->where('order_items.quantity', '>', 0)
                ->groupBy('sold_books.category_id');

            DB::table('books')
                ->leftJoinSub($salesByCategory, 'sales', function ($join): void {
                    $join->on('sales.category_id', '=', 'books.category_id');
                })
                ->selectRaw('books.category_id')
                ->selectRaw('COUNT(books.id) as total_books')
                ->selectRaw('AVG(books.price) as avg_price')
                ->selectRaw('SUM(books.stock) as total_inventory')
                ->selectRaw('COALESCE(MAX(sales.bestseller_count), 0) as bestseller_count')
                ->selectRaw('MAX(books.published_at) as latest_publication')
                ->where('books.status', 'active')
                ->groupBy('books.category_id')
                ->orderBy('books.category_id')
                ->cursor()
                ->each(function (object $row): void {
                    DB::table('mv_bestseller_stats')->insert([
                        'category_id' => $row->category_id,
                        'total_books' => (int) $row->total_books,
                        'avg_price' => round((float) $row->avg_price, 2),
                        'total_inventory' => (int) $row->total_inventory,
                        'bestseller_count' => (int) $row->bestseller_count,
                        'latest_publication' => $row->latest_publication,
                        'refreshed_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });
        });

        $this->info('Book materialized summary table refreshed.');

        return self::SUCCESS;
    }
}
