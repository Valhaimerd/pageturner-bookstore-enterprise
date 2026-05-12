<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LabSevenAdvancedScalabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_refresh_materialized_views_populates_sales_based_category_summary(): void
    {
        $category = Category::create(['name' => 'Technology', 'slug' => 'technology']);
        $unsold = $this->book($category, 'Unsold Active Book', 'unsold-active-book', '9783000002016', 10);
        $sold = $this->book($category, 'Sold Active Book', 'sold-active-book', '9783000002023', 30);
        $inactive = $this->book($category, 'Inactive Sold Book', 'inactive-sold-book', '9783000002030', 50, 'inactive');
        $order = $this->order();

        DB::table('order_items')->insert([
            [
                'order_id' => $order->id,
                'book_id' => $sold->id,
                'book_title' => $sold->title,
                'book_author' => $sold->author,
                'unit_price' => $sold->price,
                'quantity' => 3,
                'line_total' => 300,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => $order->id,
                'book_id' => $inactive->id,
                'book_title' => $inactive->title,
                'book_author' => $inactive->author,
                'unit_price' => $inactive->price,
                'quantity' => 2,
                'line_total' => 200,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->artisan('app:refresh-materialized-views')
            ->expectsOutput('Book materialized summary table refreshed.')
            ->assertSuccessful();

        $this->assertDatabaseHas('mv_bestseller_stats', [
            'category_id' => $category->id,
            'total_books' => 2,
            'total_inventory' => $unsold->stock + $sold->stock,
            'bestseller_count' => 1,
        ]);
    }

    public function test_legacy_books_refresh_materialized_views_alias_still_works(): void
    {
        Category::create(['name' => 'Technology', 'slug' => 'technology']);

        $this->artisan('books:refresh-materialized-views')
            ->expectsOutput('Book materialized summary table refreshed.')
            ->assertSuccessful();
    }

    public function test_hourly_schedule_uses_app_refresh_materialized_views_command(): void
    {
        $schedule = file_get_contents(base_path('routes/console.php'));

        $this->assertStringContainsString("Schedule::command('app:refresh-materialized-views')", $schedule);
        $this->assertStringContainsString('->hourly()', $schedule);
        $this->assertStringContainsString('->withoutOverlapping()', $schedule);
    }

    public function test_database_config_exposes_read_write_hosts_and_optional_persistent_options(): void
    {
        $database = require base_path('config/database.php');

        $this->assertSame(['127.0.0.1'], $database['connections']['mysql']['read']['host']);
        $this->assertSame(['127.0.0.1'], $database['connections']['mysql']['write']['host']);
        $this->assertArrayHasKey('read', $database['connections']['mariadb']);
        $this->assertArrayHasKey('write', $database['connections']['mariadb']);
        $this->assertArrayNotHasKey(\PDO::ATTR_PERSISTENT, $database['connections']['mysql']['options']);
    }

    public function test_partitioning_sql_is_documented_only_and_mysql_scoped(): void
    {
        $sql = file_get_contents(base_path('documentations/mysql8_books_partitioning.sql'));

        $this->assertStringContainsString('DOCUMENTATION ONLY', $sql);
        $this->assertStringContainsString('MySQL 8 only', $sql);
        $this->assertStringContainsString('ALTER TABLE books REMOVE PARTITIONING', $sql);
        $this->assertStringContainsString('unique slug and isbn keys', $sql);
    }

    public function test_benchmark_books_command_reports_all_metrics_and_writes_logs(): void
    {
        File::delete(storage_path('logs/lab7-benchmark.log'));

        $category = Category::create(['name' => 'Technology', 'slug' => 'technology']);

        foreach (range(1, 3) as $index) {
            $this->book(
                $category,
                "Benchmark Book {$index}",
                "benchmark-book-{$index}",
                '9783000003'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                20 + $index
            );
        }

        $exitCode = Artisan::call('benchmark:books', ['--iterations' => 1]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('catalog_listing', $output);
        $this->assertStringContainsString('isbn_lookup', $output);
        $this->assertStringContainsString('category_filter', $output);
        $this->assertStringContainsString('full_text_search', $output);
        $this->assertStringContainsString('export_10k_chunk_simulation', $output);

        $this->assertFileExists(storage_path('logs/lab7-benchmark.log'));
        $this->assertStringContainsString('Lab 7 Book Benchmark', File::get(storage_path('logs/lab7-benchmark.log')));
        $this->assertDatabaseCount('query_performance_logs', 5);
        $this->assertDatabaseHas('query_performance_logs', [
            'benchmark' => 'export_10k_chunk_simulation',
            'passed' => true,
        ]);
    }

    public function test_benchmark_books_command_fails_cleanly_without_active_books(): void
    {
        $exitCode = Artisan::call('benchmark:books', ['--iterations' => 1]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Benchmark requires at least one active book with an ISBN.', Artisan::output());
    }

    protected function book(Category $category, string $title, string $slug, string $isbn, int $stock, string $status = 'active'): Book
    {
        return Book::create([
            'category_id' => $category->id,
            'title' => $title,
            'slug' => $slug,
            'author' => 'Scale Author',
            'isbn' => $isbn,
            'price' => 100,
            'stock' => $stock,
            'status' => $status,
            'published_at' => now(),
        ]);
    }

    protected function order(): Order
    {
        $user = User::factory()->create();

        return Order::create([
            'user_id' => $user->id,
            'order_number' => 'ORD-LAB7-ADVANCED',
            'receipt_number' => 'RCT-LAB7-ADVANCED',
            'buyer_name' => $user->name,
            'buyer_email' => $user->email,
            'address_line_1' => 'Test Address',
            'city' => 'Cagayan de Oro',
            'province' => 'Misamis Oriental',
            'postal_code' => '9000',
            'country' => 'Philippines',
            'subtotal' => 500,
            'total_amount' => 500,
            'status' => 'completed',
            'payment_status' => 'paid',
            'placed_at' => now(),
        ]);
    }
}
