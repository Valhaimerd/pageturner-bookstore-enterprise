<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoReviewSeeder extends Seeder
{
    public function run(): void
    {
        Review::query()->delete();

        $reviews = [
            [
                'user_email' => 'palvryclount@gmail.com',
                'book_slug' => 'laravel-for-builders',
                'rating' => 5,
                'comment' => 'Very clear and useful for Laravel project building.',
            ],
            [
                'user_email' => 'palvryclount@gmail.com',
                'book_slug' => 'clean-code-essentials',
                'rating' => 4,
                'comment' => 'Good explanations and easy to follow.',
            ],
            [
                'user_email' => 'ria@pageturner.test',
                'book_slug' => 'study-smarter',
                'rating' => 5,
                'comment' => 'Practical tips. Helped me organize my study routine.',
            ],
            [
                'user_email' => 'ria@pageturner.test',
                'book_slug' => 'understanding-physics',
                'rating' => 4,
                'comment' => 'Beginner friendly and not too hard to understand.',
            ],
            [
                'user_email' => 'nico@pageturner.test',
                'book_slug' => 'startup-basics',
                'rating' => 4,
                'comment' => 'Solid introduction to business fundamentals.',
            ],
            [
                'user_email' => 'mia@pageturner.test',
                'book_slug' => 'the-silent-harbor',
                'rating' => 5,
                'comment' => 'Interesting story and a very satisfying ending.',
            ],
            [
                'user_email' => 'mia@pageturner.test',
                'book_slug' => 'ancient-civilizations',
                'rating' => 4,
                'comment' => 'Good historical overview for casual readers.',
            ],
        ];

        foreach ($reviews as $data) {
            $user = User::where('email', $data['user_email'])->first();
            $book = Book::where('slug', $data['book_slug'])->first();

            if (! $user || ! $book) {
                continue;
            }

            $latestOrderItem = OrderItem::where('book_id', $book->id)
                ->whereHas('order', function ($query) use ($user) {
                    $query->where('user_id', $user->id)
                        ->where('status', '!=', 'cancelled');
                })
                ->latest('id')
                ->first();

            if (! $latestOrderItem) {
                continue;
            }

            Review::create([
                'user_id' => $user->id,
                'book_id' => $book->id,
                'order_id' => $latestOrderItem->order_id,
                'rating' => $data['rating'],
                'comment' => $data['comment'],
                'is_visible' => true,
                'created_at' => now()->subDays(rand(1, 20)),
                'updated_at' => now()->subDays(rand(0, 5)),
            ]);
        }
    }
}
