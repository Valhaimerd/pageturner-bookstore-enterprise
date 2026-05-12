<?php

namespace Tests\Feature;

use App\Exports\BookImportTemplateExport;
use App\Exports\UserImportTemplateExport;
use App\Models\Book;
use App\Models\Category;
use App\Models\ExportLog;
use App\Models\ImportLog;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DataManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_templates_match_supported_import_headers(): void
    {
        $this->assertSame(
            ['isbn', 'title', 'author', 'price', 'stock', 'category', 'description'],
            BookImportTemplateExport::headers()
        );

        $this->assertSame(
            ['name', 'email', 'role', 'subscription_tier', 'phone', 'city', 'province', 'country', 'password'],
            UserImportTemplateExport::headers()
        );
    }

    public function test_admin_can_download_book_and_profile_import_templates(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this
            ->actingAs($admin)
            ->get(route('admin.data.template.books'))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=book-import-template.xlsx');

        $this
            ->actingAs($admin)
            ->get(route('admin.data.template.users'))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=user-profile-import-template.xlsx');
    }

    public function test_admin_can_import_books_from_csv(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'admin']);
        Category::create([
            'name' => 'Technology',
            'slug' => 'technology',
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'books.csv',
            implode("\n", [
                'isbn,title,author,price,stock,category,description',
                '9789715087228,Domain Driven Design,Eric Evans,1299.00,25,Technology,Sample description',
            ])
        );

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.data.imports.books'), [
                'file' => $file,
                'duplicate_strategy' => 'skip',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('books', [
            'isbn' => '9789715087228',
            'title' => 'Domain Driven Design',
            'stock' => 25,
        ]);

        $this->assertDatabaseHas('import_logs', [
            'type' => 'books',
            'status' => 'completed',
            'total_rows' => 1,
            'processed_rows' => 1,
            'success_rows' => 1,
            'failed_rows' => 0,
        ]);
    }

    public function test_book_import_records_duplicate_failures(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create([
            'name' => 'Technology',
            'slug' => 'technology',
        ]);

        Book::create([
            'category_id' => $category->id,
            'title' => 'Existing Book',
            'slug' => 'existing-book',
            'author' => 'Author',
            'isbn' => '9789715087228',
            'price' => 100,
            'stock' => 5,
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'duplicate-books.csv',
            implode("\n", [
                'isbn,title,author,price,stock,category,description',
                '9789715087228,Domain Driven Design,Eric Evans,1299.00,25,Technology,Sample description',
            ])
        );

        $this
            ->actingAs($admin)
            ->post(route('admin.data.imports.books'), [
                'file' => $file,
                'duplicate_strategy' => 'skip',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $log = ImportLog::firstOrFail();

        $this->assertSame('completed', $log->status);
        $this->assertSame(1, $log->processed_rows);
        $this->assertSame(0, $log->success_rows);
        $this->assertSame(1, $log->failed_rows);
        $this->assertNotNull($log->failure_report_path);
        Storage::disk('local')->assertExists($log->failure_report_path);
    }

    public function test_admin_can_import_users_from_profile_template_headers(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'admin']);

        $file = UploadedFile::fake()->createWithContent(
            'users.csv',
            implode("\n", [
                implode(',', UserImportTemplateExport::headers()),
                'Jane Customer,jane.customer@example.com,customer,standard,+639171234567,Manila,Metro Manila,Philippines,password123',
            ])
        );

        $this
            ->actingAs($admin)
            ->post(route('admin.data.imports.users'), [
                'file' => $file,
                'duplicate_strategy' => 'skip',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'name' => 'Jane Customer',
            'email' => 'jane.customer@example.com',
            'role' => 'customer',
            'subscription_tier' => 'standard',
            'default_city' => 'Manila',
            'default_province' => 'Metro Manila',
            'default_country' => 'Philippines',
        ]);

        $this->assertDatabaseHas('import_logs', [
            'type' => 'users',
            'status' => 'completed',
            'total_rows' => 1,
            'processed_rows' => 1,
            'success_rows' => 1,
            'failed_rows' => 0,
        ]);
    }

    public function test_admin_can_export_books_and_download_the_file(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create([
            'name' => 'Technology',
            'slug' => 'technology',
        ]);

        Book::create([
            'category_id' => $category->id,
            'title' => 'Domain Driven Design',
            'slug' => 'domain-driven-design',
            'author' => 'Eric Evans',
            'isbn' => '9789715087228',
            'price' => 1299,
            'stock' => 25,
        ]);

        $this
            ->actingAs($admin)
            ->post(route('admin.data.exports.books'), [
                'format' => 'csv',
                'category' => 'technology',
                'columns' => ['isbn', 'title', 'author', 'price', 'stock'],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $log = ExportLog::firstOrFail();

        $this->assertSame('completed', $log->status);
        $this->assertSame(1, $log->total_rows);
        $this->assertNotNull($log->path);
        Storage::disk('local')->assertExists($log->path);

        $this
            ->actingAs($admin)
            ->get(route('exports.download', $log))
            ->assertOk();
    }

    public function test_customer_order_export_returns_downloadable_file(): void
    {
        Storage::fake('local');

        $customer = User::factory()->create(['role' => 'customer']);

        Order::create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-1001',
            'receipt_number' => 'RCT-1001',
            'buyer_name' => $customer->name,
            'buyer_email' => $customer->email,
            'address_line_1' => '123 Main St',
            'city' => 'Manila',
            'province' => 'Metro Manila',
            'postal_code' => '1000',
            'country' => 'Philippines',
            'total_amount' => 500,
            'status' => 'completed',
            'payment_status' => 'paid',
            'placed_at' => now(),
        ]);

        $response = $this
            ->actingAs($customer)
            ->from(route('customer.dashboard'))
            ->post(route('customer.exports.orders'), [
                'format' => 'csv',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('customer.dashboard', absolute: false));

        $log = ExportLog::firstOrFail();

        $this->assertSame('customer_orders', $log->type);
        $this->assertSame('completed', $log->status);
        $this->assertSame(1, $log->total_rows);
        Storage::disk('local')->assertExists($log->path);

        $this
            ->actingAs($customer)
            ->get(route('exports.download', $log))
            ->assertOk();

        $this
            ->actingAs($customer)
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('Recent Data Exports')
            ->assertSee(route('exports.download', $log, absolute: false));
    }
}
