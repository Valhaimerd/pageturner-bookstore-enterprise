<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\LogsScheduledTask;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupPendingOrders extends Command
{
    use LogsScheduledTask;

    protected $signature = 'order:cleanup-pending';

    protected $description = 'Cancel pending orders older than 24 hours and restore stock.';

    public function handle(): int
    {
        return $this->logScheduledTask($this->signature, $this->description, function (): string {
            $cancelled = 0;

            Order::with(['items.book'])
                ->where('status', 'pending')
                ->where('placed_at', '<=', now()->subDay())
                ->chunkById(50, function ($orders) use (&$cancelled): void {
                    foreach ($orders as $order) {
                        DB::transaction(function () use ($order, &$cancelled): void {
                            foreach ($order->items as $item) {
                                if ($item->book) {
                                    $item->book->increment('stock', $item->quantity);
                                }
                            }

                            $order->update(['status' => 'cancelled']);
                            $cancelled++;
                        });
                    }
                });

            return "Cancelled {$cancelled} expired pending orders.";
        });
    }
}
