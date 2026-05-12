<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoCartSeeder extends Seeder
{
    public function run(): void
    {
        CartItem::query()->delete();
        Cart::query()->delete();

        $cartData = [
            'alya@pageturner.test' => [
                ['slug' => 'exam-ready-notes', 'quantity' => 2],
                ['slug' => 'study-smarter', 'quantity' => 1],
            ],
            'daniel@pageturner.test' => [
                ['slug' => 'database-design-made-simple', 'quantity' => 1],
            ],
            'sean@pageturner.test' => [
                ['slug' => 'midnight-train', 'quantity' => 1],
                ['slug' => 'marketing-that-works', 'quantity' => 1],
            ],
        ];

        foreach ($cartData as $email => $items) {
            $user = User::where('email', $email)->first();

            if (! $user) {
                continue;
            }

            $cart = Cart::create([
                'user_id' => $user->id,
                'status' => 'active',
            ]);

            foreach ($items as $item) {
                $book = Book::where('slug', $item['slug'])
                    ->where('status', 'active')
                    ->first();

                if (! $book || $book->stock < $item['quantity']) {
                    continue;
                }

                $cart->items()->create([
                    'book_id' => $book->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $book->price,
                ]);
            }
        }
    }
}
