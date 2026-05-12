<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::query()
            ->where(function ($query) use ($request) {
                if ($request->user()->isAdmin() && $request->query('customer_id')) {
                    $query->where('user_id', $request->integer('customer_id'));

                    return;
                }

                $query->where('user_id', $request->user()->id);
            })
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest('placed_at')
            ->cursorPaginate((int) $request->integer('per_page', 15));

        return response()->json($orders);
    }

    public function show(Request $request, Order $order)
    {
        $this->authorize('view', $order);

        $order->load('items');

        return response()->json(['data' => $order]);
    }
}
