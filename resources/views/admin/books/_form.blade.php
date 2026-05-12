<div class="space-y-6">
    <div>
        <x-input-label for="category_id" value="Category" />
        <select id="category_id"
                name="category_id"
                class="form-input"
                required>
            <option value="">Select category</option>
            @foreach($categories as $categoryOption)
                <option value="{{ $categoryOption->id }}"
                    {{ (string) old('category_id', $book->category_id ?? '') === (string) $categoryOption->id ? 'selected' : '' }}>
                    {{ $categoryOption->name }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('category_id')" />
    </div>

    <div class="admin-form-grid">
        <div>
            <x-input-label for="title" value="Title" />
            <x-text-input id="title"
                          name="title"
                          type="text"
                          :value="old('title', $book->title ?? '')"
                          required />
            <x-input-error :messages="$errors->get('title')" />
        </div>

        <div>
            <x-input-label for="author" value="Author" />
            <x-text-input id="author"
                          name="author"
                          type="text"
                          :value="old('author', $book->author ?? '')"
                          required />
            <x-input-error :messages="$errors->get('author')" />
        </div>
    </div>

    <div class="admin-form-grid">
        <div>
            <x-input-label for="isbn" value="ISBN" />
            <x-text-input id="isbn"
                          name="isbn"
                          type="text"
                          :value="old('isbn', $book->isbn ?? '')" />
            <x-input-error :messages="$errors->get('isbn')" />
        </div>

        <div>
            <x-input-label for="published_at" value="Published Date" />
            <x-text-input id="published_at"
                          name="published_at"
                          type="date"
                          :value="old('published_at', isset($book) && $book->published_at ? $book->published_at->format('Y-m-d') : '')" />
            <x-input-error :messages="$errors->get('published_at')" />
        </div>
    </div>

    <div>
        <x-input-label for="description" value="Description" />
        <textarea id="description"
                  name="description"
                  rows="5"
                  class="admin-textarea">{{ old('description', $book->description ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('description')" />
    </div>

    <div class="admin-form-grid">
        <div>
            <x-input-label for="price" value="Price" />
            <x-text-input id="price"
                          name="price"
                          type="number"
                          step="0.01"
                          min="0"
                          :value="old('price', isset($book) ? (float) $book->price : '')"
                          required />
            <x-input-error :messages="$errors->get('price')" />
        </div>

        <div>
            <x-input-label for="stock" value="Stock" />
            <x-text-input id="stock"
                          name="stock"
                          type="number"
                          min="0"
                          :value="old('stock', $book->stock ?? 0)"
                          required />
            <x-input-error :messages="$errors->get('stock')" />
        </div>
    </div>

    <div class="admin-form-grid">
        <div>
            <x-input-label for="status" value="Status" />
            <select id="status"
                    name="status"
                    class="form-input"
                    required>
                <option value="active" {{ old('status', $book->status ?? 'active') === 'active' ? 'selected' : '' }}>
                    Active
                </option>
                <option value="inactive" {{ old('status', $book->status ?? 'active') === 'inactive' ? 'selected' : '' }}>
                    Inactive
                </option>
            </select>
            <x-input-error :messages="$errors->get('status')" />
        </div>

        <div>
            <x-input-label for="cover_image" value="Book Cover" />
            <input id="cover_image"
                   name="cover_image"
                   type="file"
                   accept="image/*"
                   class="form-input">
            <x-input-error :messages="$errors->get('cover_image')" />
        </div>
    </div>

    @if(!empty($book?->cover_image))
        <div>
            <p class="form-label">Current Cover</p>
            <img src="{{ asset('storage/' . $book->cover_image) }}"
                 alt="{{ $book->title }}"
                 class="cover-preview">
        </div>
    @endif

    <div>
        <label class="admin-checkbox-wrap">
            <input type="checkbox"
                   name="is_featured"
                   value="1"
                   class="admin-checkbox"
                   {{ old('is_featured', $book->is_featured ?? false) ? 'checked' : '' }}>
            <span class="text-sm font-medium text-ink-700">Featured Book</span>
        </label>
    </div>

    <div class="admin-form-actions">
        <button type="submit" class="btn-primary-ui">
            Save Book
        </button>

        <a href="{{ route('admin.books.index') }}" class="nav-action-secondary">
            Cancel
        </a>
    </div>
</div>
