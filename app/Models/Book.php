<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Book extends Model implements AuditableContract
{
    use Auditable;
    use HasFactory;
    use Searchable;

    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'author',
        'publisher',
        'format',
        'isbn',
        'description',
        'price',
        'stock',
        'cover_image',
        'published_at',
        'status',
        'is_featured',
    ];

    protected array $auditExclude = [
        'cover_image',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'published_at' => 'date',
            'is_featured' => 'boolean',
        ];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function searchableAs(): string
    {
        return 'books_index';
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'publisher' => $this->publisher,
            'format' => $this->format,
            'isbn' => $this->isbn,
            'description' => $this->description,
            'category' => $this->category?->name,
            'status' => $this->status,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->status === 'active';
    }

    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query->with('category:id,name');
    }
}
