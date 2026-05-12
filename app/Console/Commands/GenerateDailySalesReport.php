<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\LogsScheduledTask;
use App\Exports\OrdersExport;
use App\Models\ExportLog;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class GenerateDailySalesReport extends Command
{
    use LogsScheduledTask;

    protected $signature = 'report:generate-daily {--date=}';

    protected $description = 'Generate the daily sales export and notify administrators.';

    public function handle(): int
    {
        return $this->logScheduledTask($this->signature, $this->description, function (): string {
            $date = $this->option('date') ?: now()->subDay()->toDateString();
            $filters = [
                'status' => 'completed',
                'date_from' => $date,
                'date_to' => $date,
            ];

            $log = ExportLog::create([
                'type' => 'daily_sales_report',
                'format' => 'csv',
                'status' => 'processing',
                'filters' => $filters,
                'columns' => array_keys(OrdersExport::availableColumns()),
            ]);

            $path = "exports/daily-sales-{$date}-{$log->id}.csv";
            $export = new OrdersExport($filters, array_keys(OrdersExport::availableColumns()));

            Excel::store($export, $path, 'local');

            $orders = (clone $export->query())->get();
            $revenue = $orders->sum('total_amount');

            $pdfPath = "exports/daily-sales-{$date}-{$log->id}.pdf";
            Storage::disk('local')->put($pdfPath, Pdf::loadView('exports.orders-pdf', [
                'title' => "Daily Sales Report {$date}",
                'orders' => $orders,
            ])->output());

            $log->update([
                'status' => 'completed',
                'path' => $path,
                'total_rows' => $orders->count(),
                'completed_at' => now(),
                'expires_at' => now()->addDays(14),
            ]);

            User::where('role', 'admin')->pluck('email')->filter()->each(function (string $email) use ($date, $revenue, $path): void {
                Mail::raw(
                    "Daily sales report for {$date} is ready.\nRevenue: ".number_format((float) $revenue, 2)."\nFile: {$path}",
                    fn ($message) => $message->to($email)->subject("PageTurner Daily Sales Report - {$date}")
                );
            });

            return "Generated daily sales report for {$date} covering {$orders->count()} orders.";
        });
    }
}
