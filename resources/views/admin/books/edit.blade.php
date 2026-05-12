<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Edit Book
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST"
                          action="{{ route('admin.books.update', $book) }}"
                          enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        @include('admin.books._form', ['book' => $book])
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
