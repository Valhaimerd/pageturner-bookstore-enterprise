<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-ink-900 leading-tight">
                Create Category
            </h2>
            <p class="mt-1 text-sm text-ink-500">
                Add a new catalog category.
            </p>
        </div>
    </x-slot>

    <div class="admin-form-shell max-w-3xl">
        <div class="admin-form-card">
            <form method="POST" action="{{ route('admin.categories.store') }}" class="space-y-6">
                @csrf
                @include('admin.categories._form')
            </form>
        </div>
    </div>
</x-app-layout>
