<?php

namespace App\Exports;

use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OrdersExport implements FromQuery, ShouldAutoSize, ShouldQueue, WithHeadings, WithMapping
{
    use Exportable;

    public function __construct(
        protected array $filters = [],
        protected array $columns = [],
        protected ?int $scopeUserId = null
    ) {}

    public static function availableColumns(): array
    {
        return [
            'order_number' => 'Order Number',
            'receipt_number' => 'Receipt Number',
            'buyer_name' => 'Customer',
            'buyer_email' => 'Email',
            'status' => 'Status',
            'payment_status' => 'Payment Status',
            'payment_method' => 'Payment Method',
            'total_amount' => 'Total Amount',
            'placed_at' => 'Placed At',
        ];
    }

    public function headings(): array
    {
        return array_values($this->resolvedColumns());
    }

    public function map($order): array
    {
        $row = [
            'order_number' => $order->order_number,
            'receipt_number' => $order->receipt_number,
            'buyer_name' => $order->buyer_name,
            'buyer_email' => $order->buyer_email,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'total_amount' => number_format((float) $order->total_amount, 2, '.', ''),
            'placed_at' => optional($order->placed_at ?? $order->created_at)->format('Y-m-d H:i:s'),
        ];

        return collect(array_keys($this->resolvedColumns()))
            ->map(fn (string $column) => $row[$column] ?? null)
            ->all();
    }

    public function query(): Builder
    {
        return Order::query()
            ->when($this->scopeUserId, fn (Builder $query, int $userId) => $query->where('user_id', $userId))
            ->when($this->filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($this->filters['customer_id'] ?? null, fn (Builder $query, $value) => $query->where('user_id', $value))
            ->when($this->filters['date_from'] ?? null, fn (Builder $query, $value) => $query->whereDate('placed_at', '>=', $value))
            ->when($this->filters['date_to'] ?? null, fn (Builder $query, $value) => $query->whereDate('placed_at', '<=', $value))
            ->latest('placed_at');
    }

    protected function resolvedColumns(): array
    {
        $available = static::availableColumns();
        $requested = array_values(array_intersect($this->columns ?: array_keys($available), array_keys($available)));

        return collect($requested)->mapWithKeys(fn (string $column) => [$column => $available[$column]])->all();
    }
}
