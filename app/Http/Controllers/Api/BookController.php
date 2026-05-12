<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookResource;
use App\Models\Book;
use App\Repositories\BookRepository;
use Illuminate\Http\Request;

class BookController extends Controller
{
    public function index(Request $request, BookRepository $bookRepository)
    {
        $books = $bookRepository->apiCursorCatalog(
            $request->only(['search', 'category']),
            (int) $request->integer('per_page', 20)
        );

        return BookResource::collection($books);
    }

    public function show(Book $book, BookRepository $bookRepository)
    {
        $book = $bookRepository->findActiveBySlug($book->slug);

        abort_unless($book, 404);

        return new BookResource($book);
    }
}
