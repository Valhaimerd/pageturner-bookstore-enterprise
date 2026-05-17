<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Review;
use App\Repositories\BookRepository;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(Request $request, BookRepository $bookRepository)
    {
        $selectedCategory = $request->query('category');
        $search = trim((string) $request->query('search', ''));

        $categories = Category::where('is_active', true)
            ->orderBy('name')
            ->get();

        $books = $bookRepository->catalogCursor([
            'category' => $selectedCategory,
            'search' => $search,
        ], 12);

        return view('catalog.index', compact('books', 'categories', 'selectedCategory', 'search'));
    }

    public function show(Book $book, Request $request, BookRepository $bookRepository)
    {
        $book = $bookRepository->findActiveBySlug($book->slug);

        abort_unless($book, 404);

        $averageRating = round((float) $book->reviews->avg('rating'), 1);
        $reviewCount = $book->reviews->count();

        $canReview = false;
        $purchased = false;
        $userReview = null;

        if ($request->user() && $request->user()->isCustomer()) {
            $userId = $request->user()->id;

            $purchased = OrderItem::where('book_id', $book->id)
                ->whereHas('order', function ($q) use ($userId) {
                    $q->where('user_id', $userId)
                        ->where('status', '!=', 'cancelled');
                })
                ->exists();

            $userReview = Review::where('user_id', $userId)
                ->where('book_id', $book->id)
                ->first();

            $canReview = $purchased; // if purchased, allow create or edit
        }

        return view('catalog.show', compact(
            'book',
            'averageRating',
            'reviewCount',
            'canReview',
            'purchased',
            'userReview'
        ));
    }
}
