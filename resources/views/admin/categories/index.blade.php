<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-ink-900 leading-tight">
                    Categories
                </h2>
                <p class="mt-1 text-sm text-ink-500">
                    Organize the catalog and manage category visibility.
                </p>
            </div>

            <a href="{{ route('admin.categories.create') }}" class="nav-action-primary">
                Add Category
            </a>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if(session('success'))
            <div class="soft-alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="soft-alert-danger">
                {{ session('error') }}
            </div>
        @endif

        <section class="admin-panel">
            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Books</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categories as $category)
                            <tr>
                                <td class="font-semibold text-ink-900">{{ $category->name }}</td>
                                <td>{{ $category->slug }}</td>
                                <td>{{ $category->books_count }}</td>
                                <td>
                                    <span class="{{ $category->is_active ? 'status-pill status-pill-completed' : 'status-pill status-pill-default' }}">
                                        {{ $category->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('admin.categories.edit', $category) }}" class="nav-action-secondary">
                                            Edit
                                        </a>

                                        <form action="{{ route('admin.categories.destroy', $category) }}"
                                              method="POST"
                                              onsubmit="return confirm('Delete this category?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-danger-ui">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-ink-500">No categories found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-5">
                    {{ $categories->links() }}
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
