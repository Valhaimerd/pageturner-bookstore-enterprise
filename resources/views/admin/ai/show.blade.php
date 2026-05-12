<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-ink-900 leading-tight">
                    AI Interaction #{{ $interaction->id }}
                </h2>
                <p class="mt-1 text-sm text-ink-500">
                    Provider, intent, recommendations, fallback details, and metadata.
                </p>
            </div>
            <a href="{{ route('admin.ai.index') }}" class="nav-action-secondary">Back to AI Logs</a>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
            <div class="metric-card">
                <p class="metric-label">Provider</p>
                <p class="metric-value">{{ ucfirst($interaction->provider) }}</p>
                <p class="metric-note">{{ $interaction->model ?: 'No model' }}</p>
            </div>
            <div class="metric-card">
                <p class="metric-label">Status</p>
                <p class="metric-value">{{ ucfirst($interaction->status) }}</p>
                <p class="metric-note">{{ $interaction->fallback_used ? 'Fallback used' : 'Primary provider' }}</p>
            </div>
            <div class="metric-card">
                <p class="metric-label">Latency</p>
                <p class="metric-value">{{ $interaction->latency_ms ?? 0 }}ms</p>
                <p class="metric-note">End-to-end request</p>
            </div>
            <div class="metric-card">
                <p class="metric-label">Cost</p>
                <p class="metric-value">₱0.00</p>
                <p class="metric-note">Local Ollama or fake provider</p>
            </div>
        </section>

        <section class="admin-panel">
            <h3 class="admin-panel-title">Prompt</h3>
            <p class="mt-3 text-sm leading-6 text-ink-700">{{ $interaction->prompt }}</p>
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <div class="admin-panel">
                <h3 class="admin-panel-title">Intent</h3>
                <pre class="ai-json-block">{{ json_encode($interaction->normalized_intent, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
            <div class="admin-panel">
                <h3 class="admin-panel-title">Recommendations</h3>
                <pre class="ai-json-block">{{ json_encode($interaction->recommended_book_ids, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </section>

        <section class="admin-panel">
            <h3 class="admin-panel-title">Answer</h3>
            <p class="mt-3 text-sm leading-6 text-ink-700">{{ $interaction->answer ?: 'No answer recorded.' }}</p>

            @if($interaction->fallback_reason)
                <div class="mt-5 soft-alert-warning">
                    Fallback reason: {{ $interaction->fallback_reason }}
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
