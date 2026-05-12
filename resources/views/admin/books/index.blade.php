<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-ink-900 leading-tight">
                    Books
                </h2>
                <p class="mt-1 text-sm text-ink-500">
                    Manage catalog entries, stock, status, and cover images.
                </p>
            </div>

            <a href="{{ route('admin.books.create') }}" class="nav-action-primary">
                Add Book
            </a>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if(session('success'))
            <div class="soft-alert-success">
                {{ session('success') }}
            </div>
        @endif

        <section class="admin-panel">
            @if($books->count())
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Cover</th>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Author</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($books as $book)
                                <tr>
                                    <td>
                                        @if($book->cover_image)
                                            <img src="{{ asset('storage/' . $book->cover_image) }}"
                                                 alt="{{ $book->title }}"
                                                 class="admin-table-thumb">
                                        @else
                                            <span class="admin-table-thumb-placeholder">No image</span>
                                        @endif
                                    </td>
                                    <td class="font-semibold text-ink-900">{{ $book->title }}</td>
                                    <td>{{ $book->category?->name }}</td>
                                    <td>{{ $book->author }}</td>
                                    <td>₱{{ number_format((float) $book->price, 2) }}</td>
                                    <td>{{ $book->stock }}</td>
                                    <td>
                                        <span class="{{ strtolower($book->status) === 'active' ? 'status-pill status-pill-completed' : 'status-pill status-pill-default' }}">
                                            {{ ucfirst($book->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            <a href="{{ route('admin.books.edit', $book) }}" class="nav-action-secondary">
                                                Edit
                                            </a>

                                            <form action="{{ route('admin.books.destroy', $book) }}"
                                                  method="POST"
                                                  onsubmit="return confirm('Delete this book?');">
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
                                    <td colspan="8" class="text-center text-ink-500">No books found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-5">
                    {{ $books->links() }}
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
