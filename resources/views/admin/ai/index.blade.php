<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-ink-900 leading-tight">
                AI Monitoring
            </h2>
            <p class="mt-1 text-sm text-ink-500">
                Review assistant usage, provider status, fallback events, and zero-cost reporting.
            </p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-5">
            <div class="metric-card">
                <p class="metric-label">AI Requests</p>
                <p class="metric-value">{{ $totalInteractions }}</p>
                <p class="metric-note">All assistant interactions</p>
            </div>
            <div class="metric-card">
                <p class="metric-label">Fallbacks</p>
                <p class="metric-value">{{ $fallbackCount }}</p>
                <p class="metric-note">Fake provider recoveries</p>
            </div>
            <div class="metric-card">
                <p class="metric-label">Failed</p>
                <p class="metric-value">{{ $failedCount }}</p>
                <p class="metric-note">Unresolved failures</p>
            </div>
            <div class="metric-card">
                <p class="metric-label">Avg Latency</p>
                <p class="metric-value">{{ $averageLatencyMs }}ms</p>
                <p class="metric-note">Recorded interactions</p>
            </div>
            <div class="metric-card">
                <p class="metric-label">AI Cost</p>
                <p class="metric-value">₱0.00</p>
                <p class="metric-note">{{ number_format($estimatedCostCents, 2) }} cents tracked</p>
            </div>
        </section>

        <section class="admin-panel">
            <form method="GET" action="{{ route('admin.ai.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div>
                    <label class="form-label">Status</label>
                    <select name="status" class="form-input">
                        <option value="">All statuses</option>
                        @foreach(['completed', 'fallback', 'failed'] as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Provider</label>
                    <select name="provider" class="form-input">
                        <option value="">All providers</option>
                        @foreach(['ollama', 'fake'] as $provider)
                            <option value="{{ $provider }}" @selected(($filters['provider'] ?? '') === $provider)>{{ ucfirst($provider) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end">
                    <label class="admin-checkbox-wrap">
                        <input type="checkbox" name="fallback_only" value="1" class="admin-checkbox" @checked((bool) ($filters['fallback_only'] ?? false))>
                        <span class="text-sm font-semibold text-ink-700">Fallback only</span>
                    </label>
                </div>
                <div class="flex items-end gap-3">
                    <button type="submit" class="btn-primary-ui">Apply</button>
                    <a href="{{ route('admin.ai.index') }}" class="btn-secondary-ui">Reset</a>
                </div>
            </form>
        </section>

        <section class="admin-panel">
            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Provider</th>
                            <th>Status</th>
                            <th>Prompt</th>
                            <th>Latency</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($interactions as $interaction)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.ai.show', $interaction) }}" class="font-semibold text-brand-700 hover:underline">
                                        #{{ $interaction->id }}
                                    </a>
                                </td>
                                <td>{{ $interaction->user?->email ?: 'Visitor' }}</td>
                                <td>{{ ucfirst($interaction->provider) }}</td>
                                <td>
                                    <span class="status-pill {{ $interaction->fallback_used ? 'status-pill-pending' : 'status-pill-completed' }}">
                                        {{ ucfirst($interaction->status) }}
                                    </span>
                                </td>
                                <td>{{ \Illuminate\Support\Str::limit($interaction->prompt, 80) }}</td>
                                <td>{{ $interaction->latency_ms ?? 0 }}ms</td>
                                <td>{{ optional($interaction->created_at)->format('M d, Y h:i A') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">No AI interactions recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-5">{{ $interactions->links() }}</div>
        </section>
    </div>
</x-app-layout>
