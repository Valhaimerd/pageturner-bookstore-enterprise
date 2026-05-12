<?php

namespace App\Http\Controllers\Customer;

use App\Exports\OrdersExport;
use App\Http\Controllers\Controller;
use App\Jobs\CompleteExportLog;
use App\Models\ExportLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class DataExportController extends Controller
{
    public function personalData(Request $request)
    {
        $user = $request->user()->load(['orders.items', 'reviews.book']);

        return response()->streamDownload(function () use ($user): void {
            echo json_encode([
                'profile' => $user->only([
                    'name',
                    'email',
                    'phone',
                    'role',
                    'subscription_tier',
                    'default_address_line_1',
                    'default_address_line_2',
                    'default_city',
                    'default_province',
                    'default_postal_code',
                    'default_country',
                ]),
                'orders' => $user->orders,
                'reviews' => $user->reviews,
            ], JSON_PRETTY_PRINT);
        }, 'my-pageturner-data.json');
    }

    public function orders(Request $request)
    {
        $validated = $request->validate([
            'format' => ['required', 'in:csv,xlsx,pdf'],
        ]);

        $log = ExportLog::create([
            'user_id' => $request->user()->id,
            'type' => 'customer_orders',
            'format' => $validated['format'],
            'status' => 'processing',
            'columns' => array_keys(OrdersExport::availableColumns()),
        ]);

        $query = (new OrdersExport([], [], $request->user()->id))->query();
        $count = (clone $query)->count();
        $path = "exports/customer-orders-{$request->user()->id}-{$log->id}.{$validated['format']}";

        if ($validated['format'] === 'pdf') {
            $orders = (clone $query)->limit(500)->get();
            Storage::disk('local')->put($path, Pdf::loadView('exports.orders-pdf', [
                'title' => 'Customer Order History',
                'orders' => $orders,
            ])->output());

            CompleteExportLog::dispatchSync($log->id, $path, $count);

            return back()->with('success', 'Order history export is ready.');
        }

        if ($count > 10000) {
            Excel::queue(new OrdersExport([], array_keys(OrdersExport::availableColumns()), $request->user()->id), $path, 'local')
                ->chain([new CompleteExportLog($log->id, $path, $count)]);
        } else {
            Excel::store(new OrdersExport([], array_keys(OrdersExport::availableColumns()), $request->user()->id), $path, 'local');
            CompleteExportLog::dispatchSync($log->id, $path, $count);
        }

        return back()->with('success', 'Order history export is ready.');
    }

    public function readingHistory(Request $request)
    {
        $user = $request->user()->load(['orders.items.book', 'reviews.book']);

        return response()->streamDownload(function () use ($user): void {
            echo json_encode([
                'purchased_books' => $user->orders
                    ->flatMap(fn ($order) => $order->items)
                    ->map(fn ($item) => [
                        'title' => $item->book_title,
                        'author' => $item->book_author,
                        'quantity' => $item->quantity,
                        'ordered_at' => optional($item->order?->placed_at)->toIso8601String(),
                    ])
                    ->values(),
                'reviews' => $user->reviews->map(fn ($review) => [
                    'book' => $review->book?->title,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'created_at' => optional($review->created_at)->toIso8601String(),
                ]),
            ], JSON_PRETTY_PRINT);
        }, 'reading-history.json');
    }
}
