<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-ink-900 leading-tight">
                Lab 8 AI Monitoring
            </h2>
            <p class="mt-1 text-sm text-ink-500">
                Evidence dashboard for AI usage logs, audit events, fallback activity, and zero-cost local providers.
            </p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
            <div class="metric-card">
                <p class="metric-label">AI Calls Today</p>
                <p class="metric-value">{{ $totalToday }}</p>
                <p class="metric-note">Recorded since midnight</p>
            </div>
            <div class="metric-card">
                <p class="metric-label">AI Calls All Time</p>
                <p class="metric-value">{{ $totalAllTime }}</p>
                <p class="metric-note">All usage log rows</p>
            </div>
            <div class="metric-card">
                <p class="metric-label">Success / Failed</p>
                <p class="metric-value">{{ $successCount }} / {{ $failedCount }}</p>
                <p class="metric-note">Provider call outcomes</p>
            </div>
            <div class="metric-card">
                <p class="metric-label">Fallback Used</p>
                <p class="metric-value">{{ $fallbackCount }}</p>
                <p class="metric-note">Recovered with fake provider</p>
            </div>
            <div class="metric-card">
                <p class="metric-label">Ollama Calls</p>
                <p class="metric-value">{{ $ollamaCalls }}</p>
                <p class="metric-note">Local real provider</p>
            </div>
            <div class="metric-card">
                <p class="metric-label">Fake Fallback Calls</p>
                <p class="metric-value">{{ $fakeFallbackCalls }}</p>
                <p class="metric-note">Offline fallback events</p>
            </div>
            <div class="metric-card">
                <p class="metric-label">Average Latency</p>
                <p class="metric-value">{{ $averageLatencyMs }}ms</p>
                <p class="metric-note">Usage logs with latency</p>
            </div>
            <div class="metric-card">
                <p class="metric-label">Estimated Cost Total</p>
                <p class="metric-value">&#8369;0.00</p>
                <p class="metric-note">{{ number_format($estimatedCostTotal, 6) }} tracked cost, local/free providers</p>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <div class="admin-panel">
                <h3 class="admin-panel-title">Provider Usage Breakdown</h3>
                <p class="admin-panel-subtitle">Grouped from AI usage logs.</p>

                <div class="mt-5 space-y-3">
                    @forelse($providerBreakdown as $provider)
                        <div class="flex items-center justify-between rounded-2xl bg-sage-50 px-4 py-3">
                            <span class="font-semibold text-ink-800">{{ ucfirst($provider->provider) }}</span>
                            <span class="status-pill status-pill-default">{{ $provider->total }} calls</span>
                        </div>
                    @empty
                        <div class="empty-state-card">
                            <h4 class="empty-state-title">No provider usage yet</h4>
                            <p class="empty-state-text">Provider breakdown will appear after AI calls are logged.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="admin-panel">
                <h3 class="admin-panel-title">Top Features Used</h3>
                <p class="admin-panel-subtitle">Most common AI features by usage count.</p>

                <div class="mt-5 space-y-3">
                    @forelse($topFeatures as $feature)
                        <div class="flex items-center justify-between rounded-2xl bg-sage-50 px-4 py-3">
                            <span class="font-semibold text-ink-800">{{ str_replace('_', ' ', $feature->feature) }}</span>
                            <span class="status-pill status-pill-default">{{ $feature->total }} calls</span>
                        </div>
                    @empty
                        <div class="empty-state-card">
                            <h4 class="empty-state-title">No feature usage yet</h4>
                            <p class="empty-state-text">Top features will appear after assistant activity.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="admin-panel">
            <h3 class="admin-panel-title">Recent AI Usage Logs</h3>
            <p class="admin-panel-subtitle">Safe operational details only. Raw prompts and AI output are not shown.</p>

            <div class="mt-5 ui-table-wrap">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Provider</th>
                            <th>Feature</th>
                            <th>Status</th>
                            <th>Fallback</th>
                            <th>Latency</th>
                            <th>Cost</th>
                            <th>Metadata</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentUsageLogs as $log)
                            <tr>
                                <td>#{{ $log->id }}</td>
                                <td>{{ $log->user?->email ?: 'Guest' }}</td>
                                <td>{{ ucfirst($log->provider) }}</td>
                                <td>{{ str_replace('_', ' ', $log->feature) }}</td>
                                <td>
                                    <span class="status-pill {{ $log->success ? 'status-pill-completed' : 'status-pill-pending' }}">
                                        {{ $log->success ? 'Success' : 'Failed' }}
                                    </span>
                                </td>
                                <td>{{ $log->fallback_used ? 'Yes' : 'No' }}</td>
                                <td>{{ $log->latency_ms ?? 0 }}ms</td>
                                <td>&#8369;{{ number_format((float) $log->cost_estimate, 2) }}</td>
                                <td>{{ \Illuminate\Support\Str::limit(json_encode($log->metadata ?? [], JSON_UNESCAPED_SLASHES), 90) }}</td>
                                <td>{{ optional($log->created_at)->format('M d, Y h:i A') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10">No AI usage logs recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="admin-panel">
            <h3 class="admin-panel-title">Recent AI Audit Events</h3>
            <p class="admin-panel-subtitle">Audit events show hashes and sanitized metadata instead of raw sensitive content.</p>

            <div class="mt-5 ui-table-wrap">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Feature</th>
                            <th>Action</th>
                            <th>Provider</th>
                            <th>Risk</th>
                            <th>Confidence</th>
                            <th>Input Hash</th>
                            <th>Output Hash</th>
                            <th>Metadata</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentAuditEvents as $event)
                            <tr>
                                <td>#{{ $event->id }}</td>
                                <td>{{ $event->user?->email ?: 'Guest' }}</td>
                                <td>{{ str_replace('_', ' ', $event->feature) }}</td>
                                <td>{{ str_replace('_', ' ', $event->action) }}</td>
                                <td>{{ $event->provider ? ucfirst($event->provider) : 'None' }}</td>
                                <td>
                                    <span class="status-pill {{ $event->risk_level === 'high' ? 'status-pill-pending' : 'status-pill-default' }}">
                                        {{ ucfirst($event->risk_level) }}
                                    </span>
                                </td>
                                <td>{{ $event->confidence !== null ? number_format((float) $event->confidence, 2) : 'N/A' }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($event->input_hash, 16, '') }}</td>
                                <td>{{ $event->output_hash ? \Illuminate\Support\Str::limit($event->output_hash, 16, '') : 'N/A' }}</td>
                                <td>{{ \Illuminate\Support\Str::limit(json_encode($event->metadata ?? [], JSON_UNESCAPED_SLASHES), 90) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10">No AI audit events recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
