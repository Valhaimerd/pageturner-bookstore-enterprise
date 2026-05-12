<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\User;
use App\Notifications\AdminNewReviewNotification;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, Book $book)
    {
        $user = $request->user();

        abort_unless($user && $user->isCustomer(), 403);
        abort_unless($book->status === 'active', 404);

        $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $purchased = OrderItem::where('book_id', $book->id)
            ->whereHas('order', function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->where('status', '!=', 'cancelled');
            })
            ->exists();

        if (! $purchased) {
            return redirect()
                ->route('books.show', $book->slug)
                ->with('error', 'You can only review books you purchased.');
        }

        $existing = Review::where('user_id', $user->id)
            ->where('book_id', $book->id)
            ->first();

        if ($existing) {
            return redirect()
                ->route('books.show', $book->slug)
                ->with('error', 'You already reviewed this book. Edit it instead.');
        }

        $latestOrderItem = OrderItem::where('book_id', $book->id)
            ->whereHas('order', function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->where('status', '!=', 'cancelled');
            })
            ->latest('id')
            ->first();

        $review = Review::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'order_id' => $latestOrderItem?->order_id,
            'rating' => (int) $request->input('rating'),
            'comment' => $request->input('comment'),
            'is_visible' => true,
        ]);

        User::where('role', 'admin')->get()->each(function ($admin) use ($review) {
            $admin->notify(new AdminNewReviewNotification($review));
        });

        return redirect()
            ->route('books.show', $book->slug)
            ->with('success', 'Review submitted.');
    }

    public function update(Request $request, Review $review)
    {
        $this->authorize('update', $review);

        $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $review->update([
            'rating' => (int) $request->input('rating'),
            'comment' => $request->input('comment'),
        ]);

        $book = Book::find($review->book_id);

        return redirect()
            ->route('books.show', $book?->slug ?? 'books')
            ->with('success', 'Review updated.');
    }

    public function destroy(Request $request, Review $review)
    {
        $this->authorize('delete', $review);

        $book = Book::find($review->book_id);

        $review->delete();

        return redirect()
            ->route('books.show', $book?->slug ?? 'books')
            ->with('success', 'Review deleted.');
    }
}
