<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-ink-900 leading-tight">
                Data Management
            </h2>
            <p class="mt-1 text-sm text-ink-500">
                Run imports, exports, backups, and monitor audit and API activity from one place.
            </p>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if(session('success'))
            <div class="soft-alert-success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="soft-alert-danger">
                <ul class="space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="admin-stat-grid">
            <div class="metric-card">
                <p class="metric-label">Database Size</p>
                <p class="metric-value">{{ number_format($systemHealth['database_size'] / 1048576, 2) }} MB</p>
                <p class="metric-note">Current sqlite footprint</p>
            </div>
            <div class="metric-card">
                <p class="metric-label">Storage Usage</p>
                <p class="metric-value">{{ number_format($systemHealth['storage_usage'] / 1048576, 2) }} MB</p>
                <p class="metric-note">Private app storage</p>
            </div>
            <div class="metric-card">
                <p class="metric-label">Pending Jobs</p>
                <p class="metric-value">{{ $systemHealth['pending_jobs'] }}</p>
                <p class="metric-note">Queued work waiting to run</p>
            </div>
            <div class="metric-card">
                <p class="metric-label">Failed Jobs</p>
                <p class="metric-value">{{ $systemHealth['failed_jobs'] }}</p>
                <p class="metric-note">Jobs that need review</p>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <div class="admin-panel space-y-5">
                <div>
                    <h3 class="admin-panel-title">Book Import</h3>
                    <p class="admin-panel-subtitle">Upload CSV/XLSX files using the assignment template and queued chunk processing.</p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('admin.data.template.books') }}" class="nav-action-secondary">Download Template</a>
                </div>

                <form method="POST" action="{{ route('admin.data.imports.books') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <label class="form-label">Book File</label>
                        <input type="file" name="file" class="form-input" required>
                    </div>
                    <div>
                        <label class="form-label">Duplicate Handling</label>
                        <select name="duplicate_strategy" class="form-input">
                            <option value="skip">Skip duplicates</option>
                            <option value="update_existing">Update existing books</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary-ui">Queue Book Import</button>
                </form>
            </div>

            <div class="admin-panel space-y-5">
                <div>
                    <h3 class="admin-panel-title">User Import</h3>
                    <p class="admin-panel-subtitle">Bulk-create or update institutional and customer accounts with role assignment.</p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('admin.data.template.users') }}" class="nav-action-secondary">Download Profile Template</a>
                </div>

                <form method="POST" action="{{ route('admin.data.imports.users') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <label class="form-label">User File</label>
                        <input type="file" name="file" class="form-input" required>
                    </div>
                    <div>
                        <label class="form-label">Duplicate Handling</label>
                        <select name="duplicate_strategy" class="form-input">
                            <option value="skip">Skip duplicates</option>
                            <option value="update_existing">Update existing users</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary-ui">Queue User Import</button>
                </form>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="admin-panel space-y-4">
                <div>
                    <h3 class="admin-panel-title">Book Export</h3>
                    <p class="admin-panel-subtitle">Category, price, stock, and date filtering with custom field selection.</p>
                </div>
                <form method="POST" action="{{ route('admin.data.exports.books') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="form-label">Format</label>
                        <select name="format" class="form-input">
                            <option value="csv">CSV</option>
                            <option value="xlsx">XLSX</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Category</label>
                        <select name="category" class="form-input">
                            <option value="">All categories</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->slug }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Min Price</label>
                            <input type="number" step="0.01" name="price_min" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Max Price</label>
                            <input type="number" step="0.01" name="price_max" class="form-input">
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Stock Status</label>
                        <select name="stock_status" class="form-input">
                            <option value="">Any stock state</option>
                            <option value="in_stock">In stock</option>
                            <option value="out_of_stock">Out of stock</option>
                            <option value="low_stock">Low stock</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Columns</label>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach($bookColumns as $key => $label)
                                <label class="admin-checkbox-wrap">
                                    <input type="checkbox" name="columns[]" value="{{ $key }}" class="admin-checkbox" checked>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <button type="submit" class="btn-primary-ui">Export Books</button>
                </form>
            </div>

            <div class="admin-panel space-y-4">
                <div>
                    <h3 class="admin-panel-title">Order Export</h3>
                    <p class="admin-panel-subtitle">Export admin order reports filtered by status, customer, and date range.</p>
                </div>
                <form method="POST" action="{{ route('admin.data.exports.orders') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="form-label">Format</label>
                        <select name="format" class="form-input">
                            <option value="csv">CSV</option>
                            <option value="xlsx">XLSX</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Status</label>
                        <select name="status" class="form-input">
                            <option value="">All statuses</option>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Customer</label>
                        <select name="customer_id" class="form-input">
                            <option value="">All customers</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Columns</label>
                        <div class="grid grid-cols-1 gap-2">
                            @foreach($orderColumns as $key => $label)
                                <label class="admin-checkbox-wrap">
                                    <input type="checkbox" name="columns[]" value="{{ $key }}" class="admin-checkbox" checked>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <button type="submit" class="btn-primary-ui">Export Orders</button>
                </form>
            </div>

            <div class="admin-panel space-y-4">
                <div>
                    <h3 class="admin-panel-title">User Export</h3>
                    <p class="admin-panel-subtitle">Redact PII when needed for compliance-oriented datasets.</p>
                </div>
                <form method="POST" action="{{ route('admin.data.exports.users') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="form-label">Format</label>
                        <select name="format" class="form-input">
                            <option value="csv">CSV</option>
                            <option value="xlsx">XLSX</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Role</label>
                        <select name="role" class="form-input">
                            <option value="">All roles</option>
                            <option value="admin">Admin</option>
                            <option value="customer">Customer</option>
                        </select>
                    </div>
                    <label class="admin-checkbox-wrap">
                        <input type="checkbox" name="redact_pii" value="1" class="admin-checkbox">
                        <span>Redact personally identifiable fields</span>
                    </label>
                    <div>
                        <label class="form-label">Columns</label>
                        <div class="grid grid-cols-1 gap-2">
                            @foreach($userColumns as $key => $label)
                                <label class="admin-checkbox-wrap">
                                    <input type="checkbox" name="columns[]" value="{{ $key }}" class="admin-checkbox" checked>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <button type="submit" class="btn-primary-ui">Export Users</button>
                </form>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <div class="admin-panel">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h3 class="admin-panel-title">Recent Import Logs</h3>
                        <p class="admin-panel-subtitle">Track queued progress, successes, and failure reports.</p>
                    </div>
                </div>
                <div class="mt-5 ui-table-wrap">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>File</th>
                                <th>Status</th>
                                <th>Rows</th>
                                <th>Failure Report</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($importLogs as $log)
                                <tr>
                                    <td>{{ ucfirst($log->type) }}</td>
                                    <td>{{ $log->filename }}</td>
                                    <td><span class="status-pill {{ $log->status === 'completed' ? 'status-pill-completed' : ($log->status === 'failed' ? 'status-pill-pending' : 'status-pill-default') }}">{{ ucfirst($log->status) }}</span></td>
                                    <td>{{ $log->processed_rows }}/{{ $log->total_rows }}</td>
                                    <td>
                                        @if($log->failure_report_path)
                                            <a href="{{ route('admin.data.imports.failures', $log) }}" class="nav-action-secondary">Download</a>
                                        @else
                                            <span class="text-ink-400">None</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-ink-500">No import logs yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="admin-panel">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h3 class="admin-panel-title">Recent Export Logs</h3>
                        <p class="admin-panel-subtitle">Queued and completed exports with downloadable output files.</p>
                    </div>
                    <form method="POST" action="{{ route('admin.data.backups.run') }}">
                        @csrf
                        <button type="submit" class="nav-action-primary">Run Backup Now</button>
                    </form>
                </div>
                <div class="mt-5 ui-table-wrap">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Format</th>
                                <th>Status</th>
                                <th>Rows</th>
                                <th>Download</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($exportLogs as $log)
                                <tr>
                                    <td>{{ str_replace('_', ' ', ucfirst($log->type)) }}</td>
                                    <td>{{ strtoupper($log->format) }}</td>
                                    <td><span class="status-pill {{ $log->status === 'completed' ? 'status-pill-completed' : 'status-pill-default' }}">{{ ucfirst($log->status) }}</span></td>
                                    <td>{{ $log->total_rows }}</td>
                                    <td>
                                        @if($log->status === 'completed')
                                            <a href="{{ route('exports.download', $log) }}" class="nav-action-secondary">Download</a>
                                        @else
                                            <span class="text-ink-400">Queued</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-ink-500">No export logs yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="admin-panel">
                <h3 class="admin-panel-title">Backup Monitoring</h3>
                <p class="admin-panel-subtitle">Recent backup, cleanup, and health events.</p>
                <div class="mt-5 space-y-3">
                    @forelse($backupLogs as $backup)
                        <div class="rounded-2xl border border-sage-100 bg-sage-50 p-4">
                            <p class="font-semibold text-ink-900">{{ ucfirst(str_replace('_', ' ', $backup->event)) }}</p>
                            <p class="mt-1 text-sm text-ink-500">{{ ucfirst($backup->status) }} • {{ optional($backup->happened_at)->format('M d, Y h:i A') }}</p>
                            <p class="mt-2 text-sm text-ink-600">{{ $backup->message ?: 'No details recorded.' }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-ink-500">No backup events yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="admin-panel">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h3 class="admin-panel-title">Audit Overview</h3>
                        <p class="admin-panel-subtitle">Recent auditable events and quick access to the compliance trail.</p>
                    </div>
                    <a href="{{ route('admin.audits.index') }}" class="nav-action-secondary">Open Audit Logs</a>
                </div>
                <div class="mt-5 space-y-3">
                    @forelse($recentAudits as $audit)
                        <div class="rounded-2xl border border-sage-100 bg-white p-4 shadow-soft">
                            <p class="font-semibold text-ink-900">{{ ucfirst($audit->event) }} • {{ class_basename((string) $audit->auditable_type) }}</p>
                            <p class="mt-1 text-sm text-ink-500">{{ $audit->user?->name ?: 'System' }} • {{ optional($audit->created_at)->diffForHumans() }}</p>
                            <p class="mt-2 text-xs text-ink-500">{{ $audit->url ?: 'Console or background task' }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-ink-500">No recent audit entries.</p>
                    @endforelse
                </div>
            </div>

            <div class="admin-panel">
                <h3 class="admin-panel-title">API Usage</h3>
                <p class="admin-panel-subtitle">Top endpoints by total requests and throttled hits.</p>
                <div class="mt-5 space-y-3">
                    @forelse($apiStats as $stat)
                        <div class="rounded-2xl border border-sage-100 bg-white p-4 shadow-soft">
                            <p class="font-semibold text-ink-900">{{ $stat->endpoint }}</p>
                            <p class="mt-1 text-sm text-ink-500">Requests: {{ $stat->total_requests }}</p>
                            <p class="mt-1 text-sm text-ink-500">Throttled: {{ $stat->throttled_requests }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-ink-500">No API traffic recorded yet.</p>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
