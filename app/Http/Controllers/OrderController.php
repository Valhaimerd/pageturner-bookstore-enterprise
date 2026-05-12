<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return view('orders.index', compact('orders'));
    }

    public function show(Request $request, Order $order)
    {
        $this->authorize('view', $order);

        $order->load('items');

        return view('orders.show', compact('order'));
    }

    public function receipt(Request $request, Order $order)
    {
        $this->authorize('view', $order);

        $order->load('items');

        return view('orders.receipt', compact('order'));
    }
}
