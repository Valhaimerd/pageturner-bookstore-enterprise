<?php

use App\Http\Controllers\Api\AiAssistantController;
use App\Http\Controllers\AI\BookAssistantController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api.track', 'throttle:ai-public', 'api.transform.request', 'api.transform.response'])
    ->name('api.ai.book-assistant.')
    ->group(function () {
        Route::post('/ai/book-assistant/message', [BookAssistantController::class, 'apiStore'])->name('message');
        Route::get('/ai/book-assistant/conversations', [BookAssistantController::class, 'apiConversations'])->name('conversations.index');
        Route::get('/ai/book-assistant/conversations/{conversation}', [BookAssistantController::class, 'apiShow'])->name('conversations.show');
    });

Route::prefix('v1')
    ->name('api.')
    ->group(function () {
        Route::middleware(['api.track', 'throttle:public-api', 'api.transform.request', 'api.transform.response'])
            ->group(function () {
                Route::get('/books', [BookController::class, 'index'])->name('books.index');
                Route::get('/books/{book:slug}', [BookController::class, 'show'])->name('books.show');
            });

        Route::middleware(['api.track', 'throttle:ai-public', 'api.transform.request', 'api.transform.response'])
            ->group(function () {
                Route::post('/ai/book-discovery', [AiAssistantController::class, 'store'])->name('ai.book-discovery');
            });

        Route::middleware(['auth', 'api.track', 'throttle:auth-api', 'api.transform.request', 'api.transform.response'])
            ->group(function () {
                Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
                Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
            });
    });
