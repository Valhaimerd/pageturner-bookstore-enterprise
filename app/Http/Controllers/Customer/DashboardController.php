<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\ExportLog;
use App\Models\Order;
use App\Models\Review;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $recentOrders = Order::where('user_id', $user->id)
            ->latest()
            ->take(5)
            ->get();

        $recentReviews = Review::with('book')
            ->where('user_id', $user->id)
            ->latest()
            ->take(5)
            ->get();

        return view('customer.dashboard', [
            'totalOrders' => Order::where('user_id', $user->id)->count(),
            'pendingOrders' => Order::where('user_id', $user->id)->where('status', 'pending')->count(),
            'completedOrders' => Order::where('user_id', $user->id)->where('status', 'completed')->count(),
            'totalSpent' => Order::where('user_id', $user->id)
                ->where('status', '!=', 'cancelled')
                ->sum('total_amount'),
            'totalReviews' => Review::where('user_id', $user->id)->count(),
            'recentOrders' => $recentOrders,
            'recentReviews' => $recentReviews,
            'recentExports' => ExportLog::where('user_id', $user->id)
                ->whereIn('type', ['customer_orders'])
                ->latest()
                ->take(5)
                ->get(),
        ]);
    }
}
