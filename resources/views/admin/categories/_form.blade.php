<div class="space-y-6">
    <div>
        <x-input-label for="name" value="Category Name" />
        <x-text-input id="name"
                      name="name"
                      type="text"
                      :value="old('name', $category->name ?? '')"
                      required />
        <x-input-error :messages="$errors->get('name')" />
    </div>

    <div>
        <x-input-label for="description" value="Description" />
        <textarea id="description"
                  name="description"
                  rows="4"
                  class="admin-textarea">{{ old('description', $category->description ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('description')" />
    </div>

    <div>
        <label class="admin-checkbox-wrap">
            <input type="checkbox"
                   name="is_active"
                   value="1"
                   class="admin-checkbox"
                   {{ old('is_active', $category->is_active ?? true) ? 'checked' : '' }}>
            <span class="text-sm font-medium text-ink-700">Active</span>
        </label>
    </div>

    <div class="admin-form-actions">
        <button type="submit" class="btn-primary-ui">
            Save Category
        </button>

        <a href="{{ route('admin.categories.index') }}" class="nav-action-secondary">
            Cancel
        </a>
    </div>
</div>
