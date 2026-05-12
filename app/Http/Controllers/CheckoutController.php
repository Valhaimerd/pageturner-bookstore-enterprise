<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\User;
use App\Notifications\AdminNewOrderNotification;
use App\Notifications\CustomerOrderPlacedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function index(Request $request)
    {
        $items = $this->getCheckoutItems($request);

        if ($items->isEmpty()) {
            return redirect()
                ->route('cart.index')
                ->with('error', 'No items available for checkout.');
        }

        $subtotal = $items->sum(function ($item) {
            return (float) $item->unit_price * (int) $item->quantity;
        });

        $selectedItemId = $request->integer('item') ?: null;
        $user = $request->user();

        return view('checkout.index', compact('items', 'subtotal', 'selectedItemId', 'user'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_id' => ['nullable', 'integer'],
            'buyer_name' => ['required', 'string', 'max:255'],
            'buyer_email' => ['required', 'email', 'max:255'],
            'buyer_phone' => ['nullable', 'string', 'max:255'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'payment_method' => ['required', Rule::in(['cash_on_delivery', 'manual'])],
            'notes' => ['nullable', 'string'],
        ]);

        $order = DB::transaction(function () use ($request, $validated) {
            $items = $this->getCheckoutItems($request, $validated['item_id'] ?? null);

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'checkout' => 'No items available for checkout. Please review your cart and try again.',
                ]);
            }

            foreach ($items as $item) {
                if (! $item->book || $item->book->status !== 'active') {
                    throw ValidationException::withMessages([
                        'checkout' => 'One of the books is no longer available. Please review your cart before checking out.',
                    ]);
                }

                if ($item->quantity > $item->book->stock) {
                    throw ValidationException::withMessages([
                        'checkout' => 'One of the books no longer has enough stock for this order. Please update your cart and try again.',
                    ]);
                }
            }

            $subtotal = $items->sum(function ($item) {
                return (float) $item->unit_price * (int) $item->quantity;
            });

            $shippingFee = 0;
            $taxAmount = 0;
            $totalAmount = $subtotal + $shippingFee + $taxAmount;

            $order = Order::create([
                'user_id' => $request->user()->id,
                'order_number' => $this->generateUniqueOrderNumber(),
                'receipt_number' => $this->generateUniqueReceiptNumber(),
                'buyer_name' => $validated['buyer_name'],
                'buyer_email' => $validated['buyer_email'],
                'buyer_phone' => $validated['buyer_phone'] ?? null,
                'address_line_1' => $validated['address_line_1'],
                'address_line_2' => $validated['address_line_2'] ?? null,
                'city' => $validated['city'],
                'province' => $validated['province'],
                'postal_code' => $validated['postal_code'],
                'country' => $validated['country'],
                'subtotal' => $subtotal,
                'shipping_fee' => $shippingFee,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'unpaid',
                'notes' => $validated['notes'] ?? null,
                'placed_at' => now(),
                'receipt_issued_at' => now(),
            ]);

            foreach ($items as $item) {
                $lineTotal = (float) $item->unit_price * (int) $item->quantity;

                $order->items()->create([
                    'book_id' => $item->book->id,
                    'book_title' => $item->book->title,
                    'book_author' => $item->book->author,
                    'unit_price' => $item->unit_price,
                    'quantity' => $item->quantity,
                    'line_total' => $lineTotal,
                ]);

                $item->book->decrement('stock', $item->quantity);
            }

            foreach ($items as $item) {
                $item->delete();
            }

            return $order;
        });

        $order->load('user');

        $order->user?->notify(new CustomerOrderPlacedNotification($order));

        User::where('role', 'admin')->get()->each(function ($admin) use ($order) {
            $admin->notify(new AdminNewOrderNotification($order));
        });

        return redirect()->route('orders.success', $order);
    }

    public function success(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id) {
            abort(403);
        }

        $order->load('items');

        return view('orders.success', compact('order'));
    }

    private function getCheckoutItems(Request $request, ?int $forcedItemId = null)
    {
        $cart = $request->user()->cart;

        if (! $cart) {
            return collect();
        }

        $query = $cart->items()->with('book');

        $itemId = $forcedItemId ?: $request->integer('item');

        if ($itemId) {
            $query->where('id', $itemId);
        }

        return $query->get();
    }

    private function generateUniqueOrderNumber(): string
    {
        do {
            $value = 'ORD-'.now()->format('Ymd').'-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (Order::where('order_number', $value)->exists());

        return $value;
    }

    private function generateUniqueReceiptNumber(): string
    {
        do {
            $value = 'RCT-'.now()->format('Ymd').'-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (Order::where('receipt_number', $value)->exists());

        return $value;
    }
}
