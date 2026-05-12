<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoOrderSeeder extends Seeder
{
    public function run(): void
    {
        OrderItem::query()->delete();
        Order::query()->delete();

        $orders = [
            [
                'order_number' => 'ORD-DEMO-0001',
                'receipt_number' => 'RCT-DEMO-0001',
                'user_email' => 'palvryclount@gmail.com',
                'status' => 'completed',
                'payment_status' => 'paid',
                'payment_method' => 'cash_on_delivery',
                'shipping_fee' => 50,
                'tax_amount' => 0,
                'placed_at' => now()->subDays(14),
                'notes' => 'Please pack carefully.',
                'items' => [
                    ['slug' => 'laravel-for-builders', 'quantity' => 1],
                    ['slug' => 'clean-code-essentials', 'quantity' => 1],
                ],
            ],
            [
                'order_number' => 'ORD-DEMO-0002',
                'receipt_number' => 'RCT-DEMO-0002',
                'user_email' => 'ria@pageturner.test',
                'status' => 'processing',
                'payment_status' => 'paid',
                'payment_method' => 'manual',
                'shipping_fee' => 60,
                'tax_amount' => 0,
                'placed_at' => now()->subDays(10),
                'notes' => 'Customer requested call before delivery.',
                'items' => [
                    ['slug' => 'study-smarter', 'quantity' => 2],
                    ['slug' => 'exam-ready-notes', 'quantity' => 1],
                ],
            ],
            [
                'order_number' => 'ORD-DEMO-0003',
                'receipt_number' => 'RCT-DEMO-0003',
                'user_email' => 'sean@pageturner.test',
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'payment_method' => 'cash_on_delivery',
                'shipping_fee' => 50,
                'tax_amount' => 0,
                'placed_at' => now()->subDays(8),
                'notes' => null,
                'items' => [
                    ['slug' => 'api-design-handbook', 'quantity' => 1],
                ],
            ],
            [
                'order_number' => 'ORD-DEMO-0004',
                'receipt_number' => 'RCT-DEMO-0004',
                'user_email' => 'nico@pageturner.test',
                'status' => 'completed',
                'payment_status' => 'paid',
                'payment_method' => 'manual',
                'shipping_fee' => 55,
                'tax_amount' => 0,
                'placed_at' => now()->subDays(20),
                'notes' => 'Leave at the front desk.',
                'items' => [
                    ['slug' => 'startup-basics', 'quantity' => 1],
                    ['slug' => 'marketing-that-works', 'quantity' => 1],
                ],
            ],
            [
                'order_number' => 'ORD-DEMO-0005',
                'receipt_number' => 'RCT-DEMO-0005',
                'user_email' => 'mia@pageturner.test',
                'status' => 'completed',
                'payment_status' => 'paid',
                'payment_method' => 'cash_on_delivery',
                'shipping_fee' => 45,
                'tax_amount' => 0,
                'placed_at' => now()->subDays(5),
                'notes' => null,
                'items' => [
                    ['slug' => 'the-silent-harbor', 'quantity' => 1],
                    ['slug' => 'midnight-train', 'quantity' => 1],
                ],
            ],
            [
                'order_number' => 'ORD-DEMO-0006',
                'receipt_number' => 'RCT-DEMO-0006',
                'user_email' => 'kara@pageturner.test',
                'status' => 'cancelled',
                'payment_status' => 'refunded',
                'payment_method' => 'manual',
                'shipping_fee' => 50,
                'tax_amount' => 0,
                'placed_at' => now()->subDays(12),
                'notes' => 'Cancelled by customer.',
                'items' => [
                    ['slug' => 'world-history-in-focus', 'quantity' => 1],
                ],
            ],
            [
                'order_number' => 'ORD-DEMO-0007',
                'receipt_number' => 'RCT-DEMO-0007',
                'user_email' => 'palvryclount@gmail.com',
                'status' => 'processing',
                'payment_status' => 'paid',
                'payment_method' => 'manual',
                'shipping_fee' => 65,
                'tax_amount' => 0,
                'placed_at' => now()->subDays(3),
                'notes' => 'Urgent order.',
                'items' => [
                    ['slug' => 'database-design-made-simple', 'quantity' => 1],
                    ['slug' => 'api-design-handbook', 'quantity' => 1],
                ],
            ],
            [
                'order_number' => 'ORD-DEMO-0008',
                'receipt_number' => 'RCT-DEMO-0008',
                'user_email' => 'ria@pageturner.test',
                'status' => 'completed',
                'payment_status' => 'paid',
                'payment_method' => 'cash_on_delivery',
                'shipping_fee' => 50,
                'tax_amount' => 0,
                'placed_at' => now()->subDays(25),
                'notes' => null,
                'items' => [
                    ['slug' => 'understanding-physics', 'quantity' => 1],
                    ['slug' => 'biology-basics', 'quantity' => 1],
                ],
            ],
            [
                'order_number' => 'ORD-DEMO-0009',
                'receipt_number' => 'RCT-DEMO-0009',
                'user_email' => 'nico@pageturner.test',
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'payment_method' => 'cash_on_delivery',
                'shipping_fee' => 50,
                'tax_amount' => 0,
                'placed_at' => now()->subDays(1),
                'notes' => null,
                'items' => [
                    ['slug' => 'the-last-lantern', 'quantity' => 2],
                ],
            ],
            [
                'order_number' => 'ORD-DEMO-0010',
                'receipt_number' => 'RCT-DEMO-0010',
                'user_email' => 'mia@pageturner.test',
                'status' => 'completed',
                'payment_status' => 'paid',
                'payment_method' => 'manual',
                'shipping_fee' => 55,
                'tax_amount' => 0,
                'placed_at' => now()->subDays(30),
                'notes' => 'Gift wrap requested.',
                'items' => [
                    ['slug' => 'ancient-civilizations', 'quantity' => 1],
                    ['slug' => 'world-history-in-focus', 'quantity' => 1],
                ],
            ],
        ];

        foreach ($orders as $data) {
            $user = User::where('email', $data['user_email'])->firstOrFail();

            $subtotal = 0;
            $books = [];

            foreach ($data['items'] as $item) {
                $book = Book::where('slug', $item['slug'])->firstOrFail();
                $books[] = [
                    'book' => $book,
                    'quantity' => $item['quantity'],
                ];
                $subtotal += ((float) $book->price * (int) $item['quantity']);
            }

            $totalAmount = $subtotal + $data['shipping_fee'] + $data['tax_amount'];

            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => $data['order_number'],
                'receipt_number' => $data['receipt_number'],
                'buyer_name' => $user->name,
                'buyer_email' => $user->email,
                'buyer_phone' => $user->phone,
                'address_line_1' => $user->default_address_line_1 ?: 'Demo Address 1',
                'address_line_2' => $user->default_address_line_2,
                'city' => $user->default_city ?: 'Cagayan de Oro',
                'province' => $user->default_province ?: 'Misamis Oriental',
                'postal_code' => $user->default_postal_code ?: '9000',
                'country' => $user->default_country ?: 'Philippines',
                'subtotal' => $subtotal,
                'shipping_fee' => $data['shipping_fee'],
                'tax_amount' => $data['tax_amount'],
                'total_amount' => $totalAmount,
                'status' => $data['status'],
                'payment_method' => $data['payment_method'],
                'payment_status' => $data['payment_status'],
                'notes' => $data['notes'],
                'placed_at' => $data['placed_at'],
                'receipt_issued_at' => $data['placed_at'],
                'created_at' => $data['placed_at'],
                'updated_at' => $data['placed_at'],
            ]);

            foreach ($books as $row) {
                $book = $row['book'];
                $quantity = (int) $row['quantity'];
                $lineTotal = (float) $book->price * $quantity;

                $order->items()->create([
                    'book_id' => $book->id,
                    'book_title' => $book->title,
                    'book_author' => $book->author,
                    'unit_price' => $book->price,
                    'quantity' => $quantity,
                    'line_total' => $lineTotal,
                    'created_at' => $data['placed_at'],
                    'updated_at' => $data['placed_at'],
                ]);

                if ($data['status'] !== 'cancelled') {
                    $book->decrement('stock', $quantity);
                }
            }
        }
    }
}
