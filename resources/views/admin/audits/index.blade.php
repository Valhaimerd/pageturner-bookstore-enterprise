<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-ink-900 leading-tight">
                    Audit Logs
                </h2>
                <p class="mt-1 text-sm text-ink-500">
                    Review sensitive activity, compare changes, and export compliance trails.
                </p>
            </div>

            <form method="POST" action="{{ route('admin.audits.export') }}" class="flex gap-2">
                @csrf
                @foreach($filters as $name => $value)
                    @if($value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endif
                @endforeach
                <button type="submit" name="format" value="csv" class="nav-action-secondary">Export CSV</button>
                <button type="submit" name="format" value="pdf" class="nav-action-primary">Export PDF</button>
            </form>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="admin-panel">
            <form method="GET" action="{{ route('admin.audits.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-5">
                <div>
                    <label class="form-label">User</label>
                    <select name="user_id" class="form-input">
                        <option value="">All users</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected(($filters['user_id'] ?? '') == $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Event</label>
                    <input type="text" name="event" value="{{ $filters['event'] ?? '' }}" class="form-input" placeholder="updated">
                </div>
                <div>
                    <label class="form-label">Model</label>
                    <input type="text" name="model" value="{{ $filters['model'] ?? '' }}" class="form-input" placeholder="Book">
                </div>
                <div>
                    <label class="form-label">From</label>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">To</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-input">
                </div>
                <div class="md:col-span-5 flex gap-3">
                    <button type="submit" class="btn-primary-ui">Apply Filters</button>
                    <a href="{{ route('admin.audits.index') }}" class="btn-secondary-ui">Reset</a>
                </div>
            </form>
        </section>

        <section class="space-y-4">
            @forelse($audits as $audit)
                <div class="admin-panel">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="admin-panel-title">{{ ucfirst($audit->event) }} • {{ class_basename((string) $audit->auditable_type) }} #{{ $audit->auditable_id }}</h3>
                            <p class="admin-panel-subtitle">{{ $audit->user?->name ?: 'System' }} • {{ optional($audit->created_at)->format('M d, Y h:i A') }}</p>
                        </div>
                        <div class="text-right text-xs text-ink-500">
                            <p>{{ $audit->method ?: 'CLI' }} {{ $audit->url ?: 'background task' }}</p>
                            <p>Checksum: {{ $audit->checksum ?: 'n/a' }}</p>
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <div class="rounded-2xl border border-sage-100 bg-sage-50 p-4">
                            <p class="text-sm font-semibold text-ink-900">Old Values</p>
                            <div class="mt-3 space-y-2 text-sm text-ink-600">
                                @forelse($audit->old_values ?? [] as $key => $value)
                                    <div><strong>{{ $key }}:</strong> {{ is_array($value) ? json_encode($value) : $value }}</div>
                                @empty
                                    <p>No previous values.</p>
                                @endforelse
                            </div>
                        </div>
                        <div class="rounded-2xl border border-brand-100 bg-brand-50/40 p-4">
                            <p class="text-sm font-semibold text-ink-900">New Values</p>
                            <div class="mt-3 space-y-2 text-sm text-ink-600">
                                @forelse($audit->new_values ?? [] as $key => $value)
                                    <div><strong>{{ $key }}:</strong> {{ is_array($value) ? json_encode($value) : $value }}</div>
                                @empty
                                    <p>No new values.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="empty-state-card">
                    <h3 class="empty-state-title">No audit logs found</h3>
                    <p class="empty-state-text">Try broadening the filters or perform auditable actions in the system.</p>
                </div>
            @endforelse
        </section>

        <div>{{ $audits->links() }}</div>
    </div>
</x-app-layout>
