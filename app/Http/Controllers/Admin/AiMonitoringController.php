<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AIAuditEvent;
use App\Models\AiInteraction;
use App\Models\AIUsageLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiMonitoringController extends Controller
{
    public function index(Request $request)
    {
        $query = AiInteraction::with('user')
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('provider'), fn ($query, $provider) => $query->where('provider', $provider))
            ->when($request->boolean('fallback_only'), fn ($query) => $query->where('fallback_used', true));

        return view('admin.ai.index', [
            'interactions' => (clone $query)->latest()->paginate(15)->withQueryString(),
            'filters' => $request->only(['status', 'provider', 'fallback_only']),
            'totalInteractions' => AiInteraction::count(),
            'fallbackCount' => AiInteraction::where('fallback_used', true)->count(),
            'failedCount' => AiInteraction::where('status', 'failed')->count(),
            'averageLatencyMs' => (int) AiInteraction::whereNotNull('latency_ms')->avg('latency_ms'),
            'estimatedCostCents' => (float) AiInteraction::sum('estimated_cost_cents'),
        ]);
    }

    public function show(AiInteraction $aiInteraction)
    {
        return view('admin.ai.show', [
            'interaction' => $aiInteraction->load('user'),
        ]);
    }

    public function labEightIndex()
    {
        $providerBreakdown = AIUsageLog::query()
            ->select('provider', DB::raw('count(*) as total'))
            ->groupBy('provider')
            ->orderByDesc('total')
            ->get();

        $topFeatures = AIUsageLog::query()
            ->select('feature', DB::raw('count(*) as total'))
            ->groupBy('feature')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return view('admin.ai-monitoring.index', [
            'totalToday' => AIUsageLog::whereDate('created_at', today())->count(),
            'totalAllTime' => AIUsageLog::count(),
            'successCount' => AIUsageLog::where('success', true)->count(),
            'failedCount' => AIUsageLog::where('success', false)->count(),
            'fallbackCount' => AIUsageLog::where('fallback_used', true)->count(),
            'ollamaCalls' => AIUsageLog::where('provider', 'ollama')->count(),
            'fakeFallbackCalls' => AIUsageLog::where('provider', 'fake')->where('fallback_used', true)->count(),
            'averageLatencyMs' => (int) round((float) AIUsageLog::whereNotNull('latency_ms')->avg('latency_ms')),
            'estimatedCostTotal' => (float) AIUsageLog::sum('cost_estimate'),
            'providerBreakdown' => $providerBreakdown,
            'topFeatures' => $topFeatures,
            'recentUsageLogs' => AIUsageLog::with('user')->latest()->limit(10)->get(),
            'recentAuditEvents' => AIAuditEvent::with('user')->latest()->limit(10)->get(),
        ]);
    }
}
