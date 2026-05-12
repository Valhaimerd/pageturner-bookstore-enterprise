<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiInteraction;
use App\Models\ApiRateLimitHit;
use App\Models\BackupMonitoring;
use App\Models\Book;
use App\Models\Category;
use App\Models\ExportLog;
use App\Models\ImportLog;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $statusCounts = Order::select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $latestOrders = Order::with('user')
            ->latest()
            ->take(5)
            ->get();

        $latestReviews = Review::with(['user', 'book'])
            ->latest()
            ->take(5)
            ->get();

        return view('admin.dashboard', [
            'totalUsers' => User::count(),
            'totalCustomers' => User::where('role', 'customer')->count(),
            'totalBooks' => Book::count(),
            'totalCategories' => Category::count(),
            'totalOrders' => Order::count(),
            'totalRevenue' => Order::where('status', '!=', 'cancelled')->sum('total_amount'),
            'statusCounts' => $statusCounts,
            'latestOrders' => $latestOrders,
            'latestReviews' => $latestReviews,
            'recentImportCount' => ImportLog::where('created_at', '>=', now()->subDay())->count(),
            'recentExportCount' => ExportLog::where('created_at', '>=', now()->subDay())->count(),
            'latestBackupStatus' => BackupMonitoring::latest('happened_at')->value('status') ?? 'unknown',
            'apiThrottleHits' => ApiRateLimitHit::where('throttled', true)->where('created_at', '>=', now()->subDay())->count(),
            'aiInteractionsToday' => AiInteraction::where('created_at', '>=', now()->subDay())->count(),
            'aiFallbacksToday' => AiInteraction::where('fallback_used', true)->where('created_at', '>=', now()->subDay())->count(),
            'aiEstimatedCostCents' => AiInteraction::sum('estimated_cost_cents'),
        ]);
    }
}
