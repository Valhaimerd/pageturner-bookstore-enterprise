<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $cart = $this->getOrCreateCart($request->user());

        $cart->load([
            'items.book.category',
        ]);

        $subtotal = $cart->items->sum(function ($item) {
            return (float) $item->unit_price * (int) $item->quantity;
        });

        return view('cart.index', compact('cart', 'subtotal'));
    }

    public function store(Request $request, Book $book)
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        if ($book->status !== 'active') {
            return redirect()
                ->back()
                ->with('error', 'This book is not available.');
        }

        if ($book->stock < 1) {
            return redirect()
                ->back()
                ->with('error', 'This book is out of stock.');
        }

        $cart = $this->getOrCreateCart($request->user());

        $cartItem = $cart->items()->where('book_id', $book->id)->first();
        $requestedQuantity = (int) $validated['quantity'];

        if ($cartItem) {
            $newQuantity = $cartItem->quantity + $requestedQuantity;

            if ($newQuantity > $book->stock) {
                return redirect()
                    ->back()
                    ->with('error', 'Requested quantity exceeds available stock.');
            }

            $cartItem->update([
                'quantity' => $newQuantity,
                'unit_price' => $book->price,
            ]);
        } else {
            if ($requestedQuantity > $book->stock) {
                return redirect()
                    ->back()
                    ->with('error', 'Requested quantity exceeds available stock.');
            }

            $cart->items()->create([
                'book_id' => $book->id,
                'quantity' => $requestedQuantity,
                'unit_price' => $book->price,
            ]);
        }

        return redirect()
            ->route('cart.index')
            ->with('success', 'Book added to cart.');
    }

    public function buyNow(Request $request, Book $book)
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        if ($book->status !== 'active') {
            return redirect()
                ->back()
                ->with('error', 'This book is not available.');
        }

        if ($book->stock < 1) {
            return redirect()
                ->back()
                ->with('error', 'This book is out of stock.');
        }

        $requestedQuantity = (int) $validated['quantity'];

        if ($requestedQuantity > $book->stock) {
            return redirect()
                ->back()
                ->with('error', 'Requested quantity exceeds available stock.');
        }

        $cart = $this->getOrCreateCart($request->user());

        $cartItem = $cart->items()->updateOrCreate(
            ['book_id' => $book->id],
            [
                'quantity' => $requestedQuantity,
                'unit_price' => $book->price,
            ]
        );

        return redirect()->route('checkout.index', [
            'item' => $cartItem->id,
        ]);
    }

    public function update(Request $request, CartItem $cartItem)
    {
        $this->ensureOwnedCartItem($request, $cartItem);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        if (! $cartItem->book || $cartItem->book->status !== 'active') {
            return redirect()
                ->route('cart.index')
                ->with('error', 'This book is no longer available.');
        }

        if ((int) $validated['quantity'] > $cartItem->book->stock) {
            return redirect()
                ->route('cart.index')
                ->with('error', 'Requested quantity exceeds available stock.');
        }

        $cartItem->update([
            'quantity' => (int) $validated['quantity'],
            'unit_price' => $cartItem->book->price,
        ]);

        return redirect()
            ->route('cart.index')
            ->with('success', 'Cart updated successfully.');
    }

    public function destroy(Request $request, CartItem $cartItem)
    {
        $this->ensureOwnedCartItem($request, $cartItem);

        $cartItem->delete();

        return redirect()
            ->route('cart.index')
            ->with('success', 'Item removed from cart.');
    }

    public function clear(Request $request)
    {
        $cart = $request->user()->cart;

        if ($cart) {
            $cart->items()->delete();
        }

        return redirect()
            ->route('cart.index')
            ->with('success', 'Cart cleared successfully.');
    }

    private function getOrCreateCart($user): Cart
    {
        return Cart::firstOrCreate(
            ['user_id' => $user->id],
            ['status' => 'active']
        );
    }

    private function ensureOwnedCartItem(Request $request, CartItem $cartItem): void
    {
        if ($cartItem->cart->user_id !== $request->user()->id) {
            abort(403);
        }
    }
}
