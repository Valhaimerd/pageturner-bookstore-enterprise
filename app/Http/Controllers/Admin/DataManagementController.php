<?php

namespace App\Http\Controllers\Admin;

use App\Exports\BookImportTemplateExport;
use App\Exports\BooksExport;
use App\Exports\FinancialReportExport;
use App\Exports\OrdersExport;
use App\Exports\UserImportTemplateExport;
use App\Exports\UsersExport;
use App\Http\Controllers\Controller;
use App\Imports\BooksImport;
use App\Imports\UsersImport;
use App\Jobs\CompleteExportLog;
use App\Models\Audit;
use App\Models\BackupMonitoring;
use App\Models\Category;
use App\Models\ExportLog;
use App\Models\ImportLog;
use App\Models\Order;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class DataManagementController extends Controller
{
    public function index()
    {
        $apiStats = DB::table('api_rate_limit_hits')
            ->selectRaw('endpoint, count(*) as total_requests')
            ->selectRaw('sum(case when '.$this->booleanColumnIsTrueSql('throttled').' then 1 else 0 end) as throttled_requests')
            ->groupBy('endpoint')
            ->orderByDesc('total_requests')
            ->limit(5)
            ->get();

        return view('admin.data-management.index', [
            'categories' => Category::orderBy('name')->get(),
            'customers' => User::where('role', 'customer')->orderBy('name')->get(['id', 'name']),
            'importLogs' => ImportLog::latest()->limit(8)->get(),
            'exportLogs' => ExportLog::latest()->limit(8)->get(),
            'backupLogs' => BackupMonitoring::latest('happened_at')->limit(5)->get(),
            'recentAudits' => Audit::with('user')->latest()->limit(6)->get(),
            'apiStats' => $apiStats,
            'systemHealth' => [
                'database_size' => $this->databaseSize(),
                'storage_usage' => $this->directorySize(storage_path('app/private')),
                'pending_jobs' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0,
                'failed_jobs' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
            ],
            'bookColumns' => BooksExport::availableColumns(),
            'orderColumns' => OrdersExport::availableColumns(),
            'userColumns' => UsersExport::availableColumns(),
        ]);
    }

    public function bookTemplate()
    {
        return Excel::download(new BookImportTemplateExport, 'book-import-template.xlsx');
    }

    public function userTemplate()
    {
        return Excel::download(new UserImportTemplateExport, 'user-profile-import-template.xlsx');
    }

    public function importBooks(Request $request)
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt'],
            'duplicate_strategy' => ['required', 'in:skip,update_existing'],
        ]);

        $storedPath = $request->file('file')->store('imports/books', 'local');
        $absolutePath = Storage::disk('local')->path($storedPath);

        $log = ImportLog::create([
            'user_id' => $request->user()->id,
            'type' => 'books',
            'filename' => $request->file('file')->getClientOriginalName(),
            'disk' => 'local',
            'path' => $storedPath,
            'status' => 'queued',
            'duplicate_strategy' => $validated['duplicate_strategy'],
            'total_rows' => $this->countSpreadsheetRows($absolutePath),
        ]);

        if ($missingHeaders = $this->missingSpreadsheetHeaders($absolutePath, BookImportTemplateExport::headers())) {
            $this->failImportForMissingHeaders($log, $missingHeaders);

            return back()->withErrors([
                'file' => 'Book import is missing required headers: '.implode(', ', $missingHeaders),
            ]);
        }

        Excel::queueImport(new BooksImport($log->id, $validated['duplicate_strategy']), $storedPath, 'local');

        return back()->with('success', 'Book import has been queued.');
    }

    public function importUsers(Request $request)
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt'],
            'duplicate_strategy' => ['required', 'in:skip,update_existing'],
        ]);

        $storedPath = $request->file('file')->store('imports/users', 'local');
        $absolutePath = Storage::disk('local')->path($storedPath);

        $log = ImportLog::create([
            'user_id' => $request->user()->id,
            'type' => 'users',
            'filename' => $request->file('file')->getClientOriginalName(),
            'disk' => 'local',
            'path' => $storedPath,
            'status' => 'queued',
            'duplicate_strategy' => $validated['duplicate_strategy'],
            'total_rows' => $this->countSpreadsheetRows($absolutePath),
        ]);

        if ($missingHeaders = $this->missingSpreadsheetHeaders($absolutePath, UserImportTemplateExport::headers())) {
            $this->failImportForMissingHeaders($log, $missingHeaders);

            return back()->withErrors([
                'file' => 'User import is missing required headers: '.implode(', ', $missingHeaders),
            ]);
        }

        Excel::queueImport(new UsersImport($log->id, $validated['duplicate_strategy']), $storedPath, 'local');

        return back()->with('success', 'User import has been queued.');
    }

    public function exportBooks(Request $request)
    {
        $validated = $request->validate([
            'format' => ['required', 'in:csv,xlsx,pdf'],
            'category' => ['nullable', 'string'],
            'price_min' => ['nullable', 'numeric'],
            'price_max' => ['nullable', 'numeric'],
            'stock_status' => ['nullable', 'in:in_stock,out_of_stock,low_stock'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['string'],
        ]);

        return $this->handleBooksExport($request, $validated);
    }

    public function exportOrders(Request $request)
    {
        $validated = $request->validate([
            'format' => ['required', 'in:csv,xlsx,pdf'],
            'status' => ['nullable', 'string'],
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'financial_report_type' => ['nullable', 'in:revenue_summary,tax_report'],
            'columns' => ['required_without:financial_report_type', 'array', 'min:1'],
            'columns.*' => ['string'],
        ]);

        if (($validated['financial_report_type'] ?? null) !== null) {
            return $this->handleFinancialReportExport($request, $validated);
        }

        return $this->handleOrdersExport($request, $validated);
    }

    public function exportUsers(Request $request)
    {
        $validated = $request->validate([
            'format' => ['required', 'in:csv,xlsx,pdf'],
            'role' => ['nullable', 'in:admin,customer'],
            'verified_only' => ['nullable', 'boolean'],
            'redact_pii' => ['nullable', 'boolean'],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['string'],
        ]);

        return $this->handleUsersExport($request, $validated);
    }

    public function runBackup(Request $request)
    {
        BackupMonitoring::create([
            'initiated_by_user_id' => $request->user()->id,
            'event' => 'manual_trigger',
            'status' => 'started',
            'message' => 'Manual backup triggered from admin dashboard.',
            'happened_at' => now(),
        ]);

        try {
            $exitCode = Artisan::call('app:backup-run', ['scope' => 'manual']);
        } catch (Throwable $exception) {
            report($exception);

            BackupMonitoring::create([
                'initiated_by_user_id' => $request->user()->id,
                'event' => 'manual_backup',
                'status' => 'failed',
                'message' => $exception->getMessage(),
                'metadata' => ['source' => 'admin_dashboard', 'exception' => $exception::class],
                'happened_at' => now(),
            ]);

            return back()->with('error', 'Backup failed: '.$exception->getMessage());
        }

        if ($exitCode !== 0) {
            $message = BackupMonitoring::query()
                ->where('event', 'manual_backup')
                ->where('status', 'failed')
                ->latest('happened_at')
                ->value('message') ?: 'Backup command failed. Review backup monitoring for details.';

            return back()->with('error', 'Backup failed: '.$message);
        }

        return back()->with('success', 'Backup command executed. Review backup monitoring below for the resulting status.');
    }

    public function downloadExport(ExportLog $exportLog)
    {
        if (! auth()->user()->isAdmin() && $exportLog->user_id !== auth()->id()) {
            abort(403);
        }

        abort_unless($exportLog->path && Storage::disk($exportLog->disk)->exists($exportLog->path), 404);

        return Storage::disk($exportLog->disk)->download($exportLog->path);
    }

    public function downloadImportFailures(ImportLog $importLog)
    {
        abort_unless($importLog->failure_report_path && Storage::disk('local')->exists($importLog->failure_report_path), 404);

        return Storage::disk('local')->download($importLog->failure_report_path);
    }

    protected function handleBooksExport(Request $request, array $validated)
    {
        $log = ExportLog::create([
            'user_id' => $request->user()->id,
            'type' => 'books',
            'format' => $validated['format'],
            'status' => 'processing',
            'filters' => collect($validated)->except(['format', 'columns'])->all(),
            'columns' => $validated['columns'],
        ]);

        $export = new BooksExport($log->filters ?? [], $validated['columns']);
        $count = (clone $export->query())->count();
        $path = "exports/books-{$log->id}.{$validated['format']}";

        if ($validated['format'] === 'pdf') {
            Storage::disk('local')->put($path, Pdf::loadView('exports.books-pdf', [
                'title' => 'Books Export',
                'books' => (clone $export->query())->limit(1000)->get(),
                'columns' => $validated['columns'],
            ])->output());

            CompleteExportLog::dispatchSync($log->id, $path, $count);

            return back()->with('success', 'Books export is ready.');
        }

        if ($count > 10000) {
            Excel::queue($export, $path, 'local')->chain([new CompleteExportLog($log->id, $path, $count)]);
        } else {
            Excel::store($export, $path, 'local');
            CompleteExportLog::dispatchSync($log->id, $path, $count);
        }

        return back()->with('success', 'Books export is ready.');
    }

    protected function handleOrdersExport(Request $request, array $validated)
    {
        $log = ExportLog::create([
            'user_id' => $request->user()->id,
            'type' => 'orders',
            'format' => $validated['format'],
            'status' => 'processing',
            'filters' => collect($validated)->except(['format', 'columns'])->all(),
            'columns' => $validated['columns'],
        ]);

        $export = new OrdersExport($log->filters ?? [], $validated['columns']);
        $count = (clone $export->query())->count();
        $path = "exports/orders-{$log->id}.{$validated['format']}";

        if ($validated['format'] === 'pdf') {
            Storage::disk('local')->put($path, Pdf::loadView('exports.orders-pdf', [
                'title' => 'Orders Export',
                'orders' => (clone $export->query())->limit(1000)->get(),
            ])->output());

            CompleteExportLog::dispatchSync($log->id, $path, $count);

            return back()->with('success', 'Orders export is ready.');
        }

        if ($count > 10000) {
            Excel::queue($export, $path, 'local')->chain([new CompleteExportLog($log->id, $path, $count)]);
        } else {
            Excel::store($export, $path, 'local');
            CompleteExportLog::dispatchSync($log->id, $path, $count);
        }

        return back()->with('success', 'Orders export is ready.');
    }

    protected function handleUsersExport(Request $request, array $validated)
    {
        $log = ExportLog::create([
            'user_id' => $request->user()->id,
            'type' => 'users',
            'format' => $validated['format'],
            'status' => 'processing',
            'filters' => collect($validated)->except(['format', 'columns'])->all(),
            'columns' => $validated['columns'],
        ]);

        $export = new UsersExport(
            $log->filters ?? [],
            $validated['columns'],
            (bool) ($validated['redact_pii'] ?? false)
        );

        $count = (clone $export->query())->count();
        $path = "exports/users-{$log->id}.{$validated['format']}";

        if ($validated['format'] === 'pdf') {
            Storage::disk('local')->put($path, Pdf::loadView('exports.users-pdf', [
                'title' => 'Users Export',
                'users' => (clone $export->query())->limit(1000)->get(),
                'redactPii' => (bool) ($validated['redact_pii'] ?? false),
            ])->output());

            CompleteExportLog::dispatchSync($log->id, $path, $count);

            return back()->with('success', 'Users export is ready.');
        }

        if ($count > 10000) {
            Excel::queue($export, $path, 'local')->chain([new CompleteExportLog($log->id, $path, $count)]);
        } else {
            Excel::store($export, $path, 'local');
            CompleteExportLog::dispatchSync($log->id, $path, $count);
        }

        return back()->with('success', 'Users export is ready.');
    }

    protected function handleFinancialReportExport(Request $request, array $validated)
    {
        $reportType = $validated['financial_report_type'];
        $filters = collect($validated)->only(['date_from', 'date_to'])->all();

        $log = ExportLog::create([
            'user_id' => $request->user()->id,
            'type' => $reportType,
            'format' => $validated['format'],
            'status' => 'processing',
            'filters' => $filters,
            'columns' => $reportType === 'tax_report'
                ? ['date', 'taxable_revenue', 'estimated_tax']
                : ['date', 'completed_orders', 'revenue'],
        ]);

        $orders = Order::query()
            ->where('status', 'completed')
            ->when($filters['date_from'] ?? null, fn ($query, $value) => $query->whereDate('placed_at', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($query, $value) => $query->whereDate('placed_at', '<=', $value))
            ->get(['id', 'total_amount', 'tax_amount', 'placed_at', 'created_at']);

        $rows = $orders
            ->groupBy(fn ($order) => optional($order->placed_at ?? $order->created_at)->toDateString())
            ->sortKeys()
            ->map(function ($dailyOrders, string $date) use ($reportType): array {
                $revenue = (float) $dailyOrders->sum('total_amount');
                $tax = (float) $dailyOrders->sum('tax_amount');

                if ($reportType === 'tax_report') {
                    return [$date, number_format($revenue, 2, '.', ''), number_format($tax, 2, '.', '')];
                }

                return [$date, (string) $dailyOrders->count(), number_format($revenue, 2, '.', '')];
            })
            ->values()
            ->all();

        $path = "exports/{$reportType}-{$log->id}.{$validated['format']}";

        if ($validated['format'] === 'pdf') {
            Storage::disk('local')->put($path, Pdf::loadView('exports.financial-report-pdf', [
                'title' => str($reportType)->replace('_', ' ')->title().' Report',
                'subtitle' => 'Generated '.now()->toDayDateTimeString(),
                'headings' => (new FinancialReportExport($reportType, []))->array()[0],
                'rows' => $rows,
            ])->output());
        } else {
            Excel::store(new FinancialReportExport($reportType, $rows), $path, 'local');
        }

        CompleteExportLog::dispatchSync($log->id, $path, count($rows));

        return back()->with('success', str($reportType)->replace('_', ' ')->title().' export is ready.');
    }

    protected function countSpreadsheetRows(string $absolutePath): int
    {
        $reader = IOFactory::createReaderForFile($absolutePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($absolutePath);

        return max(0, $spreadsheet->getActiveSheet()->getHighestDataRow() - 1);
    }

    protected function missingSpreadsheetHeaders(string $absolutePath, array $expectedHeaders): array
    {
        $reader = IOFactory::createReaderForFile($absolutePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($absolutePath);
        $sheet = $spreadsheet->getActiveSheet();
        $headers = collect($sheet->rangeToArray('A1:'.$sheet->getHighestColumn().'1')[0] ?? [])
            ->map(fn ($header) => str((string) $header)->trim()->lower()->replace(' ', '_')->toString())
            ->filter()
            ->values()
            ->all();

        return array_values(array_diff($expectedHeaders, $headers));
    }

    protected function failImportForMissingHeaders(ImportLog $log, array $missingHeaders): void
    {
        $failurePath = "imports/failures/import-{$log->id}.jsonl";

        Storage::disk('local')->put($failurePath, json_encode([
            'row' => 1,
            'errors' => ['Missing required headers: '.implode(', ', $missingHeaders)],
        ]).PHP_EOL);

        $log->update([
            'status' => 'failed',
            'failure_report_path' => $failurePath,
            'failed_rows' => max(1, $log->total_rows),
            'metadata' => array_merge($log->metadata ?? [], [
                'missing_headers' => $missingHeaders,
            ]),
            'completed_at' => now(),
        ]);
    }

    protected function databaseSize(): int
    {
        try {
            $driver = DB::connection()->getDriverName();

            if ($driver === 'sqlite') {
                $database = config('database.connections.sqlite.database');

                return $database && file_exists($database) ? filesize($database) : 0;
            }

            if ($driver === 'pgsql') {
                return (int) DB::selectOne('SELECT pg_database_size(current_database()) AS size')->size;
            }

            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $schema = DB::getDatabaseName();

                return (int) DB::selectOne(
                    'SELECT COALESCE(SUM(data_length + index_length), 0) AS size FROM information_schema.tables WHERE table_schema = ?',
                    [$schema]
                )->size;
            }
        } catch (Throwable) {
            return 0;
        }

        return 0;
    }

    protected function booleanColumnIsTrueSql(string $column): string
    {
        $wrapped = DB::getQueryGrammar()->wrap($column);

        return DB::connection()->getDriverName() === 'pgsql'
            ? "{$wrapped} = true"
            : "{$wrapped} = 1";
    }

    protected function directorySize(string $directory): int
    {
        if (! is_dir($directory)) {
            return 0;
        }

        return collect(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)))
            ->sum(fn ($file) => $file->getSize());
    }
}
